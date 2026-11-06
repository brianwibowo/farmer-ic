<?php

namespace Database\Seeders;

use App\Models\Commodity;
use App\Models\Farm;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\SopTemplate;
use App\Models\Team;
use App\Models\User;
use App\Services\FarmBlockService;
use App\Services\PlantingCycleTaskService;
use App\Services\SopTemplateService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Gate;
use Throwable;

class DemoSopSeeder extends Seeder
{
    public function run(): void
    {
        $team = Team::query()->where('slug', 'farm-ic-demo')->firstOrFail();
        $commodity = Commodity::query()->where('name', 'Jagung')->firstOrFail();
        $admin = User::query()->where('email', DemoIdentitySeeder::ACCOUNTS['admin']['email'])->firstOrFail();
        Gate::forUser($admin)->authorize('viewAny', [Farm::class, $team]);
        $farm = Farm::visibleTo($admin, $team)->where('team_id', $team->id)
            ->whereIn('name', DemoFarmSeeder::FARM_NAMES)->first();

        $template = SopTemplate::query()->firstOrCreate([
            'team_id' => $team->id,
            'commodity_id' => $commodity->id,
            'name' => 'SOP Budidaya Jagung',
        ], [
            'description' => 'Panduan perawatan sampai panen untuk budidaya jagung hibrida.',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        if ($farm instanceof Farm && $farm->default_sop_template_id === null) {
            $farm->forceFill(['default_sop_template_id' => $template->id])->save();
        }

        if (! $template->versions()->exists()) {
            $version = $template->versions()->create([
                'version' => 1,
                'notes' => 'Versi awal untuk demonstrasi SOP modular berbasis HST.',
                'status' => 'draft',
                'created_by' => $admin->id,
            ]);

            $tasks = [
                // Planting is the HST anchor and planting activity, not a follow-up SOP task.
                ['category' => 'maintenance', 'subcategory' => 'irrigation', 'name' => 'Pengairan awal', 'offset' => 7, 'instructions' => 'Pastikan kelembapan tanah cukup setelah tanam; hindari genangan.'],
                ['category' => 'maintenance', 'subcategory' => 'follow_up_fertilization', 'name' => 'Pemupukan susulan pertama', 'offset' => 14, 'instructions' => 'Aplikasikan NPK sesuai dosis anjuran dan hindari kontak langsung dengan batang.'],
                ['category' => 'maintenance', 'subcategory' => 'pest_control', 'name' => 'Pengendalian hama dan penyakit', 'offset' => 30, 'instructions' => 'Lakukan pengamatan dan aplikasikan pengendalian hanya jika ditemukan serangan.'],
                ['category' => 'maintenance', 'subcategory' => 'follow_up_fertilization', 'name' => 'Pemupukan lanjutan', 'offset' => 45, 'instructions' => 'Berikan pupuk susulan sesuai fase pertumbuhan dan hasil pengamatan.'],
                ['category' => 'harvest', 'subcategory' => null, 'name' => 'Panen', 'offset' => 90, 'instructions' => 'Panen saat tongkol matang fisiologis; catat luas, berat, dan kualitas hasil.'],
            ];

            foreach ($tasks as $task) {
                $version->tasks()->create([
                    'activity_category' => $task['category'],
                    'activity_subcategory' => $task['subcategory'],
                    'name' => $task['name'],
                    'instructions' => $task['instructions'],
                    'offset_days' => $task['offset'],
                    'tolerance_days' => 2,
                    'is_required' => true,
                ]);
            }

            app(SopTemplateService::class)->publish($version, $admin);
        }

        if ($farm instanceof Farm) {
            $this->generateDemoTasks($farm, $admin, $team);
        }
    }

    private function generateDemoTasks(Farm $farm, User $actor, Team $team): void
    {
        $subblok = PlantingCycle::query()
            ->where('farm_id', $farm->id)
            ->whereNotNull('planting_round_id')
            ->orderBy('batch_number')
            ->first();

        if (! $subblok instanceof PlantingCycle) {
            $block = FarmBlock::query()->where('farm_id', $farm->id)->where('code', 'B01')->first();
            if ($block === null) {
                return;
            }

            try {
                $subblok = app(FarmBlockService::class)->createBatch($block, $actor, $team, [
                    'reserved_area_ha' => '2.5000',
                    'subblock_label' => 'A',
                    'planned_planting_at' => today()->addDays(3)->toDateString(),
                ]);
            } catch (Throwable) {
                return;
            }
        }

        if ($subblok->planned_planting_at === null && $subblok->planted_at === null) {
            $subblok->forceFill(['planned_planting_at' => today()->addDays(3)->toDateString()])->save();
        }

        \Illuminate\Support\Facades\Gate::forUser($actor)->authorize('synchronize', [\App\Models\PlantingCycleTask::class, $subblok]);
        app(PlantingCycleTaskService::class)->generateForSubblok($subblok, $actor);
    }
}
