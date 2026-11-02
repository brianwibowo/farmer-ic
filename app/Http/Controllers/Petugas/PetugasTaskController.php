<?php

namespace App\Http\Controllers\Petugas;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Temporary contract stub. Replace fixtureTasks() with planting_cycle_tasks
 * queries once the batch execution layer exists.
 */
class PetugasTaskController extends Controller
{
    public function index(Request $request): Response
    {
        $status = (string) $request->query('status', 'all');
        if (! in_array($status, ['all', 'overdue', 'today', 'upcoming', 'completed'], true)) {
            $status = 'all';
        }

        return Inertia::render('petugas/Tasks', [
            'tasks' => $this->fixtureTasks(),
            'filters' => ['status' => $status],
            'isFixture' => true,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function fixtureTasks(): array
    {
        $today = CarbonImmutable::today()->toDateString();
        $yesterday = CarbonImmutable::yesterday()->toDateString();
        $tomorrow = CarbonImmutable::tomorrow()->toDateString();
        $nextWeek = CarbonImmutable::today()->addDays(7)->toDateString();
        $plantedAt = CarbonImmutable::today()->subDays(21)->toDateString();

        return [
            [
                'id' => 'fixture-task-overdue',
                'name' => 'Pemupukan susulan pertama',
                'instructions' => 'Aplikasikan NPK sesuai dosis anjuran. Hindari kontak langsung dengan batang.',
                'status' => 'pending',
                'urgency' => 'overdue',
                'activity_category' => 'maintenance',
                'activity_subcategory' => 'follow_up_fertilization',
                'offset_days' => 14,
                'scheduled_date' => $yesterday,
                'due_date' => $today,
                'is_required' => true,
                'linked_activities_count' => 0,
                'latest_submission' => null,
                'can' => ['start' => true, 'submit' => false],
                'batch' => [
                    'id' => 'fixture-batch-1',
                    'batch_number' => 1,
                    'subblock_label' => 'A1',
                    'planted_at' => $plantedAt,
                    'hst' => 21,
                    'farm' => ['id' => 'fixture-farm', 'name' => 'Kebun Cileuksa Demo'],
                    'block' => ['id' => 'fixture-block', 'name' => 'Blok Utara'],
                    'variety' => ['id' => 'fixture-variety', 'name' => 'Bisi 18'],
                ],
            ],
            [
                'id' => 'fixture-task-today',
                'name' => 'Pengairan awal',
                'instructions' => 'Pastikan kelembapan tanah cukup setelah tanam; hindari genangan.',
                'status' => 'in_progress',
                'urgency' => 'today',
                'activity_category' => 'maintenance',
                'activity_subcategory' => 'irrigation',
                'offset_days' => 21,
                'scheduled_date' => $today,
                'due_date' => $tomorrow,
                'is_required' => true,
                'linked_activities_count' => 1,
                'latest_submission' => null,
                'can' => ['start' => true, 'submit' => true],
                'batch' => [
                    'id' => 'fixture-batch-1',
                    'batch_number' => 1,
                    'subblock_label' => 'A1',
                    'planted_at' => $plantedAt,
                    'hst' => 21,
                    'farm' => ['id' => 'fixture-farm', 'name' => 'Kebun Cileuksa Demo'],
                    'block' => ['id' => 'fixture-block', 'name' => 'Blok Utara'],
                    'variety' => ['id' => 'fixture-variety', 'name' => 'Bisi 18'],
                ],
            ],
            [
                'id' => 'fixture-task-upcoming',
                'name' => 'Pengendalian hama dan penyakit',
                'instructions' => 'Lakukan pengamatan dan aplikasikan pengendalian hanya jika ditemukan serangan.',
                'status' => 'pending',
                'urgency' => 'upcoming',
                'activity_category' => 'maintenance',
                'activity_subcategory' => 'pest_control',
                'offset_days' => 30,
                'scheduled_date' => $nextWeek,
                'due_date' => CarbonImmutable::today()->addDays(9)->toDateString(),
                'is_required' => true,
                'linked_activities_count' => 0,
                'latest_submission' => null,
                'can' => ['start' => true, 'submit' => false],
                'batch' => [
                    'id' => 'fixture-batch-1',
                    'batch_number' => 1,
                    'subblock_label' => 'A1',
                    'planted_at' => $plantedAt,
                    'hst' => 21,
                    'farm' => ['id' => 'fixture-farm', 'name' => 'Kebun Cileuksa Demo'],
                    'block' => ['id' => 'fixture-block', 'name' => 'Blok Utara'],
                    'variety' => ['id' => 'fixture-variety', 'name' => 'Bisi 18'],
                ],
            ],
        ];
    }
}
