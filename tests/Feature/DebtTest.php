<?php

namespace Tests\Feature;

use App\Models\Debt;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DebtSurplusHarvestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebtTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_debt_account_with_initial_amount(): void
    {
        $response = $this->post('/debts', [
            'name' => 'سوبرماركت الأمل',
            'type' => 'supermarket',
            'initial_amount' => 120.50,
            'notes' => 'حساب شكك شهري',
        ]);

        $response->assertRedirect('/');

        $this->assertDatabaseHas('debts', [
            'name' => 'سوبرماركت الأمل',
            'type' => 'supermarket',
            'total_debt' => 120.50,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('debt_records', [
            'type' => 'add_debt',
            'amount' => 120.50,
        ]);
    }

    public function test_can_add_debt_record_for_groceries(): void
    {
        $debt = Debt::create([
            'name' => 'بقالة أبو أحمد',
            'type' => 'supermarket',
            'total_debt' => 0.00,
        ]);

        $debt->records()->create([
            'type' => 'add_debt',
            'amount' => 50.00,
            'date' => now()->toDateString(),
            'note' => 'رصيد سابق',
        ]);
        $debt->recalculateBalance();

        $response = $this->post("/debts/{$debt->id}/add", [
            'amount' => 45.00,
            'date' => now()->toDateString(),
            'note' => 'تموين وحليب واجبان',
        ]);

        $response->assertRedirect('/');

        $this->assertEquals(95.00, $debt->fresh()->total_debt);
        $this->assertDatabaseHas('debt_records', [
            'debt_id' => $debt->id,
            'type' => 'add_debt',
            'amount' => 45.00,
            'note' => 'تموين وحليب واجبان',
        ]);
    }

    public function test_can_repay_debt_and_creates_expense_transaction(): void
    {
        $debt = Debt::create([
            'name' => 'سلفة من محمد',
            'type' => 'person',
            'total_debt' => 200.00,
            'status' => 'active',
        ]);

        $debt->records()->create([
            'type' => 'add_debt',
            'amount' => 200.00,
            'date' => now()->toDateString(),
        ]);

        $response = $this->post("/debts/{$debt->id}/repay", [
            'amount' => 200.00,
            'date' => now()->toDateString(),
            'payment_method' => 'transfer',
            'note' => 'سداد السلفة بالكامل عبر التطبيق',
        ]);

        $response->assertRedirect('/');

        $fresh = $debt->fresh();
        $this->assertEquals(0.00, $fresh->total_debt);
        $this->assertEquals('settled', $fresh->status);

        // Verify financial transaction was logged
        $this->assertDatabaseHas('transactions', [
            'type' => 'expense',
            'amount' => 200.00,
            'payment_method' => 'transfer',
            'note' => 'سداد السلفة بالكامل عبر التطبيق',
        ]);
    }

    public function test_can_delete_debt_account(): void
    {
        $debt = Debt::create([
            'name' => 'متجر ملابس',
            'type' => 'store',
            'total_debt' => 0.00,
        ]);

        $response = $this->delete("/debts/{$debt->id}");

        $response->assertRedirect('/');
        $this->assertDatabaseMissing('debts', ['id' => $debt->id]);
    }

    public function test_can_activate_harvest_plan_and_accumulate_daily_surplus(): void
    {
        $debt = Debt::create([
            'name' => 'سوبرماركت الأمل',
            'type' => 'supermarket',
            'total_debt' => 100.00,
            'status' => 'active',
        ]);

        $yesterday = now()->subDay()->toDateString();

        // Recorded expense of 20 yesterday
        Transaction::create([
            'type' => 'expense',
            'amount' => 20.00,
            'date' => $yesterday,
            'payment_method' => 'transfer',
        ]);

        $response = $this->post("/debts/{$debt->id}/harvest/activate", [
            'harvest_ratio' => 0.50,
            'harvest_started_at' => $yesterday,
        ]);

        $response->assertRedirect('/');

        $fresh = $debt->fresh();
        $this->assertTrue($fresh->is_harvest_active);
        // Allowance = 50, Spent = 20 => Surplus = 30 => 50% = 15
        $this->assertEquals(15.00, $fresh->surplus_harvested);

        $this->assertDatabaseHas('debt_harvest_logs', [
            'debt_id' => $debt->id,
            'day_allowance' => 50.00,
            'day_spent' => 20.00,
            'surplus_amount' => 30.00,
            'harvested_amount' => 15.00,
        ]);

        $log = $debt->harvestLogs()->whereDate('date', $yesterday)->first();
        $this->assertNotNull($log);
        $this->assertEquals($yesterday, $log->date->toDateString());
    }

    public function test_zero_harvest_documented_when_daily_spent_exceeds_allowance(): void
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

        // Spent 60, allowance is 50 -> Surplus is 0
        Transaction::create([
            'type' => 'expense',
            'amount' => 60.00,
            'date' => $yesterday,
            'payment_method' => 'transfer',
        ]);

        DebtSurplusHarvestService::processDebt($debt, 50.0);

        $this->assertEquals(0.00, $debt->fresh()->surplus_harvested);
        $this->assertDatabaseHas('debt_harvest_logs', [
            'debt_id' => $debt->id,
            'surplus_amount' => 0.00,
            'harvested_amount' => 0.00,
        ]);

        $log = $debt->harvestLogs()->whereDate('date', $yesterday)->first();
        $this->assertNotNull($log);
        $this->assertEquals($yesterday, $log->date->toDateString());
    }

    public function test_can_repay_full_debt_from_accumulated_harvest_pot(): void
    {
        $debt = Debt::create([
            'name' => 'سوبرماركت النور',
            'type' => 'supermarket',
            'total_debt' => 150.00,
            'surplus_harvested' => 150.00,
            'status' => 'active',
            'is_harvest_active' => true,
        ]);

        $debt->records()->create([
            'type' => 'add_debt',
            'amount' => 150.00,
            'date' => now()->toDateString(),
        ]);

        $this->assertTrue($debt->is_harvest_complete);

        $response = $this->post("/debts/{$debt->id}/harvest/repay", [
            'payment_method' => 'transfer',
        ]);

        $response->assertRedirect('/');

        $fresh = $debt->fresh();
        $this->assertEquals(0.00, $fresh->total_debt);
        $this->assertEquals('settled', $fresh->status);
        $this->assertFalse($fresh->is_harvest_active);

        $this->assertDatabaseHas('transactions', [
            'type' => 'expense',
            'amount' => 150.00,
            'note' => 'سداد دين: سوبرماركت النور (تم السداد بالكامل من فوائض اليوميات المجمعة 🎉)',
        ]);
    }

    public function test_harvest_artisan_command_runs_successfully(): void
    {
        $this->artisan('debts:harvest-surplus')
            ->assertExitCode(0);
    }

    public function test_deleting_transaction_linked_to_debt_repayment_recalculates_debt_balance(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $debt = Debt::create([
            'name' => 'حنان',
            'type' => 'person',
            'total_debt' => 60.00,
            'status' => 'active',
            'workspace_id' => $user->workspace_id,
        ]);
        $debt->records()->create([
            'type' => 'add_debt',
            'amount' => 60.00,
            'date' => now()->toDateString(),
            'workspace_id' => $user->workspace_id,
        ]);

        // Repay 27
        $response = $this->post("/debts/{$debt->id}/repay", [
            'amount' => 27.00,
            'date' => now()->toDateString(),
            'payment_method' => 'transfer',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertEquals(33.00, (float) $debt->fresh()->total_debt);

        $tx = Transaction::where('amount', 27.00)->first();
        $this->assertNotNull($tx);

        // Delete transaction
        $this->delete("/transactions/{$tx->id}");

        $this->assertDatabaseMissing('transactions', ['id' => $tx->id]);
        $this->assertDatabaseMissing('debt_records', ['transaction_id' => $tx->id]);

        $this->assertEquals(60.00, (float) $debt->fresh()->total_debt);
    }

    public function test_can_delete_debt_record_directly_and_recalculate_balance(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $debt = Debt::create([
            'name' => 'حنان',
            'type' => 'person',
            'total_debt' => 60.00,
            'status' => 'active',
            'workspace_id' => $user->workspace_id,
        ]);
        $debt->records()->create([
            'type' => 'add_debt',
            'amount' => 60.00,
            'date' => now()->toDateString(),
            'workspace_id' => $user->workspace_id,
        ]);
        $rec = $debt->records()->create([
            'type' => 'repayment',
            'amount' => 27.00,
            'date' => now()->toDateString(),
            'workspace_id' => $user->workspace_id,
        ]);
        $debt->recalculateBalance();
        $this->assertEquals(33.00, (float) $debt->fresh()->total_debt);

        $this->delete("/debts/records/{$rec->id}");

        $this->assertDatabaseMissing('debt_records', ['id' => $rec->id]);
        $this->assertEquals(60.00, (float) $debt->fresh()->total_debt);
    }

    public function test_can_reset_debt_harvest_pot_and_start_from_today(): void
    {
        $debt = Debt::create([
            'name' => 'حنان',
            'type' => 'person',
            'total_debt' => 60.00,
            'status' => 'active',
            'is_harvest_active' => true,
            'surplus_harvested' => 20.50,
            'harvest_started_at' => now()->subDays(3)->toDateString(),
        ]);

        $debt->harvestLogs()->create([
            'date' => now()->subDay()->toDateString(),
            'day_allowance' => 50.00,
            'day_spent' => 9.00,
            'surplus_amount' => 41.00,
            'harvested_amount' => 20.50,
        ]);

        $this->assertCount(1, $debt->harvestLogs);
        $this->assertEquals(20.50, (float) $debt->surplus_harvested);

        $response = $this->post("/debts/{$debt->id}/harvest/reset");

        $response->assertRedirect(route('dashboard', ['tab' => 'tab-debts']));
        $response->assertSessionHas('success');

        $fresh = $debt->fresh();
        $this->assertEquals(0.00, (float) $fresh->surplus_harvested);
        $this->assertEquals(now()->toDateString(), $fresh->harvest_started_at->toDateString());
        $this->assertCount(0, $fresh->harvestLogs);
    }
}
