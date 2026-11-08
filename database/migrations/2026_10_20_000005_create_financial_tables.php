<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new LogicException('Farmer IC requires PostgreSQL. Run php artisan migrate --database=pgsql.');
        }

        Schema::create('financial_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('transaction_type', 20);

            $table->unique(['name', 'transaction_type']);
        });
        DB::statement('ALTER TABLE financial_categories ADD CONSTRAINT financial_categories_transaction_type_allowed CHECK (transaction_type IN (\'income\', \'expense\'))');

        Schema::create('financial_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('farm_id')->nullable();
            $table->uuid('planting_cycle_id')->nullable();
            $table->uuid('category_id');
            $table->uuid('recorded_by');
            $table->date('transaction_date');
            $table->string('transaction_type', 20);
            $table->decimal('amount', 18, 2);
            $table->text('description')->nullable();
            $table->timestampTz('created_at');

            $table->foreign('farm_id')->references('id')->on('farms')->restrictOnDelete();
            $table->foreign('planting_cycle_id')->references('id')->on('planting_cycles')->restrictOnDelete();
            $table->foreign('category_id')->references('id')->on('financial_categories')->restrictOnDelete();
            $table->foreign('recorded_by')->references('id')->on('users')->restrictOnDelete();
            $table->index('category_id');
            $table->index('recorded_by');
            $table->index(['farm_id', 'transaction_date']);
            $table->index(['planting_cycle_id', 'transaction_date']);
            $table->index('transaction_date');
        });
        DB::statement('ALTER TABLE financial_transactions ADD CONSTRAINT financial_transactions_amount_positive CHECK (amount > 0)');
        DB::statement('ALTER TABLE financial_transactions ADD CONSTRAINT financial_transactions_transaction_type_allowed CHECK (transaction_type IN (\'income\', \'expense\'))');

        Schema::create('financial_projections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('farm_id');
            $table->uuid('planting_cycle_id')->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('projected_income', 18, 2)->nullable();
            $table->decimal('projected_expense', 18, 2)->nullable();
            $table->timestampTz('calculated_at');

            $table->foreign('farm_id')->references('id')->on('farms')->restrictOnDelete();
            $table->foreign('planting_cycle_id')->references('id')->on('planting_cycles')->nullOnDelete();
            $table->index('planting_cycle_id');
            $table->index(['farm_id', 'period_start']);
        });
        DB::statement('ALTER TABLE financial_projections ADD CONSTRAINT financial_projections_period_order CHECK (period_end >= period_start)');
        DB::statement('ALTER TABLE financial_projections ADD CONSTRAINT financial_projections_projected_income_nonnegative CHECK (projected_income >= 0)');
        DB::statement('ALTER TABLE financial_projections ADD CONSTRAINT financial_projections_projected_expense_nonnegative CHECK (projected_expense >= 0)');

        Schema::create('financial_budgets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('farm_id')->nullable();
            $table->uuid('category_id')->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('budget_amount', 18, 2);
            $table->uuid('created_by');
            $table->timestampTz('created_at');

            $table->foreign('farm_id')->references('id')->on('farms')->nullOnDelete();
            $table->foreign('category_id')->references('id')->on('financial_categories')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->index('category_id');
            $table->index('created_by');
            $table->index(['farm_id', 'period_start']);
        });
        DB::statement('ALTER TABLE financial_budgets ADD CONSTRAINT financial_budgets_period_order CHECK (period_end >= period_start)');
        DB::statement('ALTER TABLE financial_budgets ADD CONSTRAINT financial_budgets_budget_amount_nonnegative CHECK (budget_amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_budgets');
        Schema::dropIfExists('financial_projections');
        Schema::dropIfExists('financial_transactions');
        Schema::dropIfExists('financial_categories');
    }
};
