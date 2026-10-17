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
        Schema::create('grocery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('category')->default('groceries');
            $table->decimal('estimated_price', 10, 2)->nullable();
            $table->boolean('is_bought')->default(false);
            $table->foreignId('bought_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('bought_at')->nullable();
            $table->integer('transaction_id')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'is_bought']);
        });

        Schema::create('partner_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('message');
            $table->string('icon')->default('🔔');
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index(['workspace_id', 'is_read', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_notifications');
        Schema::dropIfExists('grocery_items');
    }
};
