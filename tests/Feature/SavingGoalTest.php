<?php

namespace Tests\Feature;

use App\Models\SavingGoal;
use App\Models\Transaction;
use App\Services\GoalSurplusHarvestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavingGoalTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_dollar_saving_goal(): void
    {
        $response = $this->post('/goals', [
            'name' => 'صندوق الطوارئ بالدولار',
            'target_amount' => 1000.00,
            'current_amount' => 0,
            'currency' => 'USD',
            'icon' => '🛡️',
        ]);

        $response->assertRedirect('/');
        $this->assertDatabaseHas('saving_goals', [
            'name' => 'صندوق الطوارئ بالدولار',
            'target_amount' => 1000.00,
            'currency' => 'USD',
        ]);
    }

    public function test_user_can_harvest_daily_surplus_with_custom_percentage(): void
    {
        $goal = SavingGoal::create([
            'name' => 'صندوق الطوارئ بالدولار',
            'target_amount' => 1000.00,
            'current_amount' => 100.00,
            'currency' => 'USD',
            'icon' => '🛡️',
        ]);

        // Harvest 50% from a 60 ILS daily surplus (~30 ILS converted to USD)
        $response = $this->post("/goals/{$goal->id}/harvest-surplus", [
            'surplus_amount' => 60.00,
            'percentage' => 50,
        ]);

        $response->assertRedirect('/');

        $goal->refresh();
        $this->assertGreaterThan(100.00, (float) $goal->current_amount);

        $this->assertDatabaseHas('transactions', [
            'type' => 'savings',
            'goal_id' => $goal->id,
            'amount' => 30.00,
        ]);
    }

    public function test_user_can_activate_auto_harvest_on_goal(): void
    {
        $goal = SavingGoal::create([
            'name' => 'صندوق صيانة السيارة',
            'target_amount' => 2000.00,
            'current_amount' => 200.00,
            'currency' => 'ILS',
            'icon' => '🚗',
        ]);

        $response = $this->post("/goals/{$goal->id}/harvest/activate", [
            'ratio' => 60,
            'max_daily' => 50.00,
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');

        $goal->refresh();
        $this->assertTrue($goal->is_harvest_active);
        $this->assertEquals(0.60, (float) $goal->harvest_ratio);
        $this->assertEquals(50.00, (float) $goal->harvest_max_daily);
        $this->assertNotNull($goal->harvest_started_at);
    }

    public function test_user_can_toggle_auto_harvest_on_goal(): void
    {
        $goal = SavingGoal::create([
            'name' => 'صندوق العمرة',
            'target_amount' => 5000.00,
            'current_amount' => 1000.00,
            'currency' => 'ILS',
            'is_harvest_active' => true,
            'harvest_ratio' => 0.50,
        ]);

        $response = $this->post("/goals/{$goal->id}/harvest/toggle");
        $response->assertRedirect(route('dashboard'));

        $goal->refresh();
        $this->assertFalse($goal->is_harvest_active);

        // Toggle back on
        $this->post("/goals/{$goal->id}/harvest/toggle");
        $goal->refresh();
        $this->assertTrue($goal->is_harvest_active);
    }

    public function test_user_can_fetch_goal_harvest_logs_and_forecast(): void
    {
        $goal = SavingGoal::create([
            'name' => 'صندوق الاستثمار',
            'target_amount' => 3000.00,
            'current_amount' => 500.00,
            'currency' => 'USD',
            'is_harvest_active' => true,
            'harvest_ratio' => 0.50,
        ]);

        $response = $this->getJson("/goals/{$goal->id}/harvest/logs");
        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'goal',
            'logs',
            'forecast' => [
                'allowance',
                'spent',
                'surplus',
                'expected_harvest_ils',
                'expected_harvest_goal',
                'currency',
            ],
        ]);
    }

    public function test_auto_harvest_service_deducts_percentage_from_end_of_day_surplus(): void
    {
        $goal = SavingGoal::create([
            'name' => 'صندوق الطوارئ بالدولار',
            'target_amount' => 1000.00,
            'current_amount' => 100.00,
            'currency' => 'USD',
            'is_harvest_active' => true,
            'harvest_ratio' => 0.50,
            'harvest_started_at' => now()->subDay()->toDateString(),
        ]);

        // Create an expense of 40 ILS on yesterday
        Transaction::create([
            'type' => 'expense',
            'amount' => 40.00,
            'date' => now()->subDay()->toDateString(),
            'payment_method' => 'cash',
        ]);

        // Run process with a daily allowance of 100 ILS (surplus = 100 - 40 = 60 ILS)
        // 50% ratio = 30 ILS harvest into USD
        GoalSurplusHarvestService::processActiveHarvests(100.00);

        $goal->refresh();
        $this->assertGreaterThan(100.00, (float) $goal->current_amount);

        // Check harvest log was recorded
        $this->assertDatabaseHas('goal_harvest_logs', [
            'goal_id' => $goal->id,
            'day_allowance' => 100.00,
            'day_spent' => 40.00,
            'surplus_amount' => 60.00,
            'harvested_amount' => 30.00,
        ]);

        $log = $goal->harvestLogs()->whereDate('date', now()->subDay()->toDateString())->first();
        $this->assertNotNull($log);
        $this->assertEquals(now()->subDay()->toDateString(), $log->date->toDateString());

        // Check savings transaction
        $this->assertDatabaseHas('transactions', [
            'type' => 'savings',
            'goal_id' => $goal->id,
            'amount' => 30.00,
        ]);

        // Check idempotency (running again should not duplicate)
        $amountBefore = (float) $goal->current_amount;
        GoalSurplusHarvestService::processActiveHarvests(100.00);
        $goal->refresh();
        $this->assertEquals($amountBefore, (float) $goal->current_amount);
    }

    public function test_artisan_command_goals_harvest_surplus_executes_cleanly(): void
    {
        $this->artisan('goals:harvest-surplus --allowance=100')
            ->assertExitCode(0);
    }
}
