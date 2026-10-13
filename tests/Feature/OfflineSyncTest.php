<?php

namespace Tests\Feature;

use App\Models\Envelope;
use App\Models\SavingGoal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfflineSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_batch_sync_offline_transactions(): void
    {
        $envelope = Envelope::create([
            'name' => 'طعام وتموين',
            'icon' => '🛒',
            'type' => 'needs',
            'allocated_amount' => 500,
        ]);

        $goal = SavingGoal::create([
            'name' => 'شراء لابتوب',
            'target_amount' => 2000,
            'current_amount' => 100,
            'icon' => '💻',
            'color' => '#10b981',
        ]);

        $payload = [
            'transactions' => [
                [
                    'type' => 'expense',
                    'amount' => 35.50,
                    'envelope_id' => $envelope->id,
                    'date' => now()->toDateString(),
                    'payment_method' => 'cash',
                    'note' => 'مشتريات بقالة بدون إنترنت',
                ],
                [
                    'type' => 'income',
                    'amount' => 150.00,
                    'date' => now()->toDateString(),
                    'payment_method' => 'transfer',
                    'note' => 'يومية عمل إضافي',
                ],
                [
                    'type' => 'savings',
                    'amount' => 50.00,
                    'goal_id' => $goal->id,
                    'date' => now()->toDateString(),
                    'payment_method' => 'transfer',
                    'note' => 'تغذية صندوق اللابتوب',
                ],
            ],
        ];

        $response = $this->postJson('/transactions/sync', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'synced_count' => 3,
            ]);

        $this->assertDatabaseHas('transactions', [
            'type' => 'expense',
            'amount' => 35.50,
            'note' => 'مشتريات بقالة بدون إنترنت',
            'envelope_id' => $envelope->id,
        ]);

        $this->assertDatabaseHas('transactions', [
            'type' => 'income',
            'amount' => 150.00,
            'note' => 'يومية عمل إضافي',
        ]);

        $this->assertDatabaseHas('transactions', [
            'type' => 'savings',
            'amount' => 50.00,
            'goal_id' => $goal->id,
        ]);

        $this->assertEquals(150.00, $goal->fresh()->current_amount);
    }

    public function test_sync_validates_required_fields(): void
    {
        $response = $this->postJson('/transactions/sync', [
            'transactions' => [
                [
                    'type' => 'invalid_type',
                    'amount' => -10,
                ],
            ],
        ]);

        $response->assertStatus(422);
    }
}
