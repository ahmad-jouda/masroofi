<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('saving_goals', function (Blueprint $table) {
            if (! Schema::hasColumn('saving_goals', 'is_harvest_active')) {
                $table->boolean('is_harvest_active')->default(false)->after('current_amount');
            }
            if (! Schema::hasColumn('saving_goals', 'harvest_ratio')) {
                $table->decimal('harvest_ratio', 5, 2)->default(0.50)->after('is_harvest_active');
            }
            if (! Schema::hasColumn('saving_goals', 'harvest_max_daily')) {
                $table->decimal('harvest_max_daily', 10, 2)->nullable()->after('harvest_ratio');
            }
            if (! Schema::hasColumn('saving_goals', 'harvest_started_at')) {
                $table->date('harvest_started_at')->nullable()->after('harvest_max_daily');
            }
        });

        if (! Schema::hasTable('goal_harvest_logs')) {
            Schema::create('goal_harvest_logs', function (Blueprint $table) {
                $table->id();
                $table->integer('goal_id');
                $table->foreign('goal_id')->references('id')->on('saving_goals')->cascadeOnDelete();
                $table->date('date');
                $table->decimal('day_allowance', 10, 2)->default(0.00);
                $table->decimal('day_spent', 10, 2)->default(0.00);
                $table->decimal('surplus_amount', 10, 2)->default(0.00);
                $table->decimal('harvested_amount', 10, 2)->default(0.00);
                $table->decimal('harvested_in_goal_currency', 10, 2)->default(0.00);
                $table->string('currency', 10)->default('ILS');
                $table->decimal('exchange_rate', 12, 6)->default(1.000000);
                $table->string('note', 255)->nullable();
                $table->timestamps();

                $table->unique(['goal_id', 'date']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goal_harvest_logs');

        Schema::table('saving_goals', function (Blueprint $table) {
            $table->dropColumn([
                'is_harvest_active',
                'harvest_ratio',
                'harvest_max_daily',
                'harvest_started_at',
            ]);
        });
    }
};
