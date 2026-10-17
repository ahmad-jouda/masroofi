<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>كشف المعاملات المالية - {{ $workspace->name ?? 'مصروفي برو' }} - {{ $periodLabel }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Tajawal:wght@400;500;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #0284c7;
            --primary-dark: #0369a1;
            --expense: #dc2626;
            --income: #16a34a;
            --savings: #d97706;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --border-light: #e2e8f0;
            --border-dark: #cbd5e1;
            --bg-light: #f8fafc;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Cairo', 'Tajawal', sans-serif;
            background-color: #f1f5f9;
            color: var(--text-dark);
            line-height: 1.5;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        /* SCREEN-ONLY TOOLBAR */
        .screen-toolbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: #0f172a;
            color: #fff;
            padding: 0.85rem 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.25);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .screen-toolbar-title {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            font-size: 1.1rem;
            font-weight: 800;
        }

        .screen-toolbar-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.55rem 1.15rem;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: all 0.2s ease;
        }

        .btn-print {
            background: linear-gradient(135deg, #10b981, #059669);
            color: #fff;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.4);
        }
        .btn-print:hover {
            background: #059669;
            transform: translateY(-1px);
        }

        .btn-back {
            background: rgba(255, 255, 255, 0.12);
            color: #f8fafc;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .btn-back:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* FILTER BAR */
        .filter-panel {
            background: #fff;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-light);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .filter-group label {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
        }

        .filter-select {
            padding: 0.45rem 0.75rem;
            border-radius: 6px;
            border: 1px solid var(--border-dark);
            font-family: inherit;
            font-size: 0.85rem;
            background: #fff;
            min-width: 140px;
        }

        .btn-filter-apply {
            padding: 0.45rem 1rem;
            border-radius: 6px;
            background: var(--primary);
            color: #fff;
            font-weight: 700;
            border: none;
            cursor: pointer;
            font-size: 0.85rem;
            margin-top: 1.15rem;
        }

        /* A4 PRINT CONTAINER */
        .report-page-container {
            max-width: 210mm;
            margin: 1.5rem auto 3rem auto;
            background: #fff;
            padding: 15mm 15mm 20mm 15mm;
            border-radius: 4px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            min-height: 297mm;
            position: relative;
        }

        /* REPORT HEADER */
        .report-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 1.25rem;
            margin-bottom: 1.5rem;
            gap: 1rem;
        }

        .brand-block h1 {
            font-size: 1.6rem;
            font-weight: 900;
            color: #0f172a;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .brand-block p {
            color: var(--primary-dark);
            font-weight: 700;
            font-size: 0.95rem;
            margin-top: 0.2rem;
        }

        .brand-block .meta-period {
            font-size: 0.82rem;
            color: var(--text-muted);
            margin-top: 0.35rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .meta-info-block {
            text-align: left;
            direction: ltr;
            font-size: 0.82rem;
            color: var(--text-muted);
            line-height: 1.6;
        }

        .meta-info-block strong {
            color: #0f172a;
        }

        /* KPI SUMMARY CARDS */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.85rem;
            margin-bottom: 1.5rem;
        }

        .kpi-card {
            border: 1px solid var(--border-light);
            border-radius: 8px;
            padding: 0.75rem 0.9rem;
            background: var(--bg-light);
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }

        .kpi-card.kpi-expense {
            border-right: 4px solid var(--expense);
        }
        .kpi-card.kpi-income {
            border-right: 4px solid var(--income);
        }
        .kpi-card.kpi-savings {
            border-right: 4px solid var(--savings);
        }
        .kpi-card.kpi-net {
            border-right: 4px solid var(--primary);
        }

        .kpi-label {
            font-size: 0.76rem;
            font-weight: 700;
            color: var(--text-muted);
        }

        .kpi-value {
            font-family: 'JetBrains Mono', 'Cairo', monospace;
            font-size: 1.15rem;
            font-weight: 800;
            color: #0f172a;
        }

        .kpi-value.expense { color: var(--expense); }
        .kpi-value.income { color: var(--income); }
        .kpi-value.savings { color: var(--savings); }
        .kpi-value.net { color: var(--primary-dark); }

        /* DATA TABLE */
        .statement-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
            margin-bottom: 2rem;
        }

        .statement-table th {
            background-color: #0f172a;
            color: #fff;
            padding: 0.55rem 0.65rem;
            text-align: right;
            font-weight: 700;
            font-size: 0.78rem;
            border: 1px solid #0f172a;
        }

        .statement-table td {
            padding: 0.5rem 0.65rem;
            border: 1px solid var(--border-light);
            color: #1e293b;
        }

        .statement-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .statement-table tbody tr:hover {
            background-color: #f1f5f9;
        }

        .badge-type {
            display: inline-block;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 0.1rem 0.4rem;
            border-radius: 4px;
        }

        .badge-type.expense {
            background: #fee2e2;
            color: #b91c1c;
        }
        .badge-type.income {
            background: #dcfce7;
            color: #15803d;
        }
        .badge-type.savings {
            background: #fef3c7;
            color: #b45309;
        }

        .num-font {
            font-family: 'JetBrains Mono', 'Cairo', monospace;
            font-weight: 700;
            white-space: nowrap;
        }

        .text-expense { color: var(--expense); }
        .text-income { color: var(--income); }
        .text-savings { color: var(--savings); }

        .statement-table tfoot td {
            background-color: #f1f5f9;
            font-weight: 800;
            border-top: 2px solid #0f172a;
            padding: 0.65rem;
        }

        /* DOCUMENT FOOTER */
        .report-footer {
            margin-top: 2rem;
            border-top: 1px solid var(--border-dark);
            padding-top: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.75rem;
            color: var(--text-muted);
            flex-wrap: wrap;
            gap: 1rem;
        }

        .footer-stamp {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-weight: 700;
            color: #0f172a;
        }

        /* PRINT STYLES */
        @media print {
            body {
                background: #fff !important;
                color: #000 !important;
                font-size: 10pt;
            }

            .screen-toolbar,
            .filter-panel,
            .no-print {
                display: none !important;
            }

            .report-page-container {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                min-height: auto !important;
            }

            @page {
                size: A4 portrait;
                margin: 12mm 10mm;
            }

            .statement-table {
                font-size: 8.5pt;
            }

            .statement-table th {
                background-color: #1e293b !important;
                color: #fff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .statement-table tbody tr:nth-child(even) {
                background-color: #f8fafc !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .statement-table tr {
                page-break-inside: avoid;
            }

            thead {
                display: table-header-group;
            }

            tfoot {
                display: table-footer-group;
            }

            .kpi-card {
                background: #f8fafc !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body>

    <!-- SCREEN ONLY TOOLBAR -->
    <div class="screen-toolbar no-print">
        <div class="screen-toolbar-title">
            <span>📄</span>
            <span>كشف حساب المعاملات المالية (PDF)</span>
        </div>
        <div class="screen-toolbar-actions">
            <a href="{{ route('dashboard', ['tab' => 'tab-ledger']) }}" class="btn-action btn-back">
                <span>⬅️</span>
                <span>العودة للوحة التحكم</span>
            </a>
            <button type="button" class="btn-action btn-print" onclick="window.print()">
                <span>🖨️</span>
                <span>طباعة / حفظ بتنسيق PDF</span>
            </button>
        </div>
    </div>

    <!-- FILTER BAR (SCREEN ONLY) -->
    <div class="filter-panel no-print">
        <form method="GET" action="{{ route('transactions.exportPdf') }}" style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; width: 100%;">
            <div class="filter-group">
                <label>الفترة الزمنية:</label>
                <select name="period" class="filter-select" onchange="toggleCustomDates(this.value)">
                    <option value="current_cycle" {{ $period === 'current_cycle' ? 'selected' : '' }}>دورة الراتب الحالية</option>
                    <option value="current_month" {{ $period === 'current_month' ? 'selected' : '' }}>الشهر الحالي</option>
                    <option value="last_month" {{ $period === 'last_month' ? 'selected' : '' }}>الشهر السابق</option>
                    <option value="all" {{ $period === 'all' ? 'selected' : '' }}>كل السجل (جميع الحركات)</option>
                    <option value="custom" {{ $period === 'custom' ? 'selected' : '' }}>تحديد فترة مخصصة...</option>
                </select>
            </div>

            <div class="filter-group" id="customFromGroup" style="{{ $period === 'custom' ? '' : 'display: none;' }}">
                <label>من تاريخ:</label>
                <input type="date" name="from" value="{{ $fromDate ?? '' }}" class="filter-select">
            </div>

            <div class="filter-group" id="customToGroup" style="{{ $period === 'custom' ? '' : 'display: none;' }}">
                <label>إلى تاريخ:</label>
                <input type="date" name="to" value="{{ $toDate ?? '' }}" class="filter-select">
            </div>

            <div class="filter-group">
                <label>نوع الحركة:</label>
                <select name="type" class="filter-select">
                    <option value="all" {{ $type === 'all' ? 'selected' : '' }}>الكل</option>
                    <option value="expense" {{ $type === 'expense' ? 'selected' : '' }}>🔴 مصاريف فقط</option>
                    <option value="income" {{ $type === 'income' ? 'selected' : '' }}>🟢 دخول إضافية فقط</option>
                    <option value="savings" {{ $type === 'savings' ? 'selected' : '' }}>🔵 تحويلات تحويش فقط</option>
                </select>
            </div>

            <div class="filter-group">
                <label>الظرف / الفئة:</label>
                <select name="envelope_id" class="filter-select">
                    <option value="">جميع الفئات</option>
                    @foreach($envelopes as $env)
                        <option value="{{ $env->id }}" {{ ($envelopeId == $env->id) ? 'selected' : '' }}>{{ $env->icon }} {{ $env->name }}</option>
                    @endforeach
                </select>
            </div>

            @if(count($members) > 1)
            <div class="filter-group">
                <label>المستخدم / الشريك:</label>
                <select name="user_id" class="filter-select">
                    <option value="">جميع أفراد المحفظة</option>
                    @foreach($members as $m)
                        <option value="{{ $m->id }}" {{ ($userId == $m->id) ? 'selected' : '' }}>👤 {{ $m->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            <button type="submit" class="btn-filter-apply">🔍 تصفية وعرض</button>
        </form>
    </div>

    <!-- MAIN REPORT PAGE CONTAINER (PRINT READY) -->
    <div class="report-page-container">
        
        <!-- HEADER -->
        <header class="report-header">
            <div class="brand-block">
                <h1>
                    <span>💎</span>
                    <span>مصروفي برو Masroofi</span>
                </h1>
                <p>كشف العمليات والمعاملات المالية - Financial Ledger Statement</p>
                <div class="meta-period">
                    <span>📅 نطاق التقرير:</span>
                    <strong>{{ $periodLabel }}</strong>
                </div>
            </div>

            <div class="meta-info-block">
                <div><strong>المحفظة:</strong> {{ $workspace->name ?? 'المحفظة العائلية' }}</div>
                <div><strong>المُستخرج بواسطة:</strong> {{ auth()->user()->name ?? 'مدير المحفظة' }}</div>
                <div><strong>تاريخ الطباعة:</strong> {{ now()->format('Y-m-d H:i') }}</div>
                <div><strong>العملة المعتمدة:</strong> {{ $settings->currency ?? 'ILS' }}</div>
            </div>
        </header>

        <!-- KPI SUMMARY -->
        <div class="kpi-grid">
            <div class="kpi-card kpi-expense">
                <span class="kpi-label">إجمالي المصروفات</span>
                <span class="kpi-value expense num-font">- {{ number_format($totalExpenses, 2) }} {{ $settings->currency ?? 'ILS' }}</span>
            </div>
            <div class="kpi-card kpi-income">
                <span class="kpi-label">إجمالي الدخول</span>
                <span class="kpi-value income num-font">+ {{ number_format($totalIncome, 2) }} {{ $settings->currency ?? 'ILS' }}</span>
            </div>
            <div class="kpi-card kpi-savings">
                <span class="kpi-label">إجمالي التحويش</span>
                <span class="kpi-value savings num-font">🪙 {{ number_format($totalSavings, 2) }} {{ $settings->currency ?? 'ILS' }}</span>
            </div>
            <div class="kpi-card kpi-net">
                <span class="kpi-label">إجمالي العمليات المنفذة</span>
                <span class="kpi-value net num-font">{{ $transactions->count() }} معاملة</span>
            </div>
        </div>

        <!-- TRANSACTIONS TABLE -->
        <table class="statement-table">
            <thead>
                <tr>
                    <th style="width: 35px; text-align: center;">#</th>
                    <th style="width: 85px;">التاريخ</th>
                    <th style="width: 90px;">نوع الحركة</th>
                    <th style="width: 120px;">الفئة / الظرف</th>
                    <th>البيان والملاحظات</th>
                    <th style="width: 75px;">وسيلة الدفع</th>
                    <th style="width: 100px; text-align: left;">المبلغ ({{ $settings->currency ?? 'ILS' }})</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $index => $tx)
                    <tr>
                        <td style="text-align: center; color: var(--text-muted); font-size: 0.75rem;">{{ $index + 1 }}</td>
                        <td class="num-font">{{ $tx->date->format('Y-m-d') }}</td>
                        <td>
                            @if($tx->type === 'expense')
                                <span class="badge-type expense">🔴 مصروف</span>
                            @elseif($tx->type === 'income')
                                <span class="badge-type income">🟢 دخل</span>
                            @else
                                <span class="badge-type savings">🔵 تحويش</span>
                            @endif
                        </td>
                        <td>
                            <strong>
                                @if($tx->type === 'savings' && $tx->goal)
                                    🎯 {{ $tx->goal->name }}
                                @elseif($tx->envelope)
                                    {{ $tx->envelope->icon }} {{ $tx->envelope->name }}
                                @else
                                    عام
                                @endif
                            </strong>
                        </td>
                        <td>
                            <span>{{ $tx->note ?? '—' }}</span>
                            @if($tx->user)
                                <span style="display: block; color: var(--text-muted); font-size: 0.72rem; margin-top: 1px;">👤 {{ $tx->user->name }}</span>
                            @endif
                        </td>
                        <td style="font-size: 0.78rem;">
                            @if($tx->payment_method === 'cash') 💵 كاش
                            @elseif($tx->payment_method === 'transfer') 📱 تطبيق
                            @elseif($tx->payment_method === 'card') 💳 بطاقة
                            @else 📑 دين/آجل
                            @endif
                        </td>
                        <td class="num-font {{ $tx->type === 'expense' ? 'text-expense' : ($tx->type === 'income' ? 'text-income' : 'text-savings') }}" style="text-align: left; font-size: 0.88rem;">
                            {{ $tx->type === 'income' ? '+' : ($tx->type === 'savings' ? '🪙' : '-') }} {{ number_format($tx->amount, 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                            لا توجد أي معاملات مسجلة ضمن هذه الفترة المحددة.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6" style="text-align: right;">
                        <strong>صافي مجموع المصروفات لهذه الفترة:</strong>
                    </td>
                    <td class="num-font text-expense" style="text-align: left; font-size: 0.95rem;">
                        {{ number_format($totalExpenses, 2) }} {{ $settings->currency ?? 'ILS' }}
                    </td>
                </tr>
            </tfoot>
        </table>

        <!-- FOOTER -->
        <footer class="report-footer">
            <div class="footer-stamp">
                <span>🛡️</span>
                <span>تم استخراج هذا التقرير آلياً عبر نظام إدارة الميزانية الشخصية والعائلية «مصروفي برو»</span>
            </div>
            <div>
                <span>صفحة 1 من 1 • كشف معتمد</span>
            </div>
        </footer>

    </div>

    <script>
        function toggleCustomDates(val) {
            const fromGroup = document.getElementById('customFromGroup');
            const toGroup = document.getElementById('customToGroup');
            if (val === 'custom') {
                fromGroup.style.display = 'flex';
                toGroup.style.display = 'flex';
            } else {
                fromGroup.style.display = 'none';
                toGroup.style.display = 'none';
            }
        }
    </script>
</body>
</html>
