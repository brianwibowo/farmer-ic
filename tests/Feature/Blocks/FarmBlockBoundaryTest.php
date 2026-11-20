<?php

namespace Tests\Feature\Blocks;

use App\Enums\TeamRole;
use App\Models\Commodity;
use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\FarmBlock;
use App\Models\Team;
use App\Models\User;
use App\Services\FarmBlockService;
use App\Support\GeoPolygon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FarmBlockBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $admin;

    private Farm $farm;

    private FarmBlock $block;

    protected function setUp(): void
    {
        parent::setUp();
        $this->team = Team::factory()->create();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->join($this->admin, $this->team);
        $this->farm = new Farm([
            'name' => 'Kebun Peta Blok',
            'total_area_ha' => '10.0000',
            'land_tenure' => 'owned',
            'latitude' => '-6.5965000',
            'longitude' => '106.9502000',
        ]);
        $this->farm->forceFill(['team_id' => $this->team->id, 'created_by' => $this->admin->id])->save();
        $this->block = app(FarmBlockService::class)->create($this->farm, $this->admin, $this->team, [
            'name' => 'Blok Utara',
            'area_ha' => '5.0000',
        ]);
        $this->actingAs($this->admin);
    }

    private function join(User $user, Team $team): void
    {
        $team->members()->attach($user, ['role' => TeamRole::Member->value]);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $user->unsetRelation('teams')->unsetRelation('currentTeam');
    }

    /**
     * ~1.0000 ha rectangle near Cileuksa (-6.5965, 106.9502).
     *
     * @return array{type: 'Polygon', coordinates: list<list<array{0: float, 1: float}>>}
     */
    private function samplePolygon(float $scale = 1.0): array
    {
        $dLat = 0.0008983 * $scale;
        $dLng = 0.0009043 * $scale;
        $lat0 = -6.5965;
        $lng0 = 106.9502;

        return [
            'type' => 'Polygon',
            'coordinates' => [[
                [$lng0, $lat0],
                [$lng0 + $dLng, $lat0],
                [$lng0 + $dLng, $lat0 + $dLat],
                [$lng0, $lat0 + $dLat],
            ]],
        ];
    }

    private function boundaryUrl(?FarmBlock $block = null, ?Farm $farm = null): string
    {
        return '/kebun/'.($farm ?? $this->farm)->id.'/blocks/'.($block ?? $this->block)->id.'/boundary';
    }

    public function test_admin_can_save_update_and_clear_block_boundary_without_overwriting_area_by_default(): void
    {
        $polygon = $this->samplePolygon();
        $expectedArea = GeoPolygon::areaHectares(GeoPolygon::normalize($polygon));

        $this->put($this->boundaryUrl(), [
            'boundary_geojson' => $polygon,
            'return_to' => 'farm',
        ])->assertRedirect('/admin/farm-management/'.$this->farm->id);

        $this->block->refresh();
        $this->assertNotNull($this->block->boundary_geojson);
        $this->assertSame('Polygon', $this->block->boundary_geojson['type']);
        $this->assertCount(5, $this->block->boundary_geojson['coordinates'][0]);
        $this->assertSame($this->block->boundary_geojson['coordinates'][0][0], $this->block->boundary_geojson['coordinates'][0][4]);
        $this->assertSame($expectedArea, $this->block->mapped_area_ha);
        $this->assertGreaterThan(0.95, (float) $this->block->mapped_area_ha);
        $this->assertLessThan(1.05, (float) $this->block->mapped_area_ha);
        // Operational area stays untouched when apply_area is false.
        $this->assertSame('5.0000', $this->block->area_ha);

        // Clearing boundary resets both columns.
        $this->put($this->boundaryUrl(), [
            'boundary_geojson' => null,
            'return_to' => 'farm',
        ])->assertRedirect('/admin/farm-management/'.$this->farm->id);

        $this->block->refresh();
        $this->assertNull($this->block->boundary_geojson);
        $this->assertNull($this->block->mapped_area_ha);
        $this->assertSame('5.0000', $this->block->area_ha);
    }

    public function test_optional_apply_area_updates_block_area_and_respects_capacity_guards(): void
    {
        $polygon = $this->samplePolygon();
        $expectedArea = GeoPolygon::areaHectares(GeoPolygon::normalize($polygon));

        $this->put($this->boundaryUrl(), [
            'boundary_geojson' => $polygon,
            'apply_area' => true,
            'return_to' => 'farm',
        ])->assertRedirect('/admin/farm-management/'.$this->farm->id);

        $this->block->refresh();
        $this->assertSame($expectedArea, $this->block->area_ha);
        $this->assertSame($expectedArea, $this->block->mapped_area_ha);

        // Huge polygon (~100 ha) exceeds 10 ha farm capacity when apply_area is true.
        $hugePolygon = $this->samplePolygon(10.0);
        $this->put($this->boundaryUrl(), [
            'boundary_geojson' => $hugePolygon,
            'apply_area' => true,
        ])->assertSessionHasErrors('area_ha');
        $this->assertSame($expectedArea, $this->block->refresh()->area_ha);

        // Saving the same huge polygon WITHOUT apply_area still succeeds because mapped area is advisory.
        $this->put($this->boundaryUrl(), [
            'boundary_geojson' => $hugePolygon,
            'apply_area' => false,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertGreaterThan(90.0, (float) $this->block->refresh()->mapped_area_ha);
        $this->assertSame($expectedArea, $this->block->area_ha);

        // Reducing area below running subblocks' planted_area_ha is rejected when apply_area is true.
        $this->block->forceFill(['area_ha' => '5.0000'])->save();
        $commodity = Commodity::create(['name' => 'Jagung Batas']);
        $variety = CropVariety::create(['commodity_id' => $commodity->id, 'name' => 'Bisi Batas']);
        $cycle = app(FarmBlockService::class)->createBatch($this->block, $this->admin, $this->team);
        $cycle->forceFill(['variety_id' => $variety->id, 'planted_at' => '2026-11-01', 'planted_area_ha' => '3.0000', 'status' => 'active'])->save();

        $this->put($this->boundaryUrl(), [
            'boundary_geojson' => $this->samplePolygon(),
            'apply_area' => true,
        ])->assertSessionHasErrors('area_ha');
    }

    /** @return array<string, array{string, bool}> */
    public static function roles(): array
    {
        return [
            'owner' => ['owner', false],
            'petugas' => ['petugas', false],
            'admin' => ['admin', true],
            'superadmin' => ['superadmin', true],
        ];
    }

    #[DataProvider('roles')]
    public function test_role_authorization_allows_admin_and_superadmin_and_denies_owner_and_petugas(string $role, bool $allowed): void
    {
        $actor = User::factory()->create(['role' => $role]);
        if ($role !== 'superadmin') {
            $this->join($actor, $this->team);
        }
        if ($role === 'petugas') {
            $actor->forceFill(['assigned_farm_id' => $this->farm->id])->save();
        }

        $this->actingAs($actor);
        $response = $this->put($this->boundaryUrl(), [
            'boundary_geojson' => $this->samplePolygon(),
            'return_to' => 'farm',
        ]);

        if ($allowed) {
            $response->assertRedirect('/admin/farm-management/'.$this->farm->id);
            $this->assertNotNull($this->block->refresh()->boundary_geojson);
        } else {
            $response->assertForbidden();
            $this->assertNull($this->block->refresh()->boundary_geojson);
        }
    }

    public function test_owner_and_petugas_can_view_saved_block_boundaries_read_only(): void
    {
        $this->put($this->boundaryUrl(), ['boundary_geojson' => $this->samplePolygon()])->assertRedirect();

        $owner = User::factory()->create(['role' => 'owner']);
        $this->join($owner, $this->team);
        $this->actingAs($owner)
            ->get('/kebun/'.$this->farm->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('farms/Show')
                ->where('blocks.0.id', $this->block->id)
                ->where('blocks.0.boundary_geojson.type', 'Polygon')
                ->where('blocks.0.mapped_area_ha', $this->block->refresh()->mapped_area_ha)
                ->where('blocks.0.can.update', false));

        $petugas = User::factory()->create(['role' => 'petugas']);
        $this->join($petugas, $this->team);
        $petugas->forceFill(['assigned_farm_id' => $this->farm->id])->save();
        $this->actingAs($petugas)
            ->get('/petugas/kebun')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('petugas/Kebun')
                ->where('garden.blocks.0.id', $this->block->id)
                ->where('garden.blocks.0.boundary_geojson.type', 'Polygon')
                ->where('garden.blocks.0.mapped_area_ha', $this->block->mapped_area_ha));
    }

    public function test_cross_team_and_mismatched_farm_block_are_denied(): void
    {
        $otherTeam = Team::factory()->create();
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $this->join($otherAdmin, $otherTeam);
        $otherFarm = new Farm(['name' => 'Kebun Tim Lain', 'total_area_ha' => '5.0000', 'land_tenure' => 'owned']);
        $otherFarm->forceFill(['team_id' => $otherTeam->id, 'created_by' => $otherAdmin->id])->save();
        $otherBlock = app(FarmBlockService::class)->create($otherFarm, $otherAdmin, $otherTeam, ['name' => 'Blok Tim Lain', 'area_ha' => '2.0000']);

        $this->put($this->boundaryUrl($otherBlock, $otherFarm), ['boundary_geojson' => $this->samplePolygon()])->assertForbidden();
        $this->put($this->boundaryUrl($otherBlock, $this->farm), ['boundary_geojson' => $this->samplePolygon()])->assertNotFound();
    }

    public function test_admin_can_create_update_and_clear_block_boundary_through_block_form_endpoints(): void
    {
        $polygon = $this->samplePolygon();
        $expectedArea = GeoPolygon::areaHectares(GeoPolygon::normalize($polygon));

        $this->post('/kebun/'.$this->farm->id.'/blocks', [
            'name' => 'Blok Timur',
            'area_ha' => '2.5000',
            'boundary_geojson' => $polygon,
            'return_to' => 'farm',
        ])->assertRedirect('/admin/farm-management/'.$this->farm->id);

        $created = FarmBlock::where('name', 'Blok Timur')->firstOrFail();
        $this->assertSame('Polygon', $created->boundary_geojson['type']);
        $this->assertSame($expectedArea, $created->mapped_area_ha);
        $this->assertSame('2.5000', $created->area_ha);

        // Update block without sending boundary_geojson preserves existing boundary.
        $this->patch('/kebun/'.$this->farm->id.'/blocks/'.$created->id, [
            'name' => 'Blok Timur Revisi',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($expectedArea, $created->refresh()->mapped_area_ha);

        // Sending boundary_geojson: null clears boundary and mapped area.
        $this->put('/kebun/'.$this->farm->id.'/blocks/'.$created->id, [
            'name' => 'Blok Timur Revisi',
            'area_ha' => '2.5000',
            'boundary_geojson' => null,
            'return_to' => 'farm',
        ])->assertSessionHasNoErrors()->assertRedirect('/admin/farm-management/'.$this->farm->id);
        $created->refresh();
        $this->assertNull($created->boundary_geojson);
        $this->assertNull($created->mapped_area_ha);
    }

    public function test_validation_rejects_invalid_polygons_self_intersection_and_forged_fields(): void
    {
        // Forged server-computed fields prohibited.
        $this->put($this->boundaryUrl(), [
            'boundary_geojson' => $this->samplePolygon(),
            'mapped_area_ha' => '99.0000',
            'area_ha' => '99.0000',
            'farm_id' => $this->farm->id,
        ])->assertSessionHasErrors(['mapped_area_ha', 'area_ha', 'farm_id']);

        // Regular block create/update still prohibits forged mapped_area_ha.
        $this->patch('/kebun/'.$this->farm->id.'/blocks/'.$this->block->id, [
            'name' => 'Blok Utara',
            'boundary_geojson' => $this->samplePolygon(),
            'mapped_area_ha' => '1.0000',
        ])->assertSessionHasErrors('mapped_area_ha');

        $bowtie = [
            'type' => 'Polygon',
            'coordinates' => [[
                [106.9502, -6.5965],
                [106.9511, -6.5956],
                [106.9511, -6.5965],
                [106.9502, -6.5956],
            ]],
        ];

        // Invalid GeoJSON payloads including self-intersecting bowtie polygon.
        foreach ([
            ['type' => 'Point', 'coordinates' => [106.95, -6.59]],
            ['type' => 'Polygon', 'coordinates' => [[[106.95, -6.59], [106.96, -6.59]]]],
            ['type' => 'Polygon', 'coordinates' => [[[200.0, -6.59], [106.96, -6.59], [106.96, -6.58]]]],
            ['type' => 'Polygon', 'coordinates' => [[[106.95, -6.59], [106.95, -6.59], [106.95, -6.59]]]],
            $bowtie,
        ] as $invalid) {
            $this->put($this->boundaryUrl(), ['boundary_geojson' => $invalid])->assertSessionHasErrors('boundary_geojson');
            $this->post('/kebun/'.$this->farm->id.'/blocks', [
                'name' => 'Blok Gagal',
                'area_ha' => '1.0000',
                'boundary_geojson' => $invalid,
            ])->assertSessionHasErrors('boundary_geojson');
        }
    }

    public function test_database_constraint_rejects_inconsistent_boundary_and_mapped_area(): void
    {
        $this->expectException(QueryException::class);
        DB::table('farm_blocks')->where('id', $this->block->id)->update([
            'boundary_geojson' => json_encode($this->samplePolygon(), JSON_THROW_ON_ERROR),
            'mapped_area_ha' => null,
        ]);
    }
}
