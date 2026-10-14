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
            if (! Schema::hasColumn('goal_harvest_logs', 'workspace_id')) {
                $table->foreignId('workspace_id')->nullable()->after('id')->constrained()->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goal_harvest_logs', function (Blueprint $table) {
            if (Schema::hasColumn('goal_harvest_logs', 'workspace_id')) {
                $table->dropConstrainedForeignId('workspace_id');
            }
        });
    }
};
