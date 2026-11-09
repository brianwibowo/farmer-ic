<?php

namespace Tests\Feature\AI;

use App\Enums\TeamRole;
use App\Models\AiAttachment;
use App\Models\AiConversation;
use App\Models\AiKnowledgeDocument;
use App\Models\AiMessage;
use App\Models\Team;
use App\Models\User;
use App\Support\RolePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** HTTP authorization against the guarded, separate PostgreSQL test database. */
class AiOwnerAccessTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=';

    /** @return array<string, array{string}> */
    public static function deniedRoles(): array
    {
        return ['admin' => ['admin'], 'petugas' => ['petugas'], 'superadmin' => ['superadmin']];
    }

    #[DataProvider('deniedRoles')]
    public function test_only_owner_can_enter_any_interactive_ai_endpoint(string $role): void
    {
        // Exercise authorization rather than the shared per-user rate counter.
        $this->withoutMiddleware(ThrottleRequests::class);
        $user = User::factory()->create(['role' => $role]);
        $team = $user->currentTeam;
        $conversation = AiConversation::factory()->create(['team_id' => $team->id, 'user_id' => $user->id]);
        $attachment = $this->attachment($conversation, $user);
        $document = AiKnowledgeDocument::create([
            'team_id' => $team->id, 'created_by' => $user->id,
            'title' => 'Panduan perawatan kebun', 'content' => 'Lakukan pengamatan daun secara berkala.',
            'status' => AiKnowledgeDocument::STATUS_DRAFT, 'content_hash' => hash('sha256', 'Lakukan pengamatan daun secara berkala.'), 'version' => 1,
        ]);
        $this->actingAs($user);

        $routes = [
            ['GET', 'ai.page', [$team->slug]],
            ['GET', 'ai.conversations.index', [$team->slug]],
            ['POST', 'ai.conversations.store', [$team->slug]],
            ['GET', 'ai.conversations.show', [$team->slug, $conversation]],
            ['POST', 'ai.messages.store', [$team->slug]],
            ['POST', 'ai.conversation-messages.store', [$team->slug, $conversation]],
            ['GET', 'ai.attachments.show', [$team->slug, $attachment]],
            ['POST', 'ai.voice.transcribe', [$team->slug]],
            ['POST', 'ai.voice.synthesize', [$team->slug]],
            ['GET', 'ai.knowledge.index', [$team->slug]],
            ['POST', 'ai.knowledge.store', [$team->slug]],
            ['PATCH', 'ai.knowledge.update', [$team->slug, $document]],
            ['DELETE', 'ai.knowledge.destroy', [$team->slug, $document]],
            ['POST', 'ai.knowledge.publish', [$team->slug, $document]],
            ['POST', 'ai.knowledge.archive', [$team->slug, $document]],
        ];
        foreach ($routes as [$method, $name, $parameters]) {
            $this->json($method, route($name, $parameters))->assertForbidden();
        }
        $this->getJson('/bypass')->assertForbidden();
        $this->assertFalse(Gate::forUser($user)->allows('ai.access', $team));
        if ($role === 'superadmin') {
            $this->assertTrue(Gate::forUser($user)->allows('admin.access'));
            $this->assertTrue(Gate::forUser($user)->allows('teams.manageAll'));
        }
    }

    public function test_owner_requires_real_team_membership_and_a_valid_ai_grant(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $team = $owner->currentTeam;
        $this->actingAs($owner)->get(route('ai.page', $team->slug))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('farmer/Index'));
        $foreignTeam = Team::factory()->create(['name' => 'Tenant AI privat']);
        $this->getJson(route('ai.conversations.index', $foreignTeam->slug))->assertForbidden();

        $this->revoke(['ai.access']);
        $this->getJson(route('ai.page', $team->slug))->assertForbidden();
        $this->postJson(route('ai.messages.store', $team->slug), ['message' => 'Halo'])->assertForbidden();
        $this->getJson('/bypass')->assertForbidden();
    }

    public function test_owner_history_pagination_and_nested_messages_stay_private_and_scoped(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $teammate = User::factory()->create(['role' => 'owner']);
        $team = $owner->currentTeam;
        $team->members()->attach($teammate, ['role' => TeamRole::Member->value]);
        $own = AiConversation::factory()->create(['team_id' => $team->id, 'user_id' => $owner->id]);
        $other = AiConversation::factory()->create(['team_id' => $team->id, 'user_id' => $teammate->id]);
        AiMessage::factory()->create(['ai_conversation_id' => $own->id, 'user_id' => $owner->id, 'content' => 'Catatan Owner sendiri']);
        AiMessage::factory()->create(['ai_conversation_id' => $other->id, 'user_id' => $teammate->id, 'content' => 'Catatan pribadi Owner lain']);
        AiConversation::factory()->create(['team_id' => $teammate->currentTeam->id, 'user_id' => $owner->id]);

        $this->actingAs($owner)->getJson(route('ai.conversations.index', $team->slug))->assertOk()
            ->assertJsonPath('total', 1)->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)->assertJsonPath('data.0.preview', 'Catatan Owner sendiri');
        $this->getJson(route('ai.conversations.show', [$team->slug, $other]))->assertNotFound();
        $this->postJson(route('ai.conversation-messages.store', [$team->slug, $other]), ['message' => 'Tidak boleh'])->assertNotFound();
        $this->postJson(route('ai.messages.store', $team->slug), ['conversation_id' => $other->id, 'message' => 'Tidak boleh'])->assertNotFound();

        $this->revoke(['ai_messages.view']);
        $this->getJson(route('ai.conversations.index', $team->slug))->assertOk()->assertJsonPath('data.0.preview', null);
        $this->getJson(route('ai.conversations.show', [$team->slug, $own]))->assertOk()->assertJsonCount(0, 'messages');
        $this->revoke(['ai_conversations.view']);
        $this->getJson(route('ai.conversations.index', $team->slug))->assertForbidden();
        $this->getJson(route('ai.conversations.show', [$team->slug, $own]))->assertForbidden();
    }

    public function test_owner_can_read_a_private_upload_and_other_owners_cannot(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['role' => 'owner']);
        $team = $owner->currentTeam;
        $conversation = AiConversation::factory()->create(['team_id' => $team->id, 'user_id' => $owner->id]);
        $attachment = $this->attachment($conversation, $owner);
        Storage::disk('local')->put($attachment->path, base64_decode(self::PNG));

        $response = $this->actingAs($owner)->get(route('ai.attachments.show', [$team->slug, $attachment]));
        $response->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame(base64_decode(self::PNG), $response->streamedContent());

        $otherOwner = User::factory()->create(['role' => 'owner']);
        $otherTeam = $otherOwner->currentTeam;
        $team->members()->attach($otherOwner, ['role' => TeamRole::Member->value]);
        $this->actingAs($otherOwner)->getJson(route('ai.attachments.show', [$team->slug, $attachment]))->assertNotFound();
        $this->actingAs($owner)->getJson(route('ai.attachments.show', [$otherTeam->slug, $attachment]))->assertForbidden();

        $otherTeam->members()->attach($owner, ['role' => TeamRole::Member->value]);
        $this->actingAs($owner)->getJson(route('ai.attachments.show', [$otherTeam->slug, $attachment]))->assertNotFound();
    }

    public function test_attachment_grants_apply_to_inline_files_and_history_payloads(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['role' => 'owner']);
        $team = $owner->currentTeam;
        $conversation = AiConversation::factory()->create(['team_id' => $team->id, 'user_id' => $owner->id]);
        $message = AiMessage::factory()->create(['ai_conversation_id' => $conversation->id, 'user_id' => $owner->id]);
        $attachment = $this->attachment($conversation, $owner, ['ai_message_id' => $message->id]);
        Storage::disk('local')->put($attachment->path, base64_decode(self::PNG));

        $this->revoke(['ai_attachments.view']);
        $this->actingAs($owner)->getJson(route('ai.attachments.show', [$team->slug, $attachment]))->assertForbidden();
        $this->getJson(route('ai.conversations.show', [$team->slug, $conversation]))->assertOk()
            ->assertJsonCount(1, 'messages')->assertJsonCount(0, 'messages.0.attachments');
    }

    public function test_denied_new_message_does_not_leave_an_empty_conversation(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $this->revoke(['ai_messages.create']);
        $this->actingAs($owner)->postJson(route('ai.messages.store', $owner->currentTeam->slug), ['message' => 'Halo'])->assertForbidden();
        $this->assertDatabaseCount('ai_conversations', 0);
        $this->assertDatabaseCount('ai_messages', 0);
    }

    public function test_denied_attachment_creation_rolls_back_before_a_file_or_message_is_stored(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['role' => 'owner']);
        $this->revoke(['ai_attachments.create']);
        $this->actingAs($owner)->post(route('ai.messages.store', $owner->currentTeam->slug), [
            'message' => 'Periksa gambar ini',
            'attachments' => [UploadedFile::fake()->createWithContent('tanaman.png', base64_decode(self::PNG))],
        ])->assertForbidden();
        $this->assertDatabaseCount('ai_conversations', 0);
        $this->assertDatabaseCount('ai_messages', 0);
        $this->assertDatabaseCount('ai_attachments', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_private_image_route_rejects_missing_files_and_active_content(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['role' => 'owner']);
        $team = $owner->currentTeam;
        $conversation = AiConversation::factory()->create(['team_id' => $team->id, 'user_id' => $owner->id]);
        $attachment = $this->attachment($conversation, $owner);
        $this->actingAs($owner)->getJson(route('ai.attachments.show', [$team->slug, $attachment]))->assertNotFound();

        $attachment->forceFill(['mime_type' => 'text/html'])->save();
        Storage::disk('local')->put($attachment->path, '<script>alert(1)</script>');
        $this->getJson(route('ai.attachments.show', [$team->slug, $attachment]))->assertStatus(415);
    }

    /** @param array<string, mixed> $attributes */
    private function attachment(AiConversation $conversation, User $owner, array $attributes = []): AiAttachment
    {
        return AiAttachment::factory()->create([
            'ai_conversation_id' => $conversation->id, 'user_id' => $owner->id,
            'disk' => 'local', 'path' => 'ai/'.$conversation->id.'/daun.png', 'original_name' => 'daun.png',
            'mime_type' => 'image/png', 'size' => strlen(base64_decode(self::PNG)),
            'sha256' => hash('sha256', base64_decode(self::PNG)), 'kind' => 'image', 'status' => 'uploaded',
            ...$attributes,
        ]);
    }

    /** @param list<string> $abilities */
    private function revoke(array $abilities): void
    {
        $roles = app(RolePermissions::class)->roles();
        foreach ($abilities as $ability) {
            unset($roles['owner'][$ability]);
        }
        config(['access.roles' => $roles]);
    }
}
