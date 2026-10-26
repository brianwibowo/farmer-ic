<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Deferred: inspect actual referenced keys; this does not integrate actor identities. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('farms') || ! Schema::hasTable('teams')) {
            throw new LogicException('Integrate the farms model table with native users and teams before running the authorization migration.');
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
            $table->foreign('team_id')->references('id')->on('teams')->restrictOnDelete();
        });

        Schema::create('farm_user', function (Blueprint $table) use ($types): void {
            $table->addColumn($types['users'][0], 'user_id', $types['users'][1]);
            $table->addColumn($types['farms'][0], 'farm_id', $types['farms'][1]);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('farm_id')->references('id')->on('farms')->cascadeOnDelete();
            $table->unique(['user_id', 'farm_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_user');
        Schema::table('farms', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('team_id');
        });
    }
};
