@extends('layouts.app')

@section('content')

<!-- ============================================== -->
<!-- TAB 1: DASHBOARD & SAFE-TO-SPEND -->
<!-- ============================================== -->
<section class="tab-pane {{ ($activeTab ?? 'tab-dashboard') === 'tab-dashboard' ? 'active' : '' }}" id="tab-dashboard">
    
    <!-- CYCLE NAVIGATOR & MONTH BAR -->
    <div class="cycle-banner-bar">
        <div class="cycle-banner-info">
            <div class="cycle-banner-title-group">
                <span class="cycle-icon">🗓️</span>
                <div>
                    <h3 class="cycle-banner-name">دورة {{ $monthLabel }}</h3>
                    <span class="cycle-banner-dates">من {{ $cycleStart->format('d/m/Y') }} إلى {{ (clone $cycleEnd)->subDay()->format('d/m/Y') }}</span>
                </div>
            </div>

            <div class="cycle-badge-group">
                @if($isCurrentCycle)
                    <span class="cycle-status-pill pill-active">
                        <span class="pulse-dot-green"></span>
                        @if(($settings->cycle_mode ?? 'first_salary') === 'first_salary' && $firstSalaryDay)
                            الدورة الحالية (تبدأ مع أول راتب: يوم {{ $firstSalaryDay }} ⚡)
                        @else
                            الدورة الحالية (تتجدد تلقائياً ⚡)
                        @endif
                    </span>
                    @if($surplusCarriedOver > 0)
                        <span class="cycle-status-pill pill-surplus">
                            💰 فائض مرحّل: +{{ number_format($surplusCarriedOver, 0) }} {{ $settings->currency ?? 'ILS' }}
                        </span>
                    @endif
                @elseif($cycleOffset < 0)
                    <span class="cycle-status-pill pill-archived">
                        🗂️ تقرير دورة مؤرشفة
                    </span>
                @else
                    <span class="cycle-status-pill pill-future">
                        ⏳ دورة مستقبلية متوقعة
                    </span>
                @endif
            </div>
        </div>

        <div class="cycle-banner-actions">
            <a href="{{ route('dashboard', ['cycle_offset' => $cycleOffset - 1]) }}" class="btn-cycle-nav" title="استعراض الدورة السابقة">
                <span>▶</span>
                <span>الشهر السابق</span>
            </a>
            @if(!$isCurrentCycle)
                <a href="{{ route('dashboard') }}" class="btn-cycle-nav btn-cycle-today" title="الرجوع للدورة النشطة">
                    ↩ اليوم (الدورة الحالية)
                </a>
            @endif
            <a href="{{ route('dashboard', ['cycle_offset' => $cycleOffset + 1]) }}" class="btn-cycle-nav" title="استعراض الدورة التالية">
                <span>الشهر التالي</span>
                <span>◀</span>
            </a>
            <button type="button" class="btn-cycle-gear" onclick="openModal('salarySettingsModal'); switchSalarySubTab('cycle');" title="إعدادات الدورة والتجديد">
                ⚙️
            </button>
        </div>
    </div>

    <!-- GOLDEN SAFE-TO-SPEND CARD -->
    <div class="golden-safe-card">
        <div class="safe-card-header">
            <div class="safe-card-badge">
                <span class="pulse-dot"></span>
                <span>المؤشر المالي اليومي الأذكى</span>
            </div>
            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                <button type="button" class="btn-text-action" onclick="openModal('familyWorkspaceModal')" title="إدارة ومشاركة المحفظة مع الزوجة أو أفراد الأسرة" style="background: rgba(59, 130, 246, 0.12); border-color: rgba(59, 130, 246, 0.3); color: #60a5fa;">
                    👨‍👩‍👧‍👦 أفراد المحفظة ({{ auth()->user()->workspace?->users()->count() ?? 1 }})
                </button>
                <button class="btn-text-action" onclick="openModal('salarySettingsModal')">
                    ⚙️ ضبط الراتب والشهر
                </button>
            </div>
        </div>

        <div class="safe-card-body">
            <div class="safe-number-col">
                <div class="daily-hero-badge-wrap">
                    <span class="safe-label">متبقي لك لتصرفه اليوم بأمان:</span>
                    <span class="today-date-pill">📅 اليوم {{ date('d/m') }}</span>
                </div>
                <div class="safe-value-display">
                    <span class="safe-amount {{ $todayRemaining <= 0 ? 'safe-amount-depleted' : '' }}" 
                          id="safeDailyAmount" 
                          data-base-val="{{ (float)$todayRemaining }}"
                          data-base-budget="{{ (float)$todayBaseAllowance }}"
                          data-base-spent="{{ (float)$todayExpenses }}">
                        {{ number_format($todayRemaining, 0) }}
                    </span>
                    <span class="safe-curr">{{ $settings->currency ?? 'ILS' }}</span>
                </div>

                <!-- Today's Trio Financial Metric Strip -->
                <div class="today-trio-strip">
                    <div class="trio-item">
                        <span class="trio-title">مخصص اليوم</span>
                        <span class="trio-value" id="todayBaseBudgetDisplay">{{ number_format($todayBaseAllowance, 0) }} {{ $settings->currency ?? 'ILS' }}</span>
                    </div>
                    <div class="trio-divider">-</div>
                    <div class="trio-item">
                        <span class="trio-title">صرفته اليوم</span>
                        <span class="trio-value text-danger" id="todaySpentDisplay">{{ number_format($todayExpenses, 0) }} {{ $settings->currency ?? 'ILS' }}</span>
                    </div>
                    <div class="trio-divider">=</div>
                    <div class="trio-item">
                        <span class="trio-title">المتبقي الآن</span>
                        <span class="trio-value text-success" id="todayRemainingSummaryDisplay">{{ number_format($todayRemaining, 0) }} {{ $settings->currency ?? 'ILS' }}</span>
                    </div>
                </div>

                @php
                    $unpaidDebts = $debts->where('status', 'active')->filter(fn ($d) => (float)$d->total_debt > 0);
                    $hasUnpaidDebts = $unpaidDebts->count() > 0;
                    $incompleteGoals = $goals->filter(fn ($g) => (float)$g->current_amount < (float)$g->target_amount);
                    $hasIncompleteGoals = $incompleteGoals->count() > 0;
                @endphp

                @if($todayRemaining > 0 && ($hasUnpaidDebts || $hasIncompleteGoals))
                    <div class="today-harvest-cta-wrap">
                        <button type="button" class="btn-today-harvest-pill" onclick="openDailySurplusHarvestModal({{ (float)$todayRemaining }})">
                            <span class="harvest-icon-glow">⚡</span>
                            <div class="harvest-text-wrap">
                                <span class="harvest-title">وفرت <strong>{{ number_format($todayRemaining, 0) }} {{ $settings->currency ?? 'ILS' }}</strong> من يوميتك اليوم!</span>
                                <span class="harvest-sub">
                                    @if($hasUnpaidDebts && $hasIncompleteGoals)
                                        اضغط لتوزيع الفائض الذكي بين الديون والتحويش واليوميات ⚡
                                    @elseif($hasIncompleteGoals)
                                        اضغط لاقتناص نسبة مئوية للفائض وحوّلها لهدفك المالي 🎯
                                    @elseif($hasUnpaidDebts)
                                        اضغط لاقتناص نسبة مئوية للفائض لتسريع سداد الديون 💳
                                    @endif
                                </span>
                            </div>
                            <span class="harvest-arrow-pill">توزيع الفائض ◀</span>
                        </button>
                    </div>
                @endif

                <!-- Today's Mini Progress Bar & Status Message -->
                <div class="today-burn-wrap">
                    <div class="today-burn-header">
                        <span id="todayBurnStatusText">
                            @if($todayOverspend > 0)
                                <span class="badge-overspent">⚠️ تجاوزت يومية اليوم بـ {{ number_format($todayOverspend, 0) }} {{ $settings->currency ?? 'ILS' }}</span>
                            @elseif($todayExpenses > 0)
                                <span>استهلكت <strong id="todayBurnPercentVal">{{ $todaySpentPercent }}%</strong> من يومية اليوم</span>
                            @else
                                <span class="badge-untouched">✨ لم تصرف شيئاً من يوميتك بعد اليوم</span>
                            @endif
                        </span>
                        <span class="today-month-pace">المعدل اليومي لباقي الشهر: <strong>{{ number_format($safeDaily, 0) }} {{ $settings->currency ?? 'ILS' }}/يوم</strong> ({{ $daysRemaining }} يوماً)</span>
                    </div>
                    <div class="today-burn-bar">
                        <div class="today-burn-fill {{ $todaySpentPercent > 85 ? 'fill-danger' : ($todaySpentPercent > 50 ? 'fill-warning' : 'fill-primary') }}" 
                             id="todayBurnFill" 
                             style="width: {{ min(100, $todaySpentPercent) }}%;"></div>
                    </div>
                </div>

                <p class="safe-description">
                    محسوب على صافي رصيد التطبيق المتاح للصرف ({{ number_format($appSpendableBalance, 0) }} {{ $settings->currency ?? 'ILS' }}) بعد خصم مخصص التحويش المستهدف ({{ number_format($actualSavedOrReserved, 0) }} {{ $settings->currency ?? 'ILS' }}). ورصيد الكاش ({{ number_format($cashBalance, 0) }} {{ $settings->currency ?? 'ILS' }}) معزول ومجمد كاحتياطي طوارئ!
                </p>
            </div>

            <!-- Visual Circular Pacing Gauge -->
            <div class="pacing-gauge-col">
                <div class="gauge-ring-wrap">
                    <svg class="gauge-svg" viewBox="0 0 120 120">
                        <circle class="gauge-bg" cx="60" cy="60" r="50" />
                        <circle class="gauge-fill" id="gaugeProgressCircle" cx="60" cy="60" r="50" 
                            style="stroke-dashoffset: {{ 314.159 - (min(100, $spentPercent) / 100) * 314.159 }}; stroke: {{ $spentPercent > $daysPercent + 10 ? 'var(--danger)' : 'var(--primary)' }};" />
                    </svg>
                    <div class="gauge-inner-info">
                        <span class="gauge-percent">{{ $spentPercent }}%</span>
                        <span class="gauge-sub">استهلاك الراتب</span>
                    </div>
                </div>
                <div class="pacing-status-badge">
                    @if($spentPercent > $daysPercent + 10)
                        ⚠️ وتيرة الصرف سريعة - راقب مصاريفك
                    @else
                        الحالة المالية: مستقر وممتاز 👍
                    @endif
                </div>
            </div>
        </div>

        <!-- Month Pace Comparison Dual Progress -->
        <div class="month-pace-comparison">
            <div class="pace-row">
                <span>انقضى من الشهر: <strong>{{ $daysPercent }}%</strong> ({{ $daysElapsed }} من {{ $totalCycleDays }} يوم)</span>
                <span>صُرف من الراتب: <strong>{{ $spentPercent }}%</strong></span>
            </div>
            <div class="dual-progress-bar">
                <div class="progress-bar-time" style="width: {{ min(100, $daysPercent) }}%"></div>
                <div class="progress-bar-spent" style="width: {{ min(100, $spentPercent) }}%"></div>
            </div>
            <div class="dual-progress-legend">
                <span class="legend-item"><span class="dot-time"></span> الأيام المنقضية</span>
                <span class="legend-item"><span class="dot-spent"></span> النفقات المصروفة</span>
            </div>
        </div>
    </div>

    <!-- COMPLETED DEBT HARVEST CELEBRATION BANNER (TAB DASHBOARD) -->
    @if(isset($completedHarvestDebts) && $completedHarvestDebts->count() > 0)
        @foreach($completedHarvestDebts as $chd)
            <div class="debt-harvest-complete-banner">
                <div class="harvest-banner-content">
                    <div class="harvest-banner-icon">🎉</div>
                    <div>
                        <div class="harvest-banner-title">اكتمل تجميع سداد دين [{{ $chd->name }}] بالكامل من الفوائض!</div>
                        <div class="harvest-banner-desc">
                            نجحت الخطة الذكية! تم تجميع <strong>{{ number_format($chd->surplus_harvested, 2) }} {{ $settings->currency ?? 'ILS' }}</strong> من فوائض يومياتك السابقة بهدوء ودون أي ضغط على ميزانيتك. يمكنك الآن سداد الدين وتصفية الحساب بنقرة واحدة!
                        </div>
                    </div>
                </div>
                <div class="harvest-banner-actions">
                    <form action="{{ route('debts.harvest.repay', $chd) }}" method="POST" onsubmit="return confirmActionForm(event, { title: 'سداد الدين من الحصالة', message: '🎉 هل تود سداد هذا الدين وتصفيته بالكامل من مبالغ الحصالة المجمعة؟', confirmText: 'نعم، سداد وتصفية الدين', icon: '💳', isDanger: false })">
                        @csrf
                        <button type="submit" class="btn-primary" style="background: linear-gradient(135deg, #10b981, #059669); font-weight: 800; padding: 0.65rem 1.25rem;">
                            <span>💳</span> <span>سداد الدين الآن وإغلاقه فوراً</span>
                        </button>
                    </form>
                    <button type="button" class="btn-subtle" onclick="openHarvestLogsModal({{ $chd->id }})" style="background: rgba(255,255,255,0.08); color: #fff; padding: 0.65rem 1rem;">
                        <span>📜</span> <span>عرض تفاصيل وسجل التجميع</span>
                    </button>
                </div>
            </div>
        @endforeach
    @endif

    <!-- 4 MAIN STATS OVERVIEW CARDS -->
    <div class="stats-overview-grid">
        <div class="stat-card stat-income">
            <div class="stat-icon-badge">💵</div>
            <div class="stat-details">
                <span class="stat-title">صافي الراتب / الدخل</span>
                <div class="stat-number-wrap">
                    <span class="stat-number" id="statTotalIncome" data-base-val="{{ (float)$totalIncome }}">{{ number_format($totalIncome, 0) }}</span>
                    <span class="curr-tag">{{ $settings->currency ?? 'SAR' }}</span>
                </div>
                <span class="stat-subtext">الراتب والدخول المسجلة</span>
            </div>
        </div>

        <div class="stat-card stat-spent">
            <div class="stat-icon-badge">🛒</div>
            <div class="stat-details">
                <span class="stat-title">إجمالي المصروفات</span>
                <div class="stat-number-wrap">
                    <span class="stat-number" id="statTotalExpenses" data-base-val="{{ (float)$totalExpenses }}">{{ number_format($totalExpenses, 0) }}</span>
                    <span class="curr-tag">{{ $settings->currency ?? 'SAR' }}</span>
                </div>
                <span class="stat-subtext">يشمل الفواتير واليومي</span>
            </div>
        </div>

        <div class="stat-card stat-remaining">
            <div class="stat-icon-badge">🛡️</div>
            <div class="stat-details" style="width: 100%;">
                <span class="stat-title">المتبقي الصافي للشهر</span>
                <div class="stat-number-wrap">
                    <span class="stat-number" id="statNetRemaining" data-base-val="{{ (float)$netRemaining }}">{{ number_format($netRemaining, 0) }}</span>
                    <span class="curr-tag">{{ $settings->currency ?? 'SAR' }}</span>
                </div>
                @if($appBalance > 0 || $cashBalance > 0 || $totalDebtsOwed > 0)
                    <div style="display: flex; flex-wrap: wrap; gap: 0.4rem; margin-top: 0.4rem; font-size: 0.76rem; font-weight: 700;">
                        <span style="color: #3b82f6; background: rgba(59,130,246,0.12); border: 1px solid rgba(59,130,246,0.25); padding: 0.15rem 0.5rem; border-radius: 6px;">📱 متاح بالتطبيق: {{ number_format($appSpendableBalance, 0) }}</span>
                        <span style="color: #10b981; background: rgba(16,185,129,0.12); border: 1px solid rgba(16,185,129,0.25); padding: 0.15rem 0.5rem; border-radius: 6px;">❄️ كاش مجمد: {{ number_format($cashBalance, 0) }}</span>
                        @if($totalDebtsOwed > 0)
                            <span onclick="switchTab('tab-debts')" style="color: #f87171; background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.25); padding: 0.15rem 0.5rem; border-radius: 6px; cursor: pointer;" title="اضغط للانتقال لدفتر الديون">📑 ديون مستحقة: {{ number_format($totalDebtsOwed, 0) }} ⚠️</span>
                        @endif
                    </div>
                @else
                    <span class="stat-subtext">متاح بعد كل الالتزامات</span>
                @endif
            </div>
        </div>

        <div class="stat-card stat-savings" onclick="openModal('salarySettingsModal'); switchSalarySubTab('cycle');" style="cursor: pointer;" title="اضغط هنا لتعديل أو تقليل نسبة محجوز الادخار">
            <div class="stat-icon-badge">🎯</div>
            <div class="stat-details">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <span class="stat-title">محجوز للتحويش والادخار</span>
                    <span style="font-size: 0.72rem; color: #38bdf8; background: rgba(56, 189, 248, 0.15); padding: 1px 6px; border-radius: 4px; font-weight: 700;">⚙️ تعديل</span>
                </div>
                <div class="stat-number-wrap">
                    <span class="stat-number" id="statTotalSavings" data-base-val="{{ (float)$actualSavedOrReserved }}">{{ number_format($actualSavedOrReserved, 0) }}</span>
                    <span class="curr-tag">{{ $settings->currency ?? 'SAR' }}</span>
                </div>
                <span class="stat-subtext">معدل التحويش: {{ $settings->savings_target_percent }}% (اضغط لتغيير النسبة)</span>
            </div>
        </div>
    </div>

    <!-- QUICK ACTION BUTTONS -->
    <div class="quick-action-strip">
        <button class="action-btn action-add-expense" onclick="openTxModal('expense')">
            <span>➕</span> <span>تسجيل مصروف فوري</span>
        </button>
        <button class="action-btn action-add-income" onclick="openTxModal('income')">
            <span>💰</span> <span>إضافة دخل (يومية / مساعدة)</span>
        </button>
        <button class="action-btn action-feed-goal" onclick="openModal('feedGoalModal')">
            <span>🪙</span> <span>تغذية حصالة تحويش</span>
        </button>
        <button class="action-btn action-cool-off" onclick="openModal('wishModal')">
            <span>⏳</span> <span>فترة التهدئة (48 ساعة)</span>
        </button>
    </div>

    <!-- SALARY INFLOW & STREAMS SCHEDULE -->
    <div class="salary-schedule-card">
        <div class="salary-schedule-header">
            <div style="min-width: 0; flex: 1;">
                <h3 style="font-size: 1.05rem; font-weight: 800; color: var(--text-primary); display: flex; align-items: center; flex-wrap: wrap; gap: 0.45rem; margin: 0 0 0.25rem 0;">
                    <span style="display: inline-flex; align-items: center; gap: 0.35rem;">
                        <span>📅</span>
                        <span>جدول مواعيد وتدفق الرواتب الشهرية</span>
                    </span>
                    <span class="badge-pro" style="font-size: 0.72rem; padding: 0.2rem 0.6rem; white-space: nowrap; flex-shrink: 0;">{{ $salarySources->count() }} مصادر دخل</span>
                </h3>
                <span style="font-size: 0.8rem; color: var(--text-secondary); display: block; line-height: 1.5;">
                    إجمالي الرواتب: <strong>{{ number_format($totalSalary, 0) }} {{ $settings->currency ?? 'ILS' }}</strong> (📱 تطبيق: {{ number_format($appSalariesTotal, 0) }} | 💵 كاش: {{ number_format($cashSalariesTotal, 0) }})
                </span>
            </div>
            <button class="btn-subtle" onclick="openModal('salarySettingsModal')">
                ⚙️ إدارة الرواتب والدورة
            </button>
        </div>

        <div class="salary-timeline-items">
            @forelse($salarySources as $s)
                <div class="salary-timeline-pill {{ $s->payment_method === 'cash' ? 'cash-pill' : '' }}">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; flex-wrap: wrap;">
                        <span style="font-weight: 800; font-size: 0.92rem; color: var(--text-primary); min-width: 0; word-break: break-word;">
                            {{ $s->payment_method === 'cash' ? '💵' : ($s->payment_method === 'card' ? '💳' : '📱') }} {{ $s->name }}
                        </span>
                        <span class="salary-badge" style="font-size: 0.72rem; background: rgba(255,255,255,0.06); color: {{ $s->status_meta['color'] }}; white-space: nowrap; flex-shrink: 0;">
                            {{ $s->status_meta['badge'] }}
                        </span>
                    </div>

                    <div style="display: flex; align-items: baseline; justify-content: space-between; gap: 0.5rem; margin-top: 0.25rem; flex-wrap: wrap;">
                        <span style="font-family: var(--font-numbers); font-weight: 800; font-size: 1.15rem; color: var(--text-primary); white-space: nowrap;">
                            {{ number_format($s->amount, 0) }} {{ $s->currency }}
                        </span>
                        <span style="font-size: 0.78rem; font-weight: 700; color: var(--accent-gold); white-space: nowrap; flex-shrink: 0;">
                            📅 يوم {{ $s->day_of_month }} من الشهر
                        </span>
                    </div>

                    @if($s->notes)
                        <span style="font-size: 0.75rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="{{ $s->notes }}">
                            {{ $s->notes }}
                        </span>
                    @endif
                </div>
            @empty
                <div class="empty-state-card" style="padding: 1rem; width: 100%;">
                    لا توجد رواتب مسجلة بعد. اضغط "إدارة الرواتب والدورة" لإضافة رواتبك.
                </div>
            @endforelse
        </div>
    </div>

    <!-- CHARTS GRID -->
    <div class="dashboard-analytics-grid">
        <div class="chart-box">
            <div class="chart-header">
                <h3 class="chart-title">📊 توزيع المصروفات حسب الأظرف</h3>
                <span class="chart-badge">لهذا الشهر</span>
            </div>
            <div class="chart-canvas-container">
                <canvas id="categoryDonutChart"></canvas>
            </div>
        </div>

        <div class="chart-box">
            <div class="chart-header">
                <h3 class="chart-title">📈 مسار الصرف التراكمي مقابل الميزانية</h3>
                <span class="chart-badge">مؤشر الانضباط</span>
            </div>
            <div class="chart-canvas-container">
                <canvas id="spendingTrendChart"></canvas>
            </div>
        </div>
    </div>

    <!-- RECENT TRANSACTIONS -->
    <div class="recent-transactions-widget">
        <div class="widget-header">
            <h3 class="widget-title">⚡ آخر المعاملات المسجلة</h3>
            <button class="btn-link" onclick="switchTab('tab-ledger')">عرض كل السجل ←</button>
        </div>
        <div class="transactions-list" id="recentTxList">
            @forelse($recentTransactions as $tx)
                <div class="tx-item" data-tx-id="{{ $tx->id }}" id="recent-tx-card-{{ $tx->id }}">
                    <div class="tx-item-left">
                        <div class="tx-category-icon">
                            {{ ($tx->type === 'savings' && $tx->goal) ? $tx->goal->icon : ($tx->envelope ? $tx->envelope->icon : '💸') }}
                        </div>
                        <div class="tx-details">
                            <span class="tx-name">{{ $tx->note ?? (($tx->type === 'savings' && $tx->goal) ? 'إيداع: '.$tx->goal->name : ($tx->envelope ? $tx->envelope->name : 'معاملة')) }}</span>
                            <span class="tx-meta">{{ ($tx->type === 'savings' && $tx->goal) ? 'حصالة: '.$tx->goal->name : ($tx->envelope ? $tx->envelope->name : 'عام') }} • {{ $tx->date->format('Y-m-d') }} @if($tx->user) • 👤 {{ $tx->user->name }} @endif</span>
                        </div>
                    </div>
                    <div class="tx-item-right">
                        <span class="tx-amount-display {{ $tx->type }}">
                            {{ $tx->type == 'income' ? '+' : ($tx->type == 'savings' ? '🪙' : '-') }} {{ number_format($tx->amount, 2) }} {{ $settings->currency ?? 'SAR' }}
                        </span>
                        <div style="display: flex; align-items: center; gap: 0.35rem;">
                            <button type="button" class="btn-edit-tx" onclick='openEditTxModal(@json($tx))' title="تعديل المعاملة">✏️</button>
                            <form action="{{ route('transactions.destroy', $tx) }}" method="POST" onsubmit="return confirmDeleteForm(event, 'هل أنت متأكد من حذف هذه المعاملة؟ سيتم تحديث الرصيد ومتبقي اليومية فوراً.', 'تأكيد حذف المعاملة')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-delete-tx" title="حذف">🗑️</button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="empty-state-card">لا توجد معاملات مسجلة بعد. اضغط "تسجيل مصروف فوري" للبدء!</div>
            @endforelse
        </div>
    </div>

</section>

