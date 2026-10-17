<?php

namespace Tests\Feature;

use App\Models\Envelope;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserSetting;
use App\Models\Workspace;
use App\Services\FinancialForecastService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialForecastAndMirrorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'محفظة الاختبار التنبؤي']);
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

    public function test_cashflow_runway_detects_early_depletion_risk_and_recommends_daily(): void
    {
        Carbon::setTestNow('2026-10-17 12:00:00');

        $appSpendableBalance = 300.0;
        $cycleExpenses = 700.0;
        $daysElapsed = 10; // Burn rate = 70 ₪/day
        $daysRemaining = 12; // Runway = 300 / 70 = 4 days -> 8 days gap!
        $cycleEnd = Carbon::parse('2026-10-29');
        $safeDaily = 25.0; // 300 / 12 = 25 ₪/day

        $runway = FinancialForecastService::calculateCashflowRunway(
            $this->workspace->id,
            $appSpendableBalance,
            $cycleExpenses,
            $daysElapsed,
            $daysRemaining,
            $cycleEnd,
            $safeDaily
        );

        $this->assertEquals(70.0, $runway['burn_rate']);
        $this->assertEquals(4, $runway['runway_days']);
        $this->assertEquals(8, $runway['gap_days']);
        $this->assertEquals('danger', $runway['status']);
        $this->assertStringContainsString('قد ينفذ رصيد التطبيق قبل نهاية الدورة بـ 8 أيام', $runway['message']);
        $this->assertStringContainsString('الموصى به خفض اليومية لـ 25 ₪', $runway['message']);
    }

    public function test_cashflow_runway_detects_safe_pacing_and_calculates_projected_surplus(): void
    {
        Carbon::setTestNow('2026-10-17 12:00:00');

        $appSpendableBalance = 800.0;
        $cycleExpenses = 400.0;
        $daysElapsed = 10; // Burn rate = 40 ₪/day
        $daysRemaining = 10; // Runway = 800 / 40 = 20 days >= 10 days remaining!
        $cycleEnd = Carbon::parse('2026-10-27');
        $safeDaily = 80.0;

        $runway = FinancialForecastService::calculateCashflowRunway(
            $this->workspace->id,
            $appSpendableBalance,
            $cycleExpenses,
            $daysElapsed,
            $daysRemaining,
            $cycleEnd,
            $safeDaily
        );

        $this->assertEquals(40.0, $runway['burn_rate']);
        $this->assertEquals(20, $runway['runway_days']);
        $this->assertEquals('safe', $runway['status']);
        // Projected surplus = 800 - (40 * 10) = 400 ₪
        $this->assertStringContainsString('وتيرة صرفك متزنة ومطمئنة جداً', $runway['title']);
        $this->assertStringContainsString('ستصل لنهاية الدورة بأمان', $runway['message']);
    }

    public function test_monthly_mirror_report_computes_top_envelope_and_comparisons(): void
    {
        $cycleStart = Carbon::parse('2026-10-01')->startOfDay();
        $cycleEnd = Carbon::parse('2026-10-31')->endOfDay();

        $foodEnv = Envelope::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'مطاعم وكافيهات',
            'pillar' => 'wants',
            'budget' => 600,
        ]);

        $groceriesEnv = Envelope::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'بقالة وتموينات',
            'pillar' => 'needs',
            'budget' => 1200,
        ]);

        // Current cycle expenses: Groceries 500, Food 200 -> Total 700
        Transaction::create([
            'workspace_id' => $this->workspace->id,
            'envelope_id' => $groceriesEnv->id,
            'type' => 'expense',
            'amount' => 500.0,
            'date' => '2026-10-05',
            'payment_method' => 'card',
            'note' => 'مشتريات الشهر الكبرى',
        ]);

        Transaction::create([
            'workspace_id' => $this->workspace->id,
            'envelope_id' => $foodEnv->id,
            'type' => 'expense',
            'amount' => 200.0,
            'date' => '2026-10-10',
            'payment_method' => 'transfer',
            'note' => 'عشاء',
        ]);

        // Previous cycle expense: 1000 -> Diff = 700 - 1000 = -300 (-30%)
        Transaction::create([
            'workspace_id' => $this->workspace->id,
            'envelope_id' => $groceriesEnv->id,
            'type' => 'expense',
            'amount' => 1000.0,
            'date' => '2026-09-15',
            'payment_method' => 'card',
        ]);

        $report = FinancialForecastService::generateMonthlyMirrorReport(
            $this->workspace->id,
            $cycleStart,
            $cycleEnd
        );

        $this->assertEquals(700.0, $report['curr_expenses']);
        $this->assertEquals(1000.0, $report['prev_expenses']);
        $this->assertTrue($report['is_spending_lower']);
        $this->assertEquals(-30.0, $report['diff_percent']);

        // Top envelope should be groceriesEnv
        $this->assertNotNull($report['top_envelope']);
        $this->assertEquals('بقالة وتموينات', $report['top_envelope']->name);
        $this->assertEquals(500.0, $report['top_envelope_spent']);
        $this->assertEquals(71, $report['top_envelope_percent']); // 500/700 = 71%

        // Highest day: 2026-10-05 with 500 ₪
        $this->assertNotNull($report['highest_day']);
        $this->assertEquals('2026-10-05', $report['highest_day']['date']);
        $this->assertEquals(500.0, $report['highest_day']['amount']);
        $this->assertStringContainsString('مشتريات الشهر الكبرى', $report['highest_day']['note']);
    }

    public function test_dashboard_renders_runway_radar_and_financial_mirror(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);

        // Verify Runway Radar strip and modal
        $response->assertSee('runway-radar-strip');
        $response->assertSee('رادار التنبؤ');
        $response->assertSee('runwayRadarModal');

        // Verify Financial Mirror button and modal
        $response->assertSee('المرآة المالية الشهرية');
        $response->assertSee('financialMirrorModal');
        $response->assertSee('تقرير المرآة المالية الشهرية');
    }
}
