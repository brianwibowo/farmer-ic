<?php

namespace Tests\Feature\Activity;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class SharedDailyLogConcurrencyTest extends TestCase
{
    public function test_independent_processes_append_to_one_shared_daily_header(): void
    {
        $process = new Process([PHP_BINARY, base_path('tests/Support/shared-daily-log-concurrency.php')], base_path(), [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql_testing',
            'TEST_DB_DATABASE' => 'farmer_ic_crud_test_daily_log_concurrency',
        ], null, 120);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getOutput().$process->getErrorOutput());
        $this->assertStringContainsString('"positions":[1,2]', $process->getOutput());
    }
}
