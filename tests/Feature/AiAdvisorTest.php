<?php

namespace Tests\Feature;

use App\Models\AiChatMessage;
use App\Models\User;
use App\Models\UserSetting;
use App\Models\Workspace;
use App\Services\FinancialAdvisorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAdvisorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'محفظة المستشار الذكي']);
        $this->user = User::factory()->create([
            'workspace_id' => $this->workspace->id,
            'role' => 'admin',
        ]);
        $this->actingAs($this->user);

        UserSetting::create([
            'workspace_id' => $this->workspace->id,
            'salary' => 5000,
            'currency' => 'ILS',
            'salary_day' => 1,
            'savings_target_percent' => 20,
        ]);
    }

    public function test_get_messages_returns_snapshot_and_history(): void
    {
        AiChatMessage::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'role' => 'user',
            'message' => 'مرحبا كيف وضعي المالي؟',
        ]);

        AiChatMessage::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => null,
            'role' => 'assistant',
            'message' => 'وضعك ممتاز!',
            'verdict' => 'safe',
        ]);

        $response = $this->getJson(route('ai-advisor.messages'));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'messages',
                'snapshot' => [
                    'currency_symbol',
                    'app_spendable_balance',
                    'safe_daily',
                    'days_remaining',
                ],
            ]);

        $this->assertCount(2, $response->json('messages'));
    }

    public function test_user_can_ask_purchase_feasibility_question(): void
    {
        Carbon::setTestNow('2026-10-17 12:00:00');

        $response = $this->postJson(route('ai-advisor.chat'), [
            'message' => 'هل ميزانيتي تسمح بشراء شاشة بـ 800 شيكل هذا الأسبوع؟',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'user_message',
                'assistant_message' => [
                    'id',
                    'role',
                    'message',
                    'verdict',
                    'context_data',
                ],
                'snapshot',
            ]);

        $assistantMsg = $response->json('assistant_message');
        $this->assertEquals('assistant', $assistantMsg['role']);
        $this->assertNotNull($assistantMsg['verdict']);
        $this->assertStringContainsString('شاشة', $assistantMsg['message']);
        $this->assertStringContainsString('800', $assistantMsg['message']);

        // Check context data
        $this->assertEquals('purchase_verdict', $assistantMsg['context_data']['type']);
        $this->assertEquals(800, $assistantMsg['context_data']['amount']);
        $this->assertEquals('شاشة', $assistantMsg['context_data']['item']);

        // Verify stored in database
        $this->assertDatabaseHas('ai_chat_messages', [
            'workspace_id' => $this->workspace->id,
            'role' => 'user',
            'message' => 'هل ميزانيتي تسمح بشراء شاشة بـ 800 شيكل هذا الأسبوع؟',
        ]);
        $this->assertDatabaseHas('ai_chat_messages', [
            'workspace_id' => $this->workspace->id,
            'role' => 'assistant',
        ]);
    }

    public function test_user_can_ask_financial_status_question(): void
    {
        $response = $this->postJson(route('ai-advisor.chat'), [
            'message' => 'كيف وضعي المالي وهل وتيرة صرفي آمنة؟',
        ]);

        $response->assertOk();
        $assistantMsg = $response->json('assistant_message');
        $this->assertNotNull($assistantMsg['verdict']);
        $this->assertStringContainsString('الرصيد المتاح', $assistantMsg['message']);
    }

    public function test_user_can_clear_chat_history(): void
    {
        AiChatMessage::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'role' => 'user',
            'message' => 'سؤال للاختبار',
        ]);

        $this->assertDatabaseCount('ai_chat_messages', 1);

        $response = $this->postJson(route('ai-advisor.clear'));
        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseCount('ai_chat_messages', 0);
    }

    public function test_purchase_decision_warns_on_high_price(): void
    {
        Carbon::setTestNow('2026-10-17 12:00:00');

        $result = FinancialAdvisorService::ask(
            $this->workspace->id,
            $this->user->id,
            'بدي اشتري تلفون بـ 25000 شيكل بقدر؟'
        );

        $this->assertEquals('danger', $result['verdict']);
        $this->assertStringContainsString('لا أنصحك إطلاقاً', $result['message']);
        $this->assertEquals(25000, $result['context_data']['amount']);
    }
}
