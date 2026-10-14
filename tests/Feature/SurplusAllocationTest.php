<?php

namespace Tests\Feature;

use App\Models\Debt;
use App\Models\SavingGoal;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurplusAllocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_allocate_surplus_between_debt_and_saving_goal_simultaneously(): void
    {
        $debt = Debt::create([
            'name' => 'سوبرماركت الهناء',
            'type' => 'supermarket',
            'total_debt' => 200.00,
            'status' => 'active',
        ]);
        $debt->records()->create([
            'type' => 'add_debt',
            'amount' => 200.00,
            'date' => now()->toDateString(),
        ]);

        $goal = SavingGoal::create([
            'name' => 'صندوق الطوارئ بالدولار',
            'target_amount' => 1000.00,
            'current_amount' => 100.00,
            'currency' => 'USD',
            'icon' => '🛡️',
        ]);

        // Surplus 100 ILS: 40% Debt (40 ILS), 40% Goal (40 ILS), 20% Buffer (20 ILS)
        $response = $this->post('/surplus/allocate', [
            'surplus_amount' => 100.00,
            'debt_id' => $debt->id,
            'debt_percentage' => 40,
            'goal_id' => $goal->id,
            'goal_percentage' => 40,
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');

        $debt->refresh();
        $this->assertEquals(160.00, (float) $debt->total_debt);

        $goal->refresh();
        $this->assertGreaterThan(100.00, (float) $goal->current_amount);

        $this->assertDatabaseHas('transactions', [
            'type' => 'expense',
            'amount' => 40.00,
        ]);

        $this->assertDatabaseHas('transactions', [
            'type' => 'savings',
            'goal_id' => $goal->id,
            'amount' => 40.00,
        ]);
    }

    public function test_can_allocate_surplus_only_to_saving_goal_when_no_debt(): void
    {
        $goal = SavingGoal::create([
            'name' => 'صندوق السفر',
            'target_amount' => 500.00,
            'current_amount' => 50.00,
            'currency' => 'ILS',
            'icon' => '✈️',
        ]);

        // Surplus 80 ILS: 0% Debt, 50% Goal (40 ILS)
        $response = $this->post('/surplus/allocate', [
            'surplus_amount' => 80.00,
            'debt_percentage' => 0,
            'goal_id' => $goal->id,
            'goal_percentage' => 50,
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');

        $goal->refresh();
        $this->assertEquals(90.00, (float) $goal->current_amount);

        $this->assertDatabaseHas('transactions', [
            'type' => 'savings',
            'goal_id' => $goal->id,
            'amount' => 40.00,
        ]);
    }

    public function test_can_allocate_surplus_only_to_debt_when_no_goal(): void
    {
        $debt = Debt::create([
            'name' => 'سلفة شخصية',
            'type' => 'person',
            'total_debt' => 300.00,
            'status' => 'active',
        ]);
        $debt->records()->create([
            'type' => 'add_debt',
            'amount' => 300.00,
            'date' => now()->toDateString(),
        ]);

        // Surplus 60 ILS: 100% Debt (60 ILS), 0% Goal
        $response = $this->post('/surplus/allocate', [
            'surplus_amount' => 60.00,
            'debt_id' => $debt->id,
            'debt_percentage' => 100,
            'goal_percentage' => 0,
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');

        $debt->refresh();
        $this->assertEquals(240.00, (float) $debt->total_debt);

        $this->assertDatabaseHas('transactions', [
            'type' => 'expense',
            'amount' => 60.00,
        ]);
    }

    public function test_cannot_allocate_more_than_100_percent(): void
    {
        $response = $this->post('/surplus/allocate', [
            'surplus_amount' => 100.00,
            'debt_percentage' => 70,
            'goal_percentage' => 40,
        ]);

        $response->assertSessionHas('error');
    }

    public function test_zero_percentage_allocation_preserves_full_surplus(): void
    {
        $response = $this->post('/surplus/allocate', [
            'surplus_amount' => 100.00,
            'debt_percentage' => 0,
            'goal_percentage' => 0,
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');
        $this->assertEquals(0, Transaction::count());
    }

    public function test_can_save_auto_harvest_settings_during_surplus_allocation(): void
    {
        $debt = Debt::create([
            'name' => 'سوبرماركت',
            'type' => 'supermarket',
            'total_debt' => 200.00,
            'status' => 'active',
        ]);

        $goal = SavingGoal::create([
            'name' => 'حصالة المستقبل',
            'target_amount' => 500.00,
            'current_amount' => 50.00,
            'currency' => 'ILS',
            'icon' => '🌟',
        ]);

        $response = $this->post('/surplus/allocate', [
            'surplus_amount' => 100.00,
            'debt_id' => $debt->id,
            'debt_percentage' => 30,
            'save_debt_auto_harvest' => 1,
            'goal_id' => $goal->id,
            'goal_percentage' => 50,
            'save_goal_auto_harvest' => 1,
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');

        $debt->refresh();
        $this->assertTrue($debt->is_harvest_active);
        $this->assertEquals(0.30, (float) $debt->harvest_ratio);

        $goal->refresh();
        $this->assertTrue($goal->is_harvest_active);
        $this->assertEquals(0.50, (float) $goal->harvest_ratio);
    }
}
