<?php

namespace Tests\Feature;

use App\Models\Envelope;
use App\Models\SalarySource;
use App\Models\Transaction;
use App\Models\UserSetting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyCycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_with_auto_cycle_metrics(): void
    {
        $setting = UserSetting::create([
            'salary' => 5000,
            'salary_day' => 1,
            'currency' => 'ILS',
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewHas('isCurrentCycle', true);
        $response->assertViewHas('cycleStart');
        $response->assertViewHas('cycleEnd');
    }

    public function test_expenses_outside_active_cycle_are_not_counted_in_current_cycle(): void
    {
        Carbon::setTestNow('2026-10-15');

        UserSetting::create([
            'salary' => 5000,
            'salary_day' => 1,
            'currency' => 'ILS',
        ]);

        $env = Envelope::create([
            'name' => 'Food',
            'icon' => '🍔',
            'budget' => 1000,
            'pillar' => 'needs',
            'color' => '#10b981',
        ]);

        // Prior month expense (September 2026)
        Transaction::create([
            'type' => 'expense',
            'amount' => 300,
            'envelope_id' => $env->id,
            'date' => '2026-09-20',
            'payment_method' => 'card',
        ]);

        // Current month expense (October 2026)
        Transaction::create([
            'type' => 'expense',
            'amount' => 150,
            'envelope_id' => $env->id,
            'date' => '2026-10-10',
            'payment_method' => 'card',
        ]);

        $response = $this->get('/');
        $response->assertStatus(200);

        // Current cycle expenses should only be 150, not 450
        $response->assertViewHas('totalExpenses', 150.0);

        $envelopes = $response->viewData('envelopes');
        $this->assertEquals(150.0, $envelopes->first()->spent);
        $this->assertEquals(850.0, $envelopes->first()->remaining);
    }

    public function test_user_can_manually_start_new_cycle_from_today(): void
    {
        Carbon::setTestNow('2026-10-25');

        UserSetting::create([
            'salary' => 5000,
            'salary_day' => 1,
            'currency' => 'ILS',
        ]);

        $response = $this->post(route('settings.startNewCycle'));

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('user_settings', [
            'id' => 1,
            'salary_day' => 25,
        ]);
    }

    public function test_cycle_starts_with_first_salary_of_the_month(): void
    {
        Carbon::setTestNow('2026-10-15');

        UserSetting::create([
            'salary' => 0,
            'cycle_mode' => 'first_salary',
            'currency' => 'ILS',
        ]);

        // Salary 1: arrives on day 5 of the month (earliest)
        SalarySource::create([
            'name' => 'Main Salary',
            'amount' => 3000,
            'currency' => 'ILS',
            'payment_method' => 'transfer',
            'day_of_month' => 5,
            'is_active' => true,
        ]);

        // Salary 2: arrives on day 15 of the month
        SalarySource::create([
            'name' => 'Side Income',
            'amount' => 1500,
            'currency' => 'ILS',
            'payment_method' => 'cash',
            'day_of_month' => 15,
            'is_active' => true,
        ]);

        $response = $this->get('/');
        $response->assertStatus(200);

        // Effective salaryDay should be 5 (the earliest salary day)
        $response->assertViewHas('salaryDay', 5);
        $response->assertViewHas('firstSalaryDay', 5);

        /** @var Carbon $cycleStart */
        $cycleStart = $response->viewData('cycleStart');
        $this->assertEquals(5, $cycleStart->day);
        $this->assertEquals(10, $cycleStart->month);
    }
}
