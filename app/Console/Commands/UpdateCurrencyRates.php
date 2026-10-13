<?php

namespace App\Console\Commands;

use App\Services\CurrencyService;
use Illuminate\Console\Command;

class UpdateCurrencyRates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'currency:update-rates {--force : Force refresh even if cached}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update currency exchange rates from live providers';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('جاري جلب أحدث أسعار صرف العملات اللحظية من السوق المالي...');

        $rates = CurrencyService::refreshRates(true);

        $this->info('تم تحديث أسعار الصرف بنجاح!');
        $this->table(
            ['العملة', 'القيمة مقابل USD'],
            collect($rates)->map(fn ($rate, $code) => [$code, $rate])->values()->all()
        );

        return Command::SUCCESS;
    }
}
