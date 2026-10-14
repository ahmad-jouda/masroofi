<?php

namespace App\Console\Commands;

use App\Models\SavingGoal;
use App\Services\GoalSurplusHarvestService;
use Illuminate\Console\Command;

class HarvestGoalSurplusCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'goals:harvest-surplus {--allowance= : Manual daily allowance override}';

    /**
     * The console command description.
     */
    protected $description = 'رصد واستقطاع فوائض اليوميات تلقائياً لصناديق التحويش مع نهاية كل يوم';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 بدء دورة رصد الفوائض اليومية لصناديق وأهداف التحويش...');

        $allowance = $this->option('allowance') ? (float) $this->option('allowance') : null;

        GoalSurplusHarvestService::processActiveHarvests($allowance);

        $activeGoals = SavingGoal::where('is_harvest_active', true)->get();

        if ($activeGoals->isEmpty()) {
            $this->comment('لا توجد خطط تحويش تلقائي نشطة حالياً.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($activeGoals as $goal) {
            $rows[] = [
                $goal->name,
                number_format($goal->target_amount, 2).' '.$goal->currency,
                number_format($goal->current_amount, 2).' '.$goal->currency,
                $goal->harvest_ratio_percent.'%',
                $goal->progress_percent.'%',
            ];
        }

        $this->table(['اسم الهدف', 'المستهدف', 'المجمع حالياً', 'نسبة الاقتطاع اليومي', 'نسبة الإنجاز'], $rows);

        $this->info('✅ تمت دورة الاقتطاع التلقائي وتوثيق السجلات بنجاح.');

        return self::SUCCESS;
    }
}