<!-- ============================================== -->
<!-- TAB: SHARED SMART GROCERY LIST (قائمة المقاضي والمشتريات المشتركة) -->
<!-- ============================================== -->
<section class="tab-pane {{ ($activeTab ?? '') === 'tab-grocery' ? 'active' : '' }}" id="tab-grocery">
    
    <!-- Hero Header & Quick Counters -->
    <div class="grocery-hero-header">
        <div class="grocery-hero-title-group">
            <div class="grocery-hero-icon-wrap">🛒</div>
            <div>
                <h2 class="grocery-hero-title">
                    <span>قائمة المقاضي المشتركة</span>
                    <span style="font-size: 0.75rem; background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.4); padding: 0.15rem 0.55rem; border-radius: 20px; font-weight: 700;">تشارك عائلي 👥</span>
                </h2>
                <p class="grocery-hero-desc">
                    اكتبا ما ينقص البيت معاً، واشطبا ما تم شراؤه داخل السوبرماركت بضغطة زر حتى بدون إنترنت!
                </p>
            </div>
        </div>

        <div class="grocery-stats-bar">
            <div class="grocery-stat-pill stat-pending" title="أصناف بحاجة للشراء">
                <span>⏳ ناقص:</span>
                <strong id="groceryStatPending">{{ $pendingGroceryCount ?? 0 }}</strong>
            </div>
            <div class="grocery-stat-pill stat-bought" title="أصناف تم شراؤها">
                <span>✔️ تم شطب:</span>
                <strong id="groceryStatBought">{{ $boughtGroceryCount ?? 0 }}</strong>
            </div>
            <div class="grocery-stat-pill stat-total" title="إجمالي مصروف الأغراض المشتراة">
                <span>💰 مصروف:</span>
                <strong id="groceryStatEstimated">{{ number_format($estimatedGroceryTotal ?? 0, 1) }}</strong>
                <span>{{ $settings->currency ?? 'ILS' }}</span>
            </div>
        </div>
    </div>

    <!-- Quick 1-Click Convert Bar (Visible when there are bought items not yet converted to expense) -->
    <div class="grocery-convert-action-card" id="groceryConvertBanner" style="{{ ($boughtGroceryCount ?? 0) > 0 ? '' : 'display: none;' }}">
        <div class="convert-card-info">
            <span class="convert-card-icon">🛍️</span>
            <div>
                <div class="convert-card-title">
                    <span>تم شطب <strong id="convertBannerCount">{{ $boughtGroceryCount ?? 0 }}</strong> أغراض مشتراة!</span>
                </div>
                <div class="convert-card-subtitle">
                    حوّل هذه المشتريات الآن بضغطة زر واحدة إلى مصروف مباشر في ظرف البقالة.
                </div>
            </div>
        </div>
        <button type="button" class="btn-convert-grocery" onclick="openConvertGroceryModal()">
            <span>💳</span>
            <span>تحويل إلى مصروف في ظرف البقالة</span>
        </button>
    </div>

    <!-- Quick Add Form -->
    <div class="grocery-add-card">
        <form id="addGroceryForm" onsubmit="handleGroceryFormSubmit(event)" class="grocery-add-form">
            @csrf
            <div class="grocery-input-row">
                <input type="text" name="name" id="groceryInputName" class="styled-input grocery-input-name" placeholder="ماذا ينقص البيت؟ (مثال: حليب، جبنة، بيض، معجون أسنان...)" required autocomplete="off">
                
                <select name="category" id="grocerySelectCategory" class="styled-input grocery-select-cat">
                    @foreach($groceryCategories as $catKey => $catMeta)
                        <option value="{{ $catKey }}">{{ $catMeta['icon'] }} {{ $catMeta['name'] }}</option>
                    @endforeach
                </select>

                <button type="submit" class="grocery-add-btn" id="grocerySubmitBtn">
                    <span>➕</span>
                    <span>إضافة للقائمة</span>
                </button>
            </div>

            <!-- Fast Suggestion Chips -->
            <div class="grocery-suggestions-wrap">
                <span class="grocery-suggestions-label">أغراض شائعة سريعة:</span>
                <button type="button" class="grocery-chip" onclick="quickFillGrocery('حليب طازج 🥛', 'dairy')"><span>🥛</span> <span>حليب</span></button>
                <button type="button" class="grocery-chip" onclick="quickFillGrocery('خبز طازج 🥖', 'groceries')"><span>🥖</span> <span>خبز</span></button>
                <button type="button" class="grocery-chip" onclick="quickFillGrocery('كرتونة بيض 🥚', 'dairy')"><span>🥚</span> <span>بيض</span></button>
                <button type="button" class="grocery-chip" onclick="quickFillGrocery('جبنة بيضاء 🧀', 'dairy')"><span>🧀</span> <span>جبنة</span></button>
                <button type="button" class="grocery-chip" onclick="quickFillGrocery('خضار مشكلة 🥗', 'produce')"><span>🥗</span> <span>خضار</span></button>
                <button type="button" class="grocery-chip" onclick="quickFillGrocery('منظفات ومحارم 🧼', 'cleaning')"><span>🧼</span> <span>منظفات</span></button>
                <button type="button" class="grocery-chip" onclick="quickFillGrocery('دجاج طازج 🍗', 'meat')"><span>🍗</span> <span>دجاج</span></button>
                <button type="button" class="grocery-chip" onclick="quickFillGrocery('زيت طعام / زيتون 🫒', 'groceries')"><span>🫒</span> <span>زيت</span></button>
                <button type="button" class="grocery-chip" onclick="quickFillGrocery('شاي وسكر ☕', 'groceries')"><span>☕</span> <span>شاي وسكر</span></button>
                <button type="button" class="grocery-chip" onclick="quickFillGrocery('أدوية وعناية 💊', 'pharmacy')"><span>💊</span> <span>صيدلية</span></button>
            </div>
        </form>
    </div>

    <!-- Category Filter Bar -->
    <div class="grocery-filters-bar">
        <button type="button" class="cat-filter-btn active" data-filter="all" onclick="filterGroceryCategory('all', this)">
            <span>🌟</span> <span>الكل</span>
        </button>
        @foreach($groceryCategories as $catKey => $catMeta)
            <button type="button" class="cat-filter-btn" data-filter="{{ $catKey }}" onclick="filterGroceryCategory('{{ $catKey }}', this)">
                <span>{{ $catMeta['icon'] }}</span> <span>{{ $catMeta['name'] }}</span>
            </button>
        @endforeach
    </div>

    <!-- Interactive Grocery Checklist Container -->
    <div class="grocery-list-container" id="groceryItemsList">
        @forelse($groceryItems as $item)
            <div class="grocery-item-card {{ $item->is_bought ? 'is-bought' : '' }}" 
                 id="groceryItemRow_{{ $item->id }}" 
                 data-id="{{ $item->id }}" 
                 data-category="{{ $item->category }}" 
                 data-bought="{{ $item->is_bought ? '1' : '0' }}"
                 data-price="{{ $item->estimated_price ?? 0 }}">
                
                <div class="grocery-item-main" onclick="toggleGroceryItem({{ $item->id }})">
                    <div class="grocery-checkbox-wrap">
                        <div class="grocery-checkbox-custom">✓</div>
                    </div>
                    <span class="grocery-cat-badge">{{ $item->category_meta['icon'] ?? '🛒' }}</span>
                    <div class="grocery-text-col">
                        <span class="grocery-item-name">{{ $item->name }}</span>
                        <div class="grocery-item-meta" style="direction: rtl; text-align: right;">
                            <span>👤 أضافه: <bdi class="actor-tag" style="font-weight: 700; color: #38bdf8;">{{ $item->user?->name ?? 'عضو' }}</bdi></span>
                            @if($item->is_bought && $item->buyer)
                                <span>• ✔️ شطبه: <bdi class="buyer-tag" style="font-weight: 700; color: #34d399;">{{ $item->buyer->name }}</bdi></span>
                            @endif
                            <span>• {{ $item->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                </div>

                <div class="grocery-item-end">
                    <span class="grocery-item-price" id="groceryItemPrice_{{ $item->id }}" style="{{ ($item->estimated_price && $item->estimated_price > 0) ? '' : 'display: none;' }}">
                        {{ ($item->estimated_price && $item->estimated_price > 0) ? ('~' . number_format($item->estimated_price, 1) . ' ₪') : '' }}
                    </span>
                    <button type="button" class="grocery-del-btn" title="حذف الغرض" onclick="deleteGroceryItem({{ $item->id }}, event)">
                        🗑️
                    </button>
                </div>
            </div>
        @empty
            <div class="grocery-empty-state" id="groceryEmptyState">
                <span class="grocery-empty-icon">🛒✨</span>
                <div class="grocery-empty-title">قائمة المقاضي مكتملة ولا ينقص البيت شيء!</div>
                <div class="grocery-empty-desc">
                    اكتب أي غرض ناقص (حليب، خضار، منظفات...) وسيظهر فوراً على هاتف شريكتك!
                </div>
            </div>
        @endforelse
    </div>

</section>

<!-- ============================================== -->
<!-- TAB: BUDGET & ENVELOPES -->
<!-- ============================================== -->
<section class="tab-pane {{ ($activeTab ?? '') === 'tab-envelopes' ? 'active' : '' }}" id="tab-envelopes">
    
    <!-- 50/30/20 Rule Banner -->
    <div class="rule-50-30-20-card">
        <div class="rule-header">
            <div>
                <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.25rem;">
                    <span class="rule-badge">قاعدة الميزانية الذهبية</span>
                    <span class="badge-pro" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); font-size: 0.72rem; padding: 2px 7px;">
                        📱 موزعة على رواتب التطبيق فقط: {{ number_format($budgetBase ?? $appSalariesTotal, 0) }} {{ $settings->currency ?? 'ILS' }}
                    </span>
                    @if(($cashSalariesTotal ?? 0) > 0)
                        <span style="font-size: 0.75rem; color: var(--accent-gold); font-weight: 700; white-space: nowrap;">
                            💵 الكاش منفصل: {{ number_format($cashSalariesTotal, 0) }} {{ $settings->currency ?? 'ILS' }}
                        </span>
                    @endif
                </div>
                <h2 style="font-size: 1.15rem; font-weight: 800; margin-top: 4px; color: var(--text-primary); line-height: 1.4;">
                    توزيع الميزانية الصفرية (50% أساسيات - 30% كماليات - 20% تحويش)
                </h2>
            </div>
            <form action="{{ route('envelopes.autoAllocate') }}" method="POST" onsubmit="return confirmActionForm(event, { title: 'إعادة توزيع الميزانية', message: 'هل تريد إعادة ضبط وتوزيع ميزانيات الأظرف بحسب رواتب التطبيق ({{ number_format($budgetBase ?? $appSalariesTotal, 0) }} {{ $settings->currency ?? "ILS" }}) وقاعدة 50/30/20؟', confirmText: 'نعم، أعد التوزيع', icon: '🔄', isDanger: false })">
                @csrf
                <button type="submit" class="btn-secondary">
                    🔄 إعادة توزيع تلقائي للراتب
                </button>
            </form>
        </div>

        <div class="rule-bars-row">
            <div class="rule-pillar pillar-needs">
                <div class="pillar-top">
                    <span>🏠 الاحتياجات الأساسية (50%)</span>
                    <span>{{ number_format($needsSpent, 0) }} / {{ number_format($needsBudget, 0) }} {{ $settings->currency ?? 'ILS' }}</span>
                </div>
                <div class="pillar-progress">
                    <div class="pillar-fill" style="width: {{ $needsBudget > 0 ? min(100, ($needsSpent / $needsBudget) * 100) : 0 }}%"></div>
                </div>
                <span class="pillar-note">إيجار، بقالة، فواتير، صحة، بنزين</span>
            </div>

            <div class="rule-pillar pillar-wants">
                <div class="pillar-top">
                    <span>☕ الرغبات والكماليات (30%)</span>
                    <span>{{ number_format($wantsSpent, 0) }} / {{ number_format($wantsBudget, 0) }} {{ $settings->currency ?? 'ILS' }}</span>
                </div>
                <div class="pillar-progress">
                    <div class="pillar-fill" style="width: {{ $wantsBudget > 0 ? min(100, ($wantsSpent / $wantsBudget) * 100) : 0 }}%"></div>
                </div>
                <span class="pillar-note">مطاعم، ترفيه، تسوق، رحلات</span>
            </div>

            <div class="rule-pillar pillar-savings">
                <div class="pillar-top">
                    <span>🎯 التحويش والاستثمار (20%)</span>
                    <span>{{ number_format($savingsSpent, 0) }} / {{ number_format($savingsBudget, 0) }} {{ $settings->currency ?? 'ILS' }}</span>
                </div>
                <div class="pillar-progress">
                    <div class="pillar-fill" style="width: {{ $savingsBudget > 0 ? min(100, ($savingsSpent / $savingsBudget) * 100) : 0 }}%"></div>
                </div>
                <span class="pillar-note">صندوق طوارئ، استثمار، أهداف المستقبل</span>
            </div>
        </div>
    </div>

    <!-- Envelopes Header -->
    <div class="section-action-header">
        <div>
            <h3 style="font-size: 1.25rem; font-weight: 800">الأظرف الرقمية وتصنيفات المصاريف</h3>
            <p class="section-desc">تتبع دقيق لمصروفاتك في كل بند وتصنيف لمعرفة أين تذهب أموالك خلال الشهر.</p>
        </div>
        <button class="btn-primary" onclick="openModal('envelopeModal')">
            ➕ إضافة تصنيف / ظرف جديد
        </button>
    </div>

    <!-- Envelopes Grid -->
    <div class="envelopes-grid">
        @foreach($envelopes as $env)
            @php
                $spent = $env->spent;
                $hasBudget = ($env->budget > 0);
                $pct = $hasBudget ? $env->spent_percent : 0;
                $statusClass = $pct >= 100 ? 'env-status-danger' : ($pct >= 80 ? 'env-status-warning' : 'env-status-safe');
                $statusText = $pct >= 100 ? 'تجاوز السقف 🚨' : ($pct >= 80 ? 'قريب من النفاد ⚠️' : 'آمن ✅');
                $pillarText = $env->pillar == 'needs' ? 'أساسيات (50%)' : ($env->pillar == 'wants' ? 'كماليات (30%)' : 'تحويش (20%)');
                $shareOfExpenses = $totalExpenses > 0 ? (int) round(($spent / $totalExpenses) * 100) : 0;
            @endphp
            <div class="envelope-card" data-env-id="{{ $env->id }}">
                <div class="envelope-card-header">
                    <div class="envelope-title-wrap">
                        <div class="env-icon-box" style="background: rgba(16,185,129,0.15); color: {{ $env->color }}">
                            {{ $env->icon }}
                        </div>
                        <div>
                            <h4 class="env-name">{{ $env->name }}</h4>
                            <span class="env-pillar-badge">{{ $pillarText }}</span>
                        </div>
                    </div>
                    <div style="display:flex; align-items:center; gap:0.4rem;">
                        @if($hasBudget)
                            <span class="env-status-badge {{ $statusClass }}">{{ $statusText }}</span>
                        @endif
                        <button type="button" class="btn-env-action" title="تعديل الظرف"
                            onclick='openEditEnvelopeModal(@json($env))'>✏️</button>
                        <form action="{{ route('envelopes.destroy', $env) }}" method="POST" style="display:inline;"
                            onsubmit="return confirmDeleteForm(event, 'هل أنت متأكد من حذف ظرف {{ $env->name }}؟ سيتم حذف جميع المعاملات المرتبطة به!', 'تأكيد حذف الظرف')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-env-action danger" title="حذف الظرف">🗑️</button>
                        </form>
                    </div>
                </div>

                @if($hasBudget)
                    <div class="env-amounts-row">
                        <div class="env-amount-col">
                            <span class="env-amount-lbl">المصروف حتى الآن</span>
                            <span class="env-amount-val" style="color: {{ $env->color }}">{{ number_format($spent, 0) }} {{ $settings->currency ?? 'ILS' }}</span>
                        </div>
                        <div class="env-amount-col" style="text-align: left">
                            <span class="env-amount-lbl">المتبقي في الظرف</span>
                            <span class="env-amount-val">{{ number_format($env->remaining, 0) }} {{ $settings->currency ?? 'ILS' }}</span>
                        </div>
                    </div>

                    <div class="env-bar-wrap">
                        <div class="env-bar-fill" style="width: {{ $pct }}%; background: {{ $pct >= 100 ? '#ef4444' : $env->color }}"></div>
                    </div>

                    <div class="envelope-footer">
                        <span class="env-spent-percent">تم استهلاك {{ $pct }}% من ميزانية {{ number_format($env->budget, 0) }} {{ $settings->currency ?? 'ILS' }}</span>
                        <button class="btn-add-env-tx" onclick="openTxModal('expense', '{{ $env->id }}')">+ إضافة مصروف</button>
                    </div>
                @else
                    {{-- Clean Mode without Budgets: Pure Accumulated Spent Tracking --}}
                    <div class="env-spent-focus-box" style="padding: 1.1rem 0 0.6rem 0; text-align: center;">
                        <span class="env-amount-lbl" style="font-size: 0.8rem; color: var(--text-secondary); display: block; margin-bottom: 0.35rem; font-weight: 700;">
                            المصروف حتى الآن في هذا البند
                        </span>
                        <div style="font-family: var(--font-numbers); font-size: 2rem; font-weight: 800; color: {{ $spent > 0 ? ($env->color ?? 'var(--primary)') : 'var(--text-muted)' }}; line-height: 1.2;">
                            {{ number_format($spent, 0) }} <span style="font-size: 0.95rem; font-weight: 700; color: var(--text-secondary);">{{ $settings->currency ?? 'ILS' }}</span>
                        </div>
                        <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.45rem;">
                            @if($spent > 0)
                                يشكل <strong>{{ $shareOfExpenses }}%</strong> من إجمالي مصروفاتك الشهرية
                            @else
                                <span>لم تسجل أي مصروف هنا بعد</span>
                            @endif
                        </div>
                    </div>

                    <div class="envelope-footer" style="margin-top: 0.6rem; border-top: 1px dashed var(--border); padding-top: 0.75rem;">
                        <button class="btn-add-env-tx" style="width: 100%; justify-content: center; font-weight: 700; padding: 0.5rem;" onclick="openTxModal('expense', '{{ $env->id }}')">
                            ➕ إضافة مصروف في {{ $env->name }}
                        </button>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

</section>

<!-- ============================================== -->
<!-- TAB 3: SAVING GOALS -->
<!-- ============================================== -->
<section class="tab-pane {{ ($activeTab ?? '') === 'tab-goals' ? 'active' : '' }}" id="tab-goals">
    <div class="section-action-header">
        <div>
            <h2 style="font-size: 1.35rem; font-weight: 800">🎯 صناديق وأهداف التحويش (Sinking Funds)</h2>
            <p class="section-desc">اعزل أموالك للأهداف الكبرى لتجنب المفاجآت (طوارئ، سيارة، سفر، زواج، صيانة).</p>
        </div>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
            @if($todayRemaining > 0 && ($hasUnpaidDebts || $hasIncompleteGoals))
                <button type="button" class="btn-subtle" onclick="openDailySurplusHarvestModal({{ (float)$todayRemaining }})" style="border-color: rgba(16, 185, 129, 0.4); color: #10b981; font-weight: 700; display: inline-flex; align-items: center; gap: 0.4rem;">
                    ⚡ توزيع فائض اليوم ({{ number_format($todayRemaining, 0) }} ₪)
                </button>
            @endif
            <button class="btn-primary" onclick="openModal('goalModal')">
                ➕ إنشاء هدف تحويش جديد
            </button>
        </div>
    </div>

    <!-- Goals Summary Card -->
    <div class="goals-summary-card">
        <div>
            <span class="goals-sub">إجمالي المبلغ المحوّش حتى الآن:</span>
            <div class="goals-hero-number">{{ number_format($totalSavedAllGoals, 0) }} {{ $settings->currency ?? 'SAR' }}</div>
        </div>
        <div>
            <span class="goals-sub">المستهدف الكلي لجميع الأهداف:</span>
            <div class="goals-hero-number secondary">{{ number_format($totalTargetAllGoals, 0) }} {{ $settings->currency ?? 'SAR' }}</div>
        </div>
        <div>
            <span class="goals-sub">نسبة الإنجاز الإجمالية:</span>
            <div class="goals-hero-number highlight">{{ $overallGoalsPercent }}%</div>
        </div>
    </div>

    <!-- Goals Grid -->
    <div class="goals-grid">
        @forelse($goals as $goal)
            <div class="goal-card">
                <div>
                    <div class="goal-top">
                        <div class="goal-title-wrap">
                            <div class="goal-icon">{{ $goal->icon }}</div>
                            <div>
                                <h4 class="goal-name">{{ $goal->name }}</h4>
                                <span class="goal-deadline">{{ $goal->deadline ? 'الموعد: '.$goal->deadline->format('Y-m-d') : 'هدف مفتوح' }}</span>
                            </div>
                        </div>
                        @if($goal->current_amount >= $goal->target_amount)
                            <span class="badge-pro" style="background:#10b981">مكتمل 🎉</span>
                        @endif
                    </div>

                    <div class="goal-numbers-row">
                        <div>
                            <span class="goals-sub">المجموع حالياً</span>
                            <div class="goal-cur-saved">
                                {{ number_format($goal->current_amount, ($goal->currency == 'USD' || $goal->currency == 'JOD' ? 2 : 0)) }} 
                                <span style="font-size: 0.9rem; font-weight: 700; color: var(--primary);">{{ $goal->currency_meta['flag'] }} {{ $goal->currency_meta['symbol'] }}</span>
                            </div>
                            @if(strtoupper($goal->currency ?? 'ILS') !== 'ILS')
                                <div style="font-size: 0.78rem; color: #10b981; font-weight: 700; margin-top: 2px;">
                                    ≈ ما يعادله: {{ number_format($goal->equivalent_in_shekel, 2) }} ₪
                                </div>
                            @endif
                        </div>
                        <div style="text-align: left">
                            <span class="goals-sub">الهدف</span>
                            <div class="goal-target-val">
                                {{ number_format($goal->target_amount, ($goal->currency == 'USD' || $goal->currency == 'JOD' ? 2 : 0)) }} 
                                <span style="font-size: 0.85rem; font-weight: 600;">{{ $goal->currency_meta['symbol'] }}</span>
                            </div>
                            @if(strtoupper($goal->currency ?? 'ILS') !== 'ILS')
                                <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">
                                    ≈ {{ number_format($goal->target_equivalent_in_shekel, 0) }} ₪
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="goal-progress-wrap">
                        <div class="goal-progress-fill" style="width: {{ $goal->progress_percent }}%"></div>
                    </div>
                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.75rem">
                        نسبة الإنجاز: <strong>{{ $goal->progress_percent }}%</strong> 
                        (متبقي: {{ number_format($goal->remaining_needed, 0) }} {{ $goal->currency_meta['symbol'] }})
                    </div>

                    <!-- Auto-Harvest Pot Card / Activation -->
                    @if($goal->is_harvest_active)
                        <div class="debt-harvest-pot-card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.05) 100%); border: 1px solid rgba(16, 185, 129, 0.35); border-radius: 12px; padding: 0.75rem 0.85rem; margin-bottom: 0.85rem;">
                            <div class="harvest-pot-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                                <div style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; font-weight: 800; color: #10b981;">
                                    <span>⚡</span>
                                    <span>اقتطاع الفائض التلقائي: نشط</span>
                                </div>
                                <span style="font-size: 0.72rem; padding: 0.15rem 0.55rem; background: rgba(16, 185, 129, 0.2); color: #34d399; border-radius: 999px; font-weight: 700;">
                                    {{ $goal->harvest_ratio_percent }}% من الفائض
                                </span>
                            </div>
                            <div style="font-size: 0.76rem; color: var(--text-secondary); margin-bottom: 0.65rem; line-height: 1.4;">
                                يقتطع النظام تلقائياً <strong>{{ $goal->harvest_ratio_percent }}%</strong> من أي فائض متبقي من يوميتك عند منتصف الليل ويودعه بالـ {{ $goal->currency_meta['symbol'] }}.
                            </div>
                            <div class="harvest-pot-actions" style="display: flex; gap: 0.35rem;">
                                <button type="button" class="btn-subtle" onclick="openGoalHarvestLogsModal({{ $goal->id }}, '{{ addslashes($goal->name) }}')" style="flex: 1; padding: 0.35rem 0.5rem; font-size: 0.76rem; font-weight: 700; background: rgba(255,255,255,0.06); border-radius: 8px;">
                                    📋 سجل الفوائض ({{ $goal->harvestLogs->count() }})
                                </button>
                                <button type="button" class="btn-subtle" onclick="openActivateGoalHarvestModal({{ $goal->id }}, '{{ addslashes($goal->name) }}', {{ $goal->remaining_needed }}, '{{ $goal->currency }}', '{{ $goal->currency_meta['symbol'] }}', {{ $goal->harvest_ratio_percent }}, {{ $goal->harvest_max_daily ?? 0 }})" style="padding: 0.35rem 0.55rem; font-size: 0.76rem; font-weight: 700; background: rgba(255,255,255,0.06); border-radius: 8px;" title="تعديل النسبة">
                                    ⚙️ تعديل
                                </button>
                                <form action="{{ route('goals.harvest.toggle', $goal) }}" method="POST" style="display: inline;">
                                    @csrf
                                    <button type="submit" class="btn-subtle" style="padding: 0.35rem 0.5rem; font-size: 0.76rem; font-weight: 700; background: rgba(239, 68, 68, 0.1); color: #ef4444; border-radius: 8px;" title="إيقاف مؤقت للاقتطاع">
                                        ⏸️ إيقاف
                                    </button>
                                </form>
                            </div>
                        </div>
                    @elseif($goal->current_amount < $goal->target_amount)
                        <div style="margin-bottom: 0.85rem;">
                            <button type="button" class="btn-activate-harvest" onclick="openActivateGoalHarvestModal({{ $goal->id }}, '{{ addslashes($goal->name) }}', {{ $goal->remaining_needed }}, '{{ $goal->currency }}', '{{ $goal->currency_meta['symbol'] }}', 50, 0)" style="width: 100%; background: rgba(16, 185, 129, 0.08); border: 1px dashed rgba(16, 185, 129, 0.35); color: #34d399; border-radius: 10px; padding: 0.5rem 0.75rem; font-size: 0.8rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.4rem; transition: all 0.2s;">
                                <span>⚡</span>
                                <span>تفعيل الاقتطاع التلقائي من فائض نهاية اليوم</span>
                            </button>
                        </div>
                    @endif
                </div>

                <div class="goal-actions-row">
                    <button class="btn-feed-single-goal" onclick="openDepositModal('{{ $goal->id }}', '{{ $goal->name }}')">
                        🪙 إيداع في الحصالة
                    </button>
                    <form action="{{ route('goals.destroy', $goal) }}" method="POST" onsubmit="return confirmDeleteForm(event, 'هل أنت متأكد من حذف هذا الهدف المالي؟', 'تأكيد حذف الهدف')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-subtle" title="حذف">🗑️</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="empty-state-card full-width-card">لا توجد أهداف مضافة بعد.</div>
        @endforelse
    </div>
</section>

<!-- ============================================== -->
<!-- TAB 4: LEAKS & WASTE AUDIT -->
<!-- ============================================== -->
<section class="tab-pane {{ ($activeTab ?? '') === 'tab-leaks' ? 'active' : '' }}" id="tab-leaks">
    <div class="section-action-header">
        <div>
            <h2 style="font-size: 1.35rem; font-weight: 800">⚡ كاشف التسريبات ومحاربة الهدر المالي</h2>
            <p class="section-desc">أدوات نفسية مدمجة لإيقاف نزوات الشراء وكشف المبالغ الصغيرة التي تستنزف راتبك.</p>
        </div>
    </div>

    <div class="leaks-tools-grid">
        <!-- 0. Live Auto-Detected Leaks Radar (رادار كشف التسريبات التلقائي من مصاريفك الفعلية) -->
        <div class="leak-card full-width-card" style="background: linear-gradient(145deg, var(--card-bg), var(--card-bg-subtle)); border: 2px solid rgba(245, 158, 11, 0.25);">
            <div class="leak-header">
                <div class="leak-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">📡</div>
                <div class="leak-title-actions">
                    <div>
                        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                            <h3 style="font-weight: 800; margin: 0;">رادار كشف التسريبات الذكي (تلقائياً من واقع مصاريفك)</h3>
                            <span class="badge-pro" style="background: #10b981; font-size: 0.75rem;">🤖 كشف تلقائي بدون إدخال يدوي</span>
                        </div>
                        <p style="margin: 0.35rem 0 0; color: var(--text-secondary); font-size: 0.9rem;">
                            يقوم النظام بمسح مصاريفك اليومية لاكتشاف المصاريف الصغيرة المتكررة (مثل المواصلات، القهوة، السناكات) وحساب تكلفتها التراكمية الصادمة!
                        </p>
                    </div>
                </div>
            </div>

            @if($detectedLeaks->isNotEmpty())
                <div class="detected-leaks-banner" style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 14px; padding: 1rem 1.25rem; margin-bottom: 1.25rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <span style="font-size: 1.5rem;">⚠️</span>
                        <div>
                            <strong style="color: #ef4444; font-size: 1rem;">تم رصد {{ $detectedLeaks->count() }} عادات استنزاف صغيرة في هذه الدورة!</strong>
                            <div style="font-size: 0.85rem; color: var(--text-secondary);">هذه المبالغ تبدو غير مرئية يومياً لكنها تستنزف مئات الشواكل عند تراكمها:</div>
                        </div>
                    </div>
                    <div style="display: flex; gap: 1.5rem; align-items: center;">
                        <div style="text-align: center;">
                            <span style="font-size: 0.75rem; color: var(--text-muted); display: block;">أثرها المتوقع شهرياً</span>
                            <strong style="font-family: var(--font-numbers); font-size: 1.2rem; color: #f59e0b;">{{ number_format($totalDetectedLeakMonth, 0) }} {{ $settings->currency ?? 'ILS' }}</strong>
                        </div>
                        <div style="text-align: center;">
                            <span style="font-size: 0.75rem; color: var(--text-muted); display: block;">أثرها المتوقع سنوياً</span>
                            <strong style="font-family: var(--font-numbers); font-size: 1.35rem; color: #ef4444;">{{ number_format($totalDetectedLeakYear, 0) }} {{ $settings->currency ?? 'ILS' }}</strong>
                        </div>
                    </div>
                </div>

                <div class="detected-leaks-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                    @foreach($detectedLeaks as $leak)
                        <div class="detected-leak-item" style="background: var(--card-bg-subtle); border: 1px solid var(--card-border); border-radius: 16px; padding: 1.15rem; display: flex; flex-direction: column; justify-content: space-between; gap: 0.75rem;">
                            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 0.5rem;">
                                <div style="display: flex; align-items: center; gap: 0.65rem;">
                                    <span style="font-size: 1.4rem;">{{ $leak['envelope_icon'] ?? '💸' }}</span>
                                    <div>
                                        <h4 style="font-weight: 800; margin: 0; font-size: 1rem;">{{ $leak['name'] }}</h4>
                                        <span style="font-size: 0.78rem; color: var(--text-muted);">{{ $leak['envelope_name'] ?? 'مصروف نثري' }} • تكرر {{ $leak['count'] }} مرة</span>
                                    </div>
                                </div>
                                <span class="badge-pro" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); font-size: 0.75rem;">
                                    {{ $leak['avg_cost'] }} {{ $settings->currency ?? 'ILS' }}/مرة
                                </span>
                            </div>

                            <div style="background: var(--card-bg); border-radius: 12px; padding: 0.75rem; display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; text-align: center; border: 1px solid var(--card-border);">
                                <div>
                                    <span style="font-size: 0.72rem; color: var(--text-muted); display: block;">المصروف الفعلي بالدورة</span>
                                    <strong style="font-family: var(--font-numbers); font-size: 1rem;">{{ number_format($leak['total_spent'], 0) }} {{ $settings->currency ?? 'ILS' }}</strong>
                                </div>
                                <div>
                                    <span style="font-size: 0.72rem; color: var(--text-muted); display: block;">التكلفة السنوية التراكمية</span>
                                    <strong style="font-family: var(--font-numbers); font-size: 1.1rem; color: #ef4444;">{{ number_format($leak['projected_year'], 0) }} {{ $settings->currency ?? 'ILS' }}</strong>
                                </div>
                            </div>

                            <div style="font-size: 0.8rem; color: var(--text-secondary); background: rgba(16, 185, 129, 0.08); padding: 0.5rem 0.75rem; border-radius: 10px; border-right: 3px solid #10b981;">
                                💡 <strong>وفر هذا المبلغ واشترِ:</strong> {{ $leak['comparison'] }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="empty-radar-state" style="background: var(--card-bg-subtle); border-radius: 16px; padding: 1.75rem; text-align: center; border: 1px dashed var(--card-border);">
                    <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">📡</div>
                    <h4 style="font-weight: 800; margin: 0 0 0.5rem; color: var(--text-primary);">الرادار الذكي يعمل بالخلفية ويرصد عملياتك</h4>
                    <p style="margin: 0 auto; max-width: 550px; color: var(--text-secondary); font-size: 0.9rem; line-height: 1.5;">
                        لا تحتاج لإدخال أي شيء هنا! بمجرد أن تسجل مصاريفك اليومية العادية من الصفحة الرئيسية (مثل قهوة، مواصلات، تسالي، سناك)، سيقوم الرادار تلقائياً بتجميع المصاريف الصغيرة المتكررة واحتساب حجم تسريبها السنوي وتنبيهك فوراً.
                    </p>
                </div>
            @endif
        </div>

        <!-- 1. Purchase Impact & Professional Buying Decision Engine -->
        <div class="leak-card full-width-card" style="border: 2px solid rgba(59, 130, 246, 0.25);">
            <div class="leak-header">
                <div class="leak-icon" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6;">🎯</div>
                <div class="leak-title-actions">
                    <div>
                        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                            <h3 style="font-weight: 800; margin: 0;">مستشار قرار الشراء والآلية الاحترافية</h3>
                            <span class="badge-pro" style="background: #3b82f6; font-size: 0.75rem;">🧠 محرك مالي استشاري</span>
                        </div>
                        <p style="margin: 0.35rem 0 0; color: var(--text-secondary); font-size: 0.9rem;">
                            يحلل أثر السلعة على سيولتك فوراً، ويرسم لك الآلية الاحترافية الأنسب لشرائها دون تصفير رصيدك أو تدمير ميزانيتك.
                        </p>
                    </div>
                </div>
            </div>

            <div class="leak-inputs" style="grid-template-columns: 1.5fr 1fr; gap: 1rem;">
                <input type="hidden" id="impactSalary" value="{{ $totalSalary }}">
                <input type="hidden" id="impactAppBalance" value="{{ $appSpendableBalance > 0 ? $appSpendableBalance : $appBalance }}">
                <input type="hidden" id="impactSafeDaily" value="{{ $safeDaily }}">
                <input type="hidden" id="impactDaysRemaining" value="{{ $daysRemaining }}">
                
                <div class="form-group">
                    <label>اسم السلعة المرغوبة:</label>
                    <input type="text" class="styled-input" id="impactItemName" value="سلعة جديدة (مثل: هاتف أو جهاز)" placeholder="مثال: حذاء رياضي، لابتوب، بطارية...">
                </div>
                <div class="form-group">
                    <label>سعر السلعة ({{ $settings->currency ?? 'ILS' }}): *</label>
                    <input type="number" class="styled-input large-number" id="impactPrice" value="350" min="1" oninput="calculatePurchaseImpact()">
                </div>
            </div>

            <!-- 4 Key Diagnostics -->
            <div class="impact-results-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin: 0.75rem 0;">
                <div class="impact-stat-box">
                    <span class="impact-stat-label">من إجمالي الراتب الصافي</span>
                    <span class="impact-stat-val" id="impactSalaryPct">9.2%</span>
                </div>
                <div class="impact-stat-box">
                    <span class="impact-stat-label">من سيولة التطبيق النشطة 📱</span>
                    <span class="impact-stat-val" id="impactAppPct" style="color: #3b82f6;">29.2%</span>
                </div>
                <div class="impact-stat-box">
                    <span class="impact-stat-label">تبتلع من المصروف اليومي 🛡️</span>
                    <span class="impact-stat-val" id="impactDaysSwallowed" style="color: #f59e0b;">6.1 يوم</span>
                </div>
                <div class="impact-stat-box" style="border: 2px solid rgba(239, 68, 68, 0.3); background: rgba(239, 68, 68, 0.05);">
                    <span class="impact-stat-label" style="color: #ef4444;">المصروف اليومي الجديد لو اشتريتها الآن 📉</span>
                    <div style="display: flex; align-items: baseline; justify-content: center; gap: 0.35rem;">
                        <span class="impact-stat-val" id="impactNewSafeDaily" style="color: #ef4444;">40</span>
                        <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-secondary);">{{ $settings->currency ?? 'ILS' }}/يوم</span>
                    </div>
                    <span style="font-size: 0.72rem; color: var(--text-muted);" id="impactDailyDropDesc">خصم 17 {{ $settings->currency ?? 'ILS' }} يومياً</span>
                </div>
            </div>

            <!-- Diagnostic Verdict Banner -->
            <div class="impact-verdict-banner impact-verdict-warning" id="impactVerdict" style="margin-bottom: 1rem;">
                ⚠️ <strong>تحليل الأثر:</strong> تستهلك نسبة ملحوظة من سيولتك. راجع الآليات الاحترافية المقترحة أدناه قبل الشراء!
            </div>

            <!-- Professional Buying Mechanism Box -->
            <div class="buying-strategy-box">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <div style="font-weight: 800; font-size: 1rem; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem;">
                        <span>📋</span>
                        <span>الآلية الاحترافية الموصى بها للشراء:</span>
                    </div>
                    <span class="badge-pro" id="strategyBadge" style="background: #f59e0b; font-size: 0.75rem;">🟡 تتطلب التهدئة أو صندوق تحويش</span>
                </div>

                <div class="strategy-paths-grid">
                    <!-- Path 1: 48-Hour Cooldown -->
                    <div class="strategy-path-card" id="cardCooldown">
                        <div class="strategy-card-header">
                            <div class="strategy-card-title">
                                <span>⏳</span>
                                <span>المسار الأول: التهدئة 48 ساعة</span>
                            </div>
                            <span class="badge-pro" style="background: rgba(245, 158, 11, 0.2); color: #f59e0b; font-size: 0.7rem;">للشراء الاندفاعي</span>
                        </div>
                        <div class="strategy-card-desc">
                            70% من رغبات الشراء تتلاشى بعد يومين فقط! انقل السلعة لقائمة التهدئة، وإذا بقيت الرغبة ملحة بعد 48 ساعة، اشتريها بأمان.
                        </div>
                        <button type="button" class="btn-strategy-action btn-cooldown" onclick="sendToWishlistFromImpact()">
                            <span>⏳</span>
                            <span>إرسال لقائمة التهدئة (48 ساعة)</span>
                        </button>
                    </div>

                    <!-- Path 2: Daily Allowance Deduction (الخصم من اليومية) -->
                    <div class="strategy-path-card" id="cardDailyCut">
                        <div class="strategy-card-header">
                            <div class="strategy-card-title">
                                <span>📉</span>
                                <span>المسار الثاني: خصمها من المصروف اليومي</span>
                            </div>
                            <span class="badge-pro" style="background: rgba(59, 130, 246, 0.2); color: #3b82f6; font-size: 0.7rem;">شراء فوري مقلص لليومية</span>
                        </div>
                        <div class="strategy-card-desc" id="dailyCutPlanDesc">
                            اشترِها الآن وادفع ثمنها من ميزانيتك اليومية! سينخفض مسموحك اليومي من <strong>{{ $safeDaily }} {{ $settings->currency ?? 'ILS' }}</strong> إلى <strong style="color: #ef4444;" id="pathDailyCutNew">{{ $safeDaily }} {{ $settings->currency ?? 'ILS' }}</strong> (اقتطاع <strong id="pathDailyCutDrop">0 {{ $settings->currency ?? 'ILS' }}</strong> لكل يوم من الـ {{ $daysRemaining }} يوماً المتبقية).
                        </div>
                        <button type="button" class="btn-strategy-action btn-daily-cut" onclick="buyNowAndDeductDaily()">
                            <span>🛍️</span>
                            <span>تنفيذ الشراء وخصمها من اليومية</span>
                        </button>
                    </div>

                    <!-- Path 3: Sinking Fund -->
                    <div class="strategy-path-card" id="cardSinking">
                        <div class="strategy-card-header">
                            <div class="strategy-card-title">
                                <span>🎯</span>
                                <span>المسار الثالث: صندوق التحويش (Sinking Fund)</span>
                            </div>
                            <span class="badge-pro" style="background: rgba(16, 185, 129, 0.2); color: #10b981; font-size: 0.7rem;">المسار الآمن للأهداف</span>
                        </div>
                        <div class="strategy-card-desc" id="sinkingPlanDesc">
                            لا تقتطع هذا المبلغ من ميزانية شهرك دفعة واحدة ولا تضغط يوميتك! ادخر لها <strong>117 {{ $settings->currency ?? 'ILS' }} شهرياً لمدة 3 أشهر</strong>، واشترِها كاش دون أن ينقص مصروفك اليومي قرشاً واحداً!
                        </div>
                        <button type="button" class="btn-strategy-action btn-sinking" onclick="sendToGoalFromImpact()">
                            <span>🎯</span>
                            <span>إنشاء صندوق تحويش لهذا الهدف</span>
                        </button>
                    </div>
                </div>

                <!-- Cost Per Use Mini Calculator -->
                <div class="cost-per-use-bar">
                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                        <span>🏷️ <strong>حاسبة تكلفة المرة الواحدة (Cost Per Use):</strong> لو استخدمتها</span>
                        <input type="number" id="impactUsageCount" value="60" min="1" max="1000" style="width: 65px; padding: 0.2rem 0.5rem; border-radius: 8px; border: 1px solid var(--card-border); background: var(--card-bg); color: var(--text-primary); text-align: center; font-weight: 700;" oninput="calculatePurchaseImpact()">
                        <span>مرة في السنة ⬅️</span>
                    </div>
                    <div>
                        تكلفة الاستخدام: <strong style="font-family: var(--font-numbers); font-size: 1.05rem; color: #3b82f6;" id="impactCostPerUse">5.8</strong> <span style="font-size: 0.8rem;">{{ $settings->currency ?? 'ILS' }} / لكل مرة</span>
                    </div>
                </div>
            </div>
            <div class="leak-tip" style="margin-top: 1rem;">
                💡 <strong>القاعدة الذهبية:</strong> رصيد الكاش الاحتياطي مجمد للطوارئ القصوى فقط، لا تستخدمه أبداً في المشتريات اليومية أو الرغبات!
            </div>
        </div>

        <!-- 3. 48-Hour Wishlist -->
        <div class="leak-card full-width-card">
            <div class="leak-header">
                <div class="leak-icon">⏳</div>
                <div class="leak-title-actions">
                    <div>
                        <h3 style="font-weight: 800">قائمة "فترة التهدئة 48 ساعة" لمكافحة الشراء الاندفاعي</h3>
                        <p>قبل شراء أي كمالية، أضفها هنا وانتظر 48 ساعة. 70% من النزوات تتلاشى تلقائياً!</p>
                    </div>
                    <button class="btn-secondary" onclick="openModal('wishModal')">
                        ➕ إضافة رغبة للشراء المؤجل
                    </button>
                </div>
            </div>

            <div class="wishlist-saved-badge">
                <span>🛡️ إجمالي المبالغ التي وفرتها بإلغاء نزوات الشراء:</span>
                <strong>{{ number_format($settings->saved_from_impulse ?? 280, 0) }} {{ $settings->currency ?? 'ILS' }}</strong>
            </div>

            <div class="wishlist-container">
                @forelse($wishlist as $w)
                    <div class="wish-item">
                        <div class="wish-item-info">
                            @if($w->status == 'saved')
                                <span class="badge-pro" style="background:#10b981">وفّرت ثمنها 🎉</span>
                            @elseif($w->status == 'bought')
                                <span class="badge-pro" style="background:#ef4444">اشتريتها بعد التفكير</span>
                            @else
                                <div class="wish-countdown">متبقي {{ $w->hours_remaining }} ساعة</div>
                            @endif
                            <div>
                                <h4 style="font-weight: 800">{{ $w->title }}</h4>
                                <span style="font-size: 0.85rem; color: var(--text-secondary)">السعر التقديري: <strong>{{ number_format($w->price, 0) }} {{ $settings->currency ?? 'ILS' }}</strong></span>
                            </div>
                        </div>

                        <div class="wish-actions">
                            @if($w->status == 'pending')
                                <form action="{{ route('wishlist.update', $w) }}" method="POST" style="display:inline">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="saved">
                                    <button type="submit" class="btn-wish-saved">🛡️ صرفت النظر ووفرت المبلغ!</button>
                                </form>
                                <form action="{{ route('wishlist.update', $w) }}" method="POST" style="display:inline">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="bought">
                                    <button type="submit" class="btn-wish-bought">🛍️ قررت الشراء</button>
                                </form>
                            @else
                                <form action="{{ route('wishlist.destroy', $w) }}" method="POST" style="display:inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-subtle">🗑️ حذف</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="empty-state-card">لا توجد رغبات مؤجلة حالياً.</div>
                @endforelse
            </div>
        </div>

        <!-- 4. Subscriptions Manager -->
        <div class="leak-card full-width-card">
            <div class="leak-header">
                <div class="leak-icon">💳</div>
                <div class="leak-title-actions">
                    <div>
                        <h3 style="font-weight: 800">مدير الاشتراكات والخدمات الشهرية المتكررة</h3>
                        <p>تابع اشتراكاتك في نتفلكس، الجيم، والتطبيقات لكشف المصاريف المنسية.</p>
                    </div>
                    <button class="btn-secondary" onclick="openModal('subscriptionModal')">
                        ➕ إضافة اشتراك دوري
                    </button>
                </div>
            </div>

            <div class="subs-summary-bar">
                <div>
                    <span>إجمالي الاشتراكات شهرياً:</span>
                    <strong>{{ number_format($monthlySubsTotal, 0) }} {{ $settings->currency ?? 'ILS' }}</strong>
                </div>
                <div>
                    <span>تكلفتها الإجمالية سنوياً:</span>
                    <strong style="color: #f59e0b">{{ number_format($yearlySubsTotal, 0) }} {{ $settings->currency ?? 'ILS' }}</strong>
                </div>
            </div>

            <div class="subs-grid">
                @forelse($subscriptions as $sub)
                    <div class="sub-item-card">
                        <div>
                            <h4 style="font-weight: 700; font-size: 0.95rem">{{ $sub->name }}</h4>
                            <span style="font-size: 0.75rem; color: var(--text-muted)">التجديد: {{ $sub->period == 'monthly' ? 'شهرياً' : 'سنوياً' }}</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.75rem">
                            <strong style="color: var(--accent-gold); font-family: var(--font-numbers)">{{ number_format($sub->cost, 0) }} {{ $settings->currency ?? 'ILS' }}</strong>
                            <form action="{{ route('subscriptions.destroy', $sub) }}" method="POST" style="display:inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-delete-tx" title="حذف">🗑️</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="empty-state-card full-width-card">لا توجد اشتراكات مضافة بعد.</div>
                @endforelse
            </div>
        </div>
    </div>
