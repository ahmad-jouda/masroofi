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
        if (! Schema::hasTable('debts')) {
            Schema::create('debts', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
                $table->string('type', 50)->default('supermarket'); // supermarket, person, store, other
                $table->string('icon', 20)->default('🛒');
                $table->decimal('total_debt', 12, 2)->default(0.00);
                $table->text('notes')->nullable();
                $table->string('status', 20)->default('active'); // active, settled
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('debt_records')) {
            Schema::create('debt_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('debt_id')->constrained('debts')->cascadeOnDelete();
                $table->enum('type', ['add_debt', 'repayment'])->default('add_debt');
                $table->decimal('amount', 12, 2);
                $table->date('date');
                $table->enum('payment_method', ['transfer', 'cash', 'card'])->nullable();
                $table->string('note', 255)->nullable();
                $table->integer('transaction_id')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('debt_records');
        Schema::dropIfExists('debts');
    }
};
