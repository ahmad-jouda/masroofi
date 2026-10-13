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
        if (Schema::hasTable('saving_goals') && ! Schema::hasColumn('saving_goals', 'currency')) {
            Schema::table('saving_goals', function (Blueprint $table) {
                $table->string('currency', 10)->default('ILS')->after('current_amount');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('saving_goals') && Schema::hasColumn('saving_goals', 'currency')) {
            Schema::table('saving_goals', function (Blueprint $table) {
                $table->dropColumn('currency');
            });
        }
    }
};
