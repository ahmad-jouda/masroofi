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
        Schema::table('goal_harvest_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('goal_harvest_logs', 'transaction_id')) {
                $table->integer('transaction_id')->nullable()->after('goal_id');
                $table->foreign('transaction_id')->references('id')->on('transactions')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goal_harvest_logs', function (Blueprint $table) {
            if (Schema::hasColumn('goal_harvest_logs', 'transaction_id')) {
                $table->dropForeign(['transaction_id']);
                $table->dropColumn('transaction_id');
            }
        });
    }
};
