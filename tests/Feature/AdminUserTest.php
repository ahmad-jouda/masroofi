<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create([
            'name' => 'المحفظة الرئيسية',
            'currency' => 'ILS',
        ]);

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'workspace_id' => $this->workspace->id,
        ]);
    }

    public function test_admin_can_create_user_with_valid_palestinian_phone(): void
    {
        // 059 valid phone
        $response1 = $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'أحمد جواد',
            'username' => 'ahmadj',
            'email' => 'ahmadj@example.com',
            'phone' => '0599123456',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
            'role' => 'member',
            'workspace_mode' => 'existing',
            'workspace_id' => $this->workspace->id,
        ]);

        $response1->assertSessionHas('success');
        $this->assertDatabaseHas('users', [
            'username' => 'ahmadj',
            'phone' => '0599123456',
        ]);

        // 056 valid phone
        $response2 = $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'محمد خليل',
            'username' => 'mohk',
            'email' => 'mohk@example.com',
            'phone' => '0568987654',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
            'role' => 'member',
            'workspace_mode' => 'existing',
            'workspace_id' => $this->workspace->id,
        ]);

        $response2->assertSessionHas('success');
        $this->assertDatabaseHas('users', [
            'username' => 'mohk',
            'phone' => '0568987654',
        ]);
    }

    public function test_admin_cannot_create_user_with_invalid_phone_prefix_or_length(): void
    {
        // Invalid prefix (058)
        $respPrefix = $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'مستخدم غير صالح',
            'username' => 'invphone1',
            'email' => 'inv1@example.com',
            'phone' => '0581234567',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
            'role' => 'member',
            'workspace_mode' => 'existing',
            'workspace_id' => $this->workspace->id,
        ]);

        $respPrefix->assertSessionHasErrors('phone');

        // Invalid length (9 digits)
        $respShort = $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'مستخدم غير صالح',
            'username' => 'invphone2',
            'email' => 'inv2@example.com',
            'phone' => '059123456',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
            'role' => 'member',
            'workspace_mode' => 'existing',
            'workspace_id' => $this->workspace->id,
        ]);

        $respShort->assertSessionHasErrors('phone');

        // Invalid length (11 digits)
        $respLong = $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'مستخدم غير صالح',
            'username' => 'invphone3',
            'email' => 'inv3@example.com',
            'phone' => '059123456789',
            'password' => 'SecurePass123',
            'password_confirmation' => 'SecurePass123',
            'role' => 'member',
            'workspace_mode' => 'existing',
            'workspace_id' => $this->workspace->id,
        ]);

        $respLong->assertSessionHasErrors('phone');
    }

    public function test_admin_can_update_user_phone(): void
    {
        $user = User::factory()->create([
            'username' => 'membertoedit',
            'role' => 'member',
            'workspace_id' => $this->workspace->id,
            'phone' => '0599111222',
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => '0567333444',
            'role' => 'member',
            'workspace_id' => $this->workspace->id,
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals('0567333444', $user->fresh()->phone);
    }
}
