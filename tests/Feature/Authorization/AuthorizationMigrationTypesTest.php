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
    /** @return array<string, array{string, string, string, string}> */
    public static function keyTypes(): array
    {
        return [
            'native bigint users and teams, UUID farms' => ['bigint', 'uuid', 'bigint', 'bigInteger'],
            'UUID users and farms, bigint teams' => ['uuid', 'uuid', 'bigint', 'uuid'],
            'UUID user and farm, int4 team key' => ['uuid', 'uuid', 'integer', 'uuid'],
        ];
    }

    #[DataProvider('keyTypes')]
    public function test_referenced_key_types_are_preserved_in_compiled_postgresql_sql(
        string $users,
        string $farms,
        string $teams,
        string $expectedUserType,
    ): void {
        // Compilation only. No PDO is available and no migration SQL can run.
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
        Schema::shouldReceive('create')->once()->with('farm_user', \Mockery::type(Closure::class))
            ->andReturnUsing(function (string $table, Closure $callback) use ($connection, &$captured): void {
                $blueprint = new Blueprint($connection, $table);
                $blueprint->create();
                $callback($blueprint);
                $captured[$table] = $blueprint;
            });

        $migration = require database_path('migrations/authorization/2026_10_26_000001_add_farm_authorization_relationships.php');
        $migration->up(); // All schema calls above only capture Blueprint objects.

        $columns = [];
        foreach ($captured['farm_user']->getColumns() as $column) {
            $columns[$column->name] = $column->type;
        }
        $this->assertSame($expectedUserType, $columns['user_id']);
        $this->assertSame('uuid', $columns['farm_id']);
        $this->assertSame($teams === 'integer' ? 'integer' : 'bigInteger', $captured['farms']->getColumns()[0]->type);
        $sql = implode("\n", [...$captured['farms']->toSql(), ...$captured['farm_user']->toSql()]);
        $this->assertStringContainsString('"user_id" '.$users.' not null', $sql);
        $this->assertStringContainsString('"farm_id" uuid not null', $sql);
        $this->assertStringContainsString('"team_id" '.$teams.' null', $sql);
        $this->assertStringContainsString('foreign key ("user_id") references "users" ("id")', $sql);
        $this->assertStringContainsString('foreign key ("farm_id") references "farms" ("id")', $sql);
        $this->assertStringContainsString('foreign key ("team_id") references "teams" ("id")', $sql);
        $this->assertStringContainsString('unique ("user_id", "farm_id")', $sql);
    }

    public function test_unsupported_types_fail_before_any_schema_change(): void
    {
        foreach (['users', 'farms', 'teams'] as $table) {
            Schema::shouldReceive('hasTable')->once()->with($table)->andReturnTrue();
        }
        Schema::shouldReceive('getColumnType')->once()->with('users', 'id', true)->andReturn('bytea');
        Schema::shouldReceive('table')->never();
        Schema::shouldReceive('create')->never();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unsupported referenced key type users.id: bytea');
        (require database_path('migrations/authorization/2026_10_26_000001_add_farm_authorization_relationships.php'))->up();
    }
}