</section>

<!-- ============================================== -->
<!-- TAB 5: TRANSACTIONS LEDGER -->
<!-- ============================================== -->
<section class="tab-pane {{ ($activeTab ?? '') === 'tab-ledger' ? 'active' : '' }}" id="tab-ledger">
    <div class="section-action-header">
        <div>
            <h2 style="font-size: 1.35rem; font-weight: 800">📝 سجل المعاملات والمصروفات</h2>
            <p class="section-desc">عرض تفصيلي لجميع الحركات المالية المخزنة في MySQL.</p>
        </div>
        <div class="ledger-top-actions" style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;">
            <a href="{{ route('transactions.exportPdf') }}" target="_blank" class="btn-ledger-pdf" style="display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.62rem 1.15rem; background: linear-gradient(135deg, rgba(59, 130, 246, 0.16), rgba(37, 99, 235, 0.22)); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.45); border-radius: 12px; font-weight: 800; font-size: 0.88rem; text-decoration: none; transition: all 0.2s ease; box-shadow: 0 2px 10px rgba(59, 130, 246, 0.2);" title="تصدير كشف حساب وحركات جدول المعاملات كتقرير PDF رسمي قابل للطباعة والحفظ">
                <span style="font-size: 1rem;">📄</span>
                <span>تصدير تقرير PDF</span>
            </a>
            <button class="btn-primary" onclick="openTxModal('expense')">
                ➕ إضافة حركة جديدة
            </button>
        </div>
    </div>

    <div class="ledger-table-wrapper">
        <table class="ledger-table">
            <thead>
                <tr>
                    <th>التاريخ</th>
                    <th>نوع الحركة</th>
                    <th>الفئة / الظرف</th>
                    <th>الوصف / الملاحظة</th>
                    <th>وسيلة الدفع</th>
                    <th>المبلغ</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody id="ledgerTableBody">
                @forelse($allTransactions as $tx)
                    <tr data-tx-id="{{ $tx->id }}" id="ledger-tx-row-{{ $tx->id }}">
                        <td style="font-family: var(--font-numbers); font-weight: 600">{{ $tx->date->format('Y-m-d') }}</td>
                        <td>
                            @if($tx->type == 'expense') <span style="color:#ef4444; font-weight:700">🔴 مصروف</span>
                            @elseif($tx->type == 'income') <span style="color:#10b981; font-weight:700">🟢 دخل إضافي</span>
                            @else <span style="color:#f59e0b; font-weight:700">🔵 تحويل لحصالة</span>
                            @endif
                        </td>
                        <td><strong>{{ ($tx->type === 'savings' && $tx->goal) ? '🎯 '.$tx->goal->name : ($tx->envelope ? $tx->envelope->icon.' '.$tx->envelope->name : 'عام') }}</strong></td>
                        <td>
                            {{ $tx->note ?? '—' }}
                            @if($tx->user)
                                <small style="display: block; color: #94a3b8; font-size: 0.73rem; margin-top: 0.2rem;">👤 {{ $tx->user->name }}</small>
                            @endif
                        </td>
                        <td style="font-size: 0.85rem">
                            {{ $tx->payment_method == 'cash' ? '💵 كاش' : ($tx->payment_method == 'transfer' ? '📱 تطبيق' : ($tx->payment_method == 'card' ? '💳 بطاقة' : '📑 دين/آجل')) }}
                        </td>
                        <td class="tx-amount-display {{ $tx->type }}">
                            {{ $tx->type == 'income' ? '+' : ($tx->type == 'savings' ? '🪙' : '-') }} {{ number_format($tx->amount, 2) }} {{ $settings->currency ?? 'SAR' }}
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.35rem;">
                                <button type="button" class="btn-edit-tx" onclick='openEditTxModal(@json($tx))' title="تعديل المعاملة">✏️</button>
                                <form action="{{ route('transactions.destroy', $tx) }}" method="POST" onsubmit="return confirmDeleteForm(event, 'هل أنت متأكد من حذف هذه المعاملة؟ سيتم تحديث الرصيد ومتبقي اليومية فوراً.', 'تأكيد حذف المعاملة')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-delete-tx" title="حذف">🗑️</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="empty-state-card">لا توجد حركات مسجلة بعد.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<!-- ============================================== -->
<!-- TAB 6: DEBTS & PAYABLES (دفتر الديون والذمم المؤجلة) -->
<!-- ============================================== -->
<section class="tab-pane {{ ($activeTab ?? '') === 'tab-debts' ? 'active' : '' }}" id="tab-debts">
    <!-- Debts Overview Banner -->
    <div class="debts-summary-banner">
        <div class="debts-summary-info">
            <div class="debts-summary-icon">📑</div>
            <div>
                <h3 class="debts-summary-title">دفتر الديون والذمم المؤجلة (شكك / سلف)</h3>
                <p class="debts-summary-desc">متابعة حسابات المشتريات بالشكك من السوبرماركت والسلف المالية وسدادها بنقرة واحدة عند توفر السيولة.</p>
            </div>
        </div>

        <div class="debts-summary-stats">
            <div class="debt-stat-box">
                <span class="debt-stat-label">إجمالي الديون المستحقة:</span>
                <span class="debt-stat-val {{ $totalDebtsOwed <= 0 ? 'settled' : '' }}">
                    {{ number_format($totalDebtsOwed, 0) }} {{ $settings->currency ?? 'ILS' }}
                </span>
            </div>
            <div class="debt-stat-box">
                <span class="debt-stat-label">الحسابات المفتوحة:</span>
                <span style="font-size: 1.25rem; font-weight: 800; color: #f9fafb;">{{ $activeDebtsCount }} حساب</span>
            </div>
        </div>

        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <button type="button" class="btn-primary" onclick="openModal('newDebtModal')">
                <span>➕</span> <span>فتح حساب دين جديد</span>
            </button>
            @if($debts->count() > 0)
                <button type="button" class="btn-secondary" onclick="openModal('addDebtRecordModal')" style="background: rgba(255, 255, 255, 0.08); color: #fff; border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 10px; padding: 0.6rem 1rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem;">
                    <span>🛒</span> <span>تسجيل شراء بالدين (شكك)</span>
                </button>
            @endif
        </div>
    </div>

    <!-- COMPLETED DEBT HARVEST CELEBRATION BANNER (TAB DEBTS) -->
    @if(isset($completedHarvestDebts) && $completedHarvestDebts->count() > 0)
        @foreach($completedHarvestDebts as $chd)
            <div class="debt-harvest-complete-banner">
                <div class="harvest-banner-content">
                    <div class="harvest-banner-icon">🎉</div>
                    <div>
                        <div class="harvest-banner-title">اكتمل تجميع سداد دين [{{ $chd->name }}] بالكامل من الفوائض!</div>
                        <div class="harvest-banner-desc">
                            نجحت الخطة الذكية! تم تجميع <strong>{{ number_format($chd->surplus_harvested, 2) }} {{ $settings->currency ?? 'ILS' }}</strong> من فوائض يومياتك السابقة بهدوء ودون أي ضغط مالي. يمكنك الآن سداد الدين وتصفية الحساب بنقرة واحدة!
                        </div>
                    </div>
                </div>
                <div class="harvest-banner-actions">
                    <form action="{{ route('debts.harvest.repay', $chd) }}" method="POST" onsubmit="return confirmActionForm(event, { title: 'سداد الدين من الحصالة', message: '🎉 هل تود سداد هذا الدين وتصفيته بالكامل من مبالغ الحصالة المجمعة؟', confirmText: 'نعم، سداد وتصفية الدين', icon: '💳', isDanger: false })">
                        @csrf
                        <button type="submit" class="btn-primary" style="background: linear-gradient(135deg, #10b981, #059669); font-weight: 800; padding: 0.65rem 1.25rem;">
                            <span>💳</span> <span>سداد الدين الآن وإغلاقه فوراً</span>
                        </button>
                    </form>
                    <button type="button" class="btn-subtle" onclick="openHarvestLogsModal({{ $chd->id }})" style="background: rgba(255,255,255,0.08); color: #fff; padding: 0.65rem 1rem;">
                        <span>📜</span> <span>عرض تفاصيل وسجل التجميع</span>
                    </button>
                </div>
            </div>
        @endforeach
    @endif

    <!-- Debts Grid -->
    @if($debts->count() > 0)
        <div class="debts-grid">
            @foreach($debts as $d)
                <div class="debt-card {{ $d->total_debt <= 0 ? 'is-settled' : '' }}">
                    <div class="debt-card-header">
                        <div class="debt-creditor-info">
                            <div class="debt-creditor-icon">{{ $d->icon }}</div>
                            <div>
                                <div class="debt-creditor-name">{{ $d->name }}</div>
                                <div class="debt-creditor-type">{{ $d->type_label }}</div>
                            </div>
                        </div>

                        <div style="display: flex; align-items: center; gap: 0.4rem;">
                            @if($d->total_debt > 0)
                                <span class="debt-status-badge active">⏳ مستحق</span>
                            @else
                                <span class="debt-status-badge settled">✓ خالص</span>
                            @endif

                            <form action="{{ route('debts.destroy', $d) }}" method="POST" onsubmit="return confirmDeleteForm(event, 'هل تريد حذف حساب الدين هذا وسجلاته؟', 'تأكيد حذف الدين')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-delete-tx" title="حذف الحساب">🗑️</button>
                            </form>
                        </div>
                    </div>

                    <!-- Balance Display -->
                    <div class="debt-balance-block">
                        <span class="debt-balance-label">المبلغ المستحق حالياً:</span>
                        <span class="debt-balance-amount">
                            {{ number_format($d->total_debt, 2) }} {{ $settings->currency ?? 'ILS' }}
                        </span>
                    </div>

                    <!-- Harvester Pot Card / Activation -->
                    @if($d->is_harvest_active)
                        <div class="debt-harvest-pot-card">
                            <div class="harvest-pot-header">
                                <span style="font-weight: 700; color: #10b981; display: flex; align-items: center; gap: 0.35rem;">
                                    <span>🍯</span>
                                    <span>حصالة الفوائض: <strong>{{ number_format($d->surplus_harvested, 1) }}</strong> / {{ number_format($d->total_debt, 1) }} {{ $settings->currency ?? 'ILS' }}</span>
                                </span>
                                <span class="badge-pro" style="background: rgba(16, 185, 129, 0.2); color: #34d399; font-size: 0.75rem;">
                                    {{ $d->harvest_progress_percent }}%
                                </span>
                            </div>

                            <div class="harvest-pot-progress-wrap">
                                <div class="harvest-pot-progress-fill" style="width: {{ min(100, $d->harvest_progress_percent) }}%;"></div>
                            </div>

                            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem; color: #9ca3af; flex-wrap: wrap; gap: 0.25rem;">
                                <span>استقطاع {{ (int)(($d->harvest_ratio ?: 0.5) * 100) }}% من فائض اليومية</span>
                                <span class="harvest-forecast-pill">
                                    <span>🌙</span> <span>الرصد يعمل تلقائياً كل منتصف ليل</span>
                                </span>
                            </div>

                            <div style="display: flex; gap: 0.4rem; margin-top: 0.3rem; flex-wrap: wrap;">
                                <button type="button" class="btn-subtle" onclick="openHarvestLogsModal({{ $d->id }})" style="flex: 1; padding: 0.35rem 0.5rem; font-size: 0.78rem; font-weight: 700;">
                                    <span>📜</span> <span>سجل الرصد والشفافية</span>
                                </button>

                                <form action="{{ route('debts.harvest.toggle', $d) }}" method="POST" style="display: inline;">
                                    @csrf
                                    <button type="submit" class="btn-subtle" style="padding: 0.35rem 0.6rem; font-size: 0.78rem;" title="{{ $d->is_harvest_active ? 'إيقاف مؤقت للرصد اليومي' : 'استئناف الرصد اليومي' }}">
                                        {{ $d->is_harvest_active ? '⏸️' : '▶️' }}
                                    </button>
                                </form>

                                <form action="{{ route('debts.harvest.reset', $d) }}" method="POST" style="display: inline;" onsubmit="return confirmActionForm(event, { title: 'تصفير الحصالة والبدء من اليوم', message: 'هل أنت متأكد من تصفير حصالة الفائض والبدء في رصد الفوائض اعتباراً من اليوم؟ (سيتم تصفير الرصيد ومسح الأيام السابقة للبدء من صفر)', confirmText: 'نعم، تصفير والبدء من اليوم', icon: '🔄', isDanger: true })">
                                    @csrf
                                    <button type="submit" class="btn-subtle" style="padding: 0.35rem 0.6rem; font-size: 0.78rem; color: #f87171;" title="تصفير الحصالة وسجلاتها والبدء من اليوم">
                                        🔄
                                    </button>
                                </form>

                                @if($d->is_harvest_complete)
                                    <form action="{{ route('debts.harvest.repay', $d) }}" method="POST" style="display: inline;" onsubmit="return confirmActionForm(event, { title: 'سداد الدين من الحصالة', message: '🎉 هل تود سداد هذا الدين وتصفيته بالكامل من مبالغ الحصالة المجمعة؟', confirmText: 'نعم، سداد وتصفية الدين', icon: '💳', isDanger: false })">
                                        @csrf
                                        <button type="submit" class="btn-primary" style="padding: 0.35rem 0.75rem; font-size: 0.78rem; font-weight: 700; background: linear-gradient(135deg, #10b981, #059669);">
                                            🎉 سداد فوري من الحصالة
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @elseif($d->total_debt > 0)
                        <div style="margin-top: 0.4rem;">
                            <button type="button" class="btn-activate-harvest" onclick="openActivateHarvestModal({{ $d->id }}, '{{ addslashes($d->name) }}', {{ $d->total_debt }})" style="width: 100%; background: rgba(16, 185, 129, 0.1); border: 1px dashed rgba(16, 185, 129, 0.4); color: #34d399; border-radius: 10px; padding: 0.5rem 0.75rem; font-size: 0.8rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.4rem; transition: all 0.2s;">
                                <span>☕</span>
                                <span>تفعيل مقتنص فوائض اليومية (سداد مريح غير محسوس)</span>
                            </button>
                        </div>
                    @endif

                    <!-- Quick Actions -->
                    <div class="debt-actions-row">
                        <button type="button" class="btn-debt-add" onclick="openAddDebtForSpecific({{ $d->id }}, '{{ addslashes($d->name) }}')">
                            <span>🛒</span> <span>إضافة شكك</span>
                        </button>

                        @if($d->total_debt > 0)
                            <button type="button" class="btn-debt-repay" onclick="openRepayDebtModal({{ $d->id }}, '{{ addslashes($d->name) }}', {{ $d->total_debt }})">
                                <span>💳</span> <span>سداد الدفعة</span>
                            </button>
                        @endif
                    </div>

                    <!-- Recent 3 records preview -->
                    @if($d->records->count() > 0)
                        <div class="debt-history-preview">
                            <div class="debt-history-title">آخر العمليات على الحساب:</div>
                            @foreach($d->records->take(3) as $r)
                                <div class="debt-record-item" style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;">
                                    <span style="flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $r->type == 'add_debt' ? '➕ أخذ بضاعة/سلفة' : '💳 سداد نقدي' }} ({{ $r->note ?: '—' }})</span>
                                    <div style="display: flex; align-items: center; gap: 0.35rem; flex-shrink: 0;">
                                        <span class="rec-amount {{ $r->type == 'add_debt' ? 'add' : 'repay' }}">
                                            {{ $r->type == 'add_debt' ? '+' : '-' }}{{ number_format($r->amount, 1) }}
                                        </span>
                                        <form action="{{ route('debts.records.destroy', $r) }}" method="POST" style="display: inline; margin: 0;" onsubmit="return confirmDeleteForm(event, 'هل أنت متأكد من حذف هذه العملية والتراجع عنها؟ سيتم إعادة احتساب رصيد الدين فوراً.', 'تأكيد التراجع عن العملية')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-del-record" title="حذف هذه العملية والتراجع عنها" style="background: transparent; border: none; color: #ef4444; font-size: 0.85rem; cursor: pointer; padding: 2px 4px; border-radius: 4px; opacity: 0.75; transition: opacity 0.2s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.75'">🗑️</button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <div class="empty-state-card" style="text-align: center; padding: 3rem 1.5rem; background: var(--card-bg); border-radius: 16px; border: 1px dashed var(--border);">
            <div style="font-size: 3.5rem; margin-bottom: 0.75rem;">🛒</div>
            <h3 style="font-size: 1.25rem; font-weight: 800; color: #f9fafb; margin-bottom: 0.4rem;">لا توجد حسابات ديون أو شكك مضافة بعد</h3>
            <p style="color: var(--text-secondary); max-width: 480px; margin: 0 auto 1.25rem; font-size: 0.9rem; line-height: 1.6;">
                يمكنك فتح حساب لأي سوبرماركت تشتري منه بالشكك وتدفع له لاحقاً، أو سلفة مالية من صديق، لتسجيل ما تأخذه وسداده دفعة واحدة بدقة وسهولة.
            </p>
            <button type="button" class="btn-primary" onclick="openModal('newDebtModal')">
                <span>➕</span> <span>فتح حساب دين جديد الآن</span>
            </button>
        </div>
    @endif
</section>

<!-- ============================================== -->
<!-- MODALS -->
<!-- ============================================== -->

