<?php

namespace Tests\Unit;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

class TestDatabaseSafetyTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function unsafeDatabases(): array
    {
        return [
            'application' => ['farmer_ic', 'farmer_ic'],
            'system' => ['postgres', 'farmer_ic'],
            'sqlite' => [':memory:', 'farmer_ic'],
            'empty' => ['', 'farmer_ic'],
            'unscoped' => ['farmer_ic_test', 'farmer_ic'],
            'sql characters' => ['farmer_ic_crud_test_suite;DROP DATABASE farmer_ic', 'farmer_ic'],
            'same as application' => ['farmer_ic_crud_test_suite', 'farmer_ic_crud_test_suite'],
        ];
    }

    #[DataProvider('unsafeDatabases')]
    public function test_unsafe_targets_are_rejected_before_connecting(string $target, string $application): void
    {
        $this->expectException(LogicException::class);
        TestDatabase::assertSafe($target, $application);
    }

    public function test_separate_crud_and_schema_databases_are_allowed(): void
    {
        TestDatabase::assertSafe('farmer_ic_crud_test_suite', 'farmer_ic');
        TestDatabase::assertSafe('farmer_ic_schema_test_suite', 'farmer_ic');
        $this->addToAssertionCount(2);
    }
}
