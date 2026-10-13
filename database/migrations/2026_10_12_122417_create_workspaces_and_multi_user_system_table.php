<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create workspaces table
        if (! Schema::hasTable('workspaces')) {
            Schema::create('workspaces', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150)->default('المحفظة الرئيسية (العائلة)');
                $table->string('currency', 10)->default('ILS');
                $table->foreignId('created_by')->nullable();
                $table->timestamps();
            });
        }

        // 2. Enhance users table
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'username')) {
                $table->string('username', 60)->nullable()->unique()->after('name');
            }
            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 30)->nullable()->unique()->after('email');
            }
            if (! Schema::hasColumn('users', 'pin_code')) {
                $table->string('pin_code', 255)->nullable()->after('password');
            }
            if (! Schema::hasColumn('users', 'pin_enabled')) {
                $table->boolean('pin_enabled')->default(false)->after('pin_code');
            }
            if (! Schema::hasColumn('users', 'role')) {
                $table->enum('role', ['admin', 'member'])->default('member')->after('pin_enabled');
            }
            if (! Schema::hasColumn('users', 'workspace_id')) {
                $table->foreignId('workspace_id')->nullable()->after('role')->constrained('workspaces')->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar', 255)->nullable()->after('workspace_id');
            }
        });

        // 3. Add workspace_id and user_id to all financial and setting tables
        $tablesWithUserAndWorkspace = [
            'transactions',
            'debts',
            'debt_records',
            'wishlist',
        ];

        foreach ($tablesWithUserAndWorkspace as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (! Schema::hasColumn($tableName, 'workspace_id')) {
                        $table->foreignId('workspace_id')->nullable()->index()->constrained('workspaces')->cascadeOnDelete();
                    }
                    if (! Schema::hasColumn($tableName, 'user_id')) {
                        $table->foreignId('user_id')->nullable()->index()->constrained('users')->nullOnDelete();
                    }
                });
            }
        }

        $tablesWithWorkspaceOnly = [
            'envelopes',
            'saving_goals',
            'salary_sources',
            'user_settings',
            'subscriptions',
            'debt_harvest_logs',
        ];

        foreach ($tablesWithWorkspaceOnly as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (! Schema::hasColumn($tableName, 'workspace_id')) {
                        $table->foreignId('workspace_id')->nullable()->index()->constrained('workspaces')->cascadeOnDelete();
                    }
                });
            }
        }

        // 4. ZERO DATA LOSS MIGRATION:
        // Ensure default workspace and primary admin exist, and attach all existing data to them
        $defaultWorkspaceId = DB::table('workspaces')->value('id');
        if (! $defaultWorkspaceId) {
            $defaultWorkspaceId = DB::table('workspaces')->insertGetId([
                'name' => 'المحفظة الرئيسية (العائلة)',
                'currency' => 'ILS',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Ensure default Admin user exists
        $adminUser = DB::table('users')->where('email', 'admin@masroofi.com')->orWhere('username', 'admin')->first();
        if (! $adminUser) {
            $adminId = DB::table('users')->insertGetId([
                'name' => 'المدير (أدمن)',
                'username' => 'admin',
                'email' => 'admin@masroofi.com',
                'phone' => '0599000000',
                'password' => Hash::make('admin1234'),
                'pin_code' => Hash::make('1234'),
                'pin_enabled' => true,
                'role' => 'admin',
                'workspace_id' => $defaultWorkspaceId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $adminId = $adminUser->id;
            DB::table('users')->where('id', $adminId)->update([
                'role' => 'admin',
                'workspace_id' => $adminUser->workspace_id ?: $defaultWorkspaceId,
                'username' => $adminUser->username ?: 'admin',
            ]);
        }

        // Backfill workspace_id and user_id for all existing data
        $allTables = [
            'transactions',
            'debts',
            'debt_records',
            'wishlist',
            'envelopes',
            'saving_goals',
            'salary_sources',
            'user_settings',
            'subscriptions',
            'debt_harvest_logs',
        ];

        foreach ($allTables as $table) {
            if (Schema::hasTable($table)) {
                $updates = ['workspace_id' => $defaultWorkspaceId];
                if (Schema::hasColumn($table, 'user_id')) {
                    $updates['user_id'] = $adminId;
                }
                DB::table($table)->whereNull('workspace_id')->update($updates);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reversal logic
        $allTables = [
            'transactions',
            'debts',
            'debt_records',
            'wishlist',
            'envelopes',
            'saving_goals',
            'salary_sources',
            'user_settings',
            'subscriptions',
            'debt_harvest_logs',
        ];

        foreach ($allTables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    if (Schema::hasColumn($table->getTable(), 'workspace_id')) {
                        $table->dropForeign(['workspace_id']);
                        $table->dropColumn('workspace_id');
                    }
                    if (Schema::hasColumn($table->getTable(), 'user_id')) {
                        $table->dropForeign(['user_id']);
                        $table->dropColumn('user_id');
                    }
                });
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $columns = ['username', 'phone', 'pin_code', 'pin_enabled', 'role', 'workspace_id', 'avatar'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    if ($column === 'workspace_id') {
                        $table->dropForeign(['workspace_id']);
                    }
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('workspaces');
    }
};
