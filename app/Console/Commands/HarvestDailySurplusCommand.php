<?php

namespace App\Console\Commands;

use App\Models\Debt;
use App\Models\SavingGoal;
use App\Services\DebtSurplusHarvestService;
use App\Services\GoalSurplusHarvestService;
use Illuminate\Console\Command;

class HarvestDailySurplusCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'surplus:harvest-daily {--allowance= : Manual daily allowance override}';

    /**
     * The console command description.
     */
    protected $description = 'رصد واقتطاع فوائض اليوميات تلقائياً وتوزيع النسب بين الديون وصناديق التحويش بعد منتصف الليل مع بداية اليوم الجديد';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 بدء دورة توزيع فوائض اليوميات تلقائياً (بعد منتصف الليل)...');

        $allowance = $this->option('allowance') ? (float) $this->option('allowance') : null;

        // 1. Process Debts first
        DebtSurplusHarvestService::processActiveHarvests($allowance);

        // 2. Process Saving Goals with remaining surplus coordinate
        GoalSurplusHarvestService::processActiveHarvests($allowance);

        $activeDebts = Debt::where('is_harvest_active', true)->where('status', 'active')->get();
        $activeGoals = SavingGoal::where('is_harvest_active', true)->get();

        if ($activeDebts->isEmpty() && $activeGoals->isEmpty()) {
            $this->comment('لا توجد خطط سداد ديون أو تحويش نشطة حالياً. الفائض بالكامل يبقى في الميزانية لرفع الأيام القادمة.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($activeDebts as $d) {
            $rows[] = [
                'دين',
                $d->name,
                $d->harvest_ratio_percent.'%',
                number_format($d->surplus_harvested, 2).' ₪',
                $d->is_harvest_complete ? '🎉 مكتمل للسداد' : '⏳ جاري التجميع',
            ];
        }
        foreach ($activeGoals as $g) {
            $rows[] = [
                'حصالة تحويش',
                $g->name,
                $g->harvest_ratio_percent.'%',
                number_format($g->current_amount, 2).' '.$g->currency,
                $g->progress_percent.'%',
            ];
        }

        $this->table(['النوع', 'الاسم', 'نسبة الاقتطاع', 'المحصل حتى الآن', 'الإنجاز'], $rows);

        $this->info('✅ تمت دورة الاقتطاع والتوزيع بنجاح لجميع الخطط النشطة.');

        return self::SUCCESS;
    }
}
