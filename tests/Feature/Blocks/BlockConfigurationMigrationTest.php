<?php

namespace Tests\Feature\Blocks;

use App\Models\Commodity;
use App\Models\Farm;
use App\Models\FarmBlock;
use App\Models\SopTemplate;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Migration integration on the guarded PostgreSQL test database only. */
class BlockConfigurationMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inconsistent_legacy_configuration_is_copied_without_rewriting_it(): void
    {
        $add = require database_path('migrations/2026_11_16_000001_add_commodity_and_sop_to_farm_blocks.php');
        $remove = require database_path('migrations/2026_11_16_000002_remove_farm_commodity_and_default_sop.php');
        $remove->down();
        $add->down();

        $team = Team::factory()->create();
        $actor = User::factory()->create(['role' => 'admin']);
        $corn = Commodity::create(['name' => 'Jagung']);
        $soy = Commodity::create(['name' => 'Kedelai']);
        $sop = SopTemplate::create(['team_id' => $team->id, 'commodity_id' => $soy->id, 'name' => 'SOP Kedelai', 'is_active' => true, 'created_by' => $actor->id]);
        $farm = new Farm(['name' => 'Kebun Lama', 'total_area_ha' => 5]);
        $farm->forceFill(['team_id' => $team->id, 'created_by' => $actor->id, 'commodity_id' => $corn->id, 'default_sop_template_id' => $sop->id])->save();
        $block = new FarmBlock(['name' => 'Blok Lama', 'area_ha' => 5]);
        $block->forceFill(['farm_id' => $farm->id, 'created_by' => $actor->id, 'land_clearing_started_at' => now()])->save();

        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        $add->up();
        $remove->up();

        $this->assertFalse(Schema::hasColumn('farms', 'commodity_id'));
        $this->assertFalse(Schema::hasColumn('farms', 'default_sop_template_id'));
        $this->assertSame($corn->id, $block->fresh()->getAttribute('commodity_id'));
        $this->assertSame($sop->id, $block->fresh()->getAttribute('default_sop_template_id'));
    }
}