<!-- 1. Transaction Modal -->
<div class="modal-backdrop hidden" id="transactionModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="txModalTitle">تسجيل معاملة مالية جديدة</h3>
            <button class="modal-close-btn" onclick="closeModal('transactionModal')">&times;</button>
        </div>
        <form action="{{ route('transactions.store') }}" method="POST" class="modal-form" id="txForm">
            @csrf
            <div class="form-type-pills">
                <label class="type-pill active" onclick="setTxType('expense')">
                    <input type="radio" name="type" value="expense" checked>
                    <span>🔴 مصروف</span>
                </label>
                <label class="type-pill" onclick="setTxType('income')">
                    <input type="radio" name="type" value="income">
                    <span>🟢 دخل / يومية</span>
                </label>
                <label class="type-pill" onclick="setTxType('savings')">
                    <input type="radio" name="type" value="savings">
                    <span>🔵 تحويش</span>
                </label>
            </div>

            <div class="form-group">
                <label>المبلغ ({{ $settings->currency ?? 'ILS' }}): *</label>
                <input type="number" name="amount" id="txAmountInput" class="styled-input large-number" step="0.01" min="0.01" required placeholder="0.00" oninput="updateLiveDailyImpact(this.value)">
                
                <!-- Live Daily Impact Calculator Preview -->
                <div class="daily-impact-live-box" id="dailyImpactLiveBox">
                    <div class="impact-live-header">
                        <span class="impact-icon">⚡</span>
                        <span class="impact-title">التأثير اللحظي على يوميتك اليوم:</span>
                    </div>
                    <div class="impact-live-flow">
                        <div class="impact-step">
                            <span class="step-label">المتبقي من يوميتك الآن</span>
                            <span class="step-val current" id="impactLiveCurrent">{{ number_format($todayRemaining, 0) }} {{ $settings->currency ?? 'ILS' }}</span>
                        </div>
                        <div class="impact-arrow">⬅️</div>
                        <div class="impact-step">
                            <span class="step-label">المتبقي بعد هذا المصروف</span>
                            <span class="step-val next" id="impactLiveNext">{{ number_format($todayRemaining, 0) }} {{ $settings->currency ?? 'ILS' }}</span>
                        </div>
                    </div>
                    <div class="impact-live-note" id="impactLiveNote">
                        🟢 أدخل المبلغ لمعاينة كيف ستتأثر يوميتك لحظياً
                    </div>
                </div>
            </div>

            <div class="form-group" id="txEnvGroup">
                <label>الفئة أو الظرف:</label>
                <select name="envelope_id" id="txEnvelopeSelect" class="styled-select">
                    @foreach($envelopes as $e)
                        <option value="{{ $e->id }}">{{ $e->icon }} {{ $e->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group hidden" id="txGoalGroup">
                <label>هدف التحويش:</label>
                <select name="goal_id" id="txGoalSelect" class="styled-select" disabled>
                    <option value="">-- اختر الهدف --</option>
                    @foreach($goals as $g)
                        <option value="{{ $g->id }}">{{ $g->icon }} {{ $g->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>التاريخ: *</label>
                    <input type="date" name="date" class="styled-input" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="form-group">
                    <label>الملاحظة:</label>
                    <input type="text" name="note" id="txNoteInput" class="styled-input" placeholder="تفاصيل اختيارية...">
                </div>
            </div>

            <div class="form-group" id="txPaymentMethodGroup">
                <label id="txNoteLabel">من أين تدفع؟ (مصدر الرصيد)</label>
                <input type="hidden" name="payment_method" id="txPaymentMethodInput" value="transfer">
                <div class="payment-method-pills" id="txPaymentPills">
                    <button type="button" class="pay-pill active" data-value="transfer" onclick="setPaymentMethod('transfer')">
                        <span class="pay-pill-icon">📱</span>
                        <span class="pay-pill-label">تطبيق</span>
                        <span class="pay-pill-sub">رصيد حي</span>
                    </button>
                    <button type="button" class="pay-pill" data-value="cash" onclick="setPaymentMethod('cash')">
                        <span class="pay-pill-icon">💵</span>
                        <span class="pay-pill-label">كاش</span>
                        <span class="pay-pill-sub">مجمد</span>
                    </button>
                    <button type="button" class="pay-pill" data-value="card" onclick="setPaymentMethod('card')">
                        <span class="pay-pill-icon">💳</span>
                        <span class="pay-pill-label">بطاقة</span>
                        <span class="pay-pill-sub">صراف</span>
                    </button>
                    <button type="button" class="pay-pill" data-value="credit" onclick="setPaymentMethod('credit')">
                        <span class="pay-pill-icon">📑</span>
                        <span class="pay-pill-label">دين</span>
                        <span class="pay-pill-sub">مؤجل</span>
                    </button>
                </div>
                <div id="txPaymentHint" class="payment-hint-bar hint-transfer">
                    📱 سيُخصم من <strong>رصيد التطبيق الفعّال</strong> (السيولة الحية)
                </div>
            </div>

            <div id="txIncomeQuickTags" style="display: none; flex-wrap: wrap; gap: 0.35rem;">
                <button type="button" class="btn-income-tag" onclick="setTxNote('يومية عمل 🛠️')">🛠️ يومية</button>
                <button type="button" class="btn-income-tag" onclick="setTxNote('كود مساعدة تموين 📦')">📦 كود تموين</button>
                <button type="button" class="btn-income-tag" onclick="setTxNote('مساعدة نقدية 💵')">💵 مساعدة</button>
                <button type="button" class="btn-income-tag" onclick="setTxNote('عمل حر / إضافي 💼')">💼 عمل إضافي</button>
            </div>
        </form>
        <div class="modal-actions">
            <button type="button" class="btn-subtle" onclick="closeModal('transactionModal')">إلغاء</button>
            <button type="submit" form="txForm" class="btn-primary">💾 حفظ المعاملة</button>
        </div>
    </div>
</div>

<!-- Edit Transaction Modal -->
<div class="modal-backdrop hidden" id="editTransactionModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>✏️ تعديل المعاملة المالية</h3>
            <button class="modal-close-btn" onclick="closeModal('editTransactionModal')">&times;</button>
        </div>
        <form id="editTxForm" action="" method="POST" class="modal-form">
            @csrf
            @method('PUT')

            <div class="form-type-pills">
                <label class="type-pill" onclick="setEditTxType('expense')">
                    <input type="radio" name="type" value="expense">
                    <span>🔴 مصروف</span>
                </label>
                <label class="type-pill" onclick="setEditTxType('income')">
                    <input type="radio" name="type" value="income">
                    <span>🟢 دخل / يومية</span>
                </label>
                <label class="type-pill" onclick="setEditTxType('savings')">
                    <input type="radio" name="type" value="savings">
                    <span>🔵 تحويش</span>
                </label>
            </div>

            <div class="form-group">
                <label>المبلغ ({{ $settings->currency ?? 'SAR' }}): *</label>
                <input type="number" name="amount" id="editTxAmount" class="styled-input large-number" step="0.01" min="0.01" required placeholder="0.00">
            </div>

            <div class="form-group" id="editTxEnvGroup">
                <label>الفئة أو الظرف:</label>
                <select name="envelope_id" id="editTxEnvelopeSelect" class="styled-select">
                    <option value="">-- بدون ظرف (عام) --</option>
                    @foreach($envelopes as $e)
                        <option value="{{ $e->id }}">{{ $e->icon }} {{ $e->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group hidden" id="editTxGoalGroup">
                <label>هدف التحويش:</label>
                <select name="goal_id" id="editTxGoalSelect" class="styled-select">
                    <option value="">-- اختر الهدف --</option>
                    @foreach($goals as $g)
                        <option value="{{ $g->id }}">{{ $g->icon }} {{ $g->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>التاريخ: *</label>
                    <input type="date" name="date" id="editTxDate" class="styled-input" required>
                </div>
                <div class="form-group">
                    <label>الملاحظة / البيان:</label>
                    <input type="text" name="note" id="editTxNoteInput" class="styled-input" placeholder="تفاصيل المعاملة...">
                </div>
            </div>

            <div class="form-group" id="editTxPaymentMethodGroup">
                <label>من أين تدفع؟ (مصدر الرصيد)</label>
                <input type="hidden" name="payment_method" id="editTxPaymentMethodInput" value="transfer">
                <div class="payment-method-pills" id="editTxPaymentPills">
                    <button type="button" class="pay-pill" data-value="transfer" onclick="setEditPaymentMethod('transfer')">
                        <span class="pay-pill-icon">📱</span>
                        <span class="pay-pill-label">تطبيق</span>
                        <span class="pay-pill-sub">رصيد حي</span>
                    </button>
                    <button type="button" class="pay-pill" data-value="cash" onclick="setEditPaymentMethod('cash')">
                        <span class="pay-pill-icon">💵</span>
                        <span class="pay-pill-label">كاش</span>
                        <span class="pay-pill-sub">مجمد</span>
                    </button>
                    <button type="button" class="pay-pill" data-value="card" onclick="setEditPaymentMethod('card')">
                        <span class="pay-pill-icon">💳</span>
                        <span class="pay-pill-label">بطاقة</span>
                        <span class="pay-pill-sub">صراف</span>
                    </button>
                    <button type="button" class="pay-pill" data-value="credit" onclick="setEditPaymentMethod('credit')">
                        <span class="pay-pill-icon">📑</span>
                        <span class="pay-pill-label">دين</span>
                        <span class="pay-pill-sub">مؤجل</span>
                    </button>
                </div>
                <div id="editTxPaymentHint" class="payment-hint-bar hint-transfer">
                    📱 سيُخصم من <strong>رصيد التطبيق الفعّال</strong> (السيولة الحية)
                </div>
            </div>
        </form>
        <div class="modal-actions">
            <button type="button" class="btn-subtle" onclick="closeModal('editTransactionModal')">إلغاء</button>
            <button type="submit" form="editTxForm" class="btn-primary">💾 حفظ التعديلات</button>
        </div>
    </div>
</div>

<!-- Edit Envelope Modal -->
<div class="modal-backdrop hidden" id="editEnvelopeModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>✏️ تعديل الظرف</h3>
            <button class="modal-close-btn" onclick="closeModal('editEnvelopeModal')">&times;</button>
        </div>
        <form id="editEnvelopeForm" method="POST" class="modal-form">
            @csrf
            @method('PUT')

            <div class="form-row">
                <div class="form-group">
                    <label>اسم الظرف: *</label>
                    <input type="text" name="name" id="editEnvName" class="styled-input" required placeholder="مثال: مقاضي، صحة...">
                </div>
                <div class="form-group">
                    <label>الأيقونة (emoji):</label>
                    <input type="text" name="icon" id="editEnvIcon" class="styled-input" placeholder="📦" maxlength="4">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>سقف الميزانية (اختياري - اتركه 0 لتتبع المصروف فقط):</label>
                    <input type="number" name="budget" id="editEnvBudget" class="styled-input large-number" step="0.01" min="0" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>اللون:</label>
                    <input type="color" name="color" id="editEnvColor" class="styled-input" style="padding:0.3rem; height:2.6rem; cursor:pointer;">
                </div>
            </div>

            <div class="form-group">
                <label>تصنيف الظرف (قاعدة 50/30/20):</label>
                <div class="form-type-pills" id="editEnvPillarPills">
                    <label class="type-pill" onclick="setEditPillar('needs')">
                        <input type="radio" name="pillar" value="needs">
                        <span>🏠 أساسيات (50%)</span>
                    </label>
                    <label class="type-pill" onclick="setEditPillar('wants')">
                        <input type="radio" name="pillar" value="wants">
                        <span>🎯 كماليات (30%)</span>
                    </label>
                    <label class="type-pill" onclick="setEditPillar('savings')">
                        <input type="radio" name="pillar" value="savings">
                        <span>💰 تحويش (20%)</span>
                    </label>
                </div>
            </div>
        </form>
        <div class="modal-actions">
            <button type="button" class="btn-subtle" onclick="closeModal('editEnvelopeModal')">إلغاء</button>
            <button type="submit" form="editEnvelopeForm" class="btn-primary">💾 حفظ التعديلات</button>
        </div>
    </div>
</div>

<!-- 2. Settings Modal (Multi-Salary & Cycle Settings) -->
<div class="modal-backdrop hidden" id="salarySettingsModal">
    <div class="modal-card modal-card-lg">
        <div class="modal-header">
            <div>
                <h3 style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.25rem;">
                    <span>⚙️</span>
                    <span>إعدادات الرواتب والدورة الشهرية</span>
                </h3>
                <p style="font-size:0.82rem; color:var(--text-secondary); margin:0;">
                    إدارة مصادر الرواتب المتعددة، مواعيد القبض في الشهر، وضبط دورة الميزانية باحترافية
                </p>
            </div>
            <button class="modal-close-btn" onclick="closeModal('salarySettingsModal')">&times;</button>
        </div>

        <div class="modal-subtabs-wrap">
            <div class="salary-tabs-switcher">
                <button type="button" class="salary-tab-btn active" id="tabBtnSalariesList" onclick="switchSalarySubTab('list')">
                    <span>💼</span>
                    <span class="tab-txt-full">مصادر الرواتب والتدفقات ({{ $salarySources->count() }})</span>
                    <span class="tab-txt-short">مصادر الرواتب ({{ $salarySources->count() }})</span>
                </button>
                <button type="button" class="salary-tab-btn" id="tabBtnSalaryCycle" onclick="switchSalarySubTab('cycle')">
                    <span>📅</span>
                    <span class="tab-txt-full">ضبط الدورة الشهرية والميزانية</span>
                    <span class="tab-txt-short">الدورة والميزانية</span>
                </button>
            </div>
        </div>

        <div class="modal-body-scrollable">
            <!-- SECTION 1: SALARIES LIST & ADD FORM -->
            <div id="salarySectionList">
                <!-- Summary banner -->
                <div class="salary-summary-banner" style="margin-bottom: 1.25rem;">
                    <div class="salary-sum-stat">
                        <span class="salary-sum-label">💰 إجمالي الرواتب الشهرية</span>
                        <span class="salary-sum-val">{{ number_format($totalSalary, 0) }} {{ $settings->currency ?? 'ILS' }}</span>
                    </div>
                    <div class="salary-sum-stat">
                        <span class="salary-sum-label">📱 نصيب التطبيق (سيولة تشغيلية)</span>
                        <span class="salary-sum-val" style="color: #3b82f6;">{{ number_format($appSalariesTotal, 0) }} {{ $settings->currency ?? 'ILS' }}</span>
                    </div>
                    <div class="salary-sum-stat">
                        <span class="salary-sum-label">💵 نصيب الكاش (مجمد احتياطاً)</span>
                        <span class="salary-sum-val" style="color: #10b981;">{{ number_format($cashSalariesTotal, 0) }} {{ $settings->currency ?? 'ILS' }}</span>
                    </div>
                </div>

                <!-- Existing Salaries List -->
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                    <h4 style="font-size: 0.95rem; font-weight: 800; color: var(--text-primary); margin: 0;">
                        📋 مصادر الدخل والرواتب المعتمدة:
                    </h4>
                    <span style="font-size: 0.8rem; color: var(--text-muted);">تُجمع تلقائياً في الميزانية</span>
                </div>

                <div class="salary-list-grid" style="margin-bottom: 1.5rem;">
                    @forelse($salarySources as $source)
                        <div class="salary-item-card">
                            <div class="salary-item-main">
                                <div class="salary-item-icon">
                                    @if($source->payment_method === 'cash')
                                        💵
                                    @elseif($source->payment_method === 'card')
                                        💳
                                    @else
                                        📱
                                    @endif
                                </div>
                                <div class="salary-item-info">
                                    <span class="salary-item-name">{{ $source->name }}</span>
                                    <div class="salary-item-badges">
                                        <span class="salary-badge {{ $source->payment_method === 'cash' ? 'salary-badge-cash' : 'salary-badge-method' }}">
                                            {{ $source->payment_method_label }}
                                        </span>
                                        <span class="salary-badge salary-badge-day">
                                            📅 يوم {{ $source->day_of_month }} من الشهر
                                            @if($source->expected_date)
                                                ({{ $source->expected_date->format('Y-m-d') }})
                                            @endif
                                        </span>
                                        <span class="salary-badge" style="background: rgba(255,255,255,0.06); color: {{ $source->status_meta['color'] }};">
                                            {{ $source->status_meta['badge'] }}
                                        </span>
                                    </div>
                                    @if($source->notes)
                                        <span style="font-size: 0.78rem; color: var(--text-secondary); margin-top: 0.2rem;">
                                            📝 {{ $source->notes }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="salary-item-amount-col">
                                <span class="salary-item-amount">
                                    {{ number_format($source->amount, 2) }} {{ $source->currency }}
                                </span>
                                @if(strtoupper($source->currency) !== 'ILS')
                                    <span class="salary-item-equiv">
                                        ≈ {{ number_format($source->amount_in_shekel, 2) }} ₪
                                    </span>
                                @endif
                                <div class="salary-item-actions" style="margin-top: 0.35rem;">
                                    <button type="button" class="btn-icon-action" title="تعديل الراتب" onclick='openEditSalaryModal(@json($source))'>
                                        ✏️
                                    </button>
                                    <form action="{{ route('salaries.destroy', $source) }}" method="POST" onsubmit="return confirmDeleteForm(event, 'هل أنت متأكد من حذف مصدر الراتب هذا؟', 'تأكيد حذف مصدر الراتب')" style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-icon-action danger" title="حذف الراتب">
                                            🗑️
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="empty-state-card" style="padding: 1.5rem; text-align: center;">
                            لم يتم إضافة أي مصادر رواتب بعد. أضف راتبك الأول بالأسفل! 👇
                        </div>
                    @endforelse
                </div>

                <!-- Add Salary Form -->
                <div class="salary-add-box">
                    <div class="salary-add-title">
                        <span>➕</span>
                        <span>إضافة راتب أو مصدر دخل جديد:</span>
                    </div>

                    <form action="{{ route('salaries.store') }}" method="POST" class="modal-form" style="padding: 0; gap: 1rem;">
                        @csrf
                        <!-- 1. Source Name (Full Width) -->
                        <div class="form-group">
                            <label>مسمى الراتب / جهة الدخل: *</label>
                            <input type="text" name="name" class="styled-input" required placeholder="مثال: راتب الوظيفة الأساسية، عمل إضافي، متجر إلكتروني...">
                        </div>

                        <!-- 2. Amount & Currency (Together on Row 2) -->
                        <div class="form-row">
                            <div class="form-group">
                                <label>المبلغ: *</label>
                                <input type="number" step="0.01" name="amount" class="styled-input large-number" required placeholder="0.00">
                            </div>
                            <div class="form-group">
                                <label>العملة:</label>
                                <select name="currency" class="styled-select">
                                    <option value="ILS" selected>🇵🇸 شيكل (ILS)</option>
                                    <option value="USD">🇺🇸 دولار (USD)</option>
                                    <option value="JOD">🇯🇴 دينار (JOD)</option>
                                    <option value="SAR">🇸🇦 ريال (SAR)</option>
                                    <option value="AED">🇦🇪 درهم (AED)</option>
                                    <option value="EGP">🇪🇬 جنيه (EGP)</option>
                                    <option value="EUR">🇪🇺 يورو (EUR)</option>
                                </select>
                            </div>
                        </div>

                        <!-- 3. Payment Method & Day of Month (Together on Row 3) -->
                        <div class="form-row">
                            <div class="form-group">
                                <label>طريقة القبض / الاستلام: *</label>
                                <select name="payment_method" class="styled-select" required>
                                    <option value="transfer">📱 تطبيق إلكتروني / بنكي (سيولة حية)</option>
                                    <option value="cash">💵 نقداً - كاش (سيولة احتياطية)</option>
                                    <option value="card">💳 بطاقة مصرفية</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>يوم نزول الراتب في الشهر (1 - 31):</label>
                                <select name="day_of_month" class="styled-select">
                                    @for($d = 1; $d <= 31; $d++)
                                        <option value="{{ $d }}" {{ $d === 1 ? 'selected' : '' }}>يوم {{ $d }} من الشهر</option>
                                    @endfor
                                </select>
                            </div>
                        </div>

                        <!-- 4. Optional Date & Notes (Together on Row 4) -->
                        <div class="form-row">
                            <div class="form-group">
                                <label>تاريخ محدد (اختياري لمرة واحدة):</label>
                                <input type="date" name="expected_date" class="styled-input">
                            </div>
                            <div class="form-group">
                                <label>ملاحظات توضيحية:</label>
                                <input type="text" name="notes" class="styled-input" placeholder="مثال: موعد تقريبي من 1 إلى 10، أو يحسب بعدد أيام العمل...">
                            </div>
                        </div>

                        <div style="display: flex; justify-content: flex-end; margin-top: 0.5rem;">
                            <button type="submit" class="btn-primary" style="display: flex; align-items: center; gap: 0.5rem;">
                                <span>💾</span>
                                <span>إضافة الراتب للنظام</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- SECTION 2: CYCLE & BUDGET SETTINGS (NO WORK HOURS) -->
            <div id="salarySectionCycle" class="hidden">
                <form action="{{ route('settings.update') }}" method="POST" class="modal-form" style="padding: 0; gap: 1.25rem;">
                    @csrf
                    @method('PUT')

                    <div class="tip-card" style="margin-bottom: 0.5rem;">
                        💡 <strong>ملاحظة ذكية:</strong> يتم احتساب إجمالي الراتب الشهري تلقائياً وبدقة كحاصل جمع مصادر رواتفك الفعّالة (<strong>{{ number_format($totalSalary, 0) }} {{ $settings->currency ?? 'ILS' }}</strong>).
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>آلية بدء الدورة الشهرية: *</label>
                            <select name="cycle_mode" id="cycleModeSelect" class="styled-select" onchange="toggleCustomCycleDay(this.value)">
                                <option value="first_salary" {{ ($settings->cycle_mode ?? 'first_salary') === 'first_salary' ? 'selected' : '' }}>
                                    🎯 تلقائياً مع أول راتب ينزل في الشهر {{ $firstSalaryDay ? "(يوم {$firstSalaryDay} حالياً)" : '' }}
                                </option>
                                <option value="custom_day" {{ ($settings->cycle_mode ?? 'first_salary') === 'custom_day' ? 'selected' : '' }}>
                                    🗓️ يوم محدد ثابت من الشهر (يدوي)
                                </option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>نسبة الادخار والتحويش (%):</label>
                            <input type="number" name="savings_target_percent" class="styled-input" value="{{ $settings->savings_target_percent ?? 20 }}" min="0" max="90" placeholder="مثال: 10 أو 0">
                            <small style="font-size: 0.73rem; color: #94a3b8; display: block; margin-top: 4px;">يمكنك وضع 0% إذا لم ترغب بحجز أي مبلغ مسبقاً للتحويش.</small>
                        </div>
                    </div>

                    <div class="form-group" id="customDayGroup" style="{{ ($settings->cycle_mode ?? 'first_salary') === 'custom_day' ? '' : 'display:none;' }}">
                        <label>اليوم المحدد لبدء الدورة الشهرية (1 - 31):</label>
                        <select name="salary_day" class="styled-select">
                            @for($d = 1; $d <= 31; $d++)
                                <option value="{{ $d }}" {{ ($settings->salary_day ?? 1) == $d ? 'selected' : '' }}>يوم {{ $d }} من الشهر</option>
                            @endfor
                        </select>
                    </div>

                    <div class="form-group">
                        <label>العملة المفضلة للمحفظة:</label>
                        <select name="currency" class="styled-select">
                            <option value="ILS" {{ in_array(($settings->currency ?? 'ILS'), ['ILS', '₪']) ? 'selected' : '' }}>🇵🇸 شيكل (ILS)</option>
                            <option value="SAR" {{ ($settings->currency ?? 'ILS') == 'SAR' ? 'selected' : '' }}>🇸🇦 ريال (SAR)</option>
                            <option value="AED" {{ ($settings->currency ?? 'ILS') == 'AED' ? 'selected' : '' }}>🇦🇪 درهم (AED)</option>
                            <option value="JOD" {{ ($settings->currency ?? 'ILS') == 'JOD' ? 'selected' : '' }}>🇯🇴 دينار (JOD)</option>
                            <option value="EGP" {{ ($settings->currency ?? 'ILS') == 'EGP' ? 'selected' : '' }}>🇪🇬 جنيه (EGP)</option>
                            <option value="KWD" {{ ($settings->currency ?? 'ILS') == 'KWD' ? 'selected' : '' }}>🇰🇼 دينار (KWD)</option>
                            <option value="QAR" {{ ($settings->currency ?? 'ILS') == 'QAR' ? 'selected' : '' }}>🇶🇦 ريال (QAR)</option>
                            <option value="USD" {{ ($settings->currency ?? 'ILS') == 'USD' ? 'selected' : '' }}>🇺🇸 دولار (USD)</option>
                            <option value="EUR" {{ ($settings->currency ?? 'ILS') == 'EUR' ? 'selected' : '' }}>🇪🇺 يورو (EUR)</option>
                        </select>
                    </div>

                    <div class="modal-actions" style="margin-top: 1rem;">
                        <button type="submit" class="btn-primary">حفظ إعدادات الدورة والميزانية</button>
                    </div>
                </form>
                    <!-- AUTO-CYCLE EXPLAINER & EARLY RENEWAL -->
                    <div style="margin-top: 1.5rem; padding: 1.25rem; background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25); border-radius: 16px;">
                        <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                            <div>
                                <span style="font-weight: 800; color: #10b981; font-size: 0.95rem; display: flex; align-items: center; gap: 0.4rem;">
                                    <span>⚡</span>
                                    <span>التجديد التلقائي للدورة (Auto-Cycle Rollover):</span>
                                </span>
                                <ul style="margin: 0.5rem 0 0 1rem; padding: 0; font-size: 0.82rem; color: var(--text-secondary); line-height: 1.7;">
                                    <li><strong>تصفير تلقائي للأظرف:</strong> عند حلول يوم {{ $settings->salary_day ?? 1 }} من كل شهر، تُصفّر مصروفات الأظرف وتبدأ برصيدها الشهري الكامل.</li>
                                    <li><strong>أرشفة آمنة:</strong> تبقى معاملات الشهور الماضية محفوظة في السجل التاريخي دون حذف.</li>
                                    <li><strong>ترحيل الفائض:</strong> أي فائض غير مستهلك يظل في رصيدك الفعلي أو يرحّل للدورة الجديدة.</li>
                                </ul>
                            </div>

                            <form action="{{ route('settings.startNewCycle') }}" method="POST"
                                onsubmit="return confirmActionForm(event, { title: 'إقفال الدورة وبدء دورة جديدة', message: 'هل استلمت الراتب مبكراً وتريد إقفال الدورة السابقة وبدء دورة شهرية جديدة تبدأ من تاريخ اليوم؟', confirmText: 'نعم، ابدأ دورة جديدة', icon: '🔄', isDanger: false })">
                                @csrf
                                <button type="submit" class="btn-primary" style="background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #10b981; font-size: 0.85rem; padding: 0.55rem 1rem;">
                                    🔄 استلام الراتب مبكراً؟ ابدأ دورة جديدة الآن
                                </button>
                            </form>
                        </div>
                    </div>

                <!-- DANGER ZONE: ZERO OUT / RESET SYSTEM -->
                <div style="margin-top: 2rem; padding: 1.25rem; background: rgba(239, 68, 68, 0.08); border: 1px dashed rgba(239, 68, 68, 0.35); border-radius: 16px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
                        <div>
                            <span style="font-weight: 800; color: #ef4444; font-size: 0.95rem; display: flex; align-items: center; gap: 0.4rem;">
                                <span>⚠️</span>
                                <span>تصفير النظام والمحفظة بالكامل (Reset)</span>
                            </span>
                            <p style="font-size: 0.8rem; color: var(--text-secondary); margin: 0.25rem 0 0 0;">
                                مسح كافة المعاملات والرواتب والأهداف والبدء من الصفر تماماً.
                            </p>
                        </div>
                        <form action="{{ route('settings.reset') }}" method="POST" onsubmit="return confirmDeleteForm(event, 'هل أنت متأكد تماماً من تصفير النظام؟ سيتم مسح كافة البيانات المسجلة لتبدأ من الصفر!', '⚠️ تصفير النظام بالكامل')">
                            @csrf
                            <button type="submit" class="btn-subtle" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border-color: rgba(239, 68, 68, 0.3); font-weight: 700;">
                                🔄 تصفير كافة البيانات الآن
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 2.1 Edit Salary Modal -->
<div class="modal-backdrop hidden" id="editSalaryModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>✏️ تعديل بيانات الراتب</h3>
            <button class="modal-close-btn" onclick="closeModal('editSalaryModal')">&times;</button>
        </div>
        <form id="editSalaryForm" method="POST" class="modal-form">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label>مسمى الراتب / جهة الدخل: *</label>
                <input type="text" name="name" id="editSalaryName" class="styled-input" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>المبلغ: *</label>
                    <input type="number" step="0.01" name="amount" id="editSalaryAmount" class="styled-input large-number" required>
                </div>
                <div class="form-group">
                    <label>العملة:</label>
                    <select name="currency" id="editSalaryCurrency" class="styled-select">
                        <option value="ILS">🇵🇸 شيكل (ILS)</option>
                        <option value="USD">🇺🇸 دولار (USD)</option>
                        <option value="JOD">🇯🇴 دينار (JOD)</option>
                        <option value="SAR">🇸🇦 ريال (SAR)</option>
                        <option value="AED">🇦🇪 درهم (AED)</option>
                        <option value="EGP">🇪🇬 جنيه (EGP)</option>
                        <option value="EUR">🇪🇺 يورو (EUR)</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>طريقة القبض: *</label>
                    <select name="payment_method" id="editSalaryMethod" class="styled-select" required>
                        <option value="transfer">📱 تطبيق إلكتروني / بنكي</option>
                        <option value="cash">💵 نقداً (كاش)</option>
                        <option value="card">💳 بطاقة بنكية</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>يوم النزول في الشهر (1 - 31):</label>
                    <select name="day_of_month" id="editSalaryDay" class="styled-select">
                        @for($d = 1; $d <= 31; $d++)
                            <option value="{{ $d }}">يوم {{ $d }} من الشهر</option>
                        @endfor
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>ملاحظات توضيحية:</label>
                <input type="text" name="notes" id="editSalaryNotes" class="styled-input">
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-subtle" onclick="closeModal('editSalaryModal')">إلغاء</button>
                <button type="submit" class="btn-primary">حفظ التعديلات</button>
            </div>
        </form>
    </div>
</div>

<!-- 3. Envelope Modal -->
<div class="modal-backdrop hidden" id="envelopeModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>إضافة ظرف ميزانية جديد</h3>
            <button class="modal-close-btn" onclick="closeModal('envelopeModal')">&times;</button>
        </div>
        <form action="{{ route('envelopes.store') }}" method="POST" class="modal-form">
            @csrf
            <div class="form-group">
                <label>اسم الظرف / الفئة: *</label>
                <input type="text" name="name" class="styled-input" required placeholder="مثال: البقالة والتموين">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>سقف الميزانية (اختياري - اتركه فارغاً لتتبع المصروف فقط):</label>
                    <input type="number" name="budget" class="styled-input" placeholder="0.00 (اختياري)">
                </div>
                <div class="form-group">
                    <label>التصنيف (50/30/20):</label>
                    <select name="pillar" class="styled-select">
                        <option value="needs">🏠 احتياج أساسي (50%)</option>
                        <option value="wants">☕ رغبة وكماليات (30%)</option>
                        <option value="savings">🎯 ادخار واستثمار (20%)</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>الأيقونة:</label>
                    <select name="icon" class="styled-select">
                        <option value="🛒">🛒 بقالة</option>
                        <option value="🏠">🏠 سكن وفواتير</option>
                        <option value="🚗">🚗 سيارة وبنزين</option>
                        <option value="🍔">🍔 مطاعم</option>
                        <option value="🛍️">🛍️ تسوق</option>
                        <option value="💊">💊 صحة</option>
                        <option value="✈️">✈️ ترفيه وسفر</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>اللون المميز:</label>
                    <select name="color" class="styled-select">
                        <option value="#10b981">أخضر زمردي</option>
                        <option value="#3b82f6">أزرق سماوي</option>
                        <option value="#f59e0b">كهرماني</option>
                        <option value="#8b5cf6">بنفسجي</option>
                        <option value="#ec4899">وردي</option>
                        <option value="#ef4444">أحمر</option>
                    </select>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-subtle" onclick="closeModal('envelopeModal')">إلغاء</button>
                <button type="submit" class="btn-primary">حفظ الظرف</button>
            </div>
        </form>
    </div>
</div>

<!-- 4. Goal Modal -->
<div class="modal-backdrop hidden" id="goalModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>🎯 إنشاء هدف تحويش جديد</h3>
            <button class="modal-close-btn" onclick="closeModal('goalModal')">&times;</button>
        </div>
        <form action="{{ route('goals.store') }}" method="POST" class="modal-form">
            @csrf
            <div class="form-group">
                <label>اسم الهدف أو الحصالة: *</label>
                <input type="text" name="name" class="styled-input" required placeholder="مثال: صندوق الطوارئ">
            </div>

            <div class="form-row">
                <div class="form-group" style="flex: 1.2;">
                    <label>المبلغ المستهدف: *</label>
                    <input type="number" name="target_amount" class="styled-input" step="any" required placeholder="0">
                </div>
                <div class="form-group" style="flex: 1.2;">
                    <label>المبلغ المجموع حالياً:</label>
                    <input type="number" name="current_amount" class="styled-input" step="any" value="0">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>عملة الهدف:</label>
                    <select name="currency" class="styled-select">
                        <option value="USD">🇺🇸 دولار ($ USD)</option>
                        <option value="ILS" selected>🇵🇸 شيكل (₪ ILS)</option>
                        <option value="JOD">🇯🇴 دينار (د.أ JOD)</option>
                        <option value="EUR">🇪🇺 يورو (€ EUR)</option>
                        <option value="SAR">🇸🇦 ريال (ر.س SAR)</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>تاريخ الإنجاز المستهدف:</label>
                    <input type="date" name="deadline" class="styled-input">
                </div>
                <div class="form-group">
                    <label>الأيقونة:</label>
                    <select name="icon" class="styled-select">
                        <option value="🛡️">🛡️ أمان وطوارئ</option>
                        <option value="🚗">🚗 سيارة</option>
                        <option value="✈️">✈️ سفر</option>
                        <option value="🏡">🏡 عقار وترميم</option>
                        <option value="💍">💍 مناسبات</option>
                        <option value="📈">📈 استثمار</option>
                    </select>
                </div>
            </div>

            <!-- Optional Auto-Harvest at Goal Creation -->
            <div style="background: rgba(16, 185, 129, 0.08); border: 1px dashed rgba(16, 185, 129, 0.3); border-radius: 12px; padding: 0.85rem; margin-bottom: 1rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 700; color: #10b981; margin: 0;">
                    <input type="checkbox" name="is_harvest_active" value="1" id="chkNewGoalHarvest" onchange="toggleNewGoalHarvestOptions(this.checked)" style="accent-color: #10b981; width: 18px; height: 18px;">
                    <span>⚡ تفعيل الاقتطاع التلقائي من فائض نهاية اليوم لهذه الحصالة</span>
                </label>
                <div id="newGoalHarvestOptionsWrap" class="hidden" style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid rgba(16, 185, 129, 0.2);">
                    <label style="font-size: 0.78rem; color: var(--text-secondary); margin-bottom: 0.35rem; display: block;">نسبة الاقتطاع من فائض كل يوم:</label>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="range" min="5" max="100" value="50" step="5" class="harvest-slider" id="newGoalHarvestSlider" oninput="document.getElementById('newGoalHarvestNum').value = this.value">
                        <input type="number" name="harvest_ratio" id="newGoalHarvestNum" value="50" min="5" max="100" class="styled-input" style="width: 70px; text-align: center; font-weight: 800;" oninput="document.getElementById('newGoalHarvestSlider').value = this.value">
                        <span style="font-weight: 800; color: var(--text-secondary);">%</span>
                    </div>
                    <small style="color: var(--text-muted); font-size: 0.74rem; display: block; margin-top: 0.35rem;">
                        💡 في نهاية كل يوم (عند منتصف الليل)، يقتطع النظام هذه النسبة تلقائياً من الفائض المتبقي ويودعها بعملة الحصالة دون أي مجهود منك.
                    </small>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-subtle" onclick="closeModal('goalModal')">إلغاء</button>
                <button type="submit" class="btn-primary">إنشاء الحصالة</button>
            </div>
        </form>
    </div>
</div>

<!-- 5. Deposit Modal -->
<div class="modal-backdrop hidden" id="feedGoalModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="depositModalTitle">🪙 إيداع مبلغ في حصالة التحويش</h3>
            <button class="modal-close-btn" onclick="closeModal('feedGoalModal')">&times;</button>
        </div>
        <form id="depositForm" action="" method="POST" class="modal-form">
            @csrf
            <div class="form-group" id="depositGoalSelectGroup">
                <label>اختر الحصالة:</label>
                <select id="depositGoalSelect" class="styled-select" onchange="updateDepositAction(this.value)">
                    @foreach($goals as $g)
                        <option value="{{ $g->id }}">{{ $g->icon }} {{ $g->name }} ({{ $g->currency_meta['flag'] }} {{ $g->currency_meta['symbol'] }})</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label id="depositAmountLabel">المبلغ المراد إيداعه بنفس عملة الحصالة: *</label>
                <input type="number" name="amount" class="styled-input large-number" min="0.01" step="any" required placeholder="0">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-subtle" onclick="closeModal('feedGoalModal')">إلغاء</button>
                <button type="submit" class="btn-primary">🎉 تأكيد الإيداع في الحصالة</button>
            </div>
        </form>
    </div>
</div>

<!-- 5.1 Unified Daily Surplus Splitter Modal (100% Cap) -->
<div class="modal-backdrop hidden" id="harvestDailySurplusModal">
    <div class="modal-card modal-card-lg">
        <div class="modal-header">
            <div>
                <h3 style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem;">
                    <span>⚡</span>
                    <span>موزع الفائض الذكي الموحد (100% Surplus Splitter)</span>
                </h3>
                <p style="font-size: 0.8rem; color: var(--text-secondary); margin: 0;">
                    @if($hasUnpaidDebts && $hasIncompleteGoals)
                        وزّع فائض يوميتك بحكمة وتناغم بين سداد الديون والتحويش واليوميات دون أي تضارب!
                    @elseif($hasIncompleteGoals)
                        وزّع فائض يوميتك بمرونة بين حصة صندوق التحويش والمتبقي لرفع يوميات باقي الشهر!
                    @elseif($hasUnpaidDebts)
                        وزّع فائض يوميتك لتسريع سداد وتصفية الديون والمتبقي لرفع يوميات باقي الشهر!
                    @else
                        كامل الفائض يرحّل تلقائياً لدعم ورفع مخصصات يومياتك لباقي أيام الشهر!
                    @endif
                </p>
            </div>
            <button class="modal-close-btn" onclick="closeModal('harvestDailySurplusModal')">&times;</button>
        </div>

        <form id="unifiedSurplusForm" action="{{ route('surplus.allocate') }}" method="POST" class="modal-form" onsubmit="submitHarvestSurplusForm(event)">
            @csrf
            <input type="hidden" name="surplus_amount" id="harvestSurplusBaseInput" value="{{ $todayRemaining }}">

            <!-- Surplus Stats Banner -->
            <div class="harvest-surplus-banner">
                <div class="harvest-stat">
                    <span class="harvest-stat-lbl">مخصص اليوم</span>
                    <span class="harvest-stat-val">{{ number_format($todayBaseAllowance, 0) }} ₪</span>
                </div>
                <div class="harvest-stat">
                    <span class="harvest-stat-lbl">صرفته اليوم</span>
                    <span class="harvest-stat-val" style="color: #ef4444;">{{ number_format($todayExpenses, 0) }} ₪</span>
                </div>
                <div class="harvest-stat highlight">
                    <span class="harvest-stat-lbl">🟢 الفائض المتاح</span>
                    <span class="harvest-stat-val" id="harvestSurplusDisplay">{{ number_format($todayRemaining, 0) }} ₪</span>
                </div>
            </div>

            <!-- Dynamic Live 100% Visual Split Bar -->
            <div class="harvest-split-bar-wrap">
                <div class="harvest-split-bar">
                    @if($hasUnpaidDebts)
                        <div class="split-seg-debt" id="splitSegDebt" style="width: 0%;"></div>
                    @endif
                    @if($hasIncompleteGoals)
                        <div class="split-seg-goal" id="splitSegGoal" style="width: 0%;"></div>
                    @endif
                    <div class="split-seg-buffer" id="splitSegBuffer" style="width: 100%;"></div>
                </div>
                <div class="split-legend-row">
                    @if($hasUnpaidDebts)
                        <div class="split-legend-item">
                            <span class="legend-dot" style="background: #ef4444;"></span>
                            <span>الديون: <strong id="legendDebtPct">0%</strong></span>
                        </div>
                    @endif
                    @if($hasIncompleteGoals)
                        <div class="split-legend-item">
                            <span class="legend-dot" style="background: #10b981;"></span>
                            <span>صندوق التحويش: <strong id="legendGoalPct">0%</strong></span>
                        </div>
                    @endif
                    <div class="split-legend-item">
                        <span class="legend-dot" style="background: #3b82f6;"></span>
                        <span>رفع يوميات باقي الشهر: <strong id="legendBufferPct">100%</strong></span>
                    </div>
                </div>
            </div>

            <!-- Execution Mode Toggle (Scheduled vs Instant) -->
            <div style="margin-bottom: 0.85rem; padding: 0.65rem 0.75rem; background: rgba(255,255,255,0.03); border-radius: 12px; border: 1px solid rgba(255,255,255,0.08);">
                <div style="font-size: 0.78rem; font-weight: 800; color: var(--text-secondary); margin-bottom: 0.45rem; display: flex; align-items: center; justify-content: space-between;">
                    <span>⏰ متى تريد تنفيذ هذا التوزيع؟</span>
                    <span id="modeStatusBadge" style="font-size: 0.72rem; padding: 0.15rem 0.55rem; border-radius: 20px; background: rgba(52, 211, 153, 0.15); color: #34d399; font-weight: 700;">
                        🌙 في وقته المحدد (منتصف الليل)
                    </span>
                </div>
                <input type="hidden" name="execution_mode" id="harvestExecutionModeInput" value="scheduled">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                    <button type="button" class="btn-mode-toggle" id="btnModeScheduled" onclick="setHarvestExecutionMode('scheduled')" style="padding: 0.55rem; border-radius: 9px; border: 1px solid #10b981; background: rgba(16,185,129,0.18); color: #fff; text-align: right; cursor: pointer; transition: all 0.2s;">
                        <div class="mode-title" style="font-size: 0.82rem; font-weight: 800; color: #34d399; margin-bottom: 0.2rem;">
                            🌙 في وقته المحدد (منتصف الليل)
                        </div>
                        <div style="font-size: 0.68rem; color: #9ca3af; line-height: 1.35;">
                            تثبيت النسب واقتطاع الفائض الفعلي المتبقي بنهاية اليوم (يوميتك الحالية تبقى كاملة دون خصم الآن)
                        </div>
                    </button>

                    <button type="button" class="btn-mode-toggle" id="btnModeInstant" onclick="setHarvestExecutionMode('instant')" style="padding: 0.55rem; border-radius: 9px; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.02); color: #9ca3af; text-align: right; cursor: pointer; transition: all 0.2s;">
                        <div class="mode-title" style="font-size: 0.82rem; font-weight: 800; color: #9ca3af; margin-bottom: 0.2rem;">
                            ⚡ سداد وخصم فوري الآن
                        </div>
                        <div style="font-size: 0.68rem; color: #9ca3af; line-height: 1.35;">
                            خصم المبالغ فوراً في هذه اللحظة من رصيد يومية اليوم (سداد فوري مباشر)
                        </div>
                    </button>
                </div>
            </div>

            @if($hasUnpaidDebts && $hasIncompleteGoals)
                <!-- Quick Preset Strategies (When both exist) -->
                <div style="margin-bottom: 0.65rem;">
                    <div style="font-size: 0.76rem; font-weight: 700; color: var(--text-secondary); margin-bottom: 0.35rem;">
                        ⚡ خطط توزيع ذكية سريعة:
                    </div>
                    <div class="strategy-presets-row">
                        <button type="button" class="strategy-preset-btn active" id="btnStratBalanced" onclick="applySplitStrategy(40, 40)">
                            ⚖️ متوازن (40% دين | 40% تحويش | 20% يوميات)
                        </button>
                        <button type="button" class="strategy-preset-btn" id="btnStratDebtPriority" onclick="applySplitStrategy(70, 20)">
                            🛑 أولوية الديون (70% دين | 20% تحويش)
                        </button>
                        <button type="button" class="strategy-preset-btn" id="btnStratGoalPriority" onclick="applySplitStrategy(20, 70)">
                            🎯 أولوية التحويش (20% دين | 70% تحويش)
                        </button>
                        <button type="button" class="strategy-preset-btn" id="btnStratFullDebt" onclick="applySplitStrategy(100, 0)">
                            ⚡ 100% سداد ديون
                        </button>
                        <button type="button" class="strategy-preset-btn" id="btnStratFullGoal" onclick="applySplitStrategy(0, 100)">
                            💎 100% تحويش أهداف
                        </button>
                    </div>
                </div>
            @elseif($hasIncompleteGoals)
                <!-- Quick Preset Strategies (When only goals exist) -->
                <div style="margin-bottom: 0.65rem;">
                    <div style="font-size: 0.76rem; font-weight: 700; color: var(--text-secondary); margin-bottom: 0.35rem;">
                        ⚡ خطط تحويش سريعة للفائض:
                    </div>
                    <div class="strategy-presets-row">
                        <button type="button" class="strategy-preset-btn active" onclick="applySplitStrategy(0, 50)">
                            ⚖️ مناصفة (50% تحويش | 50% يوميات)
                        </button>
                        <button type="button" class="strategy-preset-btn" onclick="applySplitStrategy(0, 75)">
                            🎯 أولوية التحويش (75% تحويش | 25% يوميات)
                        </button>
                        <button type="button" class="strategy-preset-btn" onclick="applySplitStrategy(0, 25)">
                            🌱 حصة هادئة (25% تحويش | 75% يوميات)
                        </button>
                        <button type="button" class="strategy-preset-btn" onclick="applySplitStrategy(0, 100)">
                            💎 100% تحويش كامل
                        </button>
                    </div>
                </div>
            @elseif($hasUnpaidDebts)
                <!-- Quick Preset Strategies (When only debts exist) -->
                <div style="margin-bottom: 0.65rem;">
                    <div style="font-size: 0.76rem; font-weight: 700; color: var(--text-secondary); margin-bottom: 0.35rem;">
                        ⚡ خطط سداد ديون سريعة للفائض:
                    </div>
                    <div class="strategy-presets-row">
                        <button type="button" class="strategy-preset-btn active" onclick="applySplitStrategy(100, 0)">
                            ⚡ 100% سداد ديون كامل (تصفية أولاً)
                        </button>
                        <button type="button" class="strategy-preset-btn" onclick="applySplitStrategy(75, 0)">
                            🛑 تركيز عالي (75% دين | 25% يوميات)
                        </button>
                        <button type="button" class="strategy-preset-btn" onclick="applySplitStrategy(50, 0)">
                            ⚖️ مناصفة (50% دين | 50% يوميات)
                        </button>
                        <button type="button" class="strategy-preset-btn" onclick="applySplitStrategy(25, 0)">
                            🛡️ حصة هادئة (25% دين | 75% يوميات)
                        </button>
                    </div>
                </div>
            @endif

            <!-- CASE 1: DEBTS SECTION (Appears only if unpaid debts exist) -->
            @if($hasUnpaidDebts)
                <div class="alloc-block alloc-block-debt">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 0.4rem;">
                            <span style="font-size: 1.1rem;">🛑</span>
                            <span style="font-size: 0.9rem; font-weight: 800; color: #ef4444;">حصة تسريع سداد الديون</span>
                        </div>
                        <div class="harvest-pct-badge" id="badgeDebtPct" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border-color: rgba(239, 68, 68, 0.3);">
                            0%
                        </div>
                    </div>

                    <div class="form-row" style="margin-bottom: 0;">
                        <div class="form-group" style="flex: 1.5;">
                            <label style="font-size: 0.76rem;">اختر الدين المستهدف للسداد:</label>
                            <select name="debt_id" id="allocTargetDebtSelect" class="styled-select" onchange="updateUnifiedCalculations()">
                                @foreach($unpaidDebts as $d)
                                    <option value="{{ $d->id }}" data-remaining="{{ $d->total_debt }}" data-name="{{ $d->name }}">
                                        {{ $d->icon }} {{ $d->name }} (متبقي: {{ number_format($d->total_debt, 0) }} ₪)
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label style="font-size: 0.76rem;">نسبة الاقتطاع للدين:</label>
                            <div style="display: flex; align-items: center; gap: 0.35rem;">
                                <input type="range" min="0" max="100" value="0" class="harvest-slider" id="sliderDebtPct" style="accent-color: #ef4444;" oninput="onDebtSliderChange(this.value)">
                                <input type="number" name="debt_percentage" min="0" max="100" value="0" class="styled-input" id="numDebtPct" style="width: 65px; text-align: center; font-weight: 800;" oninput="onDebtNumChange(this.value)">
                                <span style="font-size: 0.8rem; font-weight: 800; color: var(--text-secondary);">%</span>
                            </div>
                        </div>
                    </div>
                    <div style="margin-top: 0.5rem; padding-top: 0.45rem; border-top: 1px dashed rgba(239, 68, 68, 0.2); display: flex; align-items: center; gap: 0.45rem;">
                        <input type="checkbox" name="save_debt_auto_harvest" value="1" id="chkSaveDebtAutoHarvest" style="accent-color: #ef4444; width: 16px; height: 16px; cursor: pointer;">
                        <label for="chkSaveDebtAutoHarvest" style="font-size: 0.76rem; color: #f87171; font-weight: 700; cursor: pointer; margin: 0;">
                            ⚡ تثبيت هذه النسبة كاقتطاع تلقائي مستمر من فائض نهاية كل يوم لهذا الدين
                        </label>
                    </div>
                </div>
            @endif

            <!-- CASE 2: GOALS SECTION (Appears only if incomplete goals exist) -->
            @if($hasIncompleteGoals)
                <div class="alloc-block alloc-block-goal">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 0.4rem;">
                            <span style="font-size: 1.1rem;">🎯</span>
                            <span style="font-size: 0.9rem; font-weight: 800; color: #10b981;">حصة صندوق التحويش</span>
                        </div>
                        <div class="harvest-pct-badge" id="badgeGoalPct">
                            0%
                        </div>
                    </div>

                    <div class="form-row" style="margin-bottom: 0;">
                        <div class="form-group" style="flex: 1.5;">
                            <label style="font-size: 0.76rem;">اختر الحصالة / الهدف:</label>
                            <select name="goal_id" id="allocTargetGoalSelect" class="styled-select" onchange="updateUnifiedCalculations()">
                                @foreach($incompleteGoals as $g)
                                    <option value="{{ $g->id }}" 
                                            data-currency="{{ $g->currency ?? 'ILS' }}" 
                                            data-rate-from-ils="{{ \App\Services\CurrencyService::getRate('ILS', $g->currency ?? 'ILS') }}"
                                            data-symbol="{{ $g->currency_meta['symbol'] }}"
                                            data-flag="{{ $g->currency_meta['flag'] }}"
                                            data-name="{{ $g->name }}">
                                        {{ $g->icon }} {{ $g->name }} ({{ $g->currency_meta['flag'] }} {{ $g->currency_meta['symbol'] }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label style="font-size: 0.76rem;">نسبة الاقتطاع للتحويش:</label>
                            <div style="display: flex; align-items: center; gap: 0.35rem;">
                                <input type="range" min="0" max="100" value="0" class="harvest-slider" id="sliderGoalPct" oninput="onGoalSliderChange(this.value)">
                                <input type="number" name="goal_percentage" min="0" max="100" value="0" class="styled-input" id="numGoalPct" style="width: 65px; text-align: center; font-weight: 800;" oninput="onGoalNumChange(this.value)">
                                <span style="font-size: 0.8rem; font-weight: 800; color: var(--text-secondary);">%</span>
                            </div>
                        </div>
                    </div>
                    <div style="margin-top: 0.5rem; padding-top: 0.45rem; border-top: 1px dashed rgba(16, 185, 129, 0.2); display: flex; align-items: center; gap: 0.45rem;">
                        <input type="checkbox" name="save_goal_auto_harvest" value="1" id="chkSaveGoalAutoHarvest" style="accent-color: #10b981; width: 16px; height: 16px; cursor: pointer;">
                        <label for="chkSaveGoalAutoHarvest" style="font-size: 0.76rem; color: #34d399; font-weight: 700; cursor: pointer; margin: 0;">
                            ⚡ تثبيت هذه النسبة كاقتطاع تلقائي مستمر من فائض نهاية كل يوم لهذه الحصالة
                        </label>
                    </div>
                </div>
            @endif

            <!-- CASE 3: NEITHER DEBTS NOR GOALS (Celebratory state) -->
            @if(!$hasUnpaidDebts && !$hasIncompleteGoals)
                <div class="harvest-all-done-card">
                    <div class="all-done-icon">🌟</div>
                    <div class="all-done-title">ما شاء الله! ذمتك بريئة وجميع أهدافك مكتملة 100%</div>
                    <div class="all-done-desc">
                        لا توجد عليك أي ديون نشطة، وجميع أهداف وصناديق التحويش مكتملة بالكامل!
                        <br>
                        كامل الفائض المتاح (<strong>{{ number_format($todayRemaining, 0) }} ₪</strong>) سيُرحل تلقائياً لدعم ورفع مخصصات يومياتك لباقي أيام الشهر دون الحاجة لأي توزيع يدوي.
                    </div>
                </div>
            @endif

            <!-- Live Calculation & Simulation Card -->
            <div class="harvest-simulation-card">
                @if($hasUnpaidDebts)
                    <div class="sim-row" id="simRowDebt">
                        <span class="sim-label" style="color: #ef4444;">🛑 خصم لسداد الدين:</span>
                        <span class="sim-val" id="simValDebt" style="color: #ef4444; font-weight: 800;">- 0.00 ₪</span>
                    </div>
                @endif
                @if($hasIncompleteGoals)
                    <div class="sim-row highlight" id="simRowGoal">
                        <span class="sim-label" style="color: #10b981;">🎯 إضافة لحصالة التحويش:</span>
                        <span class="sim-val-goal" id="simValGoal">+ 0.00 $</span>
                    </div>
                @endif
                <div class="sim-row muted">
                    <span class="sim-label" style="color: #3b82f6;">🛡️ المتبقي لرفع يوميات باقي الشهر:</span>
                    <span class="sim-val-left" id="simValBuffer" style="color: #3b82f6; font-weight: 800;">0.00 ₪</span>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-subtle" onclick="closeModal('harvestDailySurplusModal')">إلغاء</button>
                @if($hasUnpaidDebts || $hasIncompleteGoals)
                    <button type="submit" class="btn-primary" id="btnConfirmUnifiedHarvest">
                        <span>🎉 تأكيد توزيع الفائض</span>
                    </button>
                @else
                    <button type="button" class="btn-primary" onclick="closeModal('harvestDailySurplusModal')">
                        <span>👍 ممتاز، استمرار</span>
                    </button>
                @endif
            </div>
        </form>
    </div>
</div>

<!-- 6. Wish Modal -->
<div class="modal-backdrop hidden" id="wishModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>⏳ إضافة رغبة في فترة التهدئة (48 ساعة)</h3>
            <button class="modal-close-btn" onclick="closeModal('wishModal')">&times;</button>
        </div>
        <form action="{{ route('wishlist.store') }}" method="POST" class="modal-form">
            @csrf
            <div class="form-group">
                <label>ما هي السلعة التي ترغب بشرائها؟ *</label>
                <input type="text" name="title" class="styled-input" required placeholder="مثال: ساعة رياضية، حذاء...">
            </div>
            <div class="form-group">
                <label>السعر التقديري ({{ $settings->currency ?? 'ILS' }}): *</label>
                <input type="number" name="price" class="styled-input" min="1" required placeholder="0">
            </div>
            <div class="form-group">
                <label>فترة التهدئة المطلوبة:</label>
                <select name="duration_hours" class="styled-select">
                    <option value="48">48 ساعة (يومين) - الموصى به</option>
                    <option value="168">أسبوع كامل (7 أيام)</option>
                    <option value="720">30 يوماً</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-subtle" onclick="closeModal('wishModal')">إلغاء</button>
                <button type="submit" class="btn-primary">بدء فترة الانتظار</button>
            </div>
        </form>
    </div>
</div>

<!-- 7. Subscription Modal -->
<div class="modal-backdrop hidden" id="subscriptionModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>💳 إضافة اشتراك دوري جديد</h3>
            <button class="modal-close-btn" onclick="closeModal('subscriptionModal')">&times;</button>
        </div>
        <form action="{{ route('subscriptions.store') }}" method="POST" class="modal-form">
            @csrf
            <div class="form-group">
                <label>اسم الخدمة أو الاشتراك: *</label>
                <input type="text" name="name" class="styled-input" required placeholder="مثال: Netflix, Spotify...">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>قيمة الاشتراك: *</label>
                    <input type="number" name="cost" class="styled-input" min="1" step="0.5" required placeholder="0">
                </div>
                <div class="form-group">
                    <label>دورية التجديد:</label>
                    <select name="period" class="styled-select">
                        <option value="monthly">شهرياً</option>
                        <option value="yearly">سنوياً</option>
                    </select>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-subtle" onclick="closeModal('subscriptionModal')">إلغاء</button>
                <button type="submit" class="btn-primary">حفظ الاشتراك</button>
            </div>
        </form>
    </div>
</div>

<!-- 8. New Debt Account Modal -->
<div class="modal-backdrop hidden" id="newDebtModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>📑 فتح حساب دين / ذمة جديدة</h3>
            <button class="modal-close-btn" onclick="closeModal('newDebtModal')">&times;</button>
        </div>
        <form action="{{ route('debts.store') }}" method="POST" class="modal-form">
            @csrf
            <div class="form-group">
                <label>اسم الجهة أو الشخص الدائن: *</label>
                <input type="text" name="name" class="styled-input" required placeholder="مثال: سوبرماركت الأمل، أبو أحمد بقالة، سلفة من محمد...">
            </div>

            <div class="form-group">
                <label>نوع الحساب / الذمة:</label>
                <div class="form-type-pills">
                    <label class="type-pill active" onclick="setDebtTypePill(this)">
                        <input type="radio" name="type" value="supermarket" checked>
                        <span>🛒 سوبرماركت / بقالة</span>
                    </label>
                    <label class="type-pill" onclick="setDebtTypePill(this)">
                        <input type="radio" name="type" value="person">
                        <span>👤 شخص / صديق</span>
                    </label>
                    <label class="type-pill" onclick="setDebtTypePill(this)">
                        <input type="radio" name="type" value="store">
                        <span>🏪 متجر / محل</span>
                    </label>
                    <label class="type-pill" onclick="setDebtTypePill(this)">
                        <input type="radio" name="type" value="other">
                        <span>📑 أخرى</span>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label>الرصيد المستحق الحالي (إن وجد):</label>
                <input type="number" name="initial_amount" class="styled-input large-number" step="0.01" min="0" placeholder="0.00">
                <small style="color: var(--text-secondary); font-size: 0.78rem;">اتركه فارغاً إذا كنت تفتح الحساب لبدء تسجيل المشتريات من اليوم.</small>
            </div>

            <div class="form-group">
                <label>ملاحظات إضافية (اختياري):</label>
                <input type="text" name="notes" class="styled-input" placeholder="مثال: نسدد أول الشهر، هاتف صاحب المحل...">
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-subtle" onclick="closeModal('newDebtModal')">إلغاء</button>
                <button type="submit" class="btn-primary">💾 فتح وتثبيت الحساب</button>
            </div>
        </form>
    </div>
</div>

<!-- 9. Add Debt Record (شكك) Modal -->
<div class="modal-backdrop hidden" id="addDebtRecordModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>🛒 تسجيل مشتريات بالدين (شكك) / استدانة</h3>
            <button class="modal-close-btn" onclick="closeModal('addDebtRecordModal')">&times;</button>
        </div>
        <form id="addDebtRecordForm" action="" method="POST" class="modal-form">
            @csrf
            <div class="form-group">
                <label>اختر الجهة أو الشخص الدائن: *</label>
                <select name="debt_id" id="addDebtSelect" class="styled-select" required onchange="updateAddDebtAction(this.value)">
                    <option value="">-- اختر الحساب --</option>
                    @foreach($debts as $d)
                        <option value="{{ $d->id }}">{{ $d->icon }} {{ $d->name }} (الحالي: {{ number_format($d->total_debt, 0) }})</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label>قيمة المشتريات أو السلفة ({{ $settings->currency ?? 'ILS' }}): *</label>
                <input type="number" name="amount" class="styled-input large-number" step="0.01" min="0.01" required placeholder="0.00">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>التاريخ: *</label>
                    <input type="date" name="date" class="styled-input" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="form-group">
                    <label>بيان المشتريات / تفاصيل:</label>
                    <input type="text" name="note" class="styled-input" placeholder="مثال: خضار، تموين، أجبان، سلفة نقدية...">
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-subtle" onclick="closeModal('addDebtRecordModal')">إلغاء</button>
                <button type="submit" class="btn-primary">➕ إضافة إلى الحساب</button>
            </div>
        </form>
    </div>
</div>

<!-- 10. Repay Debt Modal -->
<div class="modal-backdrop hidden" id="repayDebtModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>💳 سداد الدين وتصفية الحساب</h3>
            <button class="modal-close-btn" onclick="closeModal('repayDebtModal')">&times;</button>
        </div>
        <form id="repayDebtForm" action="" method="POST" class="modal-form">
            @csrf
            <div class="debt-balance-block" style="margin-bottom: 1rem;">
                <div>
                    <div style="font-weight: 800; font-size: 1rem;" id="repayDebtName">اسم الجهة</div>
                    <div style="font-size: 0.8rem; color: #94a3b8;">إجمالي الدين المستحق</div>
                </div>
                <div class="debt-balance-amount" id="repayDebtDueText">0.00</div>
            </div>

            <div class="form-group">
                <label>المبلغ المراد سداده: *</label>
                <div style="display: flex; gap: 0.5rem;">
                    <input type="number" name="amount" id="repayAmountInput" class="styled-input large-number" step="0.01" min="0.01" required placeholder="0.00" style="flex: 1">
                    <button type="button" class="btn-subtle" onclick="fillFullRepayAmount()" style="white-space: nowrap; font-size: 0.8rem; font-weight: 700;">
                        سداد كامل المبلغ
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label>من أين ستدفع؟ (مصدر الخصم المالي): *</label>
                <select name="payment_method" class="styled-select" required>
                    <option value="transfer">📱 رصيد التطبيق (تحويل بنكي / سيولة حية)</option>
                    <option value="cash">💵 كاش (من مخصص الكاش اليدوي)</option>
                    <option value="card">💳 بطاقة بنكية</option>
                </select>
                <small style="color: var(--text-secondary); font-size: 0.78rem;">سيُسجل مصروف حقيقي في سجل معاملاتك ليُخصم من رصيدك بدقة.</small>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>التاريخ: *</label>
                    <input type="date" name="date" class="styled-input" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="form-group">
                    <label>ملاحظة اختيارية:</label>
                    <input type="text" name="note" id="repayNoteInput" class="styled-input" placeholder="سداد كامل / دفعة جزئية...">
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-subtle" onclick="closeModal('repayDebtModal')">إلغاء</button>
                <button type="submit" class="btn-primary" style="background: linear-gradient(135deg, #10b981, #059669)">
                    ✅ تأكيد السداد وتحديث الرصيد
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 11. Activate Harvest Modal -->
<div class="modal-backdrop hidden" id="activateHarvestModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>☕ تفعيل مقتنص فوائض اليومية لسداد الدين</h3>
            <button class="modal-close-btn" onclick="closeModal('activateHarvestModal')">&times;</button>
        </div>
        <form id="activateHarvestForm" action="" method="POST" class="modal-form">
            @csrf

            <div class="debt-balance-block" style="margin-bottom: 1rem;">
                <div>
                    <div style="font-weight: 800; font-size: 1rem;" id="activateHarvestDebtName">اسم الدين</div>
                    <div style="font-size: 0.8rem; color: #94a3b8;">إجمالي الدين المستهدف تجميعه</div>
                </div>
                <div class="debt-balance-amount" id="activateHarvestDebtDue">0.00</div>
            </div>

            <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25); border-radius: 12px; padding: 0.85rem; font-size: 0.85rem; color: #d1d5db; line-height: 1.6; margin-bottom: 1rem;">
                <div style="font-weight: 800; color: #34d399; margin-bottom: 0.3rem; display: flex; align-items: center; gap: 0.4rem;">
                    <span>💡</span> <span>كيف تعمل استراتيجية السداد من الفوائض؟</span>
                </div>
                <div>في نهاية كل يوم (عند منتصف الليل وبداية يوم جديد)، يحسب النظام المعادلة الذهبية:</div>
                <div style="direction: ltr; text-align: center; font-weight: 800; color: #10b981; margin: 0.4rem 0; font-family: monospace; font-size: 0.95rem;">
                    فائض اليوم = اليومية المسموحة - ما صُرف فعلياً اليوم
                </div>
                <div>إذا كان الفائض أكبر من 0: يستقطع النظام النسبة المتفق عليها ويجمعها في حصالة الدين. وإذا صُرفت اليومية كاملة فلا يُخصم شيء إطلاقاً!</div>
            </div>

            <div class="form-group">
                <label>نسبة الاستقطاع من الفائض اليومي: *</label>
                <div class="form-type-pills">
                    <label class="type-pill active" onclick="setHarvestRatioPill(this, 0.5)">
                        <input type="radio" name="harvest_ratio" value="0.5" checked>
                        <span>⚖️ 50% من الفائض (متوازن - موصى به)</span>
                    </label>
                    <label class="type-pill" onclick="setHarvestRatioPill(this, 1.0)">
                        <input type="radio" name="harvest_ratio" value="1.0">
                        <span>⚡ 100% من الفائض (أسرع سداد)</span>
                    </label>
                    <label class="type-pill" onclick="setHarvestRatioPill(this, 0.25)">
                        <input type="radio" name="harvest_ratio" value="0.25">
                        <span>🌱 25% من الفائض (مريح وخفيف)</span>
                    </label>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>سقف أقصى للاستقطاع اليومي (اختياري):</label>
                    <input type="number" name="harvest_max_daily" class="styled-input" placeholder="مثال: 15 (اتركه فارغاً دون سقف)">
                    <small style="color: var(--text-secondary); font-size: 0.75rem;">تحديد حد أقصى (مثلاً لا تقتطع أكثر من 15 ₪ في اليوم الواحد مهما بلغ الفائض).</small>
                </div>
                <div class="form-group">
                    <label>تاريخ بدء احتساب الفوائض:</label>
                    <input type="date" name="harvest_started_at" class="styled-input" value="{{ date('Y-m-d') }}">
                    <small style="color: var(--text-secondary); font-size: 0.75rem;">يمكنك البدء من تاريخ اليوم أو من تاريخ سابق.</small>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-subtle" onclick="closeModal('activateHarvestModal')">إلغاء</button>
                <button type="submit" class="btn-primary" style="background: linear-gradient(135deg, #10b981, #059669);">
                    🚀 تفعيل الخطة والبدء بالرصد
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 12. Harvest Logs & Daily Transparency Modal -->
<div class="modal-backdrop hidden" id="harvestLogsModal">
    <div class="modal-card modal-card-lg">
        <div class="modal-header">
            <div>
                <h3 style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem;">
                    <span>📜</span>
                    <span id="harvestLogsTitle">سجل الرصد اليومي الشفاف لدين</span>
                </h3>
                <p style="font-size: 0.82rem; color: var(--text-secondary); margin: 0;">
                    توثيق شفاف ودقيق لكل يوم: مسموح اليومية، ما صُرف، الفائض المحقق، والمبلغ المقتطع لحصالة الدين
                </p>
            </div>
            <button class="modal-close-btn" onclick="closeModal('harvestLogsModal')">&times;</button>
        </div>

        <div style="padding: 1.25rem 1.5rem;">
            <!-- Today's Live In-Progress Forecast Card -->
            <div id="harvestForecastCard" style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 14px; padding: 1rem 1.25rem; margin-bottom: 1.25rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; flex-wrap: wrap; gap: 0.5rem;">
                    <div style="font-weight: 800; color: #fbbf24; font-size: 0.95rem; display: flex; align-items: center; gap: 0.4rem;">
                        <span class="pulse-dot"></span>
                        <span>رصد اليوم الحالي (قيد المصاريف حتى منتصف الليل 🌙)</span>
                    </div>
                    <span style="font-size: 0.78rem; color: #9ca3af;" id="harvestForecastDate">{{ date('Y/m/d') }}</span>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 0.75rem; text-align: center;">
                    <div style="background: rgba(0,0,0,0.2); padding: 0.5rem; border-radius: 8px;">
                        <span style="font-size: 0.75rem; color: #9ca3af; display: block;">اليومية المسموحة</span>
                        <strong style="font-size: 1.1rem; color: #f9fafb;" id="forecastAllowance">0</strong> ₪
                    </div>
                    <div style="background: rgba(0,0,0,0.2); padding: 0.5rem; border-radius: 8px;">
                        <span style="font-size: 0.75rem; color: #9ca3af; display: block;">ما صُرف حتى الآن</span>
                        <strong style="font-size: 1.1rem; color: #f87171;" id="forecastSpent">0</strong> ₪
                    </div>
                    <div style="background: rgba(0,0,0,0.2); padding: 0.5rem; border-radius: 8px;">
                        <span style="font-size: 0.75rem; color: #9ca3af; display: block;">الفائض المتوقع</span>
                        <strong style="font-size: 1.1rem; color: #34d399;" id="forecastSurplus">0</strong> ₪
                    </div>
                    <div style="background: rgba(16, 185, 129, 0.2); border: 1px solid rgba(16, 185, 129, 0.4); padding: 0.5rem; border-radius: 8px;">
                        <span style="font-size: 0.75rem; color: #34d399; display: block; font-weight: 700;">المتوقع ترحيله للحصالة</span>
                        <strong style="font-size: 1.1rem; color: #10b981;" id="forecastHarvest">0</strong> ₪
                    </div>
                </div>
                <div style="font-size: 0.76rem; color: #9ca3af; margin-top: 0.5rem; text-align: center;">
                    ℹ️ يتم تثبيت وترحيل الفائض تلقائياً عند انتهاء اليوم وبداية اليوم الجديد (منتصف الليل).
                </div>
            </div>

            <!-- Historical Log Table -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.6rem; flex-wrap: wrap; gap: 0.5rem;">
                <h4 style="margin: 0; font-size: 0.95rem; font-weight: 800; color: #f9fafb;">
                    📅 سجل الأيام المكتملة والمقتطعات الشفافة:
                </h4>
                <div style="font-size: 0.85rem; color: #10b981; font-weight: 800;">
                    إجمالي ما جُمع بالحصالة: <span id="harvestTotalGathered">0.00</span> ₪
                </div>
            </div>

            <div style="max-height: 320px; overflow-y: auto; border: 1px solid var(--border); border-radius: 12px; background: rgba(0,0,0,0.15);">
                <table class="harvest-logs-table">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>اليومية المسموحة</th>
                            <th>ما صُرف فعلياً</th>
                            <th>فائض اليوم</th>
                            <th>المقتطع للحصالة</th>
                            <th>البيان والتفاصيل</th>
                        </tr>
                    </thead>
                    <tbody id="harvestLogsTableBody">
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 1.5rem; color: #9ca3af;">
                                جاري تحميل سجلات الرصد...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="modal-actions">
            <button type="button" class="btn-subtle" onclick="closeModal('harvestLogsModal')">إغلاق</button>
        </div>
    </div>
</div>

<!-- 13. Activate Goal Harvest Modal -->
<div class="modal-backdrop hidden" id="activateGoalHarvestModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>⚡ تفعيل الاقتطاع التلقائي من الفائض لحصالة التحويش</h3>
            <button class="modal-close-btn" onclick="closeModal('activateGoalHarvestModal')">&times;</button>
        </div>
        <form id="activateGoalHarvestForm" action="" method="POST" class="modal-form">
            @csrf

            <div class="debt-balance-block" style="margin-bottom: 1rem; border-color: rgba(16, 185, 129, 0.4); background: linear-gradient(135deg, rgba(16, 185, 129, 0.12), rgba(5, 150, 105, 0.06));">
                <div>
                    <div style="font-weight: 800; font-size: 1rem; color: #10b981;" id="activateGoalHarvestName">اسم الحصالة</div>
                    <div style="font-size: 0.8rem; color: #94a3b8;">المبلغ المتبقي لاكتمال الهدف</div>
                </div>
                <div class="debt-balance-amount" id="activateGoalHarvestDue" style="color: #10b981;">0.00 $</div>
            </div>

            <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25); border-radius: 12px; padding: 0.85rem; font-size: 0.85rem; color: #d1d5db; line-height: 1.6; margin-bottom: 1rem;">
                <div style="font-weight: 800; color: #34d399; margin-bottom: 0.3rem; display: flex; align-items: center; gap: 0.4rem;">
                    <span>💡</span> <span>كيف يعمل التحويش التلقائي من الفوائض؟</span>
                </div>
                <div>مع نهاية كل يوم (عند منتصف الليل 🌙)، يفحص النظام ميزانيتك اليومية:</div>
                <div style="direction: ltr; text-align: center; font-weight: 800; color: #10b981; margin: 0.4rem 0; font-family: monospace; font-size: 0.95rem;">
                    فائض اليوم = اليومية المسموحة - ما صُرف فعلياً
                </div>
                <div>إذا كان الفائض أكبر من 0: يقتطع النظام تلقائياً النسبة التي تحددها ويودعها فوراً في حصالتك بعملتها المختارة، والمتبقي يرحل لدعم باقي أيام الشهر. وإذا صُرفت اليومية كاملة فلا يُخصم شيء إطلاقاً!</div>
            </div>

            <div class="form-group">
                <label>نسبة الاستقطاع من الفائض اليومي: *</label>
                <div class="form-type-pills">
                    <label class="type-pill active" onclick="setGoalHarvestRatioPill(this, 50)">
                        <input type="radio" name="ratio_preset" value="50" checked>
                        <span>⚖️ 50% من الفائض (متوازن - موصى به)</span>
                    </label>
                    <label class="type-pill" onclick="setGoalHarvestRatioPill(this, 75)">
                        <input type="radio" name="ratio_preset" value="75">
                        <span>🎯 75% من الفائض (تحويش سريع)</span>
                    </label>
                    <label class="type-pill" onclick="setGoalHarvestRatioPill(this, 100)">
                        <input type="radio" name="ratio_preset" value="100">
                        <span>💎 100% من الفائض (اقتناص كامل الفائض)</span>
                    </label>
                    <label class="type-pill" onclick="setGoalHarvestRatioPill(this, 25)">
                        <input type="radio" name="ratio_preset" value="25">
                        <span>🌱 25% من الفائض (خفيف وهادئ)</span>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label>أو حدد نسبة مخصصة بدقة:</label>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="range" min="1" max="100" value="50" class="harvest-slider" id="modalGoalHarvestSlider" oninput="onModalGoalHarvestSlider(this.value)">
                    <input type="number" name="ratio" id="modalGoalHarvestRatioInput" value="50" min="1" max="100" class="styled-input" style="width: 75px; text-align: center; font-weight: 800;" oninput="onModalGoalHarvestInput(this.value)" required>
                    <span style="font-weight: 800; font-size: 1rem; color: var(--text-secondary);">%</span>
                </div>
            </div>

            <div class="form-group">
                <label>سقف أقصى للاستقطاع اليومي بالشيكل (اختياري):</label>
                <input type="number" name="max_daily" id="modalGoalHarvestMaxDaily" class="styled-input" placeholder="مثال: 20 (اتركه فارغاً دون سقف)">
                <small style="color: var(--text-secondary); font-size: 0.75rem;">تحديد حد أقصى (مثلاً: مهما كبُر الفائض اليومي، لا تقتطع أكثر من 20 ₪ في اليوم الواحد).</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-subtle" onclick="closeModal('activateGoalHarvestModal')">إلغاء</button>
                <button type="submit" class="btn-primary" style="background: linear-gradient(135deg, #10b981, #059669);">
                    🚀 حفظ وتفعيل الاقتطاع التلقائي
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 14. Goal Harvest Logs Modal -->
<div class="modal-backdrop hidden" id="goalHarvestLogsModal">
    <div class="modal-card modal-card-lg">
        <div class="modal-header">
            <div>
                <h3 style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem;">
                    <span>📜</span>
                    <span id="goalHarvestLogsTitle">سجل الاقتطاعات التلقائية للفوائض</span>
                </h3>
                <p style="font-size: 0.82rem; color: var(--text-secondary); margin: 0;">
                    توثيق يومي شفاف: مخصص اليومية، المصروف، الفائض، والمبلغ المحول تلقائياً للحصالة
                </p>
            </div>
            <button class="modal-close-btn" onclick="closeModal('goalHarvestLogsModal')">&times;</button>
        </div>

        <div style="padding: 1.25rem 1.5rem;">
            <!-- Today's Live In-Progress Forecast Card -->
            <div id="goalHarvestForecastCard" style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 14px; padding: 1rem 1.25rem; margin-bottom: 1.25rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; flex-wrap: wrap; gap: 0.5rem;">
                    <div style="font-weight: 800; color: #10b981; font-size: 0.95rem; display: flex; align-items: center; gap: 0.4rem;">
                        <span class="pulse-dot-green"></span>
                        <span>رصد اليوم الحالي (حتى منتصف الليل 🌙)</span>
                    </div>
                    <span style="font-size: 0.78rem; color: #9ca3af;" id="goalForecastDate">{{ date('Y/m/d') }}</span>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 0.75rem; text-align: center;">
                    <div style="background: rgba(0,0,0,0.2); padding: 0.5rem; border-radius: 8px;">
                        <span style="font-size: 0.75rem; color: #9ca3af; display: block;">اليومية المسموحة</span>
                        <strong style="font-size: 1.1rem; color: #f9fafb;" id="goalForecastAllowance">0</strong> ₪
                    </div>
                    <div style="background: rgba(0,0,0,0.2); padding: 0.5rem; border-radius: 8px;">
                        <span style="font-size: 0.75rem; color: #9ca3af; display: block;">المصروف اليوم</span>
                        <strong style="font-size: 1.1rem; color: #f87171;" id="goalForecastSpent">0</strong> ₪
                    </div>
                    <div style="background: rgba(0,0,0,0.2); padding: 0.5rem; border-radius: 8px;">
                        <span style="font-size: 0.75rem; color: #9ca3af; display: block;">الفائض المتوقع</span>
                        <strong style="font-size: 1.1rem; color: #34d399;" id="goalForecastSurplus">0</strong> ₪
                    </div>
                    <div style="background: rgba(16, 185, 129, 0.2); padding: 0.5rem; border-radius: 8px; border: 1px solid rgba(16, 185, 129, 0.4);">
                        <span style="font-size: 0.75rem; color: #10b981; display: block; font-weight: 700;">المتوقع اقتطاعه للحصالة</span>
                        <strong style="font-size: 1.15rem; color: #34d399;" id="goalForecastHarvest">0</strong>
                    </div>
                </div>
                <div style="font-size: 0.76rem; color: #9ca3af; margin-top: 0.5rem; text-align: center;">
                    ℹ️ يتم تثبيت وترحيل الفائض تلقائياً عند انتهاء اليوم وبداية اليوم الجديد (منتصف الليل).
                </div>
            </div>

            <!-- Historical Table -->
            <div style="max-height: 320px; overflow-y: auto; border: 1px solid var(--border); border-radius: 12px; background: rgba(0,0,0,0.15);" class="styled-scrollbar">
                <table class="harvest-logs-table" style="width: 100%; border-collapse: collapse; font-size: 0.84rem;">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>اليومية المسموحة</th>
                            <th>ما صُرف فعلياً</th>
                            <th>فائض اليوم</th>
                            <th>المقتطع للحصالة</th>
                            <th>البيان والتفاصيل</th>
                        </tr>
                    </thead>
                    <tbody id="goalHarvestLogsTbody">
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                                جاري تحميل السجلات...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="modal-actions">
            <button type="button" class="btn-subtle" onclick="closeModal('goalHarvestLogsModal')">إغلاق</button>
        </div>
    </div>
</div>

<!-- 15. Convert Grocery to Expense Modal (تحويل المقاضي إلى مصروف في ظرف البقالة) -->
<div class="modal-backdrop hidden" id="convertGroceryModal">
    <div class="modal-card">
        <div class="modal-header">
            <div>
                <h3 style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem;">
                    <span>🛒</span>
                    <span>تحويل المقاضي إلى مصروف في ظرف البقالة</span>
                </h3>
                <p style="font-size: 0.82rem; color: var(--text-secondary); margin: 0;">
                    تسجيل فاتورة السوبرماركت كمصروف فوري وشطب الأصناف تلقائياً مع إشعار الشريك
                </p>
            </div>
            <button class="modal-close-btn" onclick="closeModal('convertGroceryModal')">&times;</button>
        </div>

        <form id="convertGroceryForm" onsubmit="handleConvertGrocerySubmit(event)" style="padding: 1.25rem 1.5rem;">
            @csrf
            
            <!-- Items Summary Preview -->
            <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25); border-radius: 14px; padding: 0.9rem 1.1rem; margin-bottom: 1.15rem;">
                <div style="font-size: 0.82rem; color: #34d399; font-weight: 800; margin-bottom: 0.4rem; display: flex; justify-content: space-between; align-items: center;">
                    <span>📦 الأصناف المشطوبة للشراء (<span id="modalConvertItemsCount">0</span> أصناف):</span>
                    <span style="font-size: 0.76rem; color: var(--text-muted);">تقديري: <strong id="modalConvertEstimatedSum">0</strong> ₪</span>
                </div>
                <div id="modalConvertItemsPills" style="display: flex; gap: 0.4rem; flex-wrap: wrap; max-height: 85px; overflow-y: auto;">
                    <!-- Filled dynamically via JS -->
                </div>
            </div>

            <!-- Total Amount Input -->
            <div class="form-group">
                <label>مبلغ الفاتورة الإجمالي الفعلي (بالشيكل): *</label>
                <div style="position: relative;">
                    <input type="number" step="0.5" name="amount" id="modalConvertAmount" class="styled-input" style="font-size: 1.25rem; font-weight: 800; color: #10b981; padding-left: 2.5rem;" required>
                    <span style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); font-weight: 800; color: var(--text-secondary);">₪</span>
                </div>
                <small style="color: var(--text-secondary); font-size: 0.75rem;">تم احتساب المبلغ التقديري تلقائياً، ويمكنك تعديله لمطابقة وصل السوبرماركت الفعلي بدقة.</small>
            </div>

            <!-- Target Envelope -->
            <div class="form-group">
                <label>الظرف المالي المستهدف: *</label>
                <select name="envelope_id" id="modalConvertEnvelope" class="styled-input" required>
                    @php
                        $groceryEnvelope = $envelopes->first(fn($e) => str_contains($e->name, 'بقالة'));
                        $defaultEnvId = $groceryEnvelope?->id ?? ($envelopes->first()?->id ?? 1);
                    @endphp
                    @foreach($envelopes as $env)
                        <option value="{{ $env->id }}" {{ $env->id == $defaultEnvId ? 'selected' : '' }}>
                            {{ $env->icon ?? '✉️' }} {{ $env->name }} (رصيد متبقي: {{ number_format($env->remaining_budget, 1) }} ₪)
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Payment Method & Date -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
                <div class="form-group">
                    <label>طريقة الدفع: *</label>
                    <select name="payment_method" id="modalConvertPaymentMethod" class="styled-input" required>
                        <option value="card">💳 بطاقة مصرفية</option>
                        <option value="cash" selected>💵 كاش / نقدي</option>
                        <option value="transfer">📲 تحويل / محفظة إلكترونية</option>
                        <option value="credit">📑 ذمم / شكك</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>تاريخ المعاملة: *</label>
                    <input type="date" name="date" id="modalConvertDate" class="styled-input" value="{{ date('Y-m-d') }}" required>
                </div>
            </div>

            <!-- Note / Description -->
            <div class="form-group">
                <label>البيان / ملاحظة (اختياري):</label>
                <input type="text" name="note" id="modalConvertNote" class="styled-input" placeholder="مثال: مقاضي البيت والسوبرماركت">
            </div>

            <div class="modal-actions" style="margin-top: 1.25rem;">
                <button type="button" class="btn-subtle" onclick="closeModal('convertGroceryModal')">إلغاء</button>
                <button type="submit" class="btn-primary" id="modalConvertSubmitBtn" style="background: linear-gradient(135deg, #10b981, #059669); font-weight: 800;">
                    <span>💳</span>
                    <span>تأكيد وتسجيل المصروف</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: SINGLE GROCERY ITEM 1-CLICK PURCHASE & INSTANT EXPENSE CONVERSION -->
<div class="modal-backdrop hidden" id="buySingleGroceryItemModal">
    <div class="modal-card">
        <div class="modal-header">
            <div>
                <h3 style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.2rem;">
                    <span>🛒</span>
                    <span>تسجيل شراء: <span id="modalBuyItemNameTitle" style="color: #34d399;"></span></span>
                </h3>
                <p style="font-size: 0.82rem; color: var(--text-secondary); margin: 0;">
                    أدخل سعر الغرض الفعلي ونوع الشراء لترحيلها فوراً كمصروف وإشعار الشريك
                </p>
            </div>
            <button class="modal-close-btn" onclick="closeModal('buySingleGroceryItemModal')">&times;</button>
        </div>

        <form id="buySingleGroceryItemForm" onsubmit="handleSingleBuySubmit(event)" style="padding: 1.25rem 1.5rem;">
            @csrf
            <input type="hidden" id="modalBuyItemId">

            <!-- Item Badge Summary -->
            <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25); border-radius: 12px; padding: 0.85rem 1rem; margin-bottom: 1.15rem; display: flex; align-items: center; gap: 0.75rem;">
                <span id="modalBuyItemIcon" style="font-size: 1.6rem; line-height: 1;">🛒</span>
                <div style="flex: 1;">
                    <div id="modalBuyItemName" style="font-size: 1.05rem; font-weight: 800; color: #f8fafc;"></div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">
                        <span>التصنيف: <strong id="modalBuyItemCategory" style="color: #38bdf8;"></strong></span>
                    </div>
                </div>
                <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.35); padding: 0.3rem 0.65rem; border-radius: 8px; font-size: 0.75rem; font-weight: 800; color: #34d399;">
                    جاهز للترحيل ⚡
                </div>
            </div>

            <!-- Price Paid Input -->
            <div class="form-group">
                <label style="font-weight: 800; font-size: 0.95rem; color: var(--text-primary); margin-bottom: 0.4rem; display: block;">
                    سعر الغرض الفعلي (بالشيكل ₪): *
                </label>
                <div style="position: relative;">
                    <input type="number" step="any" min="0.1" name="amount" id="modalBuyAmount" class="styled-input" style="font-size: 1.45rem; font-weight: 900; color: #10b981; padding-left: 2.8rem; text-align: left; direction: ltr;" placeholder="0.00" required autofocus autocomplete="off">
                    <span style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); font-weight: 900; font-size: 1.25rem; color: #10b981;">₪</span>
                </div>
                <small style="color: var(--text-secondary); font-size: 0.75rem; display: block; margin-top: 0.3rem;">
                    سجل قديش كلف الغرض بالضبط ليتم خصمه من الظرف وإشعار الشريك به.
                </small>
            </div>

            <!-- Payment Method Pills -->
            <div class="form-group" style="margin-top: 1rem;">
                <label style="font-weight: 700; font-size: 0.88rem; margin-bottom: 0.4rem; display: block;">نوع الشراء / طريقة الدفع: *</label>
                <input type="hidden" name="payment_method" id="modalBuyPaymentMethod" value="cash">
                <div class="payment-method-pills" id="modalBuyPaymentPills">
                    <button type="button" class="pay-pill" data-value="transfer" onclick="setBuyPaymentMethod('transfer')">
                        <span class="pay-pill-icon">📱</span>
                        <span class="pay-pill-label">تطبيق</span>
                        <span class="pay-pill-sub">رصيد حي</span>
                    </button>
                    <button type="button" class="pay-pill active" data-value="cash" onclick="setBuyPaymentMethod('cash')">
                        <span class="pay-pill-icon">💵</span>
                        <span class="pay-pill-label">كاش</span>
                        <span class="pay-pill-sub">نقدي</span>
                    </button>
                    <button type="button" class="pay-pill" data-value="card" onclick="setBuyPaymentMethod('card')">
                        <span class="pay-pill-icon">💳</span>
                        <span class="pay-pill-label">بطاقة</span>
                        <span class="pay-pill-sub">صراف</span>
                    </button>
                    <button type="button" class="pay-pill" data-value="credit" onclick="setBuyPaymentMethod('credit')">
                        <span class="pay-pill-icon">📑</span>
                        <span class="pay-pill-label">ذمم</span>
                        <span class="pay-pill-sub">شكك</span>
                    </button>
                </div>
                <div id="modalBuyPaymentHint" class="payment-hint-bar hint-cash" style="margin-top: 0.45rem;">
                    💵 سيُخصم من <strong>الكاش الفعلي</strong> (المجمد)
                </div>
            </div>

            <!-- Target Envelope -->
            <div class="form-group" style="margin-top: 1rem;">
                <label style="font-weight: 700; font-size: 0.88rem; margin-bottom: 0.35rem; display: block;">الظرف المالي المستهدف: *</label>
                <select name="envelope_id" id="modalBuyEnvelope" class="styled-input" required>
                    @foreach($envelopes as $env)
                        <option value="{{ $env->id }}" {{ $env->id == $defaultEnvId ? 'selected' : '' }}>
                            {{ $env->icon ?? '✉️' }} {{ $env->name }} (رصيد متبقي: {{ number_format($env->remaining_budget, 1) }} ₪)
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="modal-actions" style="margin-top: 1.35rem; display: flex; gap: 0.75rem;">
                <button type="button" class="btn-subtle" onclick="closeModal('buySingleGroceryItemModal')" style="flex: 1;">إلغاء</button>
                <button type="submit" class="btn-primary" id="modalBuySubmitBtn" style="flex: 2; background: linear-gradient(135deg, #10b981, #059669); font-weight: 800; font-size: 0.95rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                    <span>💳</span>
                    <span>تأكيد الشراء وترحيل للمصروفات</span>
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
    // ==========================================
    // PERSISTENT TAB SYSTEM & ZERO-FLICKER SYNC
    // ==========================================
    const VALID_TABS = ['tab-dashboard', 'tab-grocery', 'tab-envelopes', 'tab-goals', 'tab-leaks', 'tab-ledger', 'tab-debts'];

    function getActiveTabId() {
        const activePane = document.querySelector('.tab-pane.active');
        if (activePane && VALID_TABS.includes(activePane.id)) {
            return activePane.id;
        }
        return sessionStorage.getItem('masroofi_active_tab') 
            || localStorage.getItem('masroofi_active_tab') 
            || 'tab-dashboard';
    }

    function syncFormsActiveTab(tabId) {
        if (!tabId) return;
        document.querySelectorAll('form').forEach(form => {
            let input = form.querySelector('input[name="active_tab"]');
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'active_tab';
                form.appendChild(input);
            }
            input.value = tabId;
        });
    }

    function switchTab(tabId, updateUrl = true) {
        if (!tabId || !VALID_TABS.includes(tabId)) tabId = 'tab-dashboard';
        const pane = document.getElementById(tabId);
        if (!pane) return;

        // Clean up preload CSS if present
        const preloadStyle = document.getElementById('tab-preload-style');
        if (preloadStyle) preloadStyle.remove();

        // Update DOM active classes
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));

        const btn = document.querySelector(`.tab-btn[data-tab="${tabId}"]`);
        if (btn) btn.classList.add('active');
        pane.classList.add('active');

        // Persist in client storage
        try {
            sessionStorage.setItem('masroofi_active_tab', tabId);
            localStorage.setItem('masroofi_active_tab', tabId);
        } catch(e) {}

        // Sync hidden input in all forms
        syncFormsActiveTab(tabId);

        // Update URL hash cleanly
        if (updateUrl) {
            history.replaceState(null, '', '#' + tabId);
        }
    }

    // Tab button click listeners
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const tabId = btn.getAttribute('data-tab');
            if (tabId) switchTab(tabId, true);
        });
    });

    // Global form submit interceptor (Capture Phase): guarantees all form submissions carry current active tab
    document.addEventListener('submit', function(e) {
        if (!e.target || e.target.tagName !== 'FORM') return;
        const currentTab = getActiveTabId();
        try {
            sessionStorage.setItem('masroofi_active_tab', currentTab);
            localStorage.setItem('masroofi_active_tab', currentTab);
        } catch(err) {}

        let input = e.target.querySelector('input[name="active_tab"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'active_tab';
            e.target.appendChild(input);
        }
        input.value = currentTab;
    }, true);

    // Browser navigation (Back / Forward)
    window.addEventListener('hashchange', () => {
        const hash = window.location.hash ? window.location.hash.replace('#', '') : null;
        if (hash && VALID_TABS.includes(hash)) {
            switchTab(hash, false);
        }
    });

    // Initialize active tab on DOM ready
    (function initActiveTab() {
        const urlParams = new URLSearchParams(window.location.search);
        const queryTab = urlParams.get('tab');
        const hashTab = window.location.hash ? window.location.hash.replace('#', '') : null;
        const serverActivePane = document.querySelector('.tab-pane.active');
        const serverTab = serverActivePane ? serverActivePane.id : null;

        // Default strictly to tab-dashboard
        let target = 'tab-dashboard';
        if (queryTab && VALID_TABS.includes(queryTab)) {
            target = queryTab;
        } else if (hashTab && VALID_TABS.includes(hashTab)) {
            target = hashTab;
        } else if (serverTab && serverTab !== 'tab-dashboard' && VALID_TABS.includes(serverTab)) {
            target = serverTab;
        }

        switchTab(target, true);

        // Guarantee URL hash is #tab-dashboard when opening app
        if (!window.location.hash || window.location.hash === '#' || window.location.hash === '#_=_') {
            history.replaceState(null, '', '#tab-dashboard');
        }
    })();

    function updateLiveDailyImpact(val) {
        const box = document.getElementById('dailyImpactLiveBox');
        if (!box) return;
        const type = document.querySelector('input[name="type"]:checked')?.value || 'expense';
        if (type !== 'expense') {
            box.style.display = 'none';
            return;
        }
        box.style.display = 'block';

        const dailyEl = document.getElementById('safeDailyAmount');
        const currRemaining = parseFloat(dailyEl?.getAttribute('data-base-val') || dailyEl?.textContent.replace(/,/g, '')) || 0;
        const currCurrency = document.querySelector('meta[name="app-currency"]')?.content || 'ILS';

        const amount = parseFloat(val) || 0;
        const nextRemaining = currRemaining - amount;

        const elCurrent = document.getElementById('impactLiveCurrent');
        const elNext = document.getElementById('impactLiveNext');
        const elNote = document.getElementById('impactLiveNote');

        if (elCurrent) elCurrent.textContent = `${Math.round(currRemaining).toLocaleString()} ${currCurrency}`;

        if (amount <= 0) {
            if (elNext) {
                elNext.textContent = `${Math.round(currRemaining).toLocaleString()} ${currCurrency}`;
                elNext.className = 'step-val next';
            }
            if (elNote) elNote.innerHTML = '🟢 أدخل المبلغ لمعاينة كيف ستتأثر يوميتك لحظياً';
            return;
        }

        if (nextRemaining >= 0) {
            if (elNext) {
                elNext.textContent = `${Math.round(nextRemaining).toLocaleString()} ${currCurrency}`;
                elNext.className = 'step-val next text-success';
            }
            if (elNote) {
                elNote.innerHTML = `🟢 سيبقى معك <strong>${Math.round(nextRemaining).toLocaleString()} ${currCurrency}</strong> بأمان لبقية اليوم 👍`;
            }
        } else {
            const over = Math.abs(nextRemaining);
            if (elNext) {
                elNext.textContent = `0 ${currCurrency}`;
                elNext.className = 'step-val next text-danger';
            }
            if (elNote) {
                elNote.innerHTML = `⚠️ هذا المصروف يتجاوز متبقي يوميتك بـ <strong style="color: #ef4444;">${Math.round(over).toLocaleString()} ${currCurrency}</strong>! (سيتم تعويضها من باقي أيام الشهر لحماية ميزانيتك).`;
            }
        }
    }

    function openTxModal(type, envId = null) {
        openModal('transactionModal');
        setTxType(type);
        if(envId) {
            const sel = document.getElementById('txEnvelopeSelect');
            if(sel) sel.value = envId;
        }
        const amtInput = document.getElementById('txAmountInput');
        if(amtInput) {
            amtInput.value = '';
            updateLiveDailyImpact('');
        }
    }

    function setTxType(type) {
        document.querySelectorAll('.type-pill').forEach(p => p.classList.remove('active'));
        const radio = document.querySelector(`input[name="type"][value="${type}"]`);
        if(radio) {
            radio.checked = true;
            radio.closest('.type-pill')?.classList.add('active');
        }
        const envGroup = document.getElementById('txEnvGroup');
        const goalGroup = document.getElementById('txGoalGroup');
        const incomeTags = document.getElementById('txIncomeQuickTags');
        const noteInput = document.getElementById('txNoteInput');
        const noteLabel = document.getElementById('txNoteLabel');

        const goalSelect = document.getElementById('txGoalSelect');

        if(type === 'savings') {
            goalGroup?.classList.remove('hidden');
            if(goalSelect) goalSelect.disabled = false;
            envGroup?.classList.remove('hidden');
            if(incomeTags) incomeTags.style.display = 'none';
            if(noteLabel) noteLabel.textContent = 'ملاحظة الإيداع:';
            if(noteInput) noteInput.placeholder = 'مثال: إيداع شهري للتحويش...';
            const box = document.getElementById('dailyImpactLiveBox');
            if(box) box.style.display = 'none';
        } else if(type === 'income') {
            envGroup?.classList.add('hidden');
            goalGroup?.classList.add('hidden');
            if(goalSelect) {
                goalSelect.disabled = true;
                goalSelect.value = '';
            }
            if(incomeTags) incomeTags.style.display = 'flex';
            if(noteLabel) noteLabel.textContent = 'مصدر الدخل / اليومية:';
            if(noteInput) noteInput.placeholder = 'اختر من الأزرار أو اكتب مصدر الدخل...';
            const box = document.getElementById('dailyImpactLiveBox');
            if(box) box.style.display = 'none';
        } else {
            envGroup?.classList.remove('hidden');
            goalGroup?.classList.add('hidden');
            if(goalSelect) {
                goalSelect.disabled = true;
                goalSelect.value = '';
            }
            if(incomeTags) incomeTags.style.display = 'none';
            if(noteLabel) noteLabel.textContent = 'ملاحظة / تفاصيل المصروف:';
            if(noteInput) noteInput.placeholder = 'مثال: مقاضي السوبرماركت، بنزين، قهوة...';
            updateLiveDailyImpact(document.getElementById('txAmountInput')?.value || '');
        }
    }

    function setTxNote(val) {
        const input = document.getElementById('txNoteInput');
        if(input) input.value = val;
    }

    function setPaymentMethod(value) {
        // Update hidden input
        const hidden = document.getElementById('txPaymentMethodInput');
        if(hidden) hidden.value = value;

        // Update active pill
        document.querySelectorAll('.pay-pill').forEach(p => p.classList.remove('active'));
        const activePill = document.querySelector(`.pay-pill[data-value="${value}"]`);
        if(activePill) activePill.classList.add('active');

        // Update hint bar
        const hint = document.getElementById('txPaymentHint');
        if(!hint) return;
        hint.className = `payment-hint-bar hint-${value}`;
        const hints = {
            transfer: '📱 سيُخصم من <strong>رصيد التطبيق الفعّال</strong> (السيولة الحية)',
            cash:     '💵 سيُخصم من <strong>الكاش المجمد</strong> — تأكد أن لديك القدرة على الصرف',
            card:     '💳 سيُخصم عبر <strong>البطاقة البنكية أو الصراف</strong>',
            credit:   '📑 <strong>دين مؤجل</strong> — لن يُخصم الآن، سيُسجل كالتزام مستقبلي',
        };
        hint.innerHTML = hints[value] || hints.transfer;
    }

    // Edit Transaction Modal Functions
    function openEditTxModal(tx) {
        if (!tx) return;
        const form = document.getElementById('editTxForm');
        if (form) {
            form.action = `/transactions/${tx.id}`;
            form.setAttribute('data-tx-id', tx.id);
            form.setAttribute('data-original-tx', JSON.stringify(tx));
        }

        // Set Type
        setEditTxType(tx.type || 'expense');

        // Set Amount
        const amountInput = document.getElementById('editTxAmount');
        if (amountInput) amountInput.value = parseFloat(tx.amount || 0).toFixed(2);

        // Set Envelope / Goal
        const envSelect = document.getElementById('editTxEnvelopeSelect');
        if (envSelect) envSelect.value = tx.envelope_id || '';

        const goalSelect = document.getElementById('editTxGoalSelect');
        if (goalSelect) goalSelect.value = tx.goal_id || '';

        // Set Date (handles YYYY-MM-DD or ISO string)
        const dateInput = document.getElementById('editTxDate');
        if (dateInput) {
            let d = tx.date;
            if (typeof d === 'string') {
                d = d.includes('T') ? d.split('T')[0] : d.substring(0, 10);
            }
            if (dateInput._flatpickr) {
                dateInput._flatpickr.setDate(d || '', true);
            } else {
                dateInput.value = d || '';
            }
        }

        // Set Note
        const noteInput = document.getElementById('editTxNoteInput');
        if (noteInput) noteInput.value = tx.note || '';

        // Set Payment Method
        setEditPaymentMethod(tx.payment_method || 'transfer');

        openModal('editTransactionModal');
    }

    function setEditTxType(type) {
        document.querySelectorAll('#editTxForm .type-pill').forEach(p => p.classList.remove('active'));
        const radio = document.querySelector(`#editTxForm input[name="type"][value="${type}"]`);
        if (radio) {
            radio.checked = true;
            radio.closest('.type-pill')?.classList.add('active');
        }

        const envGroup = document.getElementById('editTxEnvGroup');
        const goalGroup = document.getElementById('editTxGoalGroup');
        const envSelect = document.getElementById('editTxEnvelopeSelect');
        const goalSelect = document.getElementById('editTxGoalSelect');

        if (type === 'savings') {
            goalGroup?.classList.remove('hidden');
            if (goalSelect) goalSelect.disabled = false;
            envGroup?.classList.add('hidden');
            if (envSelect) envSelect.disabled = true;
        } else if (type === 'income') {
            envGroup?.classList.add('hidden');
            goalGroup?.classList.add('hidden');
            if (envSelect) { envSelect.disabled = true; envSelect.value = ''; }
            if (goalSelect) { goalSelect.disabled = true; goalSelect.value = ''; }
        } else {
            envGroup?.classList.remove('hidden');
            if (envSelect) envSelect.disabled = false;
            goalGroup?.classList.add('hidden');
            if (goalSelect) { goalSelect.disabled = true; goalSelect.value = ''; }
        }
    }

    function setEditPaymentMethod(value) {
        const hidden = document.getElementById('editTxPaymentMethodInput');
        if (hidden) hidden.value = value;

        document.querySelectorAll('#editTxPaymentPills .pay-pill').forEach(p => p.classList.remove('active'));
        const activePill = document.querySelector(`#editTxPaymentPills .pay-pill[data-value="${value}"]`);
        if (activePill) activePill.classList.add('active');

        const hint = document.getElementById('editTxPaymentHint');
        if (!hint) return;
        hint.className = `payment-hint-bar hint-${value}`;
        const hints = {
            transfer: '📱 سيُخصم من <strong>رصيد التطبيق الفعّال</strong> (السيولة الحية)',
            cash:     '💵 سيُخصم من <strong>الكاش المجمد</strong> — تأكد أن لديك القدرة على الصرف',
            card:     '💳 سيُخصم عبر <strong>البطاقة البنكية أو الصراف</strong>',
            credit:   '📑 <strong>دين مؤجل</strong> — لن يُخصم الآن، سيُسجل كالتزام مستقبلي',
        };
        hint.innerHTML = hints[value] || hints.transfer;
    }



    function openDepositModal(goalId = null, goalName = '') {
        const firstGoalId = goalId || document.getElementById('depositGoalSelect')?.value;
        if(firstGoalId) updateDepositAction(firstGoalId);
        if(goalId) {
            const sel = document.getElementById('depositGoalSelect');
            if(sel) sel.value = goalId;
        }
        openModal('feedGoalModal');
    }

    function updateDepositAction(goalId) {
        const form = document.getElementById('depositForm');
        if(form) form.action = `/goals/${goalId}/deposit`;
    }

    const firstG = document.getElementById('depositGoalSelect')?.value;
    if(firstG) updateDepositAction(firstG);


    // Professional Purchasing Strategy Functions
    function sendToWishlistFromImpact() {
        const itemName = document.getElementById('impactItemName')?.value || 'سلعة مرغوبة';
        const price = document.getElementById('impactPrice')?.value || 350;
        const modalInputTitle = document.querySelector('#wishModal input[name="title"]');
        const modalInputPrice = document.querySelector('#wishModal input[name="price"]');
        if(modalInputTitle) modalInputTitle.value = itemName;
        if(modalInputPrice) modalInputPrice.value = price;
        openModal('wishModal');
    }

    function sendToGoalFromImpact() {
        const itemName = document.getElementById('impactItemName')?.value || 'هدف شراء جديد';
        const price = document.getElementById('impactPrice')?.value || 350;
        const modalInputName = document.querySelector('#goalModal input[name="name"]');
        const modalInputTarget = document.querySelector('#goalModal input[name="target_amount"]');
        if(modalInputName) modalInputName.value = itemName;
        if(modalInputTarget) modalInputTarget.value = price;
        openModal('goalModal');
    }

    function buyNowAndDeductDaily() {
        const itemName = document.getElementById('impactItemName')?.value || 'شراء سلعة';
        const price = document.getElementById('impactPrice')?.value || 0;

        if(typeof setTxType === 'function') setTxType('expense');
        const amountInput = document.querySelector('#txForm input[name="amount"]');
        const noteInput = document.getElementById('txNoteInput');
        if(amountInput) amountInput.value = price;
        if(noteInput) noteInput.value = itemName;
        if(typeof setPaymentMethod === 'function') setPaymentMethod('transfer');

        openModal('transactionModal');
    }

    // Purchase Impact on Salary & Liquidity Calculation (Replaces Work Hours)
    function calculatePurchaseImpact() {
        const salary = parseFloat(document.getElementById('impactSalary')?.value) || {{ $totalSalary > 0 ? $totalSalary : 3811 }};
        const appBal = parseFloat(document.getElementById('impactAppBalance')?.value) || {{ $appSpendableBalance > 0 ? $appSpendableBalance : ($appBalance > 0 ? $appBalance : 1199) }};
        const safeDaily = parseFloat(document.getElementById('impactSafeDaily')?.value) || {{ $safeDaily > 0 ? $safeDaily : 57 }};
        const daysRemaining = parseInt(document.getElementById('impactDaysRemaining')?.value) || {{ $daysRemaining > 0 ? $daysRemaining : 21 }};
        const price = parseFloat(document.getElementById('impactPrice')?.value) || 0;
        const usageCount = parseInt(document.getElementById('impactUsageCount')?.value) || 60;
        const curr = "{{ $settings->currency ?? 'ILS' }}";

        const salaryPct = salary > 0 ? ((price / salary) * 100).toFixed(1) : 0;
        const appPct = appBal > 0 ? ((price / appBal) * 100).toFixed(1) : 0;
        const daysSwallowed = safeDaily > 0 ? (price / safeDaily).toFixed(1) : 0;

        // New Daily Safe Allowance if bought today
        const remainingBal = Math.max(0, appBal - price);
        const newDaily = daysRemaining > 0 ? Math.max(0, Math.round(remainingBal / daysRemaining)) : 0;
        const dailyDrop = Math.max(0, Math.round(safeDaily - newDaily));

        // Cost per use
        const costPerUse = (usageCount > 0 && price > 0) ? (price / usageCount).toFixed(1) : (price).toFixed(1);

        // Sinking Fund Plan (for heavy purchases)
        let sinkingMonths = 3;
        if (price > 1200) sinkingMonths = 6;
        else if (price > 600) sinkingMonths = 4;
        else if (price < 250) sinkingMonths = 2;
        const sinkingPerMonth = Math.round(price / sinkingMonths);

        // Elements
        const elSalaryPct = document.getElementById('impactSalaryPct');
        const elAppPct = document.getElementById('impactAppPct');
        const elDaysSwallowed = document.getElementById('impactDaysSwallowed');
        const elNewDaily = document.getElementById('impactNewSafeDaily');
        const elDailyDropDesc = document.getElementById('impactDailyDropDesc');
        const elCostPerUse = document.getElementById('impactCostPerUse');
        const elDailyCutDesc = document.getElementById('dailyCutPlanDesc');
        const elSinkingDesc = document.getElementById('sinkingPlanDesc');
        const elVerdict = document.getElementById('impactVerdict');
        const elBadge = document.getElementById('strategyBadge');
        const cardCooldown = document.getElementById('cardCooldown');
        const cardDailyCut = document.getElementById('cardDailyCut');
        const cardSinking = document.getElementById('cardSinking');

        if(elSalaryPct) elSalaryPct.textContent = `${salaryPct}%`;
        if(elAppPct) elAppPct.textContent = `${appPct}%`;
        if(elDaysSwallowed) elDaysSwallowed.textContent = `${daysSwallowed} يوم`;
        if(elNewDaily) elNewDaily.textContent = `${newDaily}`;
        if(elDailyDropDesc) {
            elDailyDropDesc.textContent = dailyDrop > 0 
                ? `خصم ${dailyDrop} ${curr} لكل يوم من الـ ${daysRemaining} يوماً المتبقية!`
                : `لا يؤثر بشكل ملموس على مصروفك اليومي`;
        }
        if(elCostPerUse) elCostPerUse.textContent = `${costPerUse}`;

        if(elDailyCutDesc) {
            if(price > appBal) {
                elDailyCutDesc.innerHTML = `⚠️ <strong>تنبيه:</strong> سعر السلعة أكبر من سيولتك المتاحة! شراؤها الآن سيصفر ميزانيتك اليومية بالكامل (0 ${curr}/يوم) ويجبرك على كسر الادخار.`;
            } else {
                elDailyCutDesc.innerHTML = `اشترِها الآن وادفع ثمنها من ميزانيتك اليومية! سينخفض مسموحك اليومي من <strong>${safeDaily} ${curr}</strong> إلى <strong style="color: #ef4444;">${newDaily} ${curr}</strong> (اقتطاع <strong>${dailyDrop} ${curr}</strong> لكل يوم من الـ <strong>${daysRemaining}</strong> يوماً المتبقية حتى نهاية الشهر).`;
            }
        }

        if(elSinkingDesc) {
            elSinkingDesc.innerHTML = `لا تقتطع هذا المبلغ من ميزانية شهرك دفعة واحدة ولا تضغط يوميتك! ادخر لها <strong>${sinkingPerMonth.toLocaleString()} ${curr} شهرياً لمدة ${sinkingMonths} أشهر</strong>، واشترِها كاش دون أن ينقص مصروفك اليومي قرشاً واحداً!`;
        }

        // Remove all highlight states
        cardCooldown?.classList.remove('highlight-amber', 'highlighted');
        cardDailyCut?.classList.remove('highlight-blue', 'highlighted');
        cardSinking?.classList.remove('highlight-emerald', 'highlighted');

        if(elVerdict) {
            if(price <= 0) {
                elVerdict.className = 'impact-verdict-banner impact-verdict-safe';
                elVerdict.innerHTML = '✨ أدخل سعر السلعة لمعرفة أثرها المالي واختيار المسار المناسب لشرائها!';
                if(elBadge) {
                    elBadge.style.background = '#6b7280';
                    elBadge.textContent = 'بانتظار السعر';
                }
            } else if(price <= (safeDaily * 1.5)) {
                // Light purchase (< 1.5 days of safe daily): Recommend Path 2 (Daily Cut)
                elVerdict.className = 'impact-verdict-banner impact-verdict-safe';
                elVerdict.innerHTML = `🟢 <strong>شراء فوري مقدور عليه:</strong> تبتلع فقط <strong>${appPct}%</strong> من سيولتك، وخصمها من المصروف اليومي يترك لك <strong>${newDaily} ${curr}/يوم</strong> وهو كافٍ وآمن!`;
                if(elBadge) {
                    elBadge.style.background = '#3b82f6';
                    elBadge.textContent = '🟢 الموصى به: المسار الثاني (الخصم من اليومية)';
                }
                cardDailyCut?.classList.add('highlight-blue');
            } else if(price > 350 || appPct > 30) {
                // Heavy purchase: Recommend Path 3 (Sinking Fund)
                elVerdict.className = 'impact-verdict-banner impact-verdict-danger';
                elVerdict.innerHTML = `🚨 <strong>تحذير استنزاف حاد:</strong> شراؤها دفعة واحدة الآن سيخفض مصروفك اليومي إلى <strong>${newDaily} ${curr}</strong> فقط! <strong>المسار الاحترافي هو صندوق التحويش (Sinking Fund)</strong>.`;
                if(elBadge) {
                    elBadge.style.background = '#10b981';
                    elBadge.textContent = '🔴 الموصى به: المسار الثالث (صندوق التحويش)';
                }
                cardSinking?.classList.add('highlight-emerald');
            } else {
                // Medium purchase: Recommend Path 1 (Cooldown), but Path 2 is directly available
                elVerdict.className = 'impact-verdict-banner impact-verdict-warning';
                elVerdict.innerHTML = `⚠️ <strong>عبء مالي متوسط:</strong> تبتلع مصروف <strong>${daysSwallowed} أيام</strong>. <strong>المسار الاحترافي هو إرسالها لقائمة التهدئة 48 ساعة</strong> أو قبول تقليص اليومية إلى <strong>${newDaily} ${curr}</strong>.`;
                if(elBadge) {
                    elBadge.style.background = '#f59e0b';
                    elBadge.textContent = '🟡 الموصى به: المسار الأول (التهدئة 48 ساعة)';
                }
                cardCooldown?.classList.add('highlight-amber');
            }
        }
    }

    function openEditEnvelopeModal(env) {
        document.getElementById('editEnvName').value   = env.name   || '';
        document.getElementById('editEnvIcon').value   = env.icon   || '📦';
        document.getElementById('editEnvBudget').value = env.budget  || 0;
        document.getElementById('editEnvColor').value  = env.color  || '#10b981';
        document.getElementById('editEnvelopeForm').action = `/envelopes/${env.id}`;
        setEditPillar(env.pillar || 'needs');
        openModal('editEnvelopeModal');
    }

    function setEditPillar(pillar) {
        document.querySelectorAll('#editEnvPillarPills .type-pill').forEach(p => p.classList.remove('active'));
        const radio = document.querySelector(`#editEnvPillarPills input[value="${pillar}"]`);
        if(radio) {
            radio.checked = true;
            radio.closest('.type-pill')?.classList.add('active');
        }
    }

    function toggleCustomCycleDay(mode) {
        const group = document.getElementById('customDayGroup');
        if (group) {
            group.style.display = mode === 'custom_day' ? 'block' : 'none';
        }
    }

    function switchSalarySubTab(tab) {

        const btnList = document.getElementById('tabBtnSalariesList');
        const btnCycle = document.getElementById('tabBtnSalaryCycle');
        const secList = document.getElementById('salarySectionList');
        const secCycle = document.getElementById('salarySectionCycle');

        if(tab === 'list') {
            btnList?.classList.add('active');
            btnCycle?.classList.remove('active');
            secList?.classList.remove('hidden');
            secCycle?.classList.add('hidden');
        } else {
            btnCycle?.classList.add('active');
            btnList?.classList.remove('active');
            secCycle?.classList.remove('hidden');
            secList?.classList.add('hidden');
        }
    }

    function openEditSalaryModal(source) {
        if(!source) return;
        const form = document.getElementById('editSalaryForm');
        if(form) form.action = `/salaries/${source.id}`;

        const nameInput = document.getElementById('editSalaryName');
        const amountInput = document.getElementById('editSalaryAmount');
        const currSelect = document.getElementById('editSalaryCurrency');
        const methodSelect = document.getElementById('editSalaryMethod');
        const daySelect = document.getElementById('editSalaryDay');
        const notesInput = document.getElementById('editSalaryNotes');

        if(nameInput) nameInput.value = source.name || '';
        if(amountInput) amountInput.value = source.amount || '';
        if(currSelect) currSelect.value = source.currency || 'ILS';
        if(methodSelect) methodSelect.value = source.payment_method || 'transfer';
        if(daySelect) daySelect.value = source.day_of_month || 1;
        if(notesInput) notesInput.value = source.notes || '';

        openModal('editSalaryModal');
    }

    // Chart.js initialization
    document.addEventListener('DOMContentLoaded', () => {
        calculatePurchaseImpact();
        // Donut Chart
        const donutCtx = document.getElementById('categoryDonutChart');
        if(donutCtx) {
            const labels = @json($categoryChartLabels);
            const values = @json($categoryChartValues);
            const colors = @json($categoryChartColors);

            new Chart(donutCtx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: colors,
                        borderWidth: 2,
                        borderColor: '#111827'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { color: '#9ca3af', font: { family: 'Cairo' } } }
                    },
                    cutout: '68%'
                }
            });
        }

        // Trend Chart
        const trendCtx = document.getElementById('spendingTrendChart');
        if(trendCtx) {
            const totalDays = {{ $totalCycleDays }};
            const daysElapsed = {{ $daysElapsed }};
            const totalSpent = {{ $totalExpenses }};
            const budget = {{ $totalIncome > 0 ? ($totalIncome - $actualSavedOrReserved) : 5000 }};

            const labels = Array.from({length: totalDays}, (_, i) => `يوم ${i+1}`);
            const ideal = labels.map((_, i) => Math.round((budget / totalDays) * (i + 1)));
            const actual = [];
            let run = 0;
            for(let d=1; d<=totalDays; d++) {
                if(d <= daysElapsed) {
                    run = Math.min(totalSpent, run + (totalSpent / Math.max(1, daysElapsed)));
                    actual.push(Math.round(run));
                } else {
                    actual.push(null);
                }
            }

            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'المسار المثالي للميزانية',
                            data: ideal,
                            borderColor: 'rgba(156, 163, 175, 0.5)',
                            borderDash: [5, 5],
                            fill: false,
                            pointRadius: 0
                        },
                        {
                            label: 'الصرف الفعلي التراكمي',
                            data: actual,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.12)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { ticks: { color: '#6b7280', font: { size: 10 } } },
                        y: { ticks: { color: '#6b7280', font: { size: 10 } } }
                    },
                    plugins: {
                        legend: { position: 'top', labels: { color: '#9ca3af', font: { family: 'Cairo' } } }
                    }
                }
            });
        }
    });

    // Debts & Payables Helpers
    function setDebtTypePill(el) {
        document.querySelectorAll('#newDebtModal .type-pill').forEach(p => p.classList.remove('active'));
        if(el) el.classList.add('active');
    }

    function updateAddDebtAction(debtId) {
        const form = document.getElementById('addDebtRecordForm');
        if(form && debtId) {
            form.action = `/debts/${debtId}/add`;
        }
    }

    function openAddDebtForSpecific(debtId, debtName) {
        const select = document.getElementById('addDebtSelect');
        if(select) {
            select.value = debtId;
            updateAddDebtAction(debtId);
        }
        openModal('addDebtRecordModal');
    }

    let currentRepayDue = 0;
    function openRepayDebtModal(debtId, debtName, dueAmount) {
        const form = document.getElementById('repayDebtForm');
        const nameEl = document.getElementById('repayDebtName');
        const dueEl = document.getElementById('repayDebtDueText');
        const amountInput = document.getElementById('repayAmountInput');
        const noteInput = document.getElementById('repayNoteInput');
        const curr = "{{ $settings->currency ?? 'ILS' }}";

        currentRepayDue = dueAmount;
        if(form) form.action = `/debts/${debtId}/repay`;
        if(nameEl) nameEl.textContent = debtName;
        if(dueEl) dueEl.textContent = `${parseFloat(dueAmount).toFixed(2)} ${curr}`;
        if(amountInput) amountInput.value = parseFloat(dueAmount).toFixed(2);
        if(noteInput) noteInput.value = `سداد حساب: ${debtName}`;

        openModal('repayDebtModal');
    }

    function fillFullRepayAmount() {
        const amountInput = document.getElementById('repayAmountInput');
        if(amountInput && currentRepayDue > 0) {
            amountInput.value = parseFloat(currentRepayDue).toFixed(2);
        }
    }

    // Harvester Pot Functions
    function setHarvestRatioPill(el, ratio) {
        document.querySelectorAll('#activateHarvestModal .type-pill').forEach(p => p.classList.remove('active'));
        if(el) {
            el.classList.add('active');
            const radio = el.querySelector('input[type="radio"]');
            if(radio) radio.checked = true;
        }
    }

    function openActivateHarvestModal(debtId, debtName, totalDebt) {
        const form = document.getElementById('activateHarvestForm');
        const nameEl = document.getElementById('activateHarvestDebtName');
        const dueEl = document.getElementById('activateHarvestDebtDue');
        const curr = "{{ $settings->currency ?? 'ILS' }}";

        if(form) form.action = `/debts/${debtId}/harvest/activate`;
        if(nameEl) nameEl.textContent = debtName;
        if(dueEl) dueEl.textContent = `${parseFloat(totalDebt).toFixed(2)} ${curr}`;

        openModal('activateHarvestModal');
    }

    async function openHarvestLogsModal(debtId) {
        const titleEl = document.getElementById('harvestLogsTitle');
        const totalGatheredEl = document.getElementById('harvestTotalGathered');
        const tbody = document.getElementById('harvestLogsTableBody');
        const forecastAllowance = document.getElementById('forecastAllowance');
        const forecastSpent = document.getElementById('forecastSpent');
        const forecastSurplus = document.getElementById('forecastSurplus');
        const forecastHarvest = document.getElementById('forecastHarvest');

        if(tbody) {
            tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:1.5rem; color:#9ca3af;">جاري تحميل سجلات الرصد...</td></tr>`;
        }

        openModal('harvestLogsModal');

        try {
            const res = await fetch(`/debts/${debtId}/harvest/logs`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();

            if(!data.success) {
                if(tbody) tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:1rem; color:#ef4444;">تعذر تحميل السجلات</td></tr>`;
                return;
            }

            const debt = data.debt;
            const logs = data.logs || [];
            const forecast = data.forecast || {};

            if(titleEl) titleEl.textContent = `سجل الرصد اليومي الشفاف لدين [${debt.name}]`;
            if(totalGatheredEl) totalGatheredEl.textContent = parseFloat(debt.surplus_harvested || 0).toFixed(2);

            if(forecastAllowance) forecastAllowance.textContent = Math.round(forecast.allowance || 0);
            if(forecastSpent) forecastSpent.textContent = Math.round(forecast.spent || 0);
            if(forecastSurplus) forecastSurplus.textContent = Math.round(forecast.surplus || 0);
            if(forecastHarvest) forecastHarvest.textContent = parseFloat(forecast.expected_harvest || 0).toFixed(2);

            if(logs.length === 0) {
                if(tbody) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 2rem 1rem; color: #9ca3af;">
                                <div>⏳ لا توجد أيام سابقة مرصودة بعد</div>
                                <div style="font-size: 0.78rem; margin-top: 0.35rem; color: #6b7280;">
                                    يتم تسجيل السطور الشفافة تلقائياً عند انتهاء اليوم وبداية كل يوم جديد.
                                </div>
                            </td>
                        </tr>
                    `;
                }
            } else {
                let html = '';
                logs.forEach(log => {
                    const surplus = parseFloat(log.surplus_amount || 0);
                    const harvest = parseFloat(log.harvested_amount || 0);
                    const isZero = surplus <= 0 || harvest <= 0;

                    html += `
                        <tr style="${isZero ? 'opacity: 0.75;' : ''}">
                            <td style="font-weight: 700;">${log.date}</td>
                            <td>${parseFloat(log.day_allowance).toFixed(1)} ₪</td>
                            <td style="color: #f87171;">${parseFloat(log.day_spent).toFixed(1)} ₪</td>
                            <td style="color: ${surplus > 0 ? '#34d399' : '#9ca3af'}; font-weight: 700;">
                                ${surplus > 0 ? '+' : ''}${surplus.toFixed(1)} ₪
                            </td>
                            <td style="color: ${harvest > 0 ? '#10b981' : '#9ca3af'}; font-weight: 800;">
                                ${harvest > 0 ? '+' + harvest.toFixed(1) + ' ₪' : '0 ₪'}
                            </td>
                            <td style="font-size: 0.8rem; color: #d1d5db;">
                                ${log.note || '—'}
                            </td>
                        </tr>
                    `;
                });
                if(tbody) tbody.innerHTML = html;
            }

        } catch(err) {
            console.error('Error fetching harvest logs:', err);
            if(tbody) tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:1rem; color:#ef4444;">حدث خطأ أثناء تحميل السجلات</td></tr>`;
        }
    }

    // Goal Auto-Harvest Modal & Logs Helpers
    window.openActivateGoalHarvestModal = function(goalId, goalName, remainingNeeded, currency, symbol, currentRatio = 50, currentMaxDaily = 0) {
        const form = document.getElementById('activateGoalHarvestForm');
        if (form) form.action = `/goals/${goalId}/harvest/activate`;

        const nameEl = document.getElementById('activateGoalHarvestName');
        const dueEl = document.getElementById('activateGoalHarvestDue');
        if (nameEl) nameEl.textContent = goalName;
        if (dueEl) dueEl.textContent = `${parseFloat(remainingNeeded).toFixed(2)} ${symbol || currency}`;

        const slider = document.getElementById('modalGoalHarvestSlider');
        const numInput = document.getElementById('modalGoalHarvestRatioInput');
        const maxDailyInput = document.getElementById('modalGoalHarvestMaxDaily');

        if (slider) slider.value = currentRatio;
        if (numInput) numInput.value = currentRatio;
        if (maxDailyInput) maxDailyInput.value = currentMaxDaily > 0 ? currentMaxDaily : '';

        // Select matching pill
        document.querySelectorAll('#activateGoalHarvestModal .type-pill').forEach(pill => {
            const val = parseInt(pill.querySelector('input')?.value) || 0;
            pill.classList.toggle('active', val === currentRatio);
        });

        openModal('activateGoalHarvestModal');
    };

    window.setGoalHarvestRatioPill = function(pill, ratio) {
        document.querySelectorAll('#activateGoalHarvestModal .type-pill').forEach(p => p.classList.remove('active'));
        pill.classList.add('active');
        const slider = document.getElementById('modalGoalHarvestSlider');
        const numInput = document.getElementById('modalGoalHarvestRatioInput');
        if (slider) slider.value = ratio;
        if (numInput) numInput.value = ratio;
    };

    window.onModalGoalHarvestSlider = function(val) {
        const numInput = document.getElementById('modalGoalHarvestRatioInput');
        if (numInput) numInput.value = val;
        document.querySelectorAll('#activateGoalHarvestModal .type-pill').forEach(p => {
            const v = parseInt(p.querySelector('input')?.value) || 0;
            p.classList.toggle('active', v === parseInt(val));
        });
    };

    window.onModalGoalHarvestInput = function(val) {
        const slider = document.getElementById('modalGoalHarvestSlider');
        let v = parseInt(val) || 0;
        v = Math.max(1, Math.min(100, v));
        if (slider) slider.value = v;
        document.querySelectorAll('#activateGoalHarvestModal .type-pill').forEach(p => {
            const pv = parseInt(p.querySelector('input')?.value) || 0;
            p.classList.toggle('active', pv === v);
        });
    };

    window.openGoalHarvestLogsModal = async function(goalId, goalName) {
        const titleEl = document.getElementById('goalHarvestLogsTitle');
        if (titleEl) titleEl.textContent = `سجل الاقتطاعات التلقائية لحصالة [${goalName}]`;

        const tbody = document.getElementById('goalHarvestLogsTbody');
        if (tbody) tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:1.5rem; color:#94a3b8;">جاري التحميل...</td></tr>`;

        openModal('goalHarvestLogsModal');

        try {
            const res = await fetch(`/goals/${goalId}/harvest/logs`);
            const data = await res.json();

            if (data.success) {
                // Forecast
                const f = data.forecast;
                if (f) {
                    const allowEl = document.getElementById('goalForecastAllowance');
                    const spentEl = document.getElementById('goalForecastSpent');
                    const surplusEl = document.getElementById('goalForecastSurplus');
                    const harvestEl = document.getElementById('goalForecastHarvest');

                    if (allowEl) allowEl.textContent = Math.round(f.allowance);
                    if (spentEl) spentEl.textContent = Math.round(f.spent);
                    if (surplusEl) surplusEl.textContent = Math.round(f.surplus);
                    if (harvestEl) harvestEl.textContent = `+${parseFloat(f.expected_harvest_goal).toFixed(2)} ${f.currency} (${Math.round(f.expected_harvest_ils)} ₪)`;
                }

                // Logs Table
                const logs = data.logs || [];
                if (logs.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:2rem; color:#9ca3af;">لا توجد سجلات بعد. سيبدأ الاقتطاع التلقائي مع أول فائض متبقي بنهاية اليوم 🌙</td></tr>`;
                    return;
                }

                let html = '';
                logs.forEach(log => {
                    const surplus = parseFloat(log.surplus_amount || 0);
                    const harvestGoal = parseFloat(log.harvested_in_goal_currency || log.harvested_amount || 0);
                    const isZero = surplus <= 0 || harvestGoal <= 0;

                    html += `
                        <tr style="${isZero ? 'opacity: 0.75;' : ''}">
                            <td style="font-weight: 700;">${log.date}</td>
                            <td>${parseFloat(log.day_allowance).toFixed(1)} ₪</td>
                            <td style="color: #f87171;">${parseFloat(log.day_spent).toFixed(1)} ₪</td>
                            <td style="color: ${surplus > 0 ? '#34d399' : '#9ca3af'}; font-weight: 700;">
                                ${surplus > 0 ? '+' : ''}${surplus.toFixed(1)} ₪
                            </td>
                            <td style="color: ${harvestGoal > 0 ? '#10b981' : '#9ca3af'}; font-weight: 800;">
                                ${harvestGoal > 0 ? '+' + harvestGoal.toFixed(2) + ' ' + (log.currency || 'ILS') : '0'}
                            </td>
                            <td style="font-size: 0.8rem; color: #d1d5db;">
                                ${log.note || '—'}
                            </td>
                        </tr>
                    `;
                });
                if (tbody) tbody.innerHTML = html;
            }
        } catch (err) {
            console.error('Error loading goal harvest logs:', err);
            if (tbody) tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:1.5rem; color:#ef4444;">حدث خطأ أثناء تحميل السجلات</td></tr>`;
        }
    };

    window.toggleNewGoalHarvestOptions = function(checked) {
        const wrap = document.getElementById('newGoalHarvestOptionsWrap');
        if (wrap) wrap.classList.toggle('hidden', !checked);
    };

    // Unified Daily Surplus Splitter System
    let currentHarvestSurplus = 0;
    const hasUnpaidDebts = {{ $hasUnpaidDebts ? 'true' : 'false' }};
    const hasIncompleteGoals = {{ $hasIncompleteGoals ? 'true' : 'false' }};

    window.openDailySurplusHarvestModal = function(surplus) {
        currentHarvestSurplus = parseFloat(surplus) || 0;
        const baseInput = document.getElementById('harvestSurplusBaseInput');
        const dispEl = document.getElementById('harvestSurplusDisplay');
        if (baseInput) baseInput.value = currentHarvestSurplus;
        if (dispEl) dispEl.textContent = `${Math.round(currentHarvestSurplus)} ₪`;

        if (hasUnpaidDebts && hasIncompleteGoals) {
            applySplitStrategy(40, 40);
        } else if (hasIncompleteGoals) {
            applySplitStrategy(0, 50);
        } else if (hasUnpaidDebts) {
            applySplitStrategy(50, 0);
        } else {
            applySplitStrategy(0, 0);
        }

        if (typeof window.setHarvestExecutionMode === 'function') {
            window.setHarvestExecutionMode('scheduled');
        }

        updateUnifiedCalculations();
        openModal('harvestDailySurplusModal');
    };

    window.currentHarvestExecutionMode = 'scheduled';

    window.setHarvestExecutionMode = function(mode) {
        window.currentHarvestExecutionMode = mode;
        const input = document.getElementById('harvestExecutionModeInput');
        if (input) input.value = mode;

        const btnSched = document.getElementById('btnModeScheduled');
        const btnInst = document.getElementById('btnModeInstant');
        const badge = document.getElementById('modeStatusBadge');

        if (mode === 'scheduled') {
            if (btnSched) {
                btnSched.style.borderColor = '#10b981';
                btnSched.style.background = 'rgba(16,185,129,0.18)';
                const t = btnSched.querySelector('.mode-title');
                if (t) t.style.color = '#34d399';
            }
            if (btnInst) {
                btnInst.style.borderColor = 'rgba(255,255,255,0.1)';
                btnInst.style.background = 'rgba(255,255,255,0.02)';
                const t = btnInst.querySelector('.mode-title');
                if (t) t.style.color = '#9ca3af';
            }
            if (badge) {
                badge.textContent = '🌙 في وقته المحدد (منتصف الليل)';
                badge.style.background = 'rgba(52, 211, 153, 0.15)';
                badge.style.color = '#34d399';
            }
        } else {
            if (btnSched) {
                btnSched.style.borderColor = 'rgba(255,255,255,0.1)';
                btnSched.style.background = 'rgba(255,255,255,0.02)';
                const t = btnSched.querySelector('.mode-title');
                if (t) t.style.color = '#9ca3af';
            }
            if (btnInst) {
                btnInst.style.borderColor = '#f59e0b';
                btnInst.style.background = 'rgba(245,158,11,0.18)';
                const t = btnInst.querySelector('.mode-title');
                if (t) t.style.color = '#fbbf24';
            }
            if (badge) {
                badge.textContent = '⚡ سداد وخصم فوري الآن';
                badge.style.background = 'rgba(245, 158, 11, 0.15)';
                badge.style.color = '#fbbf24';
            }
        }

        updateUnifiedCalculations();
    };

    window.applySplitStrategy = function(debtPct, goalPct) {
        debtPct = parseInt(debtPct) || 0;
        goalPct = parseInt(goalPct) || 0;

        const sliderDebt = document.getElementById('sliderDebtPct');
        const numDebt = document.getElementById('numDebtPct');
        const sliderGoal = document.getElementById('sliderGoalPct');
        const numGoal = document.getElementById('numGoalPct');

        if (sliderDebt) sliderDebt.value = debtPct;
        if (numDebt) numDebt.value = debtPct;
        if (sliderGoal) sliderGoal.value = goalPct;
        if (numGoal) numGoal.value = goalPct;

        document.querySelectorAll('.strategy-preset-btn').forEach(btn => {
            btn.classList.toggle('active', btn.getAttribute('onclick')?.includes(`${debtPct}, ${goalPct}`));
        });

        updateUnifiedCalculations();
    };

    window.onDebtSliderChange = function(val) {
        let debt = parseInt(val) || 0;
        debt = Math.max(0, Math.min(100, debt));
        const numDebt = document.getElementById('numDebtPct');
        if (numDebt) numDebt.value = debt;

        if (hasIncompleteGoals) {
            const sliderGoal = document.getElementById('sliderGoalPct');
            const numGoal = document.getElementById('numGoalPct');
            let goal = parseInt(sliderGoal ? sliderGoal.value : 0) || 0;
            if ((debt + goal) > 100) {
                goal = 100 - debt;
                if (sliderGoal) sliderGoal.value = goal;
                if (numGoal) numGoal.value = goal;
            }
        }

        clearActiveStrategyPill();
        updateUnifiedCalculations();
    };

    window.onDebtNumChange = function(val) {
        let debt = parseInt(val);
        if (isNaN(debt)) return;
        debt = Math.max(0, Math.min(100, debt));
        const sliderDebt = document.getElementById('sliderDebtPct');
        if (sliderDebt) sliderDebt.value = debt;

        if (hasIncompleteGoals) {
            const sliderGoal = document.getElementById('sliderGoalPct');
            const numGoal = document.getElementById('numGoalPct');
            let goal = parseInt(sliderGoal ? sliderGoal.value : 0) || 0;
            if ((debt + goal) > 100) {
                goal = 100 - debt;
                if (sliderGoal) sliderGoal.value = goal;
                if (numGoal) numGoal.value = goal;
            }
        }

        clearActiveStrategyPill();
        updateUnifiedCalculations();
    };

    window.onGoalSliderChange = function(val) {
        let goal = parseInt(val) || 0;
        goal = Math.max(0, Math.min(100, goal));
        const numGoal = document.getElementById('numGoalPct');
        if (numGoal) numGoal.value = goal;

        if (hasUnpaidDebts) {
            const sliderDebt = document.getElementById('sliderDebtPct');
            const numDebt = document.getElementById('numDebtPct');
            let debt = parseInt(sliderDebt ? sliderDebt.value : 0) || 0;
            if ((debt + goal) > 100) {
                debt = 100 - goal;
                if (sliderDebt) sliderDebt.value = debt;
                if (numDebt) numDebt.value = debt;
            }
        }

        clearActiveStrategyPill();
        updateUnifiedCalculations();
    };

    window.onGoalNumChange = function(val) {
        let goal = parseInt(val);
        if (isNaN(goal)) return;
        goal = Math.max(0, Math.min(100, goal));
        const sliderGoal = document.getElementById('sliderGoalPct');
        if (sliderGoal) sliderGoal.value = goal;

        if (hasUnpaidDebts) {
            const sliderDebt = document.getElementById('sliderDebtPct');
            const numDebt = document.getElementById('numDebtPct');
            let debt = parseInt(sliderDebt ? sliderDebt.value : 0) || 0;
            if ((debt + goal) > 100) {
                debt = 100 - goal;
                if (sliderDebt) sliderDebt.value = debt;
                if (numDebt) numDebt.value = debt;
            }
        }

        clearActiveStrategyPill();
        updateUnifiedCalculations();
    };

    window.clearActiveStrategyPill = function() {
        document.querySelectorAll('.strategy-preset-btn').forEach(btn => btn.classList.remove('active'));
    };

    window.updateUnifiedCalculations = function() {
        const surplus = currentHarvestSurplus > 0 ? currentHarvestSurplus : (parseFloat(document.getElementById('harvestSurplusBaseInput')?.value) || 0);

        const debtPct = hasUnpaidDebts ? (parseInt(document.getElementById('sliderDebtPct')?.value) || 0) : 0;
        const goalPct = hasIncompleteGoals ? (parseInt(document.getElementById('sliderGoalPct')?.value) || 0) : 0;
        const bufferPct = Math.max(0, 100 - (debtPct + goalPct));

        // Update Badges & Legends
        const bDebt = document.getElementById('badgeDebtPct');
        const bGoal = document.getElementById('badgeGoalPct');
        const lDebt = document.getElementById('legendDebtPct');
        const lGoal = document.getElementById('legendGoalPct');
        const lBuffer = document.getElementById('legendBufferPct');

        if (bDebt) bDebt.textContent = `${debtPct}%`;
        if (bGoal) bGoal.textContent = `${goalPct}%`;
        if (lDebt) lDebt.textContent = `${debtPct}%`;
        if (lGoal) lGoal.textContent = `${goalPct}%`;
        if (lBuffer) lBuffer.textContent = `${bufferPct}%`;

        // Update Split Progress Bar Segments
        const segDebt = document.getElementById('splitSegDebt');
        const segGoal = document.getElementById('splitSegGoal');
        const segBuffer = document.getElementById('splitSegBuffer');

        if (segDebt) segDebt.style.width = `${debtPct}%`;
        if (segGoal) segGoal.style.width = `${goalPct}%`;
        if (segBuffer) segBuffer.style.width = `${bufferPct}%`;

        // Calculate Amounts
        const debtILS = Math.round(surplus * (debtPct / 100) * 100) / 100;
        const goalILS = Math.round(surplus * (goalPct / 100) * 100) / 100;
        const bufferILS = Math.max(0, Math.round((surplus - (debtILS + goalILS)) * 100) / 100);

        // Goal Currency Conversion
        const goalSelect = document.getElementById('allocTargetGoalSelect');
        const selectedGoalOpt = goalSelect ? goalSelect.options[goalSelect.selectedIndex] : null;
        const goalRate = selectedGoalOpt ? parseFloat(selectedGoalOpt.getAttribute('data-rate-from-ils') || 1) : 1;
        const goalSymbol = selectedGoalOpt ? selectedGoalOpt.getAttribute('data-symbol') : '₪';
        const goalCurrency = selectedGoalOpt ? selectedGoalOpt.getAttribute('data-currency') : 'ILS';

        const goalConverted = goalCurrency === 'ILS' ? goalILS : Math.round(goalILS * goalRate * 100) / 100;

        // Simulation Card Values
        const simDebt = document.getElementById('simValDebt');
        const simGoal = document.getElementById('simValGoal');
        const simBuffer = document.getElementById('simValBuffer');
        const btnSubmit = document.getElementById('btnConfirmUnifiedHarvest');

        if (simDebt) simDebt.textContent = `- ${debtILS.toFixed(2)} ₪`;
        if (simGoal) simGoal.textContent = `+ ${goalConverted.toFixed(2)} ${goalSymbol} (${goalILS.toFixed(0)} ₪)`;
        if (simBuffer) simBuffer.textContent = `${bufferILS.toFixed(2)} ₪`;

        if (btnSubmit) {
            const isScheduled = (window.currentHarvestExecutionMode === 'scheduled');

            if (isScheduled) {
                btnSubmit.style.background = 'linear-gradient(135deg, #10b981, #059669)';
                btnSubmit.style.borderColor = 'rgba(16,185,129,0.4)';
                let parts = [];
                if (debtPct > 0) parts.push(`${debtPct}% للدين`);
                if (goalPct > 0) parts.push(`${goalPct}% للتحويش`);
                const desc = parts.length > 0 ? ` (${parts.join(' + ')})` : '';
                btnSubmit.innerHTML = `<span>🌙 حفظ وتثبيت التوزيع عند منتصف الليل${desc}</span>`;
            } else {
                btnSubmit.style.background = 'linear-gradient(135deg, #f59e0b, #d97706)';
                btnSubmit.style.borderColor = 'rgba(245,158,11,0.4)';
                let actionDesc = [];
                if (debtILS > 0) actionDesc.push(`${debtILS.toFixed(0)} ₪ دين`);
                if (goalILS > 0) actionDesc.push(`${goalConverted.toFixed(goalCurrency === 'USD' ? 2 : 0)} ${goalSymbol} تحويش`);
                const label = actionDesc.length > 0 ? `خصم فوري الآن (${actionDesc.join(' + ')})` : `ترحيل كامل الفائض (${bufferILS.toFixed(0)} ₪) لليوميات`;
                btnSubmit.innerHTML = `<span>⚡ تأكيد ال${label}</span>`;
            }
        }
    };

    window.submitHarvestSurplusForm = function(e) {
        if (typeof confetti === 'function') {
            confetti({
                particleCount: 80,
                spread: 75,
                origin: { y: 0.6 }
            });
        }
        return true;
    };

    // =========================================================================
    // SHARED SMART GROCERY LIST & SUPERMARKET SYNERGY
    // =========================================================================

    // Quick suggestion fill
    window.quickFillGrocery = function(name, category) {
        const inputName = document.getElementById('groceryInputName');
        const selectCat = document.getElementById('grocerySelectCategory');

        if (inputName) inputName.value = name;
        if (selectCat && category) selectCat.value = category;
        if (inputName) inputName.focus();
    };

    // Category filter
    window.filterGroceryCategory = function(cat, btn) {
        document.querySelectorAll('.cat-filter-btn').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');

        const rows = document.querySelectorAll('.grocery-item-card');
        rows.forEach(row => {
            const rowCat = row.getAttribute('data-category');
            if (cat === 'all' || rowCat === cat) {
                row.style.display = 'flex';
            } else {
                row.style.display = 'none';
            }
        });
    };

    // Recalculate grocery counters & convert banner in DOM
    window.updateGroceryDOMStats = function() {
        const rows = Array.from(document.querySelectorAll('.grocery-item-card'));
        const pendingRows = rows.filter(r => r.getAttribute('data-bought') === '0');
        const boughtRows = rows.filter(r => r.getAttribute('data-bought') === '1');

        let boughtSum = 0;
        boughtRows.forEach(r => {
            boughtSum += parseFloat(r.getAttribute('data-price') || 0);
        });

        const statPending = document.getElementById('groceryStatPending');
        const statBought = document.getElementById('groceryStatBought');
        const statEstimated = document.getElementById('groceryStatEstimated');
        const banner = document.getElementById('groceryConvertBanner');
        const bannerCount = document.getElementById('convertBannerCount');
        const navBadge = document.getElementById('groceryNavBadge');

        if (statPending) statPending.textContent = pendingRows.length;
        if (statBought) statBought.textContent = boughtRows.length;
        if (statEstimated) statEstimated.textContent = boughtSum.toFixed(1);
        if (bannerCount) bannerCount.textContent = boughtRows.length;

        if (banner) {
            banner.style.display = boughtRows.length > 0 ? 'flex' : 'none';
        }

        if (navBadge) {
            if (pendingRows.length > 0) {
                navBadge.textContent = pendingRows.length;
                navBadge.style.display = 'inline-flex';
            } else {
                navBadge.style.display = 'none';
            }
        }

        if (rows.length === 0) {
            const list = document.getElementById('groceryItemsList');
            if (list && !document.getElementById('groceryEmptyState')) {
                list.innerHTML = `
                    <div class="grocery-empty-state" id="groceryEmptyState">
                        <span class="grocery-empty-icon">🛒✨</span>
                        <div class="grocery-empty-title">قائمة المقاضي مكتملة ولا ينقص البيت شيء!</div>
                        <div class="grocery-empty-desc">
                            اكتب أي غرض ناقص (حليب، خضار، منظفات...) وسيظهر فوراً على هاتف شريكتك!
                        </div>
                    </div>
                `;
            }
        }
    };

    // Add item (Online + Offline)
    window.handleGroceryFormSubmit = async function(e) {
        e.preventDefault();
        const inputName = document.getElementById('groceryInputName');
        const selectCat = document.getElementById('grocerySelectCategory');
        const submitBtn = document.getElementById('grocerySubmitBtn');

        const name = inputName ? inputName.value.trim() : '';
        const category = selectCat ? selectCat.value : 'groceries';

        if (!name) return;

        if (submitBtn) submitBtn.disabled = true;

        const payload = {
            name: name,
            category: category,
            estimated_price: null
        };

        // If Offline
        if (!navigator.onLine) {
            if (window.offlineEngine && typeof window.offlineEngine.addOfflineGroceryItem === 'function') {
                const tempId = await window.offlineEngine.addOfflineGroceryItem(payload);
                renderNewGroceryItemInDOM({
                    id: tempId,
                    name: name,
                    category: category,
                    estimated_price: null,
                    is_bought: false,
                    is_offline: true,
                    created_at_human: 'الآن (محلياً 📴)'
                });
                if (window.showPartnerToast) {
                    showPartnerToast('📴 حُفظ بدون إنترنت', `أضيف "${name}" وسيُزامن فور عودة الشبكة`, '⚡');
                }
            }
            if (inputName) inputName.value = '';
            if (submitBtn) submitBtn.disabled = false;
            updateGroceryDOMStats();
            return;
        }

        // If Online
        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const res = await fetch("{{ route('grocery-items.store') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const data = await res.json();
            if (data.success && data.item) {
                renderNewGroceryItemInDOM(data.item);
                if (inputName) inputName.value = '';
                if (window.showPartnerToast) {
                    showPartnerToast('🛒 تمت الإضافة', `أضيف "${name}" لقائمة المقاضي`, '✅');
                }
                updateGroceryDOMStats();
            } else {
                alert(data.message || 'حدث خطأ أثناء الإضافة');
            }
        } catch(err) {
            console.error('Error adding grocery item:', err);
            // Fallback to offline store
            if (window.offlineEngine && typeof window.offlineEngine.addOfflineGroceryItem === 'function') {
                const tempId = await window.offlineEngine.addOfflineGroceryItem(payload);
                renderNewGroceryItemInDOM({
                    id: tempId,
                    name: name,
                    category: category,
                    estimated_price: null,
                    is_bought: false,
                    is_offline: true,
                    created_at_human: 'الآن (محلياً 📴)'
                });
                updateGroceryDOMStats();
            }
        } finally {
            if (submitBtn) submitBtn.disabled = false;
        }
    };

    function renderNewGroceryItemInDOM(item) {
        const list = document.getElementById('groceryItemsList');
        const emptyState = document.getElementById('groceryEmptyState');
        if (emptyState) emptyState.remove();

        const catIcons = {
            groceries: '🛒',
            produce: '🥦',
            dairy: '🥛',
            cleaning: '🧼',
            pharmacy: '💊',
            meat: '🥩',
            other: '📦'
        };
        const icon = catIcons[item.category] || '🛒';

        const card = document.createElement('div');
        card.className = `grocery-item-card ${item.is_bought ? 'is-bought' : ''}`;
        card.id = `groceryItemRow_${item.id}`;
        card.setAttribute('data-id', item.id);
        card.setAttribute('data-category', item.category || 'groceries');
        card.setAttribute('data-bought', item.is_bought ? '1' : '0');
        card.setAttribute('data-price', item.estimated_price || 0);

        card.innerHTML = `
            <div class="grocery-item-main" onclick="toggleGroceryItem(${item.id})">
                <div class="grocery-checkbox-wrap">
                    <div class="grocery-checkbox-custom">✓</div>
                </div>
                <span class="grocery-cat-badge">${icon}</span>
                <div class="grocery-text-col">
                    <span class="grocery-item-name">${item.name}</span>
                    <div class="grocery-item-meta" style="direction: rtl; text-align: right;">
                        <span>👤 أضافه: <bdi class="actor-tag" style="font-weight: 700; color: #38bdf8;">${item.user ? item.user.name : 'أنا'}</bdi></span>
                        <span>• ${item.created_at_human || 'الآن'}</span>
                    </div>
                </div>
            </div>
            <div class="grocery-item-end">
                <span class="grocery-item-price" id="groceryItemPrice_${item.id}" style="${(item.estimated_price && item.estimated_price > 0) ? '' : 'display: none;'}">
                    ${(item.estimated_price && item.estimated_price > 0) ? `~${parseFloat(item.estimated_price).toFixed(1)} ₪` : ''}
                </span>
                <button type="button" class="grocery-del-btn" title="حذف الغرض" onclick="deleteGroceryItem(${item.id}, event)">
                    🗑️
                </button>
            </div>
        `;

        if (list.firstChild) {
            list.insertBefore(card, list.firstChild);
        } else {
            list.appendChild(card);
        }
    }

    // Single Item Purchase Modal
    window.openBuyGroceryItemModal = function(id) {
        const row = document.getElementById(`groceryItemRow_${id}`);
        if (!row) return;

        const nameEl = row.querySelector('.grocery-item-name');
        const itemName = nameEl ? nameEl.textContent.trim() : 'غرض';
        const category = row.getAttribute('data-category') || 'groceries';
        const catBadge = row.querySelector('.grocery-cat-badge');
        const itemIcon = catBadge ? catBadge.textContent.trim() : '🛒';

        const modal = document.getElementById('buySingleGroceryItemModal');
        if (!modal) return;

        document.getElementById('modalBuyItemId').value = id;
        document.getElementById('modalBuyItemNameTitle').textContent = itemName;
        document.getElementById('modalBuyItemName').textContent = itemName;
        document.getElementById('modalBuyItemIcon').textContent = itemIcon;

        const catLabels = {
            groceries: 'بقالة وتموين',
            produce: 'خضار وفواكه',
            dairy: 'ألبان وأجبان',
            cleaning: 'منظفات وعناية',
            pharmacy: 'صيدلية وصحة',
            meat: 'لحوم ودواجن',
            other: 'أخرى'
        };
        const catEl = document.getElementById('modalBuyItemCategory');
        if (catEl) catEl.textContent = catLabels[category] || category;

        const amountInput = document.getElementById('modalBuyAmount');
        if (amountInput) {
            amountInput.value = '';
        }

        // Set default payment method: cash
        setBuyPaymentMethod('cash');

        openModal('buySingleGroceryItemModal');

        setTimeout(() => {
            if (amountInput) {
                amountInput.focus();
                amountInput.select();
            }
        }, 120);
    };

    // Payment method pill selection in Buy Modal
    window.setBuyPaymentMethod = function(method) {
        const input = document.getElementById('modalBuyPaymentMethod');
        if (input) input.value = method;

        const pills = document.querySelectorAll('#modalBuyPaymentPills .pay-pill');
        pills.forEach(pill => {
            if (pill.getAttribute('data-value') === method) {
                pill.classList.add('active');
            } else {
                pill.classList.remove('active');
            }
        });

        const hintEl = document.getElementById('modalBuyPaymentHint');
        if (hintEl) {
            hintEl.className = `payment-hint-bar hint-${method}`;
            if (method === 'transfer') {
                hintEl.innerHTML = '📱 سيُخصم من <strong>رصيد التطبيق الفعّال</strong> (السيولة الحية)';
            } else if (method === 'cash') {
                hintEl.innerHTML = '💵 سيُخصم من <strong>الكاش الفعلي</strong> (المجمد)';
            } else if (method === 'card') {
                hintEl.innerHTML = '💳 سيُسجل كدفع عبر <strong>البطاقة المصرفية</strong>';
            } else if (method === 'credit') {
                hintEl.innerHTML = '📑 سيُسجل كـ <strong>دين مؤجل (شكك/سلف)</strong>';
            }
        }
    };

    // Handle Buy Single Item Form Submit
    window.handleSingleBuySubmit = async function(e) {
        e.preventDefault();
        const id = document.getElementById('modalBuyItemId').value;
        const row = document.getElementById(`groceryItemRow_${id}`);
        const submitBtn = document.getElementById('modalBuySubmitBtn');
        const amountInput = document.getElementById('modalBuyAmount');
        const paymentMethod = document.getElementById('modalBuyPaymentMethod').value || 'cash';
        const envelopeSelect = document.getElementById('modalBuyEnvelope');
        const envelopeId = envelopeSelect ? envelopeSelect.value : null;

        const amount = parseFloat(amountInput ? amountInput.value : 0);
        if (!amount || amount <= 0) {
            alert('يرجى إدخال سعر الغرض الفعلي (أكبر من 0).');
            if (amountInput) amountInput.focus();
            return;
        }

        const itemName = document.getElementById('modalBuyItemName').textContent.trim();

        if (submitBtn) submitBtn.disabled = true;

        const payload = {
            amount: amount,
            payment_method: paymentMethod,
            envelope_id: envelopeId,
            date: new Date().toISOString().split('T')[0],
            note: `شراء مقاضي: ${itemName}`
        };

        // If Offline
        if (!navigator.onLine || String(id).startsWith('temp_')) {
            if (window.offlineEngine && typeof window.offlineEngine.saveOfflineTransaction === 'function') {
                await window.offlineEngine.saveOfflineTransaction({
                    type: 'expense',
                    amount: payload.amount,
                    envelope_id: payload.envelope_id,
                    payment_method: payload.payment_method,
                    date: payload.date,
                    note: payload.note
                });

                if (window.offlineEngine.toggleOfflineGroceryItem) {
                    window.offlineEngine.toggleOfflineGroceryItem(id, true);
                }
            }

            if (row) {
                row.setAttribute('data-bought', '1');
                row.setAttribute('data-price', amount);
                row.classList.add('is-bought');
                const priceEl = document.getElementById(`groceryItemPrice_${id}`) || row.querySelector('.grocery-item-price');
                if (priceEl) {
                    priceEl.textContent = `~${amount.toFixed(1)} ₪`;
                    priceEl.style.display = 'inline-block';
                }
            }

            closeModal('buySingleGroceryItemModal');
            updateGroceryDOMStats();

            if (window.showPartnerToast) {
                showPartnerToast('📴 سُجل بدون إنترنت', `سُجل شراء "${itemName}" بقيمة ${amount} ₪ وسيزامن لاحقاً`, '🛍️');
            }

            if (submitBtn) submitBtn.disabled = false;
            return;
        }

        // If Online
        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const res = await fetch(`/grocery-items/${id}/buy`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const data = await res.json();
            if (data.success) {
                if (typeof confetti === 'function') {
                    confetti({
                        particleCount: 80,
                        spread: 70,
                        origin: { y: 0.6 }
                    });
                }

                if (row) {
                    row.setAttribute('data-bought', '1');
                    row.setAttribute('data-price', amount);
                    row.classList.add('is-bought');

                    const priceEl = document.getElementById(`groceryItemPrice_${id}`) || row.querySelector('.grocery-item-price');
                    if (priceEl) {
                        priceEl.textContent = `~${amount.toFixed(1)} ₪`;
                        priceEl.style.display = 'inline-block';
                    }

                    const metaEl = row.querySelector('.grocery-item-meta');
                    if (metaEl && !metaEl.querySelector('.buyer-tag')) {
                        const buyerSpan = document.createElement('span');
                        buyerSpan.innerHTML = ` • ✔️ شطبه: <bdi class="buyer-tag" style="font-weight: 700; color: #34d399;">أنا</bdi>`;
                        metaEl.appendChild(buyerSpan);
                    }
                }

                closeModal('buySingleGroceryItemModal');
                updateGroceryDOMStats();

                if (window.showPartnerToast) {
                    showPartnerToast('💳 تم الشراء والترحيل', data.message || `تم ترحيل مصروف بقيمة ${amount} ₪ في ظرف البقالة بنجاح`, '🎉');
                }
            } else {
                alert(data.message || 'تعذر تسجيل الشراء');
            }
        } catch(err) {
            console.error('Error buying grocery item:', err);
            alert('حدث خطأ أثناء الاتصال بالخادم.');
        } finally {
            if (submitBtn) submitBtn.disabled = false;
        }
    };

    // Toggle bought status
    window.toggleGroceryItem = async function(id) {
        const row = document.getElementById(`groceryItemRow_${id}`);
        if (!row) return;

        const isCurrentlyBought = row.getAttribute('data-bought') === '1';

        if (!isCurrentlyBought) {
            // Item is NOT bought yet -> Open Buy Modal to input price & payment method
            openBuyGroceryItemModal(id);
            return;
        }

        // Item is ALREADY bought -> Ask for confirmation before undoing and refunding
        const nameEl = row.querySelector('.grocery-item-name');
        const itemName = nameEl ? nameEl.textContent.trim() : 'هذا الغرض';

        let confirmed = false;
        if (typeof window.showCustomConfirm === 'function') {
            confirmed = await window.showCustomConfirm({
                title: 'إلغاء شراء الغرض واسترداد المصروف',
                message: `الغرض "${itemName}" تم شراؤه وترحيله كمصروف. هل تريد التراجع عن شرائه، وحذف المصروف واسترداد المبلغ إلى الظرف؟`,
                confirmText: 'نعم، إلغاء الشراء واسترداد المبلغ',
                cancelText: 'إبقاء كمشترى',
                icon: '↩️',
                isDanger: true
            });
        } else {
            confirmed = confirm(`الغرض "${itemName}" تم شراؤه وترحيله كمصروف. هل تريد التراجع عن شرائه وحذف المصروف؟`);
        }

        if (!confirmed) return;

        // Perform undo
        row.setAttribute('data-bought', '0');
        row.classList.remove('is-bought');
        row.setAttribute('data-price', '0');
        const priceEl = document.getElementById(`groceryItemPrice_${id}`) || row.querySelector('.grocery-item-price');
        if (priceEl) priceEl.style.display = 'none';

        const buyerTag = row.querySelector('.buyer-tag');
        if (buyerTag && buyerTag.parentElement) {
            buyerTag.parentElement.remove();
        }

        updateGroceryDOMStats();

        // If offline
        if (!navigator.onLine || String(id).startsWith('temp_')) {
            if (window.offlineEngine && typeof window.offlineEngine.toggleOfflineGroceryItem === 'function') {
                window.offlineEngine.toggleOfflineGroceryItem(id, false);
            }
            return;
        }

        // If online
        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const res = await fetch(`/grocery-items/${id}/toggle`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                }
            });
            const data = await res.json();
            if (data.success) {
                if (window.showPartnerToast) {
                    showPartnerToast('↩️ تراجع عن الشراء', data.message || 'تم التراجع واسترداد المصروف بنجاح', '🔄');
                }
            } else {
                // Revert on error
                row.setAttribute('data-bought', '1');
                row.classList.add('is-bought');
                updateGroceryDOMStats();
                alert(data.message || 'تعذر التراجع عن الشراء');
            }
        } catch(err) {
            console.error('Error toggling grocery item:', err);
        }
    };

    // Delete item
    window.deleteGroceryItem = async function(id, event) {
        if (event) event.stopPropagation();
        const row = document.getElementById(`groceryItemRow_${id}`);
        if (!row) return;

        let confirmed = false;
        if (typeof window.showCustomConfirm === 'function') {
            confirmed = await window.showCustomConfirm({
                title: 'حذف من قائمة المقاضي',
                message: 'هل أنت متأكد من رغبتك في حذف هذا الغرض من قائمة المقاضي المشتركة؟',
                confirmText: 'نعم، حذف الغرض',
                cancelText: 'تراجع',
                icon: '🗑️',
                isDanger: true
            });
        } else {
            confirmed = confirm('هل أنت متأكد من حذف هذا الغرض من قائمة المقاضي؟');
        }

        if (!confirmed) return;

        row.remove();
        updateGroceryDOMStats();

        // If offline
        if (!navigator.onLine || String(id).startsWith('temp_')) {
            if (window.offlineEngine && typeof window.offlineEngine.deleteOfflineGroceryItem === 'function') {
                window.offlineEngine.deleteOfflineGroceryItem(id);
            }
            return;
        }

        // If online
        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            await fetch(`/grocery-items/${id}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                }
            });
        } catch(err) {
            if (window.offlineEngine && typeof window.offlineEngine.deleteOfflineGroceryItem === 'function') {
                window.offlineEngine.deleteOfflineGroceryItem(id);
            }
        }
    };

    // Open Convert to Expense Modal
    window.openConvertGroceryModal = function() {
        const rows = Array.from(document.querySelectorAll('.grocery-item-card'));
        const boughtRows = rows.filter(r => r.getAttribute('data-bought') === '1');

        if (boughtRows.length === 0) {
            alert('يرجى شطب (تحديد) الأغراض التي تم شراؤها أولاً للتحويل إلى مصروف.');
            return;
        }

        let estimatedSum = 0;
        let names = [];
        const pillsContainer = document.getElementById('modalConvertItemsPills');
        if (pillsContainer) pillsContainer.innerHTML = '';

        boughtRows.forEach(r => {
            const nameEl = r.querySelector('.grocery-item-name');
            const name = nameEl ? nameEl.textContent.trim() : 'غرض';
            const price = parseFloat(r.getAttribute('data-price') || 0);
            estimatedSum += price;
            names.push(name);

            if (pillsContainer) {
                const pill = document.createElement('span');
                pill.style.cssText = 'background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); padding: 0.2rem 0.55rem; border-radius: 8px; font-size: 0.76rem; color: #f1f5f9; display: inline-flex; align-items: center; gap: 0.3rem;';
                pill.innerHTML = `<span>✓</span> <span>${name}</span> ${price > 0 ? `<strong style="color: #34d399;">(~${price}₪)</strong>` : ''}`;
                pillsContainer.appendChild(pill);
            }
        });

        const countEl = document.getElementById('modalConvertItemsCount');
        const sumEl = document.getElementById('modalConvertEstimatedSum');
        const amountInput = document.getElementById('modalConvertAmount');
        const noteInput = document.getElementById('modalConvertNote');

        if (countEl) countEl.textContent = boughtRows.length;
        if (sumEl) sumEl.textContent = estimatedSum.toFixed(1);
        if (amountInput) amountInput.value = estimatedSum > 0 ? estimatedSum.toFixed(1) : '';

        const shortNames = names.slice(0, 4).join('، ') + (names.length > 4 ? ' وأخرى' : '');
        if (noteInput) noteInput.value = `مقاضي البيت (${boughtRows.length} أصناف: ${shortNames})`;

        openModal('convertGroceryModal');
    };

    // Handle Convert to Expense Submit
    window.handleConvertGrocerySubmit = async function(e) {
        e.preventDefault();
        const submitBtn = document.getElementById('modalConvertSubmitBtn');
        const amountInput = document.getElementById('modalConvertAmount');
        const envelopeSelect = document.getElementById('modalConvertEnvelope');
        const paymentMethodSelect = document.getElementById('modalConvertPaymentMethod');
        const dateInput = document.getElementById('modalConvertDate');
        const noteInput = document.getElementById('modalConvertNote');

        const amount = amountInput ? parseFloat(amountInput.value) : 0;
        if (!amount || amount <= 0) {
            alert('يرجى إدخال مبلغ الفاتورة الإجمالي.');
            return;
        }

        const boughtRows = Array.from(document.querySelectorAll('.grocery-item-card'))
            .filter(r => r.getAttribute('data-bought') === '1');
        
        const itemIds = boughtRows.map(r => parseInt(r.getAttribute('data-id'))).filter(id => !isNaN(id) && id > 0);

        const payload = {
            amount: amount,
            envelope_id: envelopeSelect ? envelopeSelect.value : null,
            payment_method: paymentMethodSelect ? paymentMethodSelect.value : 'cash',
            date: dateInput ? dateInput.value : new Date().toISOString().split('T')[0],
            note: noteInput ? noteInput.value : 'مقاضي البيت',
            item_ids: itemIds
        };

        if (submitBtn) submitBtn.disabled = true;

        // If Offline
        if (!navigator.onLine) {
            if (window.offlineEngine && typeof window.offlineEngine.saveOfflineTransaction === 'function') {
                await window.offlineEngine.saveOfflineTransaction({
                    type: 'expense',
                    amount: payload.amount,
                    envelope_id: payload.envelope_id,
                    payment_method: payload.payment_method,
                    date: payload.date,
                    note: payload.note
                });

                // Archive converted items locally
                if (window.offlineEngine.archiveConvertedGroceryItems) {
                    window.offlineEngine.archiveConvertedGroceryItems(itemIds);
                }

                boughtRows.forEach(r => r.remove());
                closeModal('convertGroceryModal');
                updateGroceryDOMStats();

                if (window.showPartnerToast) {
                    showPartnerToast('📴 تم التسجيل بدون إنترنت', `سُجل مصروف بقيمة ${amount} ₪ وسيزامن فور الاتصال`, '🛍️');
                }
            }
            if (submitBtn) submitBtn.disabled = false;
            return;
        }

        // If Online
        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const res = await fetch("{{ route('grocery-items.convert-expense') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const data = await res.json();
            if (data.success) {
                // Confetti celebration 🎉
                if (typeof confetti === 'function') {
                    confetti({
                        particleCount: 100,
                        spread: 80,
                        origin: { y: 0.5 }
                    });
                }

                boughtRows.forEach(r => r.remove());
                closeModal('convertGroceryModal');
                updateGroceryDOMStats();

                if (window.showPartnerToast) {
                    showPartnerToast('💳 تم تحويل المقاضي لمصروف', data.message || 'تم تسجيل المصروف في ظرف البقالة بنجاح', '🎉');
                }
            } else {
                alert(data.message || 'تعذر تحويل القائمة لمصروف');
            }
        } catch(err) {
            console.error('Error converting grocery to expense:', err);
            alert('حدث خطأ أثناء الاتصال بالخادم.');
        } finally {
            if (submitBtn) submitBtn.disabled = false;
        }
    };

    // Background refresh
    window.refreshGroceryListFromServer = async function() {
        if (!navigator.onLine) return;
        try {
            const res = await fetch("{{ route('grocery-items.index') }}", {
                headers: { 'Accept': 'application/json' }
            });
            if (!res.ok) return;
            const data = await res.json();
            if (!data.success || !data.items) return;

            const list = document.getElementById('groceryItemsList');
            if (!list) return;

            if (data.items.length === 0) {
                list.innerHTML = `
                    <div class="grocery-empty-state" id="groceryEmptyState">
                        <span class="grocery-empty-icon">🛒✨</span>
                        <div class="grocery-empty-title">قائمة المقاضي مكتملة ولا ينقص البيت شيء!</div>
                        <div class="grocery-empty-desc">
                            اكتب أي غرض ناقص (حليب، خضار، منظفات...) وسيظهر فوراً على هاتف شريكتك!
                        </div>
                    </div>
                `;
            } else {
                const catIcons = {
                    groceries: '🛒',
                    produce: '🥦',
                    dairy: '🥛',
                    cleaning: '🧼',
                    pharmacy: '💊',
                    meat: '🥩',
                    other: '📦'
                };

                list.innerHTML = data.items.map(item => `
                    <div class="grocery-item-card ${item.is_bought ? 'is-bought' : ''}" 
                         id="groceryItemRow_${item.id}" 
                         data-id="${item.id}" 
                         data-category="${item.category || 'groceries'}" 
                         data-bought="${item.is_bought ? '1' : '0'}"
                         data-price="${item.estimated_price || 0}">
                        
                        <div class="grocery-item-main" onclick="toggleGroceryItem(${item.id})">
                            <div class="grocery-checkbox-wrap">
                                <div class="grocery-checkbox-custom">✓</div>
                            </div>
                            <span class="grocery-cat-badge">${catIcons[item.category] || '🛒'}</span>
                            <div class="grocery-text-col">
                                <span class="grocery-item-name">${item.name}</span>
                                <div class="grocery-item-meta" style="direction: rtl; text-align: right;">
                                    <span>👤 أضافه: <bdi class="actor-tag" style="font-weight: 700; color: #38bdf8;">${item.user ? item.user.name : 'عضو'}</bdi></span>
                                    ${(item.is_bought && item.buyer) ? `<span>• ✔️ شطبه: <bdi class="buyer-tag" style="font-weight: 700; color: #34d399;">${item.buyer.name}</bdi></span>` : ''}
                                </div>
                            </div>
                        </div>

                        <div class="grocery-item-end">
                            ${item.estimated_price ? `<span class="grocery-item-price">~${parseFloat(item.estimated_price).toFixed(1)} ₪</span>` : ''}
                            <button type="button" class="grocery-del-btn" title="حذف الغرض" onclick="deleteGroceryItem(${item.id}, event)">
                                🗑️
                            </button>
                        </div>
                    </div>
                `).join('');
            }

            updateGroceryDOMStats();
        } catch(e) {}
    };
</script>
@endpush
