<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Clean any expense or income transactions that were erroneously assigned a goal_id
        DB::table('transactions')
            ->where('type', '!=', 'savings')
            ->whereNotNull('goal_id')
            ->update(['goal_id' => null]);

        // Clean any income transactions that had an envelope_id assigned
        DB::table('transactions')
            ->where('type', 'income')
            ->whereNotNull('envelope_id')
            ->update(['envelope_id' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reversal needed for data sanitization
    }
};
