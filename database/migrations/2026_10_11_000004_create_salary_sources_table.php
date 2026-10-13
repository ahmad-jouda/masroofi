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
        if (! Schema::hasTable('salary_sources')) {
            Schema::create('salary_sources', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
                $table->decimal('amount', 12, 2);
                $table->string('currency', 10)->default('ILS');
                $table->enum('payment_method', ['transfer', 'cash', 'card'])->default('transfer');
                $table->integer('day_of_month')->default(1);
                $table->date('expected_date')->nullable();
                $table->string('notes', 255)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_sources');
    }
};
