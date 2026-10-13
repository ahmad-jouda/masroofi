<?php

namespace Tests\Feature;

use App\Models\Envelope;
use App\Models\Transaction;
use App\Models\UserSetting;
use App\Services\CurrencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_exchange_rates_are_positive(): void
    {
        $rateIlsToSar = CurrencyService::getRate('ILS', 'SAR');
        $this->assertGreaterThan(0, $rateIlsToSar);

        $rateSarToIls = CurrencyService::getRate('SAR', 'ILS');
        $this->assertGreaterThan(0, $rateSarToIls);
    }

    public function test_changing_currency_converts_financial_data(): void
    {
        $setting = UserSetting::create([
            'salary' => 1000.00,
            'currency' => 'ILS',
            'salary_day' => 27,
            'work_hours_per_week' => 40,
            'savings_target_percent' => 20,
        ]);

        $envelope = Envelope::create([
            'name' => 'بقالة',
            'budget' => 500.00,
            'pillar' => 'needs',
        ]);

        $transaction = Transaction::create([
            'type' => 'expense',
            'amount' => 100.00,
            'envelope_id' => $envelope->id,
            'date' => now()->toDateString(),
        ]);

        $rate = CurrencyService::getRate('ILS', 'SAR');
        $expectedSalary = round(1000.00 * $rate, 2);
        $expectedBudget = round(500.00 * $rate, 2);
        $expectedTxAmount = round(100.00 * $rate, 2);

        $response = $this->put(route('settings.update'), [
            'currency' => 'SAR',
        ]);

        $response->assertRedirect(route('dashboard'));

        $setting->refresh();
        $envelope->refresh();
        $transaction->refresh();

        $this->assertEquals('SAR', $setting->currency);
        $this->assertEquals($expectedSalary, (float) $setting->salary);
        $this->assertEquals($expectedBudget, (float) $envelope->budget);
        $this->assertEquals($expectedTxAmount, (float) $transaction->amount);
    }
}
