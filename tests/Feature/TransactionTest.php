<?php

namespace Tests\Feature;

use App\Models\Envelope;
use App\Models\SavingGoal;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_expense_transaction(): void
    {
        $envelope = Envelope::create([
            'name' => 'مقاضي',
            'pillar' => 'needs',
            'budget' => 500,
        ]);

        $response = $this->post('/transactions', [
            'type' => 'expense',
            'amount' => 45.50,
            'envelope_id' => $envelope->id,
            'date' => now()->toDateString(),
            'payment_method' => 'transfer',
            'note' => 'خضار وفواكه',
        ]);

        $response->assertRedirect('/');
        $this->assertDatabaseHas('transactions', [
            'type' => 'expense',
            'amount' => 45.50,
            'note' => 'خضار وفواكه',
            'payment_method' => 'transfer',
        ]);
    }

    public function test_can_update_transaction_amount_and_note(): void
    {
        $envelope = Envelope::create([
            'name' => 'مقاضي',
            'pillar' => 'needs',
            'budget' => 500,
        ]);

        $tx = Transaction::create([
            'type' => 'expense',
            'amount' => 45.50,
            'envelope_id' => $envelope->id,
            'date' => now()->toDateString(),
            'payment_method' => 'transfer',
            'note' => 'خضار وفواكه',
        ]);

        $response = $this->put("/transactions/{$tx->id}", [
            'type' => 'expense',
            'amount' => 60.00,
            'envelope_id' => $envelope->id,
            'date' => now()->toDateString(),
            'payment_method' => 'cash',
            'note' => 'خضار وفواكه ولحوم',
        ]);

        $response->assertRedirect('/');

        $fresh = $tx->fresh();
        $this->assertEquals(60.00, $fresh->amount);
        $this->assertEquals('cash', $fresh->payment_method);
        $this->assertEquals('خضار وفواكه ولحوم', $fresh->note);
    }

    public function test_updating_savings_transaction_adjusts_goal_balance(): void
    {
        $goal = SavingGoal::create([
            'name' => 'صندوق طوارئ',
            'target_amount' => 1000,
            'current_amount' => 100,
            'currency' => 'ILS',
        ]);

        // Create savings tx: 50
        $tx = Transaction::create([
            'type' => 'savings',
            'amount' => 50.00,
            'goal_id' => $goal->id,
            'date' => now()->toDateString(),
            'payment_method' => 'transfer',
        ]);
        $goal->increment('current_amount', 50.00); // Now 150

        $this->assertEquals(150.00, $goal->fresh()->current_amount);

        // Update tx to 80.00
        $response = $this->put("/transactions/{$tx->id}", [
            'type' => 'savings',
            'amount' => 80.00,
            'goal_id' => $goal->id,
            'date' => now()->toDateString(),
            'payment_method' => 'transfer',
            'note' => 'زيادة الادخار',
        ]);

        $response->assertRedirect('/');

        // Old 50 was subtracted (150 - 50 = 100), new 80 was added (100 + 80 = 180)
        $this->assertEquals(180.00, $goal->fresh()->current_amount);
    }

    public function test_can_delete_transaction(): void
    {
        $tx = Transaction::create([
            'type' => 'expense',
            'amount' => 25.00,
            'date' => now()->toDateString(),
            'payment_method' => 'card',
            'note' => 'بنزين',
        ]);

        $response = $this->delete("/transactions/{$tx->id}");
        $response->assertRedirect('/');
        $this->assertDatabaseMissing('transactions', ['id' => $tx->id]);
    }
}
