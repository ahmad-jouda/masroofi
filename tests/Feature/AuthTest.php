<?php

namespace Tests\Feature;

use App\Models\Debt;
use App\Models\Envelope;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // The default migration creates the workspace and admin, but let's retrieve them cleanly
        $this->workspace = Workspace::firstOrCreate(
            ['name' => 'المحفظة الرئيسية (العائلة)'],
            ['currency' => 'ILS']
        );

        $this->adminUser = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'المدير (أدمن)',
                'email' => 'admin@masroofi.com',
                'phone' => '0599000000',
                'password' => Hash::make('admin1234'),
                'pin_code' => Hash::make('1234'),
                'pin_enabled' => true,
                'role' => 'admin',
                'workspace_id' => $this->workspace->id,
            ]
        );
    }

    public function test_user_can_login_with_username(): void
    {
        Auth::logout();

        $response = $this->post('/login', [
            'login_identifier' => 'admin',
            'password' => 'admin1234',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($this->adminUser);
        $response->assertCookie('masroofi_device_user_id', $this->adminUser->id);
    }

    public function test_user_can_login_with_email(): void
    {
        Auth::logout();

        $response = $this->post('/login', [
            'login_identifier' => 'admin@masroofi.com',
            'password' => 'admin1234',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($this->adminUser);
    }

    public function test_user_can_login_with_phone(): void
    {
        Auth::logout();

        $response = $this->post('/login', [
            'login_identifier' => '0599000000',
            'password' => 'admin1234',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($this->adminUser);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        Auth::logout();

        $response = $this->post('/login', [
            'login_identifier' => 'admin',
            'password' => 'wrongpassword',
        ]);

        $response->assertSessionHasErrors('login_identifier');
        $this->assertGuest();
    }

    public function test_user_without_pin_is_redirected_to_pin_setup_after_login(): void
    {
        Auth::logout();

        $userWithoutPin = User::create([
            'name' => 'مستخدم بدون بن',
            'username' => 'nopin',
            'email' => 'nopin@example.com',
            'password' => Hash::make('password123'),
            'role' => 'member',
            'workspace_id' => $this->workspace->id,
            'pin_enabled' => false,
            'pin_code' => null,
        ]);

        $response = $this->post('/login', [
            'login_identifier' => 'nopin',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/pin/setup');
        $this->assertAuthenticatedAs($userWithoutPin);
    }

    public function test_user_can_setup_4_digit_pin(): void
    {
        $user = User::create([
            'name' => 'مستخدم جديد',
            'username' => 'newuser',
            'email' => 'newuser@example.com',
            'password' => Hash::make('password123'),
            'role' => 'member',
            'workspace_id' => $this->workspace->id,
            'pin_enabled' => false,
        ]);

        $this->actingAs($user);

        $response = $this->post('/pin/setup', [
            'pin' => '7890',
            'pin_confirmation' => '7890',
        ]);

        $response->assertRedirect('/');
        $user->refresh();

        $this->assertTrue($user->pin_enabled);
        $this->assertTrue($user->verifyPin('7890'));
        $this->assertFalse($user->verifyPin('0000'));
    }

    public function test_user_can_login_with_pin_using_remembered_device(): void
    {
        Auth::logout();

        $response = $this->post('/pin/login', [
            'user_id' => $this->adminUser->id,
            'pin' => '1234',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($this->adminUser);
    }

    public function test_user_can_lock_and_unlock_session(): void
    {
        $this->actingAs($this->adminUser);

        // Lock session
        $lockResponse = $this->post('/pin/lock');
        $lockResponse->assertRedirect('/pin/unlock');

        // Requesting dashboard while locked should redirect to unlock screen
        $dashResponse = $this->withSession(['masroofi_locked' => true])->get('/');
        $dashResponse->assertRedirect('/pin/unlock');

        // Unlock with correct PIN
        $unlockResponse = $this->withSession(['masroofi_locked' => true])->post('/pin/unlock', [
            'pin' => '1234',
        ]);
        $unlockResponse->assertRedirect('/');
        $unlockResponse->assertSessionMissing('masroofi_locked');
    }

    public function test_admin_can_create_user_in_shared_family_workspace(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->post('/admin/users', [
            'name' => 'أميرة (الزوجة)',
            'username' => 'amira',
            'email' => 'amira@example.com',
            'phone' => '0599111222',
            'password' => 'Amira1234',
            'password_confirmation' => 'Amira1234',
            'role' => 'member',
            'workspace_mode' => 'existing',
            'workspace_id' => $this->workspace->id,
        ]);

        $response->assertRedirect('/admin/users');

        $this->assertDatabaseHas('users', [
            'username' => 'amira',
            'email' => 'amira@example.com',
            'workspace_id' => $this->workspace->id,
        ]);

        $createdUser = User::where('username', 'amira')->first();
        $this->assertEquals($this->workspace->id, $createdUser->workspace_id);
    }

    public function test_admin_can_create_user_in_new_clean_workspace(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->post('/admin/users', [
            'name' => 'أحمد (الأخ)',
            'username' => 'ahmed',
            'email' => 'ahmed@example.com',
            'phone' => '0599333444',
            'password' => 'Ahmed1234',
            'password_confirmation' => 'Ahmed1234',
            'role' => 'member',
            'workspace_mode' => 'new',
            'new_workspace_name' => 'محفظة أحمد الشخصية',
        ]);

        $response->assertRedirect('/admin/users');

        $this->assertDatabaseHas('workspaces', [
            'name' => 'محفظة أحمد الشخصية',
        ]);

        $newWorkspace = Workspace::where('name', 'محفظة أحمد الشخصية')->first();
        $this->assertNotNull($newWorkspace);

        $this->assertDatabaseHas('users', [
            'username' => 'ahmed',
            'workspace_id' => $newWorkspace->id,
        ]);

        // Clean starter envelopes should exist in new workspace
        $this->assertDatabaseHas('envelopes', [
            'workspace_id' => $newWorkspace->id,
            'name' => 'بقالة وتموين',
        ]);
    }

    public function test_workspace_data_is_completely_isolated(): void
    {
        // 1. Admin creates a transaction in Workspace 1
        $envelope1 = Envelope::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'مصاريف العائلة',
            'pillar' => 'needs',
            'budget' => 500,
        ]);

        $this->actingAs($this->adminUser);
        $this->post('/transactions', [
            'type' => 'expense',
            'amount' => 150.00,
            'envelope_id' => $envelope1->id,
            'date' => now()->toDateString(),
            'payment_method' => 'cash',
            'note' => 'فاتورة كهرباء عائلية',
        ]);

        $this->post('/debts', [
            'creditor_name' => 'سوبرماركت المدينة',
            'total_debt' => 300.00,
            'phone' => '0599000111',
            'repayment_type' => 'harvest',
        ]);

        // 2. Create another user in a separate workspace
        $workspace2 = Workspace::create([
            'name' => 'محفظة مستقلة تماماً',
            'currency' => 'ILS',
        ]);

        $isolatedUser = User::create([
            'name' => 'خالد',
            'username' => 'khaled',
            'email' => 'khaled@example.com',
            'password' => Hash::make('khaled1234'),
            'role' => 'member',
            'workspace_id' => $workspace2->id,
            'pin_enabled' => true,
            'pin_code' => Hash::make('9999'),
        ]);

        // 3. Authenticate as the isolated user
        $this->actingAs($isolatedUser);

        // When isolatedUser queries transactions and debts, they should see ZERO records from workspace 1
        $this->assertEquals(0, Transaction::count());
        $this->assertEquals(0, Debt::count());

        // Visiting dashboard does not display workspace 1 data
        $dashResponse = $this->get('/');
        $dashResponse->assertStatus(200);
        $dashResponse->assertDontSee('فاتورة كهرباء عائلية');
        $dashResponse->assertDontSee('سوبرماركت المدينة');
    }

    public function test_members_of_same_workspace_share_data_with_creator_attribution(): void
    {
        // Create Wife in the same workspace
        $wife = User::create([
            'name' => 'سارة (الزوجة)',
            'username' => 'sara',
            'email' => 'sara@example.com',
            'password' => Hash::make('sara1234'),
            'role' => 'member',
            'workspace_id' => $this->workspace->id,
            'pin_enabled' => true,
            'pin_code' => Hash::make('4321'),
        ]);

        $envelope = Envelope::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'طلبات البيت',
            'pillar' => 'needs',
            'budget' => 600,
        ]);

        // Wife adds an expense
        $this->actingAs($wife);
        $this->post('/transactions', [
            'type' => 'expense',
            'amount' => 75.00,
            'envelope_id' => $envelope->id,
            'date' => now()->toDateString(),
            'payment_method' => 'cash',
            'note' => 'خضار وفواكه طازجة',
        ]);

        // Verify transaction is attributed to Wife
        $transaction = Transaction::where('note', 'خضار وفواكه طازجة')->first();
        $this->assertNotNull($transaction);
        $this->assertEquals($wife->id, $transaction->user_id);
        $this->assertEquals($this->workspace->id, $transaction->workspace_id);

        // Switch to Admin (Husband)
        $this->actingAs($this->adminUser);

        // Admin should see the transaction and Wife's attribution
        $dashResponse = $this->get('/');
        $dashResponse->assertStatus(200);
        $dashResponse->assertSee('خضار وفواكه طازجة');
        $dashResponse->assertSee('سارة (الزوجة)');
    }

    public function test_admin_can_view_user_management_screen(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->get('/admin/users');
        $response->assertStatus(200);
        $response->assertSee('إدارة المستخدمين والمحافظ المشتركة');
        $response->assertSee('المدير (أدمن)');
    }

    public function test_non_admin_cannot_access_user_management(): void
    {
        $member = User::create([
            'name' => 'عضو عادي',
            'username' => 'memberuser',
            'email' => 'member@example.com',
            'password' => Hash::make('password123'),
            'role' => 'member',
            'workspace_id' => $this->workspace->id,
            'pin_enabled' => true,
            'pin_code' => Hash::make('1111'),
        ]);

        $this->actingAs($member);

        $response = $this->get('/admin/users');
        $response->assertStatus(403);

        $postResponse = $this->post('/admin/users', [
            'name' => 'مستخدم غير مصرح به',
            'username' => 'unauthorized',
            'email' => 'unauth@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'role' => 'member',
            'workspace_mode' => 'existing',
        ]);
        $postResponse->assertStatus(403);
    }

    public function test_password_reset_flow(): void
    {
        Auth::logout();

        // 1. Request reset link
        $response = $this->post('/forgot-password', [
            'email' => 'admin@masroofi.com',
        ]);
        $response->assertSessionHas('status');

        // 2. Generate valid token
        $token = Password::broker()->createToken($this->adminUser);

        // 3. Visit reset page
        $resetFormResponse = $this->get("/reset-password/{$token}?email=admin@masroofi.com");
        $resetFormResponse->assertStatus(200);
        $resetFormResponse->assertSee('تعيين كلمة مرور جديدة');

        // 4. Submit new password
        $resetResponse = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'admin@masroofi.com',
            'password' => 'NewAdminPassword2026',
            'password_confirmation' => 'NewAdminPassword2026',
        ]);
        $resetResponse->assertRedirect('/login');

        // 5. Test login with new password
        $this->adminUser->refresh();
        $this->assertTrue(Hash::check('NewAdminPassword2026', $this->adminUser->password));
    }

    public function test_user_can_change_pin_using_current_pin(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->post('/pin/change', [
            'auth_method' => 'current_pin',
            'current_pin' => '1234',
            'new_pin' => '5678',
            'new_pin_confirmation' => '5678',
        ]);

        $response->assertRedirect();
        $this->adminUser->refresh();
        $this->assertTrue($this->adminUser->verifyPin('5678'));
        $this->assertFalse($this->adminUser->verifyPin('1234'));
    }

    public function test_user_can_change_pin_using_account_password(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->post('/pin/change', [
            'auth_method' => 'password',
            'current_password' => 'admin1234',
            'new_pin' => '9988',
            'new_pin_confirmation' => '9988',
        ]);

        $response->assertRedirect();
        $this->adminUser->refresh();
        $this->assertTrue($this->adminUser->verifyPin('9988'));
    }

    public function test_change_pin_fails_with_wrong_current_pin_or_mismatched_confirmation(): void
    {
        $this->actingAs($this->adminUser);

        // Wrong current PIN
        $wrongPinResponse = $this->post('/pin/change', [
            'auth_method' => 'current_pin',
            'current_pin' => '0000',
            'new_pin' => '5555',
            'new_pin_confirmation' => '5555',
        ]);
        $wrongPinResponse->assertSessionHasErrors('current_pin');

        // Mismatched confirmation
        $mismatchResponse = $this->post('/pin/change', [
            'auth_method' => 'current_pin',
            'current_pin' => '1234',
            'new_pin' => '5555',
            'new_pin_confirmation' => '6666',
        ]);
        $mismatchResponse->assertSessionHasErrors('new_pin');
    }

    public function test_password_complexity_rules_enforced_in_admin_user_management(): void
    {
        $this->actingAs($this->adminUser);

        // 1. Creation fails when < 9 chars
        $r1 = $this->post('/admin/users', [
            'name' => 'تجربة طول كلمة المرور',
            'username' => 'testlen',
            'email' => 'testlen@example.com',
            'password' => 'Pass1234', // 8 chars
            'password_confirmation' => 'Pass1234',
            'role' => 'member',
            'workspace_mode' => 'existing',
            'workspace_id' => $this->workspace->id,
        ]);
        $r1->assertSessionHasErrors('password');

        // 2. Creation fails when missing uppercase
        $r2 = $this->post('/admin/users', [
            'name' => 'تجربة أحرف كبيرة',
            'username' => 'testupper',
            'email' => 'testupper@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'member',
            'workspace_mode' => 'existing',
            'workspace_id' => $this->workspace->id,
        ]);
        $r2->assertSessionHasErrors('password');

        // 3. Creation fails when missing lowercase
        $r3 = $this->post('/admin/users', [
            'name' => 'تجربة أحرف صغيرة',
            'username' => 'testlower',
            'email' => 'testlower@example.com',
            'password' => 'PASSWORD123',
            'password_confirmation' => 'PASSWORD123',
            'role' => 'member',
            'workspace_mode' => 'existing',
            'workspace_id' => $this->workspace->id,
        ]);
        $r3->assertSessionHasErrors('password');

        // 4. Creation fails when missing numbers
        $r4 = $this->post('/admin/users', [
            'name' => 'تجربة أرقام',
            'username' => 'testnum',
            'email' => 'testnum@example.com',
            'password' => 'PasswordOnly',
            'password_confirmation' => 'PasswordOnly',
            'role' => 'member',
            'workspace_mode' => 'existing',
            'workspace_id' => $this->workspace->id,
        ]);
        $r4->assertSessionHasErrors('password');

        // 5. Update fails when weak password provided
        $targetUser = User::factory()->create(['workspace_id' => $this->workspace->id]);
        $r5 = $this->put("/admin/users/{$targetUser->id}", [
            'name' => $targetUser->name,
            'username' => $targetUser->username,
            'email' => $targetUser->email,
            'role' => 'member',
            'workspace_id' => $this->workspace->id,
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]);
        $r5->assertSessionHasErrors('password');
    }

    public function test_password_complexity_rules_enforced_in_password_reset(): void
    {
        Auth::logout();

        $token = Password::broker()->createToken($this->adminUser);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'admin@masroofi.com',
            'password' => 'weakpass1', // No uppercase, 9 chars
            'password_confirmation' => 'weakpass1',
        ]);

        $response->assertSessionHasErrors('password');
    }
}
