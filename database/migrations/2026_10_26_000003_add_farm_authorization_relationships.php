<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Add the persisted farm-to-team boundary after identity and team tables exist. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('farms') || ! Schema::hasTable('teams')) {
            throw new LogicException('The users, farms, and teams tables must exist before adding farm authorization relationships.');
        }

        // Resolve all types before issuing any DDL. Never infer the physical key
        // from an Eloquent actor model that may point at an incompatible schema.
        $types = [];
        foreach (['users', 'farms', 'teams'] as $name) {
            $definition = strtolower(Schema::getColumnType($name, 'id', true));
            $base = trim(str_replace(' unsigned', '', $definition));
            $types[$name] = [match ($base) {
                'uuid' => 'uuid',
                'bigint', 'int8' => 'bigInteger',
                'integer', 'int', 'int4' => 'integer',
                'smallint', 'int2' => 'smallInteger',
                default => throw new LogicException("Unsupported referenced key type {$name}.id: {$definition}; choose the identity integration before migrating."),
            }, ['unsigned' => str_contains($definition, 'unsigned')]];
        }

        Schema::table('farms', function (Blueprint $table) use ($types): void {
            $table->addColumn($types['teams'][0], 'team_id', $types['teams'][1])->nullable();
            $table->index('team_id');
            $table->foreign('team_id')->references('id')->on('teams')->restrictOnDelete();
        });

    }

    public function down(): void
    {
        Schema::table('farms', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('team_id');
        });
    }
};
