<?php

namespace Tests\Feature\Activity;

use App\Models\ActivityType;
use App\Models\Commodity;
use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Models\Team;
use App\Models\User;
use App\Services\FarmBlockService;
use App\Services\SopTemplateService;
use App\Support\ActivityInputLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Integration tests against the guarded PostgreSQL test database. */
class ActivityInputLimitsTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $admin;

    private User $petugas;

    private Farm $farm;

    private FarmBlock $block;

    private PlantingCycle $cycle;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->team = Team::factory()->create(['is_personal' => false]);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->petugas = User::factory()->create(['role' => 'petugas']);
        foreach ([$this->admin, $this->petugas] as $user) {
            $this->team->members()->attach($user, ['role' => 'member']);
            $user->forceFill(['current_team_id' => $this->team->id])->save();
        }
        $commodity = Commodity::create(['name' => 'Jagung']);
        $variety = CropVariety::create(['commodity_id' => $commodity->id, 'name' => 'Bisi 18']);
        $this->farm = new Farm(['name' => 'Kebun Batas Aktivitas', 'land_tenure' => 'owned', 'total_area_ha' => '10']);
        $this->farm->forceFill(['team_id' => $this->team->id, 'created_by' => $this->admin->id, 'commodity_id' => $commodity->id])->save();
        $this->petugas->forceFill(['assigned_farm_id' => $this->farm->id])->save();
        $blocks = app(FarmBlockService::class);
        $this->block = $blocks->create($this->farm, $this->admin, $this->team, ['code' => 'B01', 'name' => 'Blok Utara', 'area_ha' => '5']);
        $this->cycle = $blocks->createBatch($this->block, $this->admin, $this->team, ['reserved_area_ha' => '2']);
        $this->cycle->forceFill(['planted_at' => today()->subDays(21), 'planted_area_ha' => '2', 'variety_id' => $variety->id, 'status' => 'active'])->save();
        ActivityType::firstOrCreate(['code' => 'harvest'], ['name' => 'Panen']);
        ActivityType::firstOrCreate(['code' => 'irrigation'], ['name' => 'Pengairan']);
    }

    public function test_task_limit_uses_current_harvests_and_stale_writes_are_still_rejected(): void
    {
        $task = $this->task('harvest', null);
        $url = '/petugas/tasks/'.$task->id.'/aktivitas';
        $this->actingAs($this->petugas)->get($url.'/buat')->assertInertia(fn (Assert $page) => $page
            ->component('petugas/ActivityCreate')->where('areaLimitM2', 20000)
            ->where('numericLimits.workerCount', ActivityInputLimits::WORKER_COUNT)
            ->where('fields', fn ($fields) => collect($fields)->firstWhere('key', 'moisture')['max'] === 100));

        $second = User::factory()->create(['role' => 'petugas']);
        $this->team->members()->attach($second, ['role' => 'member']);
        $second->forceFill(['current_team_id' => $this->team->id, 'assigned_farm_id' => $this->farm->id])->save();
        $payload = ['schema' => 'taxonomy', 'work_date' => now('Asia/Jakarta')->toDateString(), 'start_time' => '07:00', 'worker_count' => 2,
            'details' => ['area' => '0.5', 'area_unit' => 'Ha', 'weight' => '100', 'weight_unit' => 'kg'],
            'photos' => [UploadedFile::fake()->image('panen.jpg')]];
        $this->actingAs($second)->post($url, $payload)->assertSessionHasNoErrors();
        $this->actingAs($this->petugas)->get($url.'/buat')->assertInertia(fn (Assert $page) => $page->where('areaLimitM2', 15000));
        $payload['details']['area'] = '1.5001';
        $this->post($url, $payload)->assertSessionHasErrors('details.area');
        $payload['details']['area'] = '1.5';
        $this->post($url, $payload)->assertSessionHasNoErrors();
        $this->get($url.'/buat')->assertInertia(fn (Assert $page) => $page->where('areaLimitM2', 0));
    }

    public function test_task_form_uses_planted_area_and_server_attaches_errors_to_taxonomy_field(): void
    {
        $this->cycle->forceFill(['planted_area_ha' => '1.2500'])->save();
        $task = $this->task('maintenance', 'irrigation');
        $url = '/petugas/tasks/'.$task->id.'/aktivitas';
        $this->actingAs($this->petugas)->get($url.'/buat')->assertInertia(fn (Assert $page) => $page->where('areaLimitM2', 12500));
        $this->post($url, ['schema' => 'taxonomy', 'work_date' => now('Asia/Jakarta')->toDateString(), 'start_time' => '07:00', 'worker_count' => 2,
            'details' => ['area' => 12501, 'area_unit' => 'm²', 'water_source' => 'Sumur bor', 'method' => 'Irigasi parit', 'duration_hours' => 1],
            'photos' => [UploadedFile::fake()->image('irigasi.jpg')]])->assertSessionHasErrors('details.area');
    }

    public function test_voluntary_location_limits_are_scoped_and_planting_limit_is_refreshed(): void
    {
        $other = new Farm(['name' => 'Kebun Lain', 'land_tenure' => 'owned', 'total_area_ha' => '3']);
        $other->forceFill(['team_id' => $this->team->id, 'created_by' => $this->admin->id])->save();
        $foreignBlock = app(FarmBlockService::class)->create($other, $this->admin, $this->team, ['code' => 'B02', 'name' => 'Blok Lain', 'area_ha' => '3']);
        $this->actingAs($this->petugas)->get('/petugas/aktivitas/mandiri/incident')->assertInertia(fn (Assert $page) => $page
            ->component('petugas/VoluntaryCreate')
            ->where('blocks', fn ($blocks) => collect($blocks)->contains(fn ($block) => $block['id'] === $this->block->id && $block['area_limit_m2'] === 50000)
                && ! collect($blocks)->contains('id', $foreignBlock->id))
            ->where('cycles', fn ($cycles) => collect($cycles)->contains(fn ($cycle) => $cycle['id'] === $this->cycle->id && $cycle['area_limit_m2'] === 20000)));

        $this->cycle->forceFill(['planted_area_ha' => '1.2500'])->save();
        $plantingUrl = '/petugas/subbloks/'.$this->cycle->id.'/penanaman';
        $this->get($plantingUrl)->assertInertia(fn (Assert $page) => $page->component('petugas/PlantingCreate')
            ->where('areaLimitM2', 7500)->where('numericLimits.area', ActivityInputLimits::PLANTING_AREA));
        $this->cycle->forceFill(['planted_area_ha' => '2.0000'])->save();
        $this->get($plantingUrl)->assertInertia(fn (Assert $page) => $page->where('areaLimitM2', 0));
        $this->petugas->forceFill(['assigned_farm_id' => $other->id])->save();
        $this->get($plantingUrl)->assertForbidden();
    }

    public function test_mapping_and_admin_sop_forms_receive_the_same_maxima_as_the_server_rules(): void
    {
        $this->actingAs($this->petugas)->get('/kebun/'.$this->farm->id.'/daily-logs')
            ->assertInertia(fn (Assert $page) => $page->component('farmer/DailyActivityLog')
                ->where('numericLimits.length', ActivityInputLimits::MAPPING_LENGTH)
                ->where('numericLimits.spacing', ActivityInputLimits::MAPPING_SPACING)
                ->where('numericLimits.rows', ActivityInputLimits::MAPPING_ROWS));

        $sop = app(SopTemplateService::class)->create($this->admin, $this->team, [
            'commodity_id' => $this->farm->commodity_id, 'name' => 'SOP Jagung', 'is_active' => true,
        ]);
        $version = $sop->versions()->firstOrFail();
        $this->actingAs($this->admin)->get('/admin/sops/'.$sop->id)
            ->assertInertia(fn (Assert $page) => $page->component('sops/Show')
                ->where('numericLimits.offsetDays', ActivityInputLimits::SOP_OFFSET)
                ->where('numericLimits.toleranceDays', ActivityInputLimits::SOP_TOLERANCE));
        $url = '/admin/sops/'.$sop->id.'/versions/'.$version->id.'/tasks';
        $payload = ['activity_category' => 'maintenance', 'activity_subcategory' => 'irrigation', 'name' => 'Pengairan',
            'offset_days' => ActivityInputLimits::SOP_OFFSET + 1, 'tolerance_days' => ActivityInputLimits::SOP_TOLERANCE + 1];
        $this->post($url, $payload)->assertSessionHasErrors(['offset_days', 'tolerance_days']);
        $payload['offset_days'] = ActivityInputLimits::SOP_OFFSET;
        $payload['tolerance_days'] = ActivityInputLimits::SOP_TOLERANCE;
        $this->post($url, $payload)->assertSessionHasNoErrors();
    }

    private function task(string $category, ?string $subcategory): PlantingCycleTask
    {
        return PlantingCycleTask::create([
            'planting_cycle_id' => $this->cycle->id,
            'planting_cycle_stage_id' => $this->cycle->stages()->where('code', $category === 'harvest' ? 'harvest' : 'maintenance')->firstOrFail()->id,
            'activity_category' => $category, 'activity_subcategory' => $subcategory, 'name' => 'Aktivitas kebun',
            'offset_days' => 7, 'tolerance_days' => 2, 'scheduled_date' => today(), 'due_date' => today()->addDays(2),
            'status' => 'pending', 'is_required' => true,
        ]);
    }
}
