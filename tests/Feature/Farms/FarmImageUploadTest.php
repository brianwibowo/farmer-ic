<?php

namespace Tests\Feature\Farms;

use App\Enums\TeamRole;
use App\Models\Farm;
use App\Models\Team;
use App\Models\User;
use App\Services\FarmImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class FarmImageUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->team = Team::factory()->create(['is_personal' => false]);
        $this->admin = $this->actor('admin', $this->team);
        $this->actingAs($this->admin);
    }

    public function test_create_stores_a_private_image_under_its_persisted_farm_and_serves_an_authorized_url(): void
    {
        $this->post('/admin/farm-management', [...$this->payload(), 'image' => UploadedFile::fake()->image('field.jpg')->size(15360)])
            ->assertRedirect();
        $farm = Farm::query()->where('name', 'Lahan Dokumentasi')->firstOrFail();
        $this->assertMatchesRegularExpression('#^farms/'.$farm->id.'/[a-f0-9-]+\.jpg$#', $farm->image_url);
        Storage::disk('farm')->assertExists($farm->image_url);
        $this->assertSame('private', config('filesystems.disks.farm.visibility'));
        $this->get(route('farms.image', $farm))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/admin/farm-management/'.$farm->id.'/edit')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('farm.image_url', route('farms.image', $farm, false)));
    }

    public function test_edit_accepts_multipart_method_spoofing_and_preserves_image_when_no_replacement_is_sent(): void
    {
        $farm = $this->withImage();
        $old = $farm->image_url;
        $this->post('/admin/farm-management/'.$farm->id, [...$this->payload(), '_method' => 'PUT', 'image' => UploadedFile::fake()->image('new.png')])
            ->assertRedirect();
        $farm->refresh();
        $this->assertNotSame($old, $farm->image_url);
        Storage::disk('farm')->assertExists($farm->image_url);
        Storage::disk('farm')->assertMissing($old);
        $new = $farm->image_url;
        $this->put('/admin/farm-management/'.$farm->id, $this->payload())->assertRedirect();
        $this->assertSame($new, $farm->fresh()->image_url);
    }

    public function test_high_resolution_photo_under_ten_megabytes_is_accepted_as_a_user_upload(): void
    {
        $image = UploadedFile::fake()->image('48mp.jpg', 6048, 8064)->size(8704);
        $this->post('/admin/farm-management', [...$this->payload(), 'image' => $image])->assertRedirect()->assertSessionHasNoErrors();
        $farm = Farm::query()->where('name', 'Lahan Dokumentasi')->firstOrFail();
        $contents = Storage::disk('farm')->get($farm->image_url);
        $this->assertSame(file_get_contents($image->getPathname()), $contents);
        $this->assertSame([6048, 8064], array_slice(getimagesizefromstring($contents), 0, 2));
        $this->get(route('farms.image', $farm))->assertOk();
        $this->assertSame(2048, config('photo_uploads.max_dimension'));
    }

    public function test_image_can_be_explicitly_removed(): void
    {
        $farm = $this->withImage();
        $this->put('/admin/farm-management/'.$farm->id, [...$this->payload(), 'remove_image' => true])->assertRedirect();
        $this->assertNull($farm->fresh()->image_url);
        $this->get(route('farms.image', $farm))->assertNotFound();
    }

    public function test_file_validation_rejects_oversize_non_images_svg_and_client_supplied_storage_paths(): void
    {
        foreach ([UploadedFile::fake()->image('large.jpg')->size(15361),
            UploadedFile::fake()->create('document.pdf', 1, 'application/pdf'),
            UploadedFile::fake()->create('image.svg', 1, 'image/svg+xml')] as $file) {
            $this->post('/admin/farm-management', [...$this->payload(), 'image' => $file])
                ->assertSessionHasErrors('image');
        }
        $this->post('/admin/farm-management', [...$this->payload(), 'image_url' => 'farms/foreign/secret.jpg'])
            ->assertSessionHasErrors('image_url');
        $this->assertSame([], Storage::disk('farm')->allFiles());
        $this->assertDatabaseMissing('farms', ['name' => 'Lahan Dokumentasi']);
    }

    public function test_owner_cannot_upload_and_other_tenants_and_unassigned_officers_cannot_read(): void
    {
        $farm = $this->withImage();
        $owner = $this->actor('owner', $this->team);
        $this->actingAs($owner)->post('/admin/farm-management', [...$this->payload(), 'image' => UploadedFile::fake()->image('field.jpg')])->assertForbidden();
        $this->get(route('farms.image', $farm))->assertOk();
        $officer = $this->actor('petugas', $this->team);
        $this->actingAs($officer)->get(route('farms.image', $farm))->assertForbidden();
        $officer->forceFill(['assigned_farm_id' => $farm->id])->save();
        $this->actingAs($officer->fresh());
        $this->get(route('farms.image', $farm))->assertOk();
        $foreign = $this->actor('admin', Team::factory()->create(['is_personal' => false]));
        $this->actingAs($foreign)->get(route('farms.image', $farm))->assertForbidden();
        $this->actingAs($this->admin);
        $farm->forceFill(['image_url' => 'farms/another-farm/secret.jpg'])->save();
        $this->get(route('farms.image', $farm))->assertNotFound();
    }

    public function test_failed_replacement_rolls_back_metadata_and_retains_the_previous_file(): void
    {
        $farm = $this->withImage();
        $previous = $farm->image_url;
        $this->partialMock(FarmImageService::class, function ($mock): void {
            $mock->shouldReceive('store')->once()->andThrow(new RuntimeException('Storage unavailable'));
        });
        $this->withoutExceptionHandling();
        try {
            $this->put('/admin/farm-management/'.$farm->id, [...$this->payload(), 'image' => UploadedFile::fake()->image('new.jpg')]);
            $this->fail('Expected storage failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Storage unavailable', $exception->getMessage());
        }
        $this->assertSame($previous, $farm->fresh()->image_url);
        Storage::disk('farm')->assertExists($previous);
        $this->assertCount(1, Storage::disk('farm')->allFiles());
    }

    public function test_partial_storage_failure_cleans_up_the_new_object_and_returns_a_field_error(): void
    {
        $farm = $this->withImage();
        $previous = $farm->image_url;
        $disk = Storage::disk('farm');
        $failing = \Mockery::mock($disk)->makePartial();
        $failing->shouldReceive('putFileAs')->once()->andReturnUsing(function ($directory, $file, $filename) use ($disk): bool {
            $disk->put($directory.'/'.$filename, 'partial upload');

            return false;
        });
        Storage::set('farm', $failing);
        $this->put('/admin/farm-management/'.$farm->id, [...$this->payload(), 'image' => UploadedFile::fake()->image('new.jpg')])
            ->assertSessionHasErrors('image');
        $this->assertSame($previous, $farm->fresh()->image_url);
        $this->assertSame([$previous], $disk->allFiles());
    }

    private function withImage(): Farm
    {
        $this->post('/admin/farm-management', [...$this->payload(), 'image' => UploadedFile::fake()->image('field.jpg')])->assertRedirect();

        return Farm::query()->where('name', 'Lahan Dokumentasi')->firstOrFail();
    }

    private function actor(string $role, Team $team): User
    {
        $user = User::factory()->create(['role' => $role]);
        $team->members()->attach($user, ['role' => TeamRole::Member->value]);
        $user->switchTeam($team);

        return $user;
    }

    /** @return array<string, string> */
    private function payload(): array
    {
        return ['name' => 'Lahan Dokumentasi', 'land_tenure' => 'owned', 'total_area_ha' => '2'];
    }
}
