<?php

namespace Tests\Feature\Farms;

use App\Enums\TeamRole;
use App\Models\Farm;
use App\Models\FarmLegality;
use App\Models\FarmLegalityDocument;
use App\Models\Team;
use App\Models\User;
use App\Support\RolePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

/** CRUD, private-file and Owner integration on the guarded separate PostgreSQL schema. */
class FarmLegalityManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    private Team $team;

    /** @var list<string> */
    private array $temporaryUploads = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('legality');
        $this->team = Team::factory()->create(['name' => 'Tim Administrasi Kebun', 'is_personal' => false]);
        $this->admin = $this->actor('admin');
        $this->owner = $this->actor('owner');
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryUploads as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    public function test_farm_detail_links_to_a_separate_legality_page_with_an_honest_empty_state(): void
    {
        $farm = $this->farm();
        $this->get('/admin/farm-management/'.$farm->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('farms/Show')
            ->where('legalityUrl', $this->detailUrl($farm))
            ->missing('legality')->missing('legalityPermissions')->missing('legalityUrls')->missing('legalityLimits'));
        $this->get($this->detailUrl($farm))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('farms/Legality')
            ->where('farm.id', $farm->id)
            ->where('legality', null)
            ->where('backUrl', '/admin/farm-management/'.$farm->id)
            ->where('legalityPermissions.create', true)
            ->where('legalityPermissions.update', false)
            ->where('legalityPermissions.delete', false));
    }

    public function test_admin_legality_page_rejects_other_roles_foreign_tenants_and_forged_scope_ids(): void
    {
        $farm = $this->farm();
        $record = $this->legality($farm);
        $foreignTeam = Team::factory()->create(['is_personal' => false]);
        $foreign = $this->farm(['name' => 'Lahan tenant lain', 'team_id' => $foreignTeam->id]);
        $this->legality($foreign);
        $this->get($this->detailUrl($foreign).'?team_id='.$this->team->id.'&farm_id='.$farm->id)->assertForbidden();
        $this->get($this->detailUrl($farm).'?team_id='.$foreignTeam->id.'&farm_id='.$foreign->id)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('farm.id', $farm->id)->where('legality.id', $record->id));
        foreach ([$this->owner, $this->actor('petugas')] as $actor) {
            $this->actingAs($actor)->get($this->detailUrl($farm))->assertForbidden();
        }
        $this->app['auth']->guard()->logout();
        $this->get($this->detailUrl($farm))->assertRedirect(route('login'));
    }

    public function test_missing_legality_view_grant_denies_the_page_and_removes_the_farm_detail_link(): void
    {
        $farm = $this->farm();
        $this->legality($farm);
        $roles = app(RolePermissions::class)->roles();
        unset($roles['admin']['farm_legalities.view']);
        config(['access.roles' => $roles]);
        $this->get($this->detailUrl($farm))->assertForbidden();
        $this->get('/admin/farm-management/'.$farm->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('legalityUrl', null)->missing('legality'));
    }

    public function test_admin_creates_legality_with_multiple_private_pdf_drafts_and_owner_reads_the_saved_data(): void
    {
        $farm = $this->farm();
        $this->post($this->createUrl($farm), $this->payload([
            'documents' => [$this->pdf('Draft PKS.pdf'), $this->pdf('Lampiran batas lahan.pdf')],
        ]))->assertSessionHasNoErrors()->assertRedirect($this->detailUrl($farm));

        $legality = FarmLegality::where('farm_id', $farm->id)->firstOrFail();
        $this->assertSame('Koperasi Tani Sejahtera', $legality->institution);
        $this->assertSame('2026-01-01', $legality->pks_started_at->toDateString());
        $documents = FarmLegalityDocument::where('farm_legality_id', $legality->id)->get();
        $this->assertCount(2, $documents);
        foreach ($documents as $document) {
            $this->assertSame('legality', $document->disk);
            $this->assertSame('application/pdf', $document->mime_type);
            Storage::disk('legality')->assertExists($document->path);
            $this->assertStringStartsWith('%PDF-', Storage::disk('legality')->get($document->path));
            $this->get($this->downloadUrl($document))->assertOk()->assertDownload($document->original_name);
        }
        $this->get($this->detailUrl($farm))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('farms/Legality')
            ->where('legality.id', $legality->id)
            ->where('legalityPermissions.create', false)
            ->where('legalityPermissions.update', true)
            ->where('legalityPermissions.delete', true)
            ->has('legality.documents', 2));

        $this->actingAs($this->owner)->get('/kebun/'.$farm->id.'/legalitas')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('legality.institution', 'Koperasi Tani Sejahtera')
                ->where('legality.summary', $this->payload()['summary'])
                ->has('legality.documents', 2)
                ->missing('legality.documents.0.path')->missing('legality.documents.0.disk'));
        $document = $documents->first();
        $this->get($this->downloadUrl($document))->assertOk()->assertDownload($document->original_name);
    }

    public function test_admin_can_save_without_drafts_and_owned_land_does_not_require_pks_dates(): void
    {
        $farm = $this->farm(['land_tenure' => 'owned']);
        $this->post($this->createUrl($farm), $this->payload(['pks_started_at' => null, 'pks_ends_at' => null]))
            ->assertSessionHasNoErrors()->assertRedirect($this->detailUrl($farm));
        $record = FarmLegality::where('farm_id', $farm->id)->firstOrFail();
        $this->assertNull($record->pks_started_at);
        $this->assertNull($record->pks_ends_at);
        $this->assertDatabaseCount('farm_legality_documents', 0);
    }

    public function test_edit_retains_unselected_drafts_removes_selected_drafts_and_adds_new_pdfs(): void
    {
        $farm = $this->farm();
        $record = $this->legality($farm);
        $keep = $this->document($record, 'Tetap disimpan.pdf');
        $remove = $this->document($record, 'Diganti.pdf');

        $this->post($this->editUrl($farm, $record), $this->payload([
            '_method' => 'PUT',
            'summary' => 'Addendum perawatan lahan disepakati bersama.',
            'pks_ends_at' => '2030-12-31',
            'remove_document_ids' => [$remove->id],
            'documents' => [$this->pdf('Addendum.pdf')],
        ]))->assertSessionHasNoErrors()->assertRedirect($this->detailUrl($farm));

        $this->assertSame('2030-12-31', $record->refresh()->pks_ends_at->toDateString());
        $this->assertDatabaseMissing('farm_legality_documents', ['id' => $remove->id]);
        $this->assertDatabaseHas('farm_legality_documents', ['id' => $keep->id]);
        Storage::disk('legality')->assertMissing($remove->path);
        Storage::disk('legality')->assertExists($keep->path);
        $this->assertEqualsCanonicalizing(['Tetap disimpan.pdf', 'Addendum.pdf'], FarmLegalityDocument::where('farm_legality_id', $record->id)->pluck('original_name')->all());
        $this->actingAs($this->owner)->get('/kebun/'.$farm->id.'/legalitas')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('legality.summary', 'Addendum perawatan lahan disepakati bersama.')
            ->where('legality.pks_ends_at', '2030-12-31')
            ->has('legality.documents', 2));
        $this->get($this->downloadUrl($remove))->assertNotFound();
    }

    public function test_deleting_legality_removes_its_drafts_and_owner_sees_the_empty_state(): void
    {
        $farm = $this->farm();
        $record = $this->legality($farm);
        $document = $this->document($record);
        $this->delete($this->editUrl($farm, $record))->assertSessionHasNoErrors()->assertRedirect($this->detailUrl($farm));
        $this->assertDatabaseMissing('farm_legalities', ['id' => $record->id]);
        $this->assertDatabaseMissing('farm_legality_documents', ['id' => $document->id]);
        Storage::disk('legality')->assertMissing($document->path);
        $this->get($this->detailUrl($farm))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('farms/Legality')->where('legality', null)->where('legalityPermissions.create', true));
        $this->actingAs($this->owner)->get('/kebun/'.$farm->id.'/legalitas')->assertOk()->assertInertia(fn (Assert $page) => $page->where('legality', null));
        $this->get($this->downloadUrl($document))->assertNotFound();
    }

    public function test_required_fields_date_order_and_nonowned_pks_dates_are_validated_before_persistence(): void
    {
        $farm = $this->farm();
        foreach ([
            ['institution' => '', 'summary' => '', 'pks_started_at' => null, 'pks_ends_at' => null],
            ['pks_started_at' => '2027-12-31', 'pks_ends_at' => '2026-01-01'],
            ['pks_started_at' => 'not-a-date'],
            ['institution' => str_repeat('a', 256), 'summary' => str_repeat('a', 10001)],
        ] as $invalid) {
            $this->from('/admin/farm-management/'.$farm->id)->post($this->createUrl($farm), $this->payload($invalid))
                ->assertSessionHasErrors();
            $this->assertDatabaseCount('farm_legalities', 0);
            $this->assertSame([], Storage::disk('legality')->allFiles());
        }
    }

    public function test_pdf_extension_and_content_are_both_checked_and_oversized_files_are_rejected(): void
    {
        $farm = $this->farm();
        foreach ([
            $this->upload('Disamarkan.pdf', 'Ini hanya berkas teks, bukan PDF.'),
            $this->upload('Dokumen.txt', $this->pdfContent()),
            UploadedFile::fake()->create('Terlalu besar.pdf', 10241, 'application/pdf'),
        ] as $invalid) {
            $this->post($this->createUrl($farm), $this->payload(['documents' => [$invalid]]))->assertSessionHasErrors('documents.0');
            $this->assertDatabaseCount('farm_legalities', 0);
            $this->assertSame([], Storage::disk('legality')->allFiles());
        }
    }

    public function test_maximum_drafts_includes_existing_documents_and_replacement_can_stay_within_the_limit(): void
    {
        $farm = $this->farm();
        $record = $this->legality($farm);
        $documents = collect(range(1, 5))->map(fn (int $number) => $this->document($record, 'Draft '.$number.'.pdf'));
        $this->put($this->editUrl($farm, $record), $this->payload(['documents' => [$this->pdf('Keenam.pdf')]]))->assertSessionHasErrors();
        $this->assertDatabaseCount('farm_legality_documents', 5);
        $this->assertCount(5, Storage::disk('legality')->allFiles());

        $this->post($this->editUrl($farm, $record), $this->payload([
            '_method' => 'PUT', 'remove_document_ids' => [$documents->first()->id], 'documents' => [$this->pdf('Pengganti.pdf')],
        ]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('farm_legality_documents', 5);
        Storage::disk('legality')->assertMissing($documents->first()->path);
        $other = $this->farm(['name' => 'Kebun Unggah Banyak']);
        $this->post($this->createUrl($other), $this->payload(['documents' => array_map(fn ($i) => $this->pdf('Draft '.$i.'.pdf'), range(1, 6))]))->assertSessionHasErrors('documents');
        $this->assertDatabaseMissing('farm_legalities', ['farm_id' => $other->id]);
    }

    public function test_duplicate_create_does_not_replace_an_existing_record_or_leave_uploaded_files(): void
    {
        $farm = $this->farm();
        $record = $this->legality($farm);
        $original = $record->refresh()->getRawOriginal();
        $this->post($this->createUrl($farm), $this->payload(['documents' => [$this->pdf()]]))->assertSessionHasErrors();
        $this->assertSame($original, $record->fresh()->getRawOriginal());
        $this->assertSame([], Storage::disk('legality')->allFiles());
    }

    public function test_document_removal_ids_cannot_target_another_legal_record(): void
    {
        $farm = $this->farm();
        $record = $this->legality($farm);
        $other = $this->document($this->legality($this->farm(['name' => 'Kebun Kedua'])));
        $before = $record->refresh()->getRawOriginal();
        $this->put($this->editUrl($farm, $record), $this->payload(['remove_document_ids' => [$other->id]]))->assertSessionHasErrors();
        $this->assertSame($before, $record->fresh()->getRawOriginal());
        $this->assertDatabaseHas('farm_legality_documents', ['id' => $other->id]);
        Storage::disk('legality')->assertExists($other->path);
    }

    public function test_route_farm_and_persisted_membership_control_writes_and_downloads_despite_forged_ids(): void
    {
        $farm = $this->farm();
        $otherTeam = Team::factory()->create(['is_personal' => false]);
        $foreign = $this->farm(['name' => 'Lahan Tenant Lain', 'team_id' => $otherTeam->id]);
        $foreignRecord = $this->legality($foreign);
        $document = $this->document($foreignRecord);

        $this->post($this->createUrl($farm), $this->payload([
            'farm_id' => $foreign->id, 'team_id' => $otherTeam->id, 'disk' => 'public', 'path' => '../../draft.pdf', 'documents' => [$this->pdf()],
        ]))->assertSessionHasErrors(['farm_id', 'team_id', 'disk', 'path']);
        $this->assertDatabaseMissing('farm_legalities', ['farm_id' => $farm->id]);
        $this->assertCount(1, Storage::disk('legality')->allFiles());
        $this->post($this->createUrl($farm), $this->payload())->assertSessionHasNoErrors();
        $ownRecord = FarmLegality::where('farm_id', $farm->id)->firstOrFail();
        $this->post($this->createUrl($foreign), $this->payload())->assertForbidden();
        $this->put($this->editUrl($foreign, $foreignRecord), $this->payload())->assertForbidden();
        $this->delete($this->editUrl($foreign, $foreignRecord))->assertForbidden();
        $this->get($this->downloadUrl($document).'?team_id='.$this->team->id.'&farm_id='.$farm->id)->assertForbidden();
        $this->put($this->editUrl($farm, $foreignRecord), $this->payload())->assertForbidden();
        $this->delete($this->editUrl($farm, $foreignRecord))->assertForbidden();
        $sameTeamOther = $this->legality($this->farm(['name' => 'Kebun Tenant Sama']));
        $this->put($this->editUrl($farm, $sameTeamOther), $this->payload())->assertNotFound();
        $this->delete($this->editUrl($farm, $sameTeamOther))->assertNotFound();
        $this->assertSame($farm->id, $ownRecord->fresh()->farm_id);
        $this->assertSame($foreign->id, $foreignRecord->fresh()->farm_id);
        $this->assertSame(0, FarmLegalityDocument::visibleTo($this->admin, $this->team)->count());
    }

    public function test_owner_and_petugas_cannot_mutate_legality_and_only_authorized_readers_download_drafts(): void
    {
        $farm = $this->farm();
        $record = $this->legality($farm);
        $document = $this->document($record);
        $petugas = $this->actor('petugas');
        $petugas->forceFill(['assigned_farm_id' => $farm->id])->save();
        foreach ([$this->owner, $petugas] as $actor) {
            $this->actingAs($actor)->post($this->createUrl($farm), $this->payload())->assertForbidden();
            $this->put($this->editUrl($farm, $record), $this->payload())->assertForbidden();
            $this->delete($this->editUrl($farm, $record))->assertForbidden();
            foreach (['create', 'update', 'delete'] as $ability) {
                $target = $ability === 'create' ? [FarmLegality::class, $farm] : $record;
                $this->assertFalse(Gate::forUser($actor)->allows($ability, $target));
            }
        }
        $this->get($this->downloadUrl($document))->assertForbidden();
        $this->actingAs($this->owner)->get($this->downloadUrl($document))->assertOk();
        $this->assertSame([$document->id], FarmLegalityDocument::visibleTo($this->owner, $this->team)->pluck('id')->all());
        $this->assertSame(0, FarmLegalityDocument::visibleTo($petugas, $this->team)->count());
        $this->assertDatabaseHas('farm_legalities', ['id' => $record->id]);
    }

    public function test_superadmin_can_manage_and_download_with_the_same_active_team_context(): void
    {
        $farm = $this->farm();
        $this->actingAs($this->actor('superadmin'))->post($this->createUrl($farm), $this->payload(['documents' => [$this->pdf()]]))
            ->assertSessionHasNoErrors()->assertRedirect();
        $record = FarmLegality::where('farm_id', $farm->id)->firstOrFail();
        $document = FarmLegalityDocument::where('farm_legality_id', $record->id)->firstOrFail();
        $this->get($this->downloadUrl($document))->assertOk();
        $this->put($this->editUrl($farm, $record), $this->payload(['summary' => 'Pembaruan oleh Superadmin.']))->assertSessionHasNoErrors();
        $this->delete($this->editUrl($farm, $record))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('farm_legalities', ['id' => $record->id]);
    }

    public function test_missing_or_invalid_grants_deny_mutations_and_downloads(): void
    {
        $farm = $this->farm();
        $record = $this->legality($farm);
        $document = $this->document($record);
        $original = app(RolePermissions::class)->roles();
        foreach ([null, 'invalid'] as $scope) {
            $roles = $original;
            foreach (['farm_legalities.create', 'farm_legalities.update', 'farm_legalities.delete', 'farm_legality_documents.view'] as $permission) {
                if ($scope === null) {
                    unset($roles['admin'][$permission]);
                } else {
                    $roles['admin'][$permission] = $scope;
                }
            }
            config(['access.roles' => $roles]);
            $this->post($this->createUrl($farm), $this->payload())->assertForbidden();
            $this->put($this->editUrl($farm, $record), $this->payload())->assertForbidden();
            $this->delete($this->editUrl($farm, $record))->assertForbidden();
            $this->get($this->downloadUrl($document))->assertForbidden();
            $this->assertSame(0, FarmLegalityDocument::visibleTo($this->admin, $this->team)->count());
        }
        config(['access.roles' => $original]);
        $this->assertDatabaseHas('farm_legalities', ['id' => $record->id]);
        Storage::disk('legality')->assertExists($document->path);
    }

    public function test_guests_must_sign_in_and_missing_private_file_returns_not_found(): void
    {
        $farm = $this->farm();
        $record = $this->legality($farm);
        $document = $this->document($record);
        Storage::disk('legality')->delete($document->path);
        $this->get($this->downloadUrl($document))->assertNotFound();
        $this->app['auth']->guard()->logout();
        $this->get($this->downloadUrl($document))->assertRedirect(route('login'));
        $this->post($this->createUrl($farm), $this->payload())->assertRedirect(route('login'));
    }

    public function test_hidden_drafts_block_parent_changes_instead_of_being_silently_cascaded(): void
    {
        $farm = $this->farm();
        $record = $this->legality($farm);
        $document = $this->document($record);
        $before = $record->refresh()->getRawOriginal();
        $roles = app(RolePermissions::class)->roles();
        unset($roles['admin']['farm_legality_documents.view']);
        config(['access.roles' => $roles]);

        $this->put($this->editUrl($farm, $record), $this->payload(['summary' => 'Perubahan tidak boleh tersimpan.']))->assertSessionHasErrors('legality');
        $this->delete($this->editUrl($farm, $record))->assertSessionHasErrors('legality');
        $this->assertSame($before, $record->fresh()->getRawOriginal());
        $this->assertDatabaseHas('farm_legality_documents', ['id' => $document->id]);
        Storage::disk('legality')->assertExists($document->path);
    }

    public function test_document_upload_and_removal_require_their_own_explicit_grants(): void
    {
        $farm = $this->farm();
        $record = $this->legality($farm);
        $document = $this->document($record);
        $original = app(RolePermissions::class)->roles();
        $roles = $original;
        unset($roles['admin']['farm_legality_documents.create']);
        config(['access.roles' => $roles]);
        $this->post($this->editUrl($farm, $record), $this->payload(['_method' => 'PUT', 'documents' => [$this->pdf('Ditolak.pdf')]]))->assertForbidden();
        $this->assertDatabaseCount('farm_legality_documents', 1);
        $this->assertCount(1, Storage::disk('legality')->allFiles());

        $roles = $original;
        unset($roles['admin']['farm_legality_documents.delete']);
        config(['access.roles' => $roles]);
        $this->put($this->editUrl($farm, $record), $this->payload(['remove_document_ids' => [$document->id]]))->assertForbidden();
        $this->delete($this->editUrl($farm, $record))->assertForbidden();
        $this->assertDatabaseHas('farm_legalities', ['id' => $record->id]);
        $this->assertDatabaseHas('farm_legality_documents', ['id' => $document->id]);
        Storage::disk('legality')->assertExists($document->path);
    }

    public function test_failed_document_persistence_rolls_back_the_record_and_cleans_newly_uploaded_files(): void
    {
        $farm = $this->farm();
        $event = 'eloquent.creating: '.FarmLegalityDocument::class;
        Event::listen($event, function (): void {
            throw new RuntimeException('Simulated database write failure.');
        });
        $this->withoutExceptionHandling();
        try {
            $this->post($this->createUrl($farm), $this->payload(['documents' => [$this->pdf()]]));
            $this->fail('The simulated document write must fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated database write failure.', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertDatabaseCount('farm_legalities', 0);
        $this->assertDatabaseCount('farm_legality_documents', 0);
        $this->assertSame([], Storage::disk('legality')->allFiles());
    }

    public function test_normal_farm_deletion_preserves_legal_records_and_drafts(): void
    {
        $farm = $this->farm();
        $record = $this->legality($farm);
        $document = $this->document($record);
        $this->delete('/admin/farm-management/'.$farm->id)->assertSessionHasErrors('delete');
        $this->assertDatabaseHas('farms', ['id' => $farm->id]);
        $this->assertDatabaseHas('farm_legalities', ['id' => $record->id]);
        $this->assertDatabaseHas('farm_legality_documents', ['id' => $document->id]);
        Storage::disk('legality')->assertExists($document->path);
    }

    public function test_authorized_farm_force_deletion_counts_and_cleans_legality_drafts_without_touching_other_farms(): void
    {
        $farm = $this->farm();
        $record = $this->legality($farm);
        $document = $this->document($record);
        $other = $this->document($this->legality($this->farm(['name' => 'Kebun yang tetap disimpan'])));
        $this->actingAs($this->actor('superadmin'));
        $url = '/admin/farm-management/'.$farm->id.'/force-delete';
        $summary = $this->get($url)->assertOk()->viewData('page')['props']['summary'];
        $this->assertSame(1, collect($summary['groups'])->firstWhere('key', 'farm_legalities')['count']);
        $this->assertSame(1, collect($summary['groups'])->firstWhere('key', 'farm_legality_documents')['count']);
        $this->delete($url, [
            'confirmation_name' => $farm->name, 'reason' => 'Penggabungan administrasi lahan yang sudah disetujui.', 'summary_token' => $summary['token'],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseMissing('farms', ['id' => $farm->id]);
        $this->assertDatabaseMissing('farm_legalities', ['id' => $record->id]);
        $this->assertDatabaseMissing('farm_legality_documents', ['id' => $document->id]);
        Storage::disk('legality')->assertMissing($document->path);
        $this->assertDatabaseHas('farm_legality_documents', ['id' => $other->id]);
        Storage::disk('legality')->assertExists($other->path);
    }

    private function actor(string $role): User
    {
        $actor = User::factory()->create(['role' => $role]);
        $this->team->members()->attach($actor, ['role' => TeamRole::Member->value]);
        $actor->switchTeam($this->team);

        return $actor;
    }

    /** @param array<string, mixed> $overrides */
    private function farm(array $overrides = []): Farm
    {
        $farm = new Farm;
        $farm->forceFill([
            'name' => 'Kebun Administrasi Legalitas', 'total_area_ha' => '4.0000', 'land_tenure' => 'partnership',
            'team_id' => $this->team->id, 'created_by' => $this->admin->id, 'land_tenure_party' => 'Koperasi Tani Sejahtera', ...$overrides,
        ])->save();

        return $farm;
    }

    private function legality(Farm $farm): FarmLegality
    {
        return FarmLegality::create(['farm_id' => $farm->id, ...$this->payload()]);
    }

    private function document(FarmLegality $record, string $name = 'Draft PKS.pdf'): FarmLegalityDocument
    {
        $path = 'farms/'.$record->farm_id.'/'.fake()->uuid().'.pdf';
        Storage::disk('legality')->put($path, $this->pdfContent());

        return FarmLegalityDocument::create([
            'farm_legality_id' => $record->id, 'disk' => 'legality', 'path' => $path,
            'original_name' => $name, 'mime_type' => 'application/pdf', 'size_bytes' => strlen($this->pdfContent()),
        ]);
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'pks_started_at' => '2026-01-01', 'pks_ends_at' => '2028-12-31', 'institution' => 'Koperasi Tani Sejahtera',
            'summary' => 'Kerja sama pemanfaatan lahan dan pembagian tanggung jawab perawatan.', ...$overrides,
        ];
    }

    private function pdf(string $name = 'Draft PKS.pdf'): UploadedFile
    {
        return $this->upload($name, $this->pdfContent());
    }

    private function upload(string $name, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'legality-test-');
        file_put_contents($path, $contents);
        $this->temporaryUploads[] = $path;

        return new UploadedFile($path, $name, null, null, true);
    }

    private function pdfContent(): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
    }

    private function createUrl(Farm $farm): string
    {
        return '/admin/farm-management/'.$farm->id.'/legality';
    }

    private function detailUrl(Farm $farm): string
    {
        return '/admin/farm-management/'.$farm->id.'/legalitas';
    }

    private function editUrl(Farm $farm, FarmLegality $legality): string
    {
        return $this->createUrl($farm).'/'.$legality->id;
    }

    private function downloadUrl(FarmLegalityDocument $document): string
    {
        return '/farm-legality-documents/'.$document->id.'/download';
    }
}
