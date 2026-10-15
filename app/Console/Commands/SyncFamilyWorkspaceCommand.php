<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncFamilyWorkspaceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workspace:sync-family {--workspace= : المعرف الرقمي للمحفظة الأساسية المراد توحيد الجميع عليها}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'توحيد وربط جميع أفراد العائلة (الزوج، الزوجة) والمعاملات السابقة في محفظة عائلية واحدة مشتركة';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔄 بدء فحص وتوحيد المحفظة العائلية لجميع الحسابات...');

        // 1. Determine target primary workspace
        $customWsId = $this->option('workspace');
        if ($customWsId) {
            $workspace = Workspace::find($customWsId);
            if (! $workspace) {
                $this->error("❌ لم يتم العثور على محفظة بالرقم {$customWsId}");

                return 1;
            }
        } else {
            // Find admin user's workspace, or first workspace, or ID 1
            $admin = User::where('role', 'admin')->first();
            $workspaceId = $admin?->workspace_id ?: (Workspace::value('id') ?: 1);
            $workspace = Workspace::find($workspaceId);

            if (! $workspace) {
                $workspace = Workspace::create([
                    'name' => 'المحفظة الرئيسية (العائلة)',
                    'currency' => 'ILS',
                ]);
            }
        }

        $targetWsId = $workspace->id;
        $this->line("🏢 المحفظة المعتمدة: <comment>{$workspace->name}</comment> (ID: {$targetWsId})");

        // 2. Link all users to this workspace
        $users = User::all();
        $updatedUsersCount = 0;

        foreach ($users as $user) {
            if ($user->workspace_id !== $targetWsId) {
                $user->workspace_id = $targetWsId;
                $user->save();
                $updatedUsersCount++;
                $this->info("✓ تم ربط المستخدم: [{$user->name}] ({$user->email} - {$user->role}) بالمحفظة العائلية المشتركة.");
            } else {
                $this->line("• المستخدم: [{$user->name}] ({$user->email}) مرتبط بالفعل بالمحفظة.");
            }
        }

        // 3. Unify all financial tables to the target workspace
        $tablesToUnify = [
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
            'goal_harvest_logs',
        ];

        $totalMigratedRows = 0;

        foreach ($tablesToUnify as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'workspace_id')) {
                $affected = DB::table($tableName)
                    ->where(function ($q) use ($targetWsId) {
                        $q->whereNull('workspace_id')
                            ->orWhere('workspace_id', '!=', $targetWsId);
                    })
                    ->update(['workspace_id' => $targetWsId]);

                if ($affected > 0) {
                    $this->info("✓ جدول [{$tableName}]: تم تحويل {$affected} سجل للمحفظة العائلية المشتركة.");
                    $totalMigratedRows += $affected;
                }
            }
        }

        $this->newLine();
        $this->info('====================================================');
        $this->info('🎉 تم بنجاح توحيد النظام والمحفظة العائلية بالكامل!');
        $this->line("• إجمالي الحسابات المربوطة الآن: <comment>{$users->count()}</comment> حسابات.");
        $this->line("• إجمالي السجلات والعمليات الموحدة: <comment>{$totalMigratedRows}</comment> سجل.");
        $this->line('• الآن، أي معاملة يضيفها الزوج أو الزوجة ستظهر فوراً وبشكل مشترك لدى الطرفين حتى لو لم تفتح الزوجة التطبيق.');
        $this->info('====================================================');

        return 0;
    }
}
