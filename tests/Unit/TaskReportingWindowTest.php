<?php

namespace Tests\Unit;

use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Services\Activity\TaskReportingWindow;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TaskReportingWindowTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_inclusive_boundaries_zero_hst_and_zero_tolerance(): void
    {
        $cycle = new PlantingCycle(['planted_at' => '2026-05-10']);
        $task = new PlantingCycleTask(['offset_days' => 0, 'tolerance_days' => 0]);
        $window = new TaskReportingWindow;
        $this->assertSame(['start' => '2026-05-10', 'end' => '2026-05-10'], $window->range($task, $cycle));
        $this->assertNotNull($window->blockedReason($task, $cycle, '2026-05-09'));
        $this->assertNull($window->blockedReason($task, $cycle, '2026-05-10'));
        $this->assertNotNull($window->blockedReason($task, $cycle, '2026-05-11'));

        $task->tolerance_days = 2;
        $this->assertNull($window->blockedReason($task, $cycle, '2026-05-12'));
        $this->assertNotNull($window->blockedReason($task, $cycle, '2026-05-13'));
    }

    public function test_reschedule_overrides_hst_and_today_uses_jakarta(): void
    {
        $cycle = new PlantingCycle(['planted_at' => '2026-05-01']);
        $task = new PlantingCycleTask(['offset_days' => 5, 'tolerance_days' => 1, 'rescheduled_date' => '2026-05-10']);
        $window = new TaskReportingWindow;
        $this->assertSame(['start' => '2026-05-10', 'end' => '2026-05-11'], $window->range($task, $cycle));
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-09 17:00:00', 'UTC'));
        $this->assertNull($window->blockedReason($task, $cycle));
        $window->ensureAllowed($task, $cycle, '2026-05-10');
        $this->expectException(ValidationException::class);
        $window->ensureAllowed($task, $cycle, '2026-05-09');
    }

    public function test_recording_outside_window_is_blocked_even_for_an_in_window_work_date(): void
    {
        $cycle = new PlantingCycle(['planted_at' => '2026-05-10']);
        $task = new PlantingCycleTask(['offset_days' => 0, 'tolerance_days' => 0]);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-11 00:00:00', 'Asia/Jakarta'));
        $this->expectException(ValidationException::class);
        (new TaskReportingWindow)->ensureAllowed($task, $cycle, '2026-05-10');
    }
}
