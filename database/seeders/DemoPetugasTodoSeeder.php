<?php

namespace Database\Seeders;

use App\Models\ActivityType;
use App\Models\Commodity;
use App\Models\CropVariety;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleStage;
use App\Models\PlantingCycleTask;
use App\Models\SopTemplate;
use App\Models\SopTemplateTask;
use App\Models\SopTemplateVersion;
use App\Models\User;
use App\Services\Activity\PlantingCycleStageService;
use App\Services\FarmBlockService;
use App\Services\PlantingCycleTaskService;
use App\Services\SopTemplateService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Explicit preview seed; deliberately excluded from DatabaseSeeder. */
class DemoPetugasTodoSeeder extends Seeder
{
    public const TEMPLATE_NAME = 'SOP Perawatan Jagung 14 HST · Demo Beranda';

    public function run(): void
    {
        $actor = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['superadmin']['email'])->firstOrFail();
        $team = $actor->teams()->where('slug', 'farm-ic-demo')->firstOrFail();
        $gate = Gate::forUser($actor);
        foreach ([User::class, Farm::class, FarmBlock::class, PlantingCycle::class, PlantingCycleStage::class,
            PlantingCycleTask::class, SopTemplate::class, SopTemplateVersion::class, SopTemplateTask::class,
            Commodity::class, CropVariety::class] as $model) {
            $gate->authorize('viewAny', [$model, $team]);
        }
        $petugas = User::visibleTo($actor, $team)->where('email', DemoIdentitySeeder::ACCOUNTS['petugas']['email'])->firstOrFail();

        DB::transaction(function () use ($actor, $team, $gate, $petugas): void {
            $farm = Farm::visibleTo($actor, $team)->whereKey($petugas->assigned_farm_id)
                ->whereIn('name', DemoFarmSeeder::FARM_NAMES)->lockForUpdate()->firstOrFail();
            $exists = PlantingCycle::visibleTo($actor, $team)->where('farm_id', $farm->id)
                ->whereHas('sopTemplateVersion', fn ($versions) => $versions->visibleTo($actor, $team)
                    ->whereHas('sopTemplate', fn ($templates) => $templates->visibleTo($actor, $team)->where('name', self::TEMPLATE_NAME)))->exists();
            if ($exists) {
                $this->command->info('Contoh tugas sudah tersedia; jadwal dan status sebelumnya dipertahankan.');

                return;
            }

            $today = CarbonImmutable::today('Asia/Jakarta');
            $planted = $today->subDays(14);
            $commodity = Commodity::visibleTo($actor, $team)->where('name', 'Jagung')->firstOrFail();
            $variety = CropVariety::visibleTo($actor, $team)->where('commodity_id', $commodity->id)->where('name', 'Bisi 18')->firstOrFail();
            $block = FarmBlock::visibleTo($actor, $team)->where('farm_id', $farm->id)->where('code', 'B01')->firstOrFail();
            $sops = app(SopTemplateService::class);
            $gate->authorize('create', [SopTemplate::class, $team]);
            $template = $sops->create($actor, $team, [
                'commodity_id' => $commodity->id, 'name' => self::TEMPLATE_NAME, 'is_active' => true,
                'description' => 'Contoh jadwal perawatan untuk pratinjau daftar tugas beranda petugas.',
            ]);
            $version = SopTemplateVersion::visibleTo($actor, $team)->where('sop_template_id', $template->id)->firstOrFail();
            $definitions = [
                ['name' => 'Pengairan pagi', 'subcategory' => 'irrigation', 'status' => 'in_progress',
                    'instructions' => 'Periksa kelembapan tanah. Siram di sekitar pangkal tanaman tanpa menimbulkan genangan.'],
                ['name' => 'Pemupukan susulan pertama', 'subcategory' => 'follow_up_fertilization', 'status' => 'pending',
                    'instructions' => 'Gunakan NPK sesuai dosis SOP kebun dan hindari kontak langsung dengan batang.'],
                ['name' => 'Penyiangan gulma', 'subcategory' => 'weeding', 'status' => 'pending',
                    'instructions' => 'Bersihkan gulma di antara barisan tanpa melukai akar dan pangkal tanaman.'],
                ['name' => 'Pengamatan hama', 'subcategory' => 'pest_control', 'status' => 'completed',
                    'instructions' => 'Periksa daun dan pucuk di beberapa titik. Catat gejala serangan dan dokumentasikan tanaman terdampak.'],
            ];
            foreach ($definitions as $definition) {
                $gate->authorize('create', [SopTemplateTask::class, $version]);
                $sops->createTask($version, [
                    'activity_category' => 'maintenance', 'activity_subcategory' => $definition['subcategory'],
                    'name' => $definition['name'], 'instructions' => $definition['instructions'],
                    'offset_days' => 14, 'tolerance_days' => 0, 'is_required' => true,
                ]);
            }
            $gate->authorize('update', $version);
            $sops->publish($version, $actor);
            $gate->authorize('create', [PlantingCycle::class, $block]);
            $batch = app(FarmBlockService::class)->createBatch($block, $actor, $team, [
                'reserved_area_ha' => '0.2500', 'subblock_label' => 'SOP-DEMO', 'planned_planting_at' => $planted->toDateString(),
            ]);
            $gate->authorize('update', $batch);
            $batch->forceFill(['status' => 'active', 'variety_id' => $variety->id, 'planted_at' => $planted->toDateString(),
                'planted_area_ha' => '0.2500', 'sop_template_version_id' => $version->id,
                'estimated_harvest_at' => $planted->addDays(90)->toDateString()])->save();

            $gate->authorize('create', [PlantingCycleTask::class, $batch]);
            app(PlantingCycleTaskService::class)->generateForSubblok($batch, $actor);
            $statuses = array_column($definitions, 'status', 'name');
            foreach (PlantingCycleTask::visibleTo($actor, $team)->where('planting_cycle_id', $batch->id)->get() as $task) {
                $gate->authorize('update', $task);
                $status = $statuses[$task->name];
                $task->forceFill(['status' => $status, 'completed_at' => $status === 'completed' ? now() : null])->save();
            }
            foreach (['planting', 'pest_control'] as $code) {
                $type = ActivityType::visibleTo($actor, $team)->where('code', $code)->firstOrFail();
                $gate->authorize('create', [FarmActivity::class, $farm]);
                $activity = new FarmActivity([
                    'farm_id' => $farm->id, 'planting_cycle_id' => $batch->id, 'activity_type_id' => $type->id,
                    'performed_by' => $petugas->id, 'activity_date' => ($code === 'planting' ? $planted : $today)->setTime(8, 0),
                    'description' => $code === 'planting' ? 'Penanaman contoh tugas SOP.' : 'Pemantauan hama contoh tugas SOP.',
                    'status' => 'completed',
                ]);
                $activity->forceFill(['block_id' => $block->id])->save();
                if ($code === 'planting') {
                    $activity->plantingDetail()->create(['seed_name' => 'Benih jagung Bisi 18', 'quantity_kg' => 5, 'area_ha' => '0.2500']);
                } else {
                    $activity->pesticideDetail()->create(['pesticide_category' => 'Biologis', 'purpose' => 'Pemantauan hama']);
                }
                app(PlantingCycleStageService::class)->record($actor, $team, $activity, $type);
            }
            $this->command->info('Empat contoh tugas SOP ditambahkan untuk '.$today->toDateString().' (WIB).');
        });
    }
}
