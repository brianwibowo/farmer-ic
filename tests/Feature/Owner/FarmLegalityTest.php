<?php

namespace Tests\Feature\Owner;

use App\Enums\TeamRole;
use App\Models\Farm;
use App\Models\FarmLegality;
use App\Models\Team;
use App\Models\User;
use App\Support\RolePermissions;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** HTTP, policy and persistence integration on the guarded separate PostgreSQL schema. */
class FarmLegalityTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 10)->setTime(12, 0));
        $this->owner = User::factory()->create(['role' => 'owner']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->team = Team::factory()->create(['name' => 'Tenant Legalitas Lahan', 'is_personal' => false]);
        foreach ([$this->owner, $this->admin] as $actor) {
            $this->team->members()->attach($actor, ['role' => TeamRole::Member->value]);
            $actor->switchTeam($this->team);
        }
        $this->actingAs($this->owner);
    }

    public function test_owner_reads_the_persisted_legality_for_the_requested_farm(): void
    {
        $farm = $this->farm('Kebun Kerja Sama');
        $record = $this->legality($farm);
        $other = $this->farm('Kebun Sewa');
        $this->legality($other, ['institution' => 'Koperasi Kebun Lain']);

        $this->get($this->url($farm).'?from=home')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('owner/Legality')
            ->where('farm.id', $farm->id)
            ->where('farm.name', 'Kebun Kerja Sama')
            ->where('farm.land_tenure', 'partnership')
            ->where('farm.land_tenure_party', 'Kelompok Tani Makmur')
            ->where('legality.id', $record->id)
            ->where('legality.pks_started_at', '2026-01-01')
            ->where('legality.pks_ends_at', '2028-12-31')
            ->where('legality.institution', 'Kelompok Tani Makmur')
            ->where('legality.summary', 'Kerja sama pengelolaan lahan dan pemeliharaan jaringan irigasi.')
            ->where('legality.status', 'active')
            ->where('urls.farm', '/kebun/'.$farm->id)
            ->where('urls.home', '/')
            ->where('urls.farms', '/kebun'));

        $record->update(['summary' => 'Addendum pembagian tanggung jawab pemeliharaan telah dicatat.']);
        $this->get($this->url($farm))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('legality.summary', 'Addendum pembagian tanggung jawab pemeliharaan telah dicatat.'));
    }

    public function test_farm_with_no_record_has_an_explicit_empty_state_and_does_not_borrow_another_farms_record(): void
    {
        $farm = $this->farm('Kebun Belum Memiliki Dokumen');
        $this->legality($this->farm('Kebun Sudah Tercatat'));

        $this->get($this->url($farm))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('owner/Legality')->where('farm.id', $farm->id)->where('legality', null));
    }

    public function test_status_is_derived_from_dates_including_pks_boundaries_and_owned_land(): void
    {
        $farm = $this->farm('Kebun Status PKS');
        $record = $this->legality($farm);
        foreach ([
            ['2026-11-11', '2027-11-11', 'upcoming'],
            ['2026-01-01', '2026-11-09', 'expired'],
            ['2026-11-10', '2026-11-10', 'active'],
            [null, null, 'incomplete'],
            ['2026-01-01', null, 'incomplete'],
            [null, '2028-12-31', 'incomplete'],
        ] as [$start, $end, $status]) {
            $record->update(['pks_started_at' => $start, 'pks_ends_at' => $end]);
            $this->get($this->url($farm))->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('legality.status', $status)
                ->where('legality.pks_started_at', $start)
                ->where('legality.pks_ends_at', $end));
        }
        $farm->update(['land_tenure' => 'owned']);
        $record->update(['pks_started_at' => null, 'pks_ends_at' => null]);
        $this->get($this->url($farm))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('legality.status', 'not_applicable'));
    }

    public function test_owner_route_denies_other_roles_and_requires_authentication(): void
    {
        $farm = $this->farm('Kebun Akses Owner');
        $this->legality($farm);
        foreach (['admin', 'petugas'] as $role) {
            $actor = $role === 'admin' ? $this->admin : User::factory()->create(['role' => $role]);
            $this->actingAs($actor)->get($this->url($farm))->assertForbidden();
        }
        $this->app['auth']->guard()->logout();
        $this->get($this->url($farm))->assertRedirect(route('login'));
    }

    public function test_foreign_tenant_and_forged_scope_ids_cannot_change_the_target_or_expose_its_legal_record(): void
    {
        $farm = $this->farm('Kebun Dalam Tenant');
        $record = $this->legality($farm);
        $otherTeam = Team::factory()->create(['is_personal' => false]);
        $foreign = $this->farm('Kebun Tenant Rahasia', $otherTeam);
        $foreignRecord = $this->legality($foreign, ['summary' => 'Informasi tenant lain']);

        $this->get($this->url($foreign).'?team_id='.$this->team->id.'&farm_id='.$farm->id)->assertForbidden();
        $this->get($this->url($farm).'?team_id='.$otherTeam->id.'&farm_id='.$foreign->id)
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('legality.id', $record->id));
        $query = FarmLegality::visibleTo($this->owner, $this->team);
        $this->assertSame(1, (clone $query)->count());
        $this->assertSame(1, (clone $query)->paginate(1)->total());
        $this->assertSame([$record->id], (clone $query)->pluck('id')->all());
        $this->assertFalse(Gate::forUser($this->owner)->allows('view', $foreignRecord));
    }

    public function test_owner_with_two_tenants_must_switch_the_persisted_active_team_to_read_the_other_detail(): void
    {
        $second = Team::factory()->create(['is_personal' => false]);
        $second->members()->attach($this->owner, ['role' => TeamRole::Member->value]);
        $farm = $this->farm('Kebun Tenant Kedua', $second);
        $record = $this->legality($farm);
        $this->get($this->url($farm).'?team_id='.$second->id)->assertNotFound();
        $this->owner->refresh()->switchTeam($second);
        $this->get($this->url($farm))->assertOk()->assertInertia(fn (Assert $page) => $page->where('legality.id', $record->id));
    }

    public function test_new_resource_keeps_owner_read_only_and_explicitly_allows_admin_management(): void
    {
        $farm = $this->farm('Kebun Izin Baca');
        $record = $this->legality($farm);
        foreach ([$this->owner, $this->admin] as $actor) {
            $this->assertTrue(Gate::forUser($actor)->allows('view', $record));
            $this->assertTrue(Gate::forUser($actor)->allows('viewAny', [FarmLegality::class, $this->team]));
            $this->assertSame([$record->id], FarmLegality::visibleTo($actor, $this->team)->pluck('id')->all());
            $this->assertSame($actor->is($this->admin), Gate::forUser($actor)->allows('create', [FarmLegality::class, $farm]));
            foreach (['update', 'delete'] as $ability) {
                $this->assertSame($actor->is($this->admin), Gate::forUser($actor)->allows($ability, $record));
            }
            foreach (['configure', 'forceDelete'] as $ability) {
                $this->assertFalse(Gate::forUser($actor)->allows($ability, $record));
            }
        }
        $petugas = User::factory()->create(['role' => 'petugas', 'assigned_farm_id' => $farm->id]);
        $this->team->members()->attach($petugas, ['role' => TeamRole::Member->value]);
        $this->assertFalse(Gate::forUser($petugas)->allows('view', $record));
        $this->assertSame(0, FarmLegality::visibleTo($petugas, $this->team)->count());
    }

    public function test_missing_invalid_grants_and_revoked_membership_deny_the_http_read_and_query(): void
    {
        $farm = $this->farm('Kebun Grant Legalitas');
        $record = $this->legality($farm);
        $original = app(RolePermissions::class)->roles();
        foreach ([null, 'invalid'] as $scope) {
            $roles = $original;
            if ($scope === null) {
                unset($roles['owner']['farm_legalities.view']);
            } else {
                $roles['owner']['farm_legalities.view'] = $scope;
            }
            config(['access.roles' => $roles]);
            $this->assertFalse(Gate::forUser($this->owner)->allows('view', $record));
            $this->assertSame(0, FarmLegality::visibleTo($this->owner, $this->team)->count());
            $this->get($this->url($farm))->assertForbidden();
        }
        config(['access.roles' => $original]);
        $this->team->members()->detach($this->owner);
        $this->owner->unsetRelation('teams')->unsetRelation('currentTeam');
        $this->get($this->url($farm))->assertForbidden();
        $this->assertSame(0, FarmLegality::visibleTo($this->owner, $this->team)->count());
    }

    public function test_own_scope_when_explicitly_granted_uses_the_persisted_farm_assignment(): void
    {
        $first = $this->farm('Kebun Ditugaskan');
        $second = $this->farm('Kebun Tidak Ditugaskan');
        $allowed = $this->legality($first);
        $denied = $this->legality($second);
        $petugas = User::factory()->create(['role' => 'petugas', 'assigned_farm_id' => $first->id]);
        $this->team->members()->attach($petugas, ['role' => TeamRole::Member->value]);
        $roles = app(RolePermissions::class)->roles();
        $roles['petugas']['farm_legalities.view'] = 'own';
        config(['access.roles' => $roles]);
        $this->assertTrue(Gate::forUser($petugas)->allows('view', $allowed));
        $this->assertFalse(Gate::forUser($petugas)->allows('view', $denied));
        $this->assertSame([$allowed->id], FarmLegality::visibleTo($petugas, $this->team)->pluck('id')->all());
        $petugas->forceFill(['assigned_farm_id' => null])->save();
        $petugas->refresh();
        $this->assertSame(0, FarmLegality::visibleTo($petugas, $this->team)->count());
    }

    public function test_database_rejects_an_end_date_before_the_start_date(): void
    {
        $farm = $this->farm('Kebun Validasi Tanggal');
        $this->expectException(QueryException::class);
        $this->legality($farm, ['pks_started_at' => '2027-01-01', 'pks_ends_at' => '2026-12-31']);
    }

    public function test_database_keeps_one_legality_record_per_farm(): void
    {
        $farm = $this->farm('Kebun Satu Legalitas');
        $this->legality($farm);
        $this->expectException(QueryException::class);
        $this->legality($farm);
    }

    private function farm(string $name, ?Team $team = null): Farm
    {
        $farm = new Farm(['name' => $name, 'total_area_ha' => '6.5000', 'land_tenure' => 'partnership', 'city' => 'Kabupaten Bogor']);
        $farm->forceFill(['team_id' => ($team ?? $this->team)->id, 'created_by' => $this->admin->id, 'land_tenure_party' => 'Kelompok Tani Makmur'])->save();

        return $farm;
    }

    /** @param array<string, mixed> $overrides */
    private function legality(Farm $farm, array $overrides = []): FarmLegality
    {
        return FarmLegality::create([
            'farm_id' => $farm->id, 'pks_started_at' => '2026-01-01', 'pks_ends_at' => '2028-12-31',
            'institution' => 'Kelompok Tani Makmur', 'summary' => 'Kerja sama pengelolaan lahan dan pemeliharaan jaringan irigasi.',
            ...$overrides,
        ]);
    }

    private function url(Farm $farm): string
    {
        return '/kebun/'.$farm->id.'/legalitas';
    }
}
