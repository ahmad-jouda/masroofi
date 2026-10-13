<?php

namespace App\Console\Commands;

use App\Models\Debt;
use App\Services\DebtSurplusHarvestService;
use Illuminate\Console\Command;

class HarvestDebtSurplusCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'debts:harvest-surplus {--allowance= : Manual daily allowance override}';

    /**
     * The console command description.
     */
    protected $description = 'رصد الفوائض اليومية وتجميعها تلقائياً في حصالة سداد الديون مع نهاية اليوم وبداية يوم جديد';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 بدء دورة رصد الفوائض اليومية لحصالة سداد الديون...');

        $allowance = $this->option('allowance') ? (float) $this->option('allowance') : null;

        DebtSurplusHarvestService::processActiveHarvests($allowance);

        $activeDebts = Debt::where('is_harvest_active', true)->get();

        if ($activeDebts->isEmpty()) {
            $this->comment('لا توجد خطط سداد نشطة حالياً.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($activeDebts as $debt) {
            $rows[] = [
                $debt->name,
                number_format($debt->total_debt, 2).' ₪',
                number_format($debt->surplus_harvested, 2).' ₪',
                $debt->harvest_progress_percent.'%',
                $debt->is_harvest_complete ? '🎉 مكتمل للسداد' : '⏳ جاري التجميع',
            ];
        }

        $this->table(['اسم الدين', 'المبلغ الإجمالي', 'المجمع بالحصالة', 'نسبة الإنجاز', 'الحالة'], $rows);

        $this->info('✅ تمت دورة الرصد وتوثيق السجلات الشفافة بنجاح.');

        return self::SUCCESS;
    }
}
