<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\User;
use App\Models\UserSetting;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebPushNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'عائلة أحمد ومريم']);
        $this->user = User::factory()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'أحمد',
            'email' => 'ahmad@example.com',
        ]);

        UserSetting::create([
            'workspace_id' => $this->workspace->id,
            'currency' => 'ILS',
            'theme' => 'dark',
        ]);
    }

    public function test_can_fetch_public_vapid_key(): void
    {
        $response = $this->actingAs($this->user)->getJson(route('push.public-key'));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertNotEmpty($response->json('public_key'));
    }

    public function test_user_can_subscribe_to_web_push(): void
    {
        $payload = [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/fake-endpoint-token-12345',
            'keys' => [
                'p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QT9AcPP313T59P1W_I90s_rW9PO1K5_0',
                'auth' => 'tBHItJI5svbpez7KI4CCXg',
            ],
            'content_encoding' => 'aes128gcm',
        ];

        $response = $this->actingAs($this->user)->postJson(route('push.subscribe'), $payload);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $this->user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/fake-endpoint-token-12345',
        ]);
    }

    public function test_user_can_unsubscribe_from_web_push(): void
    {
        PushSubscription::create([
            'user_id' => $this->user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/to-delete-123',
            'public_key' => 'fake-key',
            'auth_token' => 'fake-auth',
        ]);

        $response = $this->actingAs($this->user)->postJson(route('push.unsubscribe'), [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/to-delete-123',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('push_subscriptions', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/to-delete-123',
        ]);
    }

    public function test_test_push_fails_gracefully_when_no_devices_registered(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('push.test'));

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_test_push_succeeds_when_device_registered(): void
    {
        PushSubscription::create([
            'user_id' => $this->user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/sample-device-token',
            'public_key' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QT9AcPP313T59P1W_I90s_rW9PO1K5_0',
            'auth_token' => 'tBHItJI5svbpez7KI4CCXg',
        ]);

        $response = $this->actingAs($this->user)->postJson(route('push.test'));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_evening_surplus_reminder_creates_notification(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('push.evening-reminder'));

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('partner_notifications', [
            'workspace_id' => $this->workspace->id,
            'actor_id' => $this->user->id,
            'type' => 'evening_surplus_reminder',
        ]);
    }

    public function test_partner_transaction_dispatches_partner_notification(): void
    {
        $partner = User::factory()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'مريم',
            'email' => 'mariam@example.com',
        ]);

        PushSubscription::create([
            'user_id' => $partner->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/mariam-phone-123',
            'public_key' => 'fake-key',
            'auth_token' => 'fake-auth',
        ]);

        $response = $this->actingAs($this->user)->post(route('transactions.store'), [
            'type' => 'expense',
            'amount' => 60,
            'date' => now()->toDateString(),
            'payment_method' => 'cash',
            'note' => 'تموين خضار وفواكه',
        ]);

        $this->assertDatabaseHas('partner_notifications', [
            'workspace_id' => $this->workspace->id,
            'actor_id' => $this->user->id,
            'type' => 'transaction_added',
        ]);
    }
}
