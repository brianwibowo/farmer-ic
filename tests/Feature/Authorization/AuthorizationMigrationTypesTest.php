<?php

namespace Tests\Feature\Authorization;

use Closure;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\PostgresGrammar;
use Illuminate\Support\Facades\Schema;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthorizationMigrationTypesTest extends TestCase
{
    /** @return array<string, array{string, string, string}> */
    public static function keyTypes(): array
    {
        return [
            'native bigint users and teams, UUID farms' => ['bigint', 'uuid', 'bigint'],
            'UUID users and farms, bigint teams' => ['uuid', 'uuid', 'bigint'],
            'UUID user and farm, int4 team key' => ['uuid', 'uuid', 'integer'],
        ];
    }

    #[DataProvider('keyTypes')]
    public function test_farm_team_key_type_is_preserved_in_compiled_postgresql_sql(
        string $users,
        string $farms,
        string $teams,
    ): void {
        $connection = new PostgresConnection(fn () => throw new LogicException('Database execution is forbidden in this compilation test.'));
        $connection->setSchemaGrammar(new PostgresGrammar($connection));
        foreach (['users' => $users, 'farms' => $farms, 'teams' => $teams] as $table => $type) {
            Schema::shouldReceive('hasTable')->once()->with($table)->andReturnTrue();
            Schema::shouldReceive('getColumnType')->once()->with($table, 'id', true)->andReturn($type);
        }
        $captured = [];
        Schema::shouldReceive('table')->once()->with('farms', \Mockery::type(Closure::class))
            ->andReturnUsing(function (string $table, Closure $callback) use ($connection, &$captured): void {
                $blueprint = new Blueprint($connection, $table);
                $callback($blueprint);
                $captured[$table] = $blueprint;
            });

        (require database_path('migrations/2026_10_26_000003_add_farm_authorization_relationships.php'))->up();

        $sql = implode("\n", $captured['farms']->toSql());
        $expected = $teams === 'integer' ? 'integer' : 'bigint';
        $this->assertStringContainsString('"team_id" '.$expected.' null', $sql);
        $this->assertStringContainsString('foreign key ("team_id") references "teams" ("id")', $sql);
    }

    public function test_unsupported_types_fail_before_any_schema_change(): void
    {
        foreach (['users', 'farms', 'teams'] as $table) {
            Schema::shouldReceive('hasTable')->once()->with($table)->andReturnTrue();
        }
        Schema::shouldReceive('getColumnType')->once()->with('users', 'id', true)->andReturn('bytea');
        Schema::shouldReceive('table')->never();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unsupported referenced key type users.id: bytea');
        (require database_path('migrations/2026_10_26_000003_add_farm_authorization_relationships.php'))->up();
    }
}
