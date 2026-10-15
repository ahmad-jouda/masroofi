<?php

namespace Tests\Feature;

use App\Models\Debt;
use App\Models\SavingGoal;
use App\Models\Transaction;
use App\Services\DebtSurplusHarvestService;
use App\Services\GoalSurplusHarvestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailySurplusAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_retroactive_expense_deducts_from_debt_and_goal_harvest_according_to_ratios(): void
    {
        $yesterday = now()->subDay()->toDateString();

        // 1. Create a debt with 50% harvest ratio
        $debt = Debt::create([
            'name' => 'سوبرماركت الأمل',
            'type' => 'supermarket',
            'total_debt' => 200.00,
            'status' => 'active',
            'is_harvest_active' => true,
            'harvest_ratio' => 0.50,
            'harvest_started_at' => $yesterday,
        ]);

        // 2. Create a goal with 50% harvest ratio
        $goal = SavingGoal::create([
            'name' => 'صندوق الطوارئ',
            'target_amount' => 500.00,
            'current_amount' => 0.00,
            'currency' => 'ILS',
            'is_harvest_active' => true,
            'harvest_ratio' => 0.50,
            'harvest_started_at' => $yesterday,
        ]);

        // Initial recorded expense yesterday: 10 ILS (out of 50 ILS allowance -> 40 ILS surplus)
        Transaction::create([
            'type' => 'expense',
            'amount' => 10.00,
            'date' => $yesterday,
            'payment_method' => 'card',
        ]);

        // Process harvest for yesterday:
        // Surplus = 50 - 10 = 40 ILS
        // Debt (50%) gets 20 ILS
        // Goal (50%) gets 20 ILS
        DebtSurplusHarvestService::processDebt($debt, 50.0);
        GoalSurplusHarvestService::processGoal($goal, 50.0);

        $this->assertEquals(20.00, (float) $debt->fresh()->surplus_harvested);
        $this->assertEquals(20.00, (float) $goal->fresh()->current_amount);

        // Verify goal savings transaction created
        $savingsTx = Transaction::where('type', 'savings')->where('goal_id', $goal->id)->first();
        $this->assertNotNull($savingsTx);
        $this->assertEquals(20.00, (float) $savingsTx->amount);

        // NOW: The user remembers they bought something yesterday for 20 ILS and enters it today
        $response = $this->post('/transactions', [
            'type' => 'expense',
            'amount' => 20.00,
            'date' => $yesterday,
            'payment_method' => 'card',
            'note' => 'مشتريات نسيت تسجيلها أمس',
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');

        // New total spent yesterday: 10 + 20 = 30 ILS.
        // New surplus: 50 - 30 = 20 ILS.
        // Debt (50%): should now be 10 ILS (deducted 10 ILS from 20 ILS).
        // Goal (50%): should now be 10 ILS (deducted 10 ILS from 20 ILS).
        $this->assertEquals(10.00, (float) $debt->fresh()->surplus_harvested);
        $this->assertEquals(10.00, (float) $goal->fresh()->current_amount);

        // Goal savings transaction amount should be updated to 10 ILS
        $this->assertEquals(10.00, (float) $savingsTx->fresh()->amount);

        // Verify logs updated
        $this->assertDatabaseHas('debt_harvest_logs', [
            'debt_id' => $debt->id,
            'day_spent' => 30.00,
            'surplus_amount' => 20.00,
            'harvested_amount' => 10.00,
        ]);

        $this->assertDatabaseHas('goal_harvest_logs', [
            'goal_id' => $goal->id,
            'day_spent' => 30.00,
            'surplus_amount' => 20.00,
            'harvested_amount' => 10.00,
        ]);
    }

    public function test_retroactive_expense_exceeding_allowance_wipes_out_harvest_and_deletes_savings_tx(): void
    {
        $yesterday = now()->subDay()->toDateString();

        $debt = Debt::create([
            'name' => 'دين شخصي',
            'type' => 'person',
            'total_debt' => 100.00,
            'status' => 'active',
            'is_harvest_active' => true,
            'harvest_ratio' => 0.50,
            'harvest_started_at' => $yesterday,
        ]);

        $goal = SavingGoal::create([
            'name' => 'حصالة الهاتف',
            'target_amount' => 300.00,
            'current_amount' => 0.00,
            'currency' => 'ILS',
            'is_harvest_active' => true,
            'harvest_ratio' => 0.50,
            'harvest_started_at' => $yesterday,
        ]);

        // Spent 20 ILS yesterday -> Surplus = 50 - 20 = 30 ILS. Debt gets 15, Goal gets 15.
        Transaction::create([
            'type' => 'expense',
            'amount' => 20.00,
            'date' => $yesterday,
            'payment_method' => 'card',
        ]);

        DebtSurplusHarvestService::processDebt($debt, 50.0);
        GoalSurplusHarvestService::processGoal($goal, 50.0);

        $this->assertEquals(15.00, (float) $debt->fresh()->surplus_harvested);
        $this->assertEquals(15.00, (float) $goal->fresh()->current_amount);
        $savingsTx = Transaction::where('type', 'savings')->where('goal_id', $goal->id)->first();
        $this->assertNotNull($savingsTx);

        // Retroactive expense of 35 ILS yesterday -> Total spent 55 ILS > 50 ILS allowance -> Surplus = 0!
        $this->post('/transactions', [
            'type' => 'expense',
            'amount' => 35.00,
            'date' => $yesterday,
            'payment_method' => 'card',
        ]);

        // Debt harvest pot and goal current_amount should be reduced to 0
        $this->assertEquals(0.00, (float) $debt->fresh()->surplus_harvested);
        $this->assertEquals(0.00, (float) $goal->fresh()->current_amount);

        // Savings transaction should be deleted
        $this->assertDatabaseMissing('transactions', ['id' => $savingsTx->id]);

        // Harvest logs should record 0 surplus and 0 harvest
        $this->assertDatabaseHas('debt_harvest_logs', [
            'debt_id' => $debt->id,
            'surplus_amount' => 0.00,
            'harvested_amount' => 0.00,
        ]);

        $this->assertDatabaseHas('goal_harvest_logs', [
            'goal_id' => $goal->id,
            'surplus_amount' => 0.00,
            'harvested_amount' => 0.00,
        ]);
    }

    public function test_updating_past_expense_recalculates_harvest(): void
    {
        $yesterday = now()->subDay()->toDateString();

        $debt = Debt::create([
            'name' => 'سوبرماركت',
            'type' => 'supermarket',
            'total_debt' => 100.00,
            'status' => 'active',
            'is_harvest_active' => true,
            'harvest_ratio' => 0.50,
            'harvest_started_at' => $yesterday,
        ]);

        $initialTx = Transaction::create([
            'type' => 'expense',
            'amount' => 20.00,
            'date' => $yesterday,
            'payment_method' => 'card',
        ]);

        DebtSurplusHarvestService::processDebt($debt, 50.0);
        // Surplus = 30 -> 15 ILS harvested
        $this->assertEquals(15.00, (float) $debt->fresh()->surplus_harvested);

        // Update the expense from 20 to 10 ILS -> Surplus increases to 40 ILS -> Harvest should become 20 ILS (+5)
        $this->put("/transactions/{$initialTx->id}", [
            'type' => 'expense',
            'amount' => 10.00,
            'date' => $yesterday,
            'payment_method' => 'card',
        ]);

        $this->assertEquals(20.00, (float) $debt->fresh()->surplus_harvested);

        // Now update from 10 to 40 ILS -> Surplus decreases to 10 ILS -> Harvest should become 5 ILS (-15)
        $this->put("/transactions/{$initialTx->id}", [
            'type' => 'expense',
            'amount' => 40.00,
            'date' => $yesterday,
            'payment_method' => 'card',
        ]);

        $this->assertEquals(5.00, (float) $debt->fresh()->surplus_harvested);
    }

    public function test_deleting_past_expense_recalculates_and_restores_harvest(): void
    {
        $yesterday = now()->subDay()->toDateString();

        $debt = Debt::create([
            'name' => 'سوبرماركت',
            'type' => 'supermarket',
            'total_debt' => 100.00,
            'status' => 'active',
            'is_harvest_active' => true,
            'harvest_ratio' => 0.50,
            'harvest_started_at' => $yesterday,
        ]);

        $tx = Transaction::create([
            'type' => 'expense',
            'amount' => 30.00,
            'date' => $yesterday,
            'payment_method' => 'card',
        ]);

        DebtSurplusHarvestService::processDebt($debt, 50.0);
        // Surplus = 20 -> 10 ILS harvested
        $this->assertEquals(10.00, (float) $debt->fresh()->surplus_harvested);

        // Delete the expense -> Spent becomes 0 -> Surplus becomes 50 -> Harvest becomes 25 ILS (+15)
        $this->delete("/transactions/{$tx->id}");

        $this->assertEquals(25.00, (float) $debt->fresh()->surplus_harvested);
        $this->assertDatabaseHas('debt_harvest_logs', [
            'debt_id' => $debt->id,
            'day_spent' => 0.00,
            'surplus_amount' => 50.00,
            'harvested_amount' => 25.00,
        ]);
    }
}
