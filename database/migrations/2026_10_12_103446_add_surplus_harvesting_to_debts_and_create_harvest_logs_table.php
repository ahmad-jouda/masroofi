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
        Schema::table('debts', function (Blueprint $table) {
            $table->boolean('is_harvest_active')->default(false)->after('status');
            $table->decimal('harvest_ratio', 5, 2)->default(0.50)->after('is_harvest_active');
            $table->decimal('harvest_max_daily', 10, 2)->nullable()->after('harvest_ratio');
            $table->decimal('surplus_harvested', 12, 2)->default(0.00)->after('harvest_max_daily');
            $table->date('harvest_started_at')->nullable()->after('surplus_harvested');
        });

        if (! Schema::hasTable('debt_harvest_logs')) {
            Schema::create('debt_harvest_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('debt_id')->constrained('debts')->cascadeOnDelete();
                $table->date('date');
                $table->decimal('day_allowance', 10, 2)->default(0.00);
                $table->decimal('day_spent', 10, 2)->default(0.00);
                $table->decimal('surplus_amount', 10, 2)->default(0.00);
                $table->decimal('harvested_amount', 10, 2)->default(0.00);
                $table->string('note', 255)->nullable();
                $table->timestamps();

                $table->unique(['debt_id', 'date']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('debt_harvest_logs');

        Schema::table('debts', function (Blueprint $table) {
            $table->dropColumn([
                'is_harvest_active',
                'harvest_ratio',
                'harvest_max_daily',
                'surplus_harvested',
                'harvest_started_at',
            ]);
        });
    }
};
