<?php

namespace Tests\Feature\Database;

use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FarmerIcMigrationsTest extends TestCase
{
    private const PATH = 'database/migrations/farmer_ic';

    private Connection $database;

    /** @var array<string, list<array{name: string, type: string, settings: string}>> */
    private array $definition = [];

    /** @var array<string, list<list<string>>> */
    private array $uniqueKeys = [];

    /** @var array<string, string> */
    private array $ids = [];

    /** @var array<string, string> */
    private array $types = [];

    public function test_postgresql_dbml_contract_constraints_and_migration_round_trip(): void
    {
        $database = getenv('FARMER_IC_TEST_DATABASE');
        if (! $database) {
            $this->markTestSkipped('Set FARMER_IC_TEST_DATABASE to a dedicated local PostgreSQL test database.');
        }
        $this->assertStringStartsWith('farmer_ic_schema_test_', $database);

        config(['database.connections.farmer_ic_test' => [
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'port' => getenv('FARMER_IC_TEST_PORT') ?: '55439',
            'database' => $database,
            'username' => 'farmer_ic_schema_test',
            'password' => '',
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'public',
            'sslmode' => 'disable',
            'timezone' => 'UTC',
        ]]);
        $this->database = DB::connection('farmer_ic_test');
        $this->assertSame('farmer_ic_schema_test', $this->database->scalar('SELECT current_user'));
        $this->assertSame($database, $this->database->scalar('SELECT current_database()'));
        $this->readDefinition();

        $options = ['--database' => 'farmer_ic_test', '--path' => self::PATH, '--force' => true];
        $this->artisan('migrate', $options)->assertSuccessful();
        try {
            $this->assertDbmlContract();
            // A repeated migrate must neither duplicate tables nor change schema.
            $this->artisan('migrate', $options)->assertSuccessful();
            $this->assertSame(9, $this->database->table('migrations')->count());
            $this->seedFixtures();
            $this->assertNumericAndEnumChecks();
            $this->assertActivityIntegrity();
            $this->assertCrossTableIntegrity();
            $this->assertDeletePolicies();
        } finally {
            while ($this->database->transactionLevel() > 0) {
                $this->database->rollBack();
            }
            $this->artisan('migrate:rollback', $options)->assertSuccessful();
        }

        $this->assertSame(['migrations'], $this->tableNames());
        $this->assertSame(0, (int) $this->database->scalar("SELECT COUNT(*) FROM pg_proc p JOIN pg_namespace n ON n.oid = p.pronamespace WHERE n.nspname = 'public' AND p.proname LIKE 'farmer_ic_%'"));
        $this->artisan('migrate', $options)->assertSuccessful();
        try {
            $this->assertDbmlContract();
        } finally {
            $this->artisan('migrate:rollback', $options)->assertSuccessful();
        }
        $this->assertSame(['migrations'], $this->tableNames());
    }

    private function readDefinition(): void
    {
        $source = file_get_contents(base_path(self::PATH.'/schema.dbml'));
        preg_match_all('/^Table\s+(\w+)\s*\{([\s\S]*?)^\}/m', $source, $tables, PREG_SET_ORDER);
        foreach ($tables as $table) {
            preg_match_all('/^  (\w+)\s+(uuid|varchar\(\d+\)|text|boolean|timestamptz|date|decimal\(\d+,\d+\)|int|jsonb)(?:\s+\[(.*)\])?\s*$/m', $table[2], $columns, PREG_SET_ORDER);
            $this->definition[$table[1]] = array_map(fn (array $column) => [
                'name' => $column[1], 'type' => $column[2], 'settings' => $column[3] ?? '',
            ], $columns);
            $this->uniqueKeys[$table[1]] = [];
            foreach ($this->definition[$table[1]] as $column) {
                if (preg_match('/(?:^|,\s*)unique(?:,|$)/', $column['settings'])) {
                    $this->uniqueKeys[$table[1]][] = [$column['name']];
                }
            }
            preg_match_all('/\((\w+(?:,\s*\w+)*)\)\s+\[unique\]/', $table[2], $uniqueKeys, PREG_SET_ORDER);
            foreach ($uniqueKeys as $uniqueKey) {
                $this->uniqueKeys[$table[1]][] = preg_split('/,\s*/', $uniqueKey[1]);
            }
        }
        $this->assertCount(31, $this->definition);
    }

    /** @return list<string> */
    private function tableNames(): array
    {
        return array_column($this->database->select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename"), 'tablename');
    }

    private function assertDbmlContract(): void
    {
        $expectedTables = [...array_keys($this->definition), 'migrations'];
        sort($expectedTables);
        $this->assertSame($expectedTables, $this->tableNames());
        foreach ($this->definition as $table => $columns) {
            $actual = $this->database->select('SELECT column_name, udt_name, is_nullable, character_maximum_length, numeric_precision, numeric_scale, column_default FROM information_schema.columns WHERE table_schema = ? AND table_name = ? ORDER BY ordinal_position', ['public', $table]);
            $this->assertSame(array_column($columns, 'name'), array_column($actual, 'column_name'), $table.' columns must exactly match DBML');
            foreach ($columns as $index => $column) {
                $field = $actual[$index];
                $type = $column['type'];
                $settings = $column['settings'];
                $nativeType = match (true) {
                    str_starts_with($type, 'varchar') => 'varchar',
                    str_starts_with($type, 'decimal') => 'numeric',
                    $type === 'boolean' => 'bool',
                    $type === 'int' => 'int4',
                    default => $type,
                };
                $this->assertSame($nativeType, $field->udt_name, $table.'.'.$column['name']);
                $required = str_contains($settings, 'not null') || preg_match('/(?:^|,\s*)pk(?:,|$)/', $settings);
                $this->assertSame($required ? 'NO' : 'YES', $field->is_nullable, $table.'.'.$column['name'].' nullability');
                if (preg_match('/varchar\((\d+)\)/', $type, $length)) {
                    $this->assertSame((int) $length[1], $field->character_maximum_length);
                }
                if (preg_match('/decimal\((\d+),(\d+)\)/', $type, $decimal)) {
                    $this->assertSame((int) $decimal[1], $field->numeric_precision);
                    $this->assertSame((int) $decimal[2], $field->numeric_scale);
                }
                if (preg_match('/default:\s*(true|false|\d+)/', $settings, $default)) {
                    $this->assertStringContainsString($default[1], $field->column_default);
                }
                if (preg_match('/ref:\s*>\s*(\w+)\.(\w+)/', $settings, $reference)) {
                    $this->assertSame(1, (int) $this->database->scalar(<<<'SQL'
SELECT COUNT(*) FROM pg_constraint c
JOIN pg_attribute child ON child.attrelid = c.conrelid AND child.attnum = c.conkey[1]
JOIN pg_attribute parent ON parent.attrelid = c.confrelid AND parent.attnum = c.confkey[1]
WHERE c.contype = 'f' AND c.conrelid = ?::regclass AND c.confrelid = ?::regclass
AND array_length(c.conkey, 1) = 1 AND child.attname = ? AND parent.attname = ?
SQL, [$table, $reference[1], $column['name'], $reference[2]]));
                    $this->assertGreaterThan(0, (int) $this->database->scalar(<<<'SQL'
SELECT COUNT(*) FROM pg_index i
JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = i.indkey[0]
WHERE i.indrelid = ?::regclass AND a.attname = ? AND i.indisvalid AND i.indpred IS NULL
SQL, [$table, $column['name']]), $table.'.'.$column['name'].' needs an index prefix');
                }
            }
            $primaryColumn = str_ends_with($table, '_details') ? 'activity_id' : 'id';
            $this->assertSame($primaryColumn, $this->database->scalar("SELECT a.attname FROM pg_constraint c JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = c.conkey[1] WHERE c.contype = 'p' AND c.conrelid = ?::regclass", [$table]));
            $uniqueKeys = array_column($this->database->select(<<<'SQL'
SELECT string_agg(a.attname, ',' ORDER BY key.position) AS columns
FROM pg_constraint c
CROSS JOIN LATERAL unnest(c.conkey) WITH ORDINALITY AS key(number, position)
JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = key.number
WHERE c.contype = 'u' AND c.conrelid = ?::regclass GROUP BY c.oid
SQL, [$table]), 'columns');
            foreach ($this->uniqueKeys[$table] as $uniqueKey) {
                $this->assertContains(implode(',', $uniqueKey), $uniqueKeys, $table.' unique constraint');
            }
        }
        $this->assertSame(45, (int) $this->database->scalar("SELECT COUNT(*) FROM pg_constraint c JOIN pg_namespace n ON n.oid = c.connamespace WHERE n.nspname = 'public' AND c.contype = 'f' AND array_length(c.conkey, 1) = 1"));
        $this->assertSame(8, (int) $this->database->scalar("SELECT COUNT(*) FROM pg_constraint c JOIN pg_namespace n ON n.oid = c.connamespace WHERE n.nspname = 'public' AND c.contype = 'f' AND array_length(c.conkey, 1) > 1"));
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function row(string $table, array $overrides = []): array
    {
        $row = [];
        foreach ($this->definition[$table] as $column) {
            $settings = $column['settings'];
            if (! str_contains($settings, 'not null') && ! preg_match('/(?:^|,\s*)pk(?:,|$)/', $settings)) {
                continue;
            }
            if (str_contains($settings, 'default:')) {
                continue;
            }
            $value = match (true) {
                $column['type'] === 'uuid' => (string) Str::uuid(),
                $column['type'] === 'timestamptz' => '2026-10-20 00:00:00+00',
                $column['type'] === 'date' => '2026-09-01',
                $column['type'] === 'int', str_starts_with($column['type'], 'decimal') => 1,
                $column['type'] === 'boolean' => true,
                $column['type'] === 'jsonb' => '{"test":true}',
                default => 'Fixture',
            };
            if (preg_match('/ref:\s*>\s*(\w+)\./', $settings, $reference)) {
                $value = $this->ids[$reference[1]] ?? $value;
            }
            $row[$column['name']] = $value;
        }

        return array_replace($row, $overrides);
    }

    /** @param array<string, mixed> $overrides */
    private function insert(string $table, array $overrides = []): string
    {
        $row = $this->row($table, $overrides);
        $this->database->table($table)->insert($row);

        return $row['id'] ?? $row['activity_id'];
    }

    private function seedFixtures(): void
    {
        $this->ids['roles'] = $this->insert('roles', ['name' => 'admin']);
        $this->ids['users'] = $this->insert('users', ['email' => 'migration@example.test']);
        $this->ids['commodities'] = $this->insert('commodities', ['name' => 'Jagung']);
        $this->ids['crop_varieties'] = $this->insert('crop_varieties', ['name' => 'Bisi']);
        $this->ids['farms'] = $this->insert('farms', ['total_area_ha' => 100]);
        $this->ids['planting_cycles'] = $this->insert('planting_cycles', ['status' => 'active', 'planted_area_ha' => 10]);
        $this->ids['other_farm'] = $this->insert('farms', ['total_area_ha' => 50]);
        $this->ids['other_cycle'] = $this->insert('planting_cycles', ['farm_id' => $this->ids['other_farm'], 'status' => 'active']);
        foreach (['planting', 'fertilization', 'irrigation', 'growth_check', 'pesticide', 'incident', 'harvest'] as $code) {
            $this->types[$code] = $this->insert('activity_types', ['code' => $code, 'name' => $code]);
        }
        $this->ids['financial_categories'] = $this->insert('financial_categories', ['name' => 'Sales', 'transaction_type' => 'income']);
        $this->ids['expense_category'] = $this->insert('financial_categories', ['name' => 'Fertilizer', 'transaction_type' => 'expense']);
        $this->ids['ai_conversations'] = $this->insert('ai_conversations');
        $this->ids['ai_messages'] = $this->insert('ai_messages', ['sender' => 'user']);
    }

    /** @param array<string, mixed> $overrides */
    private function activity(string $code, array $overrides = []): string
    {
        return $this->database->transaction(function () use ($code, $overrides) {
            $id = $this->insert('farm_activities', array_replace([
                'activity_type_id' => $this->types[$code],
                'planting_cycle_id' => $this->ids['planting_cycles'],
            ], $overrides));
            $detail = match ($code) {
                'growth_check' => 'plant_observations',
                default => $code === 'harvest' ? 'harvest_details' : $code.'_details',
            };
            $this->insert($detail, ['activity_id' => $id]);

            return $id;
        });
    }

    private function reject(callable $operation, string $sqlState): void
    {
        $this->database->beginTransaction();
        try {
            $operation();
            $this->database->statement('SET CONSTRAINTS ALL IMMEDIATE');
            $this->fail('Expected PostgreSQL SQLSTATE '.$sqlState);
        } catch (QueryException $error) {
            $this->assertSame($sqlState, (string) $error->getCode(), $error->getMessage());
        } finally {
            $this->database->rollBack();
        }
    }

    private function assertNumericAndEnumChecks(): void
    {
        foreach ([
            ['farms', ['total_area_ha' => 0]],
            ['farms', ['latitude' => 91]],
            ['farms', ['longitude' => -181]],
            ['planting_cycles', ['status' => 'active', 'planted_area_ha' => 0]],
            ['planting_cycles', ['status' => 'active', 'estimated_harvest_at' => '2026-08-01']],
            ['planting_cycles', ['status' => 'unknown']],
            ['planting_cycles', ['status' => 'active', 'planted_area_ha' => 95]],
            ['roles', ['name' => 'superuser']],
            ['activity_types', ['code' => 'unknown']],
            ['plant_observations', ['growth_percentage' => 101]],
            ['plant_observations', ['average_height_cm' => -1]],
            ['weather_records', ['humidity_percentage' => -1]],
            ['ai_insights', ['confidence_score' => 101]],
            ['financial_transactions', ['transaction_type' => 'income', 'amount' => 0]],
            ['financial_transactions', ['transaction_type' => 'invalid']],
            ['financial_projections', ['period_end' => '2026-08-01']],
            ['financial_projections', ['projected_income' => -1]],
            ['financial_budgets', ['budget_amount' => -1]],
            ['financial_budgets', ['period_end' => '2026-08-01']],
            ['operational_targets', ['target_value' => -1]],
            ['operational_targets', ['completed_value' => -1]],
            ['ai_messages', ['sender' => 'invalid']],
            ['ai_usage_logs', ['input_tokens' => -1]],
            ['ai_usage_logs', ['estimated_cost' => -1]],
        ] as [$table, $row]) {
            $this->reject(fn () => $this->insert($table, $row), '23514');
        }
        $this->reject(fn () => $this->insert('users', ['email' => 'migration@example.test']), '23505');
        $this->reject(fn () => $this->insert('crop_varieties', ['name' => 'Bisi']), '23505');
        $this->reject(fn () => $this->insert('financial_categories', ['name' => 'Sales', 'transaction_type' => 'income']), '23505');
        $this->reject(fn () => $this->insert('users', ['email' => 'missing-role@example.test', 'role_id' => (string) Str::uuid()]), '23503');
        // Nullable fields, zero forecasts, signed temperatures, and overshot
        // operational targets remain valid where the DBML allows them.
        $this->insert('weather_records', ['temperature_c' => -5, 'humidity_percentage' => 100]);
        $this->insert('financial_projections', ['projected_income' => 0, 'projected_expense' => 0]);
        $this->insert('operational_targets', ['target_value' => 1, 'completed_value' => 2]);
    }

    private function assertActivityIntegrity(): void
    {
        foreach (array_keys($this->types) as $code) {
            $this->ids[$code.'_activity'] = $this->activity($code);
        }
        $this->reject(fn () => $this->insert('farm_activities', ['activity_type_id' => $this->types['irrigation']]), '23514');
        $this->reject(function () {
            $id = $this->insert('farm_activities', ['activity_type_id' => $this->types['irrigation']]);
            $this->insert('fertilization_details', ['activity_id' => $id]);
        }, '23514');
        $this->reject(fn () => $this->insert('planting_details', ['activity_id' => $this->ids['irrigation_activity']]), '23514');
        $this->reject(fn () => $this->database->table('irrigation_details')->where('activity_id', $this->ids['irrigation_activity'])->delete(), '23514');
        $this->reject(fn () => $this->database->table('farm_activities')->where('id', $this->ids['irrigation_activity'])->update(['activity_type_id' => $this->types['fertilization']]), '23514');
        $this->reject(fn () => $this->activity('harvest', ['activity_date' => '2026-08-01 00:00:00+00']), '23514');
        foreach ([
            ['planting_details', 'planting', 'quantity_kg', -1],
            ['planting_details', 'planting', 'area_ha', -1],
            ['fertilization_details', 'fertilization', 'quantity_kg', -1],
            ['irrigation_details', 'irrigation', 'duration_hours', -1],
            ['pesticide_details', 'pesticide', 'tank_count', -1],
            ['pesticide_details', 'pesticide', 'tank_size_liters', -1],
            ['incident_details', 'incident', 'affected_area_ha', -1],
            ['harvest_details', 'harvest', 'gross_weight_ton', -1],
            ['harvest_details', 'harvest', 'moisture_percentage', 101],
        ] as [$table, $code, $column, $value]) {
            $this->reject(fn () => $this->database->table($table)->where('activity_id', $this->ids[$code.'_activity'])->update([$column => $value]), '23514');
        }
    }

    private function assertCrossTableIntegrity(): void
    {
        $this->reject(fn () => $this->activity('irrigation', ['planting_cycle_id' => $this->ids['other_cycle']]), '23503');
        foreach (['financial_projections', 'operational_targets'] as $table) {
            $this->reject(fn () => $this->insert($table, ['planting_cycle_id' => $this->ids['other_cycle']]), '23503');
        }
        $this->reject(fn () => $this->insert('financial_transactions', ['farm_id' => $this->ids['farms'], 'planting_cycle_id' => $this->ids['other_cycle'], 'transaction_type' => 'income']), '23503');
        $this->reject(fn () => $this->insert('financial_transactions', ['category_id' => $this->ids['expense_category'], 'transaction_type' => 'income']), '23503');
        $observation = $this->insert('plant_observations', ['planting_cycle_id' => $this->ids['other_cycle']]);
        foreach (['farm_risks', 'ai_insights'] as $table) {
            $this->reject(fn () => $this->insert($table, ['observation_id' => $observation]), '23503');
        }
        $this->reject(fn () => $this->database->table('farms')->where('id', $this->ids['farms'])->update(['total_area_ha' => 1]), '23514');
        $this->reject(fn () => $this->database->table('planting_cycles')->where('id', $this->ids['planting_cycles'])->update(['planted_at' => '2026-11-01']), '23514');
        // A cross-farm transaction without a cycle is allowed by the DBML.
        $this->insert('financial_transactions', ['transaction_type' => 'income']);
    }

    private function assertDeletePolicies(): void
    {
        $activity = $this->activity('irrigation');
        $photo = $this->insert('activity_photos', ['activity_id' => $activity]);
        $this->database->table('farm_activities')->where('id', $activity)->delete();
        $this->assertFalse($this->database->table('activity_photos')->where('id', $photo)->exists());
        $this->assertFalse($this->database->table('irrigation_details')->where('activity_id', $activity)->exists());

        $growthActivity = $this->activity('growth_check');
        $observation = $this->database->table('plant_observations')->where('activity_id', $growthActivity)->value('id');
        $this->database->table('farm_activities')->where('id', $growthActivity)->delete();
        $this->assertNull($this->database->table('plant_observations')->where('id', $observation)->value('activity_id'));
        $this->assertTrue($this->database->table('plant_observations')->where('id', $observation)->exists());
        $this->reject(fn () => $this->database->table('planting_cycles')->where('id', $this->ids['planting_cycles'])->delete(), '23001');
        $this->reject(fn () => $this->database->table('users')->where('id', $this->ids['users'])->delete(), '23001');

        $user = $this->insert('users', ['email' => 'optional@example.test']);
        $notification = $this->insert('notifications', ['user_id' => $user]);
        $audit = $this->insert('audit_logs', ['user_id' => $user, 'old_values' => '{"before":1}', 'new_values' => '{"after":2}']);
        $this->database->table('users')->where('id', $user)->delete();
        $this->assertFalse($this->database->table('notifications')->where('id', $notification)->exists());
        $this->assertNull($this->database->table('audit_logs')->where('id', $audit)->value('user_id'));
        $this->assertSame('{"after": 2}', $this->database->table('audit_logs')->where('id', $audit)->value('new_values'));

        $attachment = $this->insert('ai_attachments');
        $usage = $this->insert('ai_usage_logs', ['message_id' => $this->ids['ai_messages']]);
        $this->database->table('ai_messages')->where('id', $this->ids['ai_messages'])->delete();
        $this->assertFalse($this->database->table('ai_attachments')->where('id', $attachment)->exists());
        $this->assertNull($this->database->table('ai_usage_logs')->where('id', $usage)->value('message_id'));
    }
}
