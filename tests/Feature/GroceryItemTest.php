<?php

namespace Tests\Feature;

use App\Models\Envelope;
use App\Models\GroceryItem;
use App\Models\PartnerNotification;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroceryItemTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;

    protected User $husband;

    protected User $wife;

    protected Envelope $groceryEnvelope;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create([
            'name' => 'محفظة العائلة',
            'currency' => 'ILS',
        ]);

        $this->husband = User::factory()->create([
            'name' => 'أحمد جودة',
            'email' => 'ahmad@example.com',
            'workspace_id' => $this->workspace->id,
            'role' => 'admin',
        ]);

        $this->wife = User::factory()->create([
            'name' => 'الزوجة',
            'email' => 'wife@example.com',
            'workspace_id' => $this->workspace->id,
            'role' => 'member',
        ]);

        $this->groceryEnvelope = Envelope::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'بقالة وتموين',
            'icon' => '🛒',
            'type' => 'needs',
            'allocated_amount' => 1200.00,
        ]);
    }

    public function test_user_can_add_item_to_grocery_list_and_notify_partner(): void
    {
        $response = $this->actingAs($this->husband)->postJson(route('grocery-items.store'), [
            'name' => 'حليب طازج 🥛',
            'category' => 'dairy',
            'estimated_price' => 7.50,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('grocery_items', [
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->husband->id,
            'name' => 'حليب طازج 🥛',
            'category' => 'dairy',
            'estimated_price' => 7.50,
            'is_bought' => false,
        ]);

        // Partner notification dispatched
        $this->assertDatabaseHas('partner_notifications', [
            'workspace_id' => $this->workspace->id,
            'actor_id' => $this->husband->id,
            'type' => 'grocery_add',
        ]);
    }

    public function test_user_can_toggle_grocery_item_bought_status(): void
    {
        $item = GroceryItem::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->husband->id,
            'name' => 'خبز طازج 🥖',
            'category' => 'groceries',
            'estimated_price' => 4.00,
            'is_bought' => false,
        ]);

        // Wife toggles item to bought
        $response = $this->actingAs($this->wife)->postJson(route('grocery-items.toggle', $item));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'item' => [
                    'is_bought' => true,
                    'bought_by' => $this->wife->id,
                ],
            ]);

        $this->assertTrue($item->fresh()->is_bought);
        $this->assertEquals($this->wife->id, $item->fresh()->bought_by);
        $this->assertNotNull($item->fresh()->bought_at);

        // Toggle back to unbought
        $this->actingAs($this->wife)->postJson(route('grocery-items.toggle', $item));
        $this->assertFalse($item->fresh()->is_bought);
        $this->assertNull($item->fresh()->bought_by);
    }

    public function test_user_can_delete_grocery_item(): void
    {
        $item = GroceryItem::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->husband->id,
            'name' => 'غرض ملغى',
            'category' => 'other',
            'is_bought' => false,
        ]);

        $response = $this->actingAs($this->husband)->deleteJson(route('grocery-items.destroy', $item));

        $response->assertStatus(200);
        $this->assertDatabaseMissing('grocery_items', ['id' => $item->id]);
    }

    public function test_user_can_convert_bought_grocery_items_to_expense_in_envelope(): void
    {
        $item1 = GroceryItem::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->wife->id,
            'name' => 'حليب 🥛',
            'category' => 'dairy',
            'estimated_price' => 7.00,
            'is_bought' => true,
        ]);

        $item2 = GroceryItem::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->husband->id,
            'name' => 'بيض 🥚',
            'category' => 'dairy',
            'estimated_price' => 18.00,
            'is_bought' => true,
        ]);

        $response = $this->actingAs($this->husband)->postJson(route('grocery-items.convert-expense'), [
            'amount' => 26.50, // Actual supermarket bill
            'envelope_id' => $this->groceryEnvelope->id,
            'payment_method' => 'card',
            'date' => now()->toDateString(),
            'note' => 'مشتريات السوبرماركت الأسبوعية',
            'item_ids' => [$item1->id, $item2->id],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'converted_count' => 2,
            ]);

        // Transaction created
        $this->assertDatabaseHas('transactions', [
            'workspace_id' => $this->workspace->id,
            'type' => 'expense',
            'amount' => 26.50,
            'envelope_id' => $this->groceryEnvelope->id,
            'payment_method' => 'card',
            'note' => 'مشتريات السوبرماركت الأسبوعية',
        ]);

        $tx = Transaction::where('amount', 26.50)->first();

        // Items linked and archived
        $this->assertEquals($tx->id, $item1->fresh()->transaction_id);
        $this->assertEquals($tx->id, $item2->fresh()->transaction_id);
        $this->assertEquals($this->husband->id, $item1->fresh()->bought_by);

        // Partner notification dispatched
        $this->assertDatabaseHas('partner_notifications', [
            'workspace_id' => $this->workspace->id,
            'actor_id' => $this->husband->id,
            'type' => 'grocery_converted',
        ]);
    }

    public function test_can_batch_sync_offline_grocery_items(): void
    {
        $existingItem = GroceryItem::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->husband->id,
            'name' => 'جبنة كيري 🧀',
            'category' => 'dairy',
            'is_bought' => false,
        ]);

        $itemToDelete = GroceryItem::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->husband->id,
            'name' => 'غرض تم حذفه أوفلاين',
            'category' => 'other',
            'is_bought' => false,
        ]);

        $payload = [
            'new_items' => [
                [
                    'name' => 'منظف أطباق 🧼',
                    'category' => 'cleaning',
                    'estimated_price' => 12.00,
                    'is_bought' => false,
                ],
            ],
            'toggles' => [
                [
                    'id' => $existingItem->id,
                    'is_bought' => true,
                ],
            ],
            'deletes' => [
                $itemToDelete->id,
            ],
        ];

        $response = $this->actingAs($this->husband)->postJson(route('grocery-items.sync'), $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('grocery_items', [
            'name' => 'منظف أطباق 🧼',
            'category' => 'cleaning',
        ]);

        $this->assertTrue($existingItem->fresh()->is_bought);
        $this->assertDatabaseMissing('grocery_items', ['id' => $itemToDelete->id]);
    }

    public function test_partner_can_poll_notifications_and_mark_all_read(): void
    {
        // Husband performs an action that notifies wife
        PartnerNotification::notifyPartner(
            workspaceId: $this->workspace->id,
            actorId: $this->husband->id,
            type: 'grocery_add',
            title: '🛒 إضافة لقائمة المقاضي',
            message: '👤 أضاف أحمد: حليب',
            icon: '🛒'
        );

        // Wife polls for notifications
        $response = $this->actingAs($this->wife)->getJson(route('partner.notifications.poll'));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'unread_count' => 1,
            ]);

        // Wife marks all read
        $markRes = $this->actingAs($this->wife)->postJson(route('partner.notifications.mark-read'));
        $markRes->assertStatus(200);

        // Poll again -> 0 unread
        $pollAgain = $this->actingAs($this->wife)->getJson(route('partner.notifications.poll'));
        $pollAgain->assertJson([
            'unread_count' => 0,
        ]);
    }

    public function test_recording_transaction_notifies_partner(): void
    {
        $response = $this->actingAs($this->husband)->postJson(route('transactions.store'), [
            'type' => 'expense',
            'amount' => 60.00,
            'envelope_id' => $this->groceryEnvelope->id,
            'date' => now()->toDateString(),
            'payment_method' => 'cash',
            'note' => 'مصروف بقالة',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('partner_notifications', [
            'workspace_id' => $this->workspace->id,
            'actor_id' => $this->husband->id,
            'type' => 'transaction_added',
        ]);

        $notif = PartnerNotification::where('type', 'transaction_added')->first();
        $this->assertStringContainsString('60.00', $notif->message);
        $this->assertStringContainsString('أحمد جودة', $notif->message);
    }
}
