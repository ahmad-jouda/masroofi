<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WorkspaceMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_add_spouse_or_family_member_to_their_workspace(): void
    {
        $workspace = Workspace::create([
            'name' => 'محفظة خالد المستقلة',
            'currency' => 'ILS',
        ]);

        $brother = User::factory()->create([
            'name' => 'خالد',
            'username' => 'khaled',
            'email' => 'khaled@example.com',
            'role' => 'member', // Regular user, not super admin
            'workspace_id' => $workspace->id,
        ]);

        $response = $this->actingAs($brother)->post(route('workspace.members.store'), [
            'name' => 'سارة (الزوجة)',
            'username' => 'sara',
            'email' => 'sara@example.com',
            'phone' => '0599112233',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'name' => 'سارة (الزوجة)',
            'username' => 'sara',
            'email' => 'sara@example.com',
            'workspace_id' => $workspace->id,
            'role' => 'member',
        ]);

        $wife = User::where('username', 'sara')->first();
        $this->assertTrue(Hash::check('Secret123', $wife->password));
        $this->assertEquals($workspace->id, $wife->workspace_id);
    }

    public function test_user_cannot_remove_themselves_from_workspace(): void
    {
        $workspace = Workspace::create(['name' => 'محفظة تجريبية']);
        $user = User::factory()->create(['workspace_id' => $workspace->id]);

        $response = $this->actingAs($user)->delete(route('workspace.members.destroy', $user));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_user_cannot_remove_member_from_another_workspace(): void
    {
        $ws1 = Workspace::create(['name' => 'محفظة 1']);
        $ws2 = Workspace::create(['name' => 'محفظة 2']);

        $user1 = User::factory()->create(['workspace_id' => $ws1->id]);
        $user2 = User::factory()->create(['workspace_id' => $ws2->id]);

        $response = $this->actingAs($user1)->delete(route('workspace.members.destroy', $user2));

        $response->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $user2->id]);
    }

    public function test_user_can_reset_pin_of_a_family_member(): void
    {
        $workspace = Workspace::create(['name' => 'محفظة عائلية']);
        $brother = User::factory()->create(['workspace_id' => $workspace->id]);
        $wife = User::factory()->create([
            'workspace_id' => $workspace->id,
            'pin_code' => Hash::make('1234'),
            'pin_enabled' => true,
        ]);

        $response = $this->actingAs($brother)->post(route('workspace.members.resetPin', $wife));

        $response->assertSessionHas('success');
        $this->assertFalse($wife->fresh()->hasPin());
    }

    public function test_workspace_member_password_must_meet_complexity_requirements(): void
    {
        $workspace = Workspace::create(['name' => 'محفظة الاختبار']);
        $brother = User::factory()->create(['workspace_id' => $workspace->id]);

        // 1. Fails when less than 9 characters (e.g. 8 chars)
        $resp1 = $this->actingAs($brother)->post(route('workspace.members.store'), [
            'name' => 'مستخدم جديد',
            'username' => 'newuser1',
            'email' => 'newuser1@example.com',
            'password' => 'Pass1234',
            'password_confirmation' => 'Pass1234',
        ]);
        $resp1->assertSessionHasErrors('password');

        // 2. Fails when missing uppercase
        $resp2 = $this->actingAs($brother)->post(route('workspace.members.store'), [
            'name' => 'مستخدم جديد',
            'username' => 'newuser2',
            'email' => 'newuser2@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $resp2->assertSessionHasErrors('password');

        // 3. Fails when missing lowercase
        $resp3 = $this->actingAs($brother)->post(route('workspace.members.store'), [
            'name' => 'مستخدم جديد',
            'username' => 'newuser3',
            'email' => 'newuser3@example.com',
            'password' => 'PASSWORD123',
            'password_confirmation' => 'PASSWORD123',
        ]);
        $resp3->assertSessionHasErrors('password');

        // 4. Fails when missing numbers
        $resp4 = $this->actingAs($brother)->post(route('workspace.members.store'), [
            'name' => 'مستخدم جديد',
            'username' => 'newuser4',
            'email' => 'newuser4@example.com',
            'password' => 'PasswordOnly',
            'password_confirmation' => 'PasswordOnly',
        ]);
        $resp4->assertSessionHasErrors('password');
    }
}
