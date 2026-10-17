<?php

namespace Tests\Feature;

use App\Models\Debt;
use App\Models\Envelope;
use App\Models\SavingGoal;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSetting;
use App\Models\Workspace;
use App\Services\GamificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamificationAndExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'محفظة الاختبار']);
        $this->user = User::factory()->create([
            'workspace_id' => $this->workspace->id,
            'role' => 'admin',
        ]);
        $this->actingAs($this->user);

        UserSetting::create([
            'workspace_id' => $this->workspace->id,
            'salary' => 6000,
            'currency' => 'ILS',
            'salary_day' => 1,
        ]);
    }

    public function test_can_export_transactions_ledger_to_excel_csv_with_utf8_bom(): void
    {
        $envelope = Envelope::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'بقالة وتموينات',
            'pillar' => 'needs',
            'budget' => 800,
        ]);

        Transaction::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'type' => 'expense',
            'amount' => 125.50,
            'envelope_id' => $envelope->id,
            'date' => now()->toDateString(),
            'payment_method' => 'transfer',
            'note' => 'مشتريات خضار وزيوت',
        ]);

        Transaction::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'type' => 'income',
            'amount' => 500.00,
            'date' => now()->toDateString(),
            'payment_method' => 'cash',
            'note' => 'مكافأة عمل إضافي',
        ]);

        $response = $this->get('/ledger/export/excel?period=all');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="masroofi_transactions_', $response->headers->get('Content-Disposition'));

        // Stream the CSV content
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        // Verify UTF-8 BOM is present
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        // Verify Arabic headers and content
        $this->assertStringContainsString('مصروفي برو - كشف سجل المعاملات المالية', $content);
        $this->assertStringContainsString('المعرف', $content);
        $this->assertStringContainsString('بقالة وتموينات', $content);
        $this->assertStringContainsString('مشتريات خضار وزيوت', $content);
        $this->assertStringContainsString('125.50', $content);
        $this->assertStringContainsString('500.00', $content);
        $this->assertStringContainsString('إجمالي المصروفات', $content);
        $this->assertStringContainsString('إجمالي الدخل الإضافي', $content);
    }

    public function test_export_excel_respects_type_and_period_filters(): void
    {
        $envelope = Envelope::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'مطاعم',
            'pillar' => 'wants',
            'budget' => 300,
        ]);

        Transaction::create([
            'workspace_id' => $this->workspace->id,
            'type' => 'expense',
            'amount' => 70.00,
            'envelope_id' => $envelope->id,
            'date' => now()->toDateString(),
            'payment_method' => 'card',
            'note' => 'عشاء مع العائلة',
        ]);

        Transaction::create([
            'workspace_id' => $this->workspace->id,
            'type' => 'income',
            'amount' => 200.00,
            'date' => now()->toDateString(),
            'payment_method' => 'transfer',
            'note' => 'حوالة دخل',
        ]);

        // Filter only expense
        $response = $this->get('/ledger/export/excel?type=expense&period=all');
        $response->assertStatus(200);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('عشاء مع العائلة', $content);
        $this->assertStringNotContainsString('حوالة دخل', $content);
    }

    public function test_gamification_streak_counts_consecutive_days_within_daily_limit(): void
    {
        Carbon::setTestNow('2026-10-17 12:00:00');

        $dailyAllowance = 100.0;

        // Today: spent 40 <= 100 (Safe -> Day 1)
        Transaction::create([
            'workspace_id' => $this->workspace->id,
            'type' => 'expense',
            'amount' => 40.0,
            'date' => '2026-10-17',
            'payment_method' => 'cash',
        ]);

        // Yesterday (10-16): spent 60 <= 100 (Safe -> Day 2)
        Transaction::create([
            'workspace_id' => $this->workspace->id,
            'type' => 'expense',
            'amount' => 60.0,
            'date' => '2026-10-16',
            'payment_method' => 'cash',
        ]);

        // Day before (10-15): spent 80 <= 100 (Safe -> Day 3)
        Transaction::create([
            'workspace_id' => $this->workspace->id,
            'type' => 'expense',
            'amount' => 80.0,
            'date' => '2026-10-15',
            'payment_method' => 'cash',
        ]);

        // Day before (10-14): spent 150 > 100 (Overspent -> Streak breaks here!)
        Transaction::create([
            'workspace_id' => $this->workspace->id,
            'type' => 'expense',
            'amount' => 150.0,
            'date' => '2026-10-14',
            'payment_method' => 'cash',
        ]);

        $streak = GamificationService::calculateStreak($this->workspace->id, $dailyAllowance, $dailyAllowance, 40.0);

        $this->assertEquals(3, $streak['current_streak']);
        $this->assertTrue($streak['is_today_safe']);
        $this->assertStringContainsString('3 أيام متتالية دون تجاوز اليومية المسموحة', $streak['streak_title']);
    }

    public function test_gamification_streak_resets_if_today_exceeded_daily_allowance(): void
    {
        Carbon::setTestNow('2026-10-17 12:00:00');

        $dailyAllowance = 50.0;

        // Today: spent 90 > 50 (Overspent)
        $streak = GamificationService::calculateStreak($this->workspace->id, $dailyAllowance, $dailyAllowance, 90.0);

        $this->assertEquals(0, $streak['current_streak']);
        $this->assertFalse($streak['is_today_safe']);
        $this->assertStringContainsString('ابدأ سلسلتك اليوم', $streak['streak_title']);
    }

    public function test_achievements_detect_saving_goal_near_and_completed_milestones(): void
    {
        // 1. Goal near completion (85%)
        $nearGoal = SavingGoal::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'لابتوب جديد',
            'target_amount' => 1000,
            'current_amount' => 850, // 85%
            'currency' => 'ILS',
        ]);

        // 2. Goal fully completed (100%)
        $completedGoal = SavingGoal::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'سداد الرسوم',
            'target_amount' => 500,
            'current_amount' => 500, // 100%
            'currency' => 'ILS',
        ]);

        $achievements = GamificationService::getAchievements($this->workspace->id, 4);

        $this->assertTrue($achievements['has_celebration']);

        $medalTitles = collect($achievements['medals'])->pluck('title')->all();
        $this->assertContains('قناص هدف: لابتوب جديد', $medalTitles);
        $this->assertContains('مُحقق هدف: سداد الرسوم', $medalTitles);

        // Streak tier 3 days should also be unlocked since current streak is 4
        $this->assertContains('وسام بداية الانضباط', $medalTitles);
    }

    public function test_achievements_detect_cleared_debt_milestone(): void
    {
        $settledDebt = Debt::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'سوبرماركت البركة',
            'type' => 'supermarket',
            'total_debt' => 0,
            'status' => 'settled',
        ]);

        $achievements = GamificationService::getAchievements($this->workspace->id, 0);

        $medalTitles = collect($achievements['medals'])->pluck('title')->all();
        $this->assertContains('متحرر من دين: سوبرماركت البركة', $medalTitles);

        $celebrations = collect($achievements['celebrations'])->pluck('title')->all();
        $this->assertContains('حرية مالية! تم تصفية دين: سوبرماركت البركة', $celebrations);
    }

    public function test_dashboard_displays_streak_and_excel_export_button(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);

        // Verify Streak Badge is present in hero section
        $response->assertSee('streak-gamification-pill');
        $response->assertSee('دون تجاوز اليومية المسموحة');
        $response->assertSee('achievementsModal');

        // Verify Excel Export button is present in transactions tab
        $response->assertSee('/ledger/export/excel');
        $response->assertSee('تصدير إكسل (Excel)');
    }
}
