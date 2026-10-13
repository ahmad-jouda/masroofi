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
        // 1. User Settings
        if (! Schema::hasTable('user_settings')) {
            Schema::create('user_settings', function (Blueprint $table) {
                $table->id();
                $table->decimal('salary', 12, 2)->default(7500.00);
                $table->integer('salary_day')->default(27);
                $table->integer('work_hours_per_week')->default(40);
                $table->integer('savings_target_percent')->default(20);
                $table->string('currency', 10)->default('SAR');
                $table->string('theme', 10)->default('dark');
                $table->boolean('sound_enabled')->default(true);
                $table->decimal('saved_from_impulse', 12, 2)->default(280.00);
                $table->timestamps();
            });
        }

        // 2. Envelopes
        if (! Schema::hasTable('envelopes')) {
            Schema::create('envelopes', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->string('icon', 20)->default('📦');
                $table->decimal('budget', 12, 2)->default(0.00);
                $table->enum('pillar', ['needs', 'wants', 'savings'])->default('needs');
                $table->string('color', 20)->default('#10b981');
                $table->timestamps();
            });
        }

        // 3. Saving Goals
        if (! Schema::hasTable('saving_goals')) {
            Schema::create('saving_goals', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
                $table->string('icon', 20)->default('🎯');
                $table->decimal('target_amount', 12, 2);
                $table->decimal('current_amount', 12, 2)->default(0.00);
                $table->date('deadline')->nullable();
                $table->timestamps();
            });
        }

        // 4. Transactions
        if (! Schema::hasTable('transactions')) {
            Schema::create('transactions', function (Blueprint $table) {
                $table->id();
                $table->enum('type', ['expense', 'income', 'savings'])->default('expense');
                $table->decimal('amount', 12, 2);
                $table->foreignId('envelope_id')->nullable()->constrained('envelopes')->nullOnDelete();
                $table->foreignId('goal_id')->nullable()->constrained('saving_goals')->nullOnDelete();
                $table->date('date');
                $table->enum('payment_method', ['card', 'cash', 'transfer', 'credit'])->default('card');
                $table->string('note', 255)->nullable();
                $table->timestamps();
            });
        }

        // 5. Wishlist
        if (! Schema::hasTable('wishlist')) {
            Schema::create('wishlist', function (Blueprint $table) {
                $table->id();
                $table->string('title', 200);
                $table->decimal('price', 12, 2);
                $table->integer('duration_hours')->default(48);
                $table->enum('status', ['pending', 'saved', 'bought'])->default('pending');
                $table->timestamps();
            });
        }

        // 6. Subscriptions
        if (! Schema::hasTable('subscriptions')) {
            Schema::create('subscriptions', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
                $table->decimal('cost', 12, 2);
                $table->enum('period', ['monthly', 'yearly'])->default('monthly');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('wishlist');
        Schema::dropIfExists('saving_goals');
        Schema::dropIfExists('envelopes');
        Schema::dropIfExists('user_settings');
    }
};
