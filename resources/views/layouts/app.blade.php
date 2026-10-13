<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-currency" content="{{ $settings->currency ?? 'ILS' }}">
    <title>{{ config('app.name', 'مصروفي Pro') }} - إدارة المصاريف والتحويش الذكي</title>
    
    <!-- PWA & Mobile Web App Meta Tags -->
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#10b981">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="مصروفي Pro">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('icons/icon.svg') }}">
    <link rel="shortcut icon" href="{{ asset('icons/icon-192.png') }}">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- App CSS -->
    <link rel="stylesheet" href="{{ asset('css/style.css') }}?v={{ time() }}">
    
    <!-- Chart.js & Confetti CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.4/dist/confetti.browser.min.js"></script>

    <!-- Global Modal Helpers -->
    <script>
        window.openModal = function(id) {
            const m = document.getElementById(id);
            if (m) {
                m.classList.remove('hidden');
                m.style.display = 'flex';
            }
        };
        window.closeModal = function(id) {
            const m = document.getElementById(id);
            if (m) {
                m.classList.add('hidden');
                m.style.display = 'none';
            }
        };

        // User Profile Dropdown Handler
        window.toggleUserDropdown = function(e) {
            if (e) e.stopPropagation();
            const wrap = document.getElementById('userProfileMenuWrap');
            if (wrap) wrap.classList.toggle('open');
        };
        window.closeUserDropdown = function() {
            const wrap = document.getElementById('userProfileMenuWrap');
            if (wrap) wrap.classList.remove('open');
        };
        document.addEventListener('click', function(e) {
            const wrap = document.getElementById('userProfileMenuWrap');
            if (wrap && wrap.classList.contains('open')) {
                if (!wrap.contains(e.target)) {
                    wrap.classList.remove('open');
                }
            }
        });
    </script>

    <!-- Instant Theme Persistence -->
    <script>
        (function() {
            try {
                const savedTheme = localStorage.getItem('masroofi_theme');
                if (savedTheme) {
                    document.documentElement.classList.add('theme-' + savedTheme);
                }
            } catch(e) {}
        })();
    </script>

    <!-- Instant Tab Persistence & Zero-Flicker Preload -->
    <script>
        (function() {
            try {
                const validTabs = ['tab-dashboard', 'tab-envelopes', 'tab-goals', 'tab-leaks', 'tab-ledger', 'tab-debts'];
                const hash = window.location.hash ? window.location.hash.replace('#', '') : null;
                const params = new URLSearchParams(window.location.search);
                const queryTab = params.get('tab');
                const storedTab = sessionStorage.getItem('masroofi_active_tab') || localStorage.getItem('masroofi_active_tab');
                
                const targetTab = (queryTab && validTabs.includes(queryTab)) ? queryTab
                                : (hash && validTabs.includes(hash)) ? hash
                                : (storedTab && validTabs.includes(storedTab)) ? storedTab
                                : null;
                
                if (targetTab && targetTab !== 'tab-dashboard') {
                    const style = document.createElement('style');
                    style.id = 'tab-preload-style';
                    style.textContent = `
                        .tab-pane { display: none !important; }
                        #${targetTab} { display: block !important; }
                        .tab-btn { background: transparent !important; color: var(--text-muted) !important; border-color: transparent !important; }
                        .tab-btn[data-tab="${targetTab}"] {
                            background: var(--card-bg) !important;
                            color: var(--primary) !important;
                            border-color: rgba(16, 185, 129, 0.3) !important;
                        }
                    `;
                    document.head.appendChild(style);
                }
            } catch(e) {}
        })();
    </script>
</head>
<body class="theme-{{ $settings->theme ?? 'dark' }}">
    <div id="app" class="app-container">
        <!-- TOP NAVIGATION -->
        <header class="top-nav" id="mainHeader">
            <!-- Brand & Current Workspace -->
            <div class="nav-brand">
                <a href="{{ route('dashboard') }}" class="brand-link">
                    <div class="logo-icon-wrap">
                        <svg class="logo-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <div class="brand-text">
                        <div class="brand-title-row">
                            <span class="brand-name">مصروفي</span>
                            <span class="badge-pro">PRO</span>
                        </div>
                        <span class="brand-subtitle">{{ auth()->user()->workspace->name ?? 'نظام الإدارة المالية والتحويش' }}</span>
                    </div>
                </a>
            </div>

            <!-- Center Context: Current Cycle Status -->
            @if(isset($monthLabel))
            <div class="nav-cycle-widget" id="cycleWidget" title="دورة الراتب الحالية">
                <span class="cycle-icon">📅</span>
                <span class="cycle-title">{{ $monthLabel }}</span>
                <span class="cycle-sep">•</span>
                <span class="cycle-days-badge">متبقي {{ $daysRemaining ?? 0 }} يوماً</span>
            </div>
            @endif

            <!-- Right/Actions: Currency, Sync, Theme, User Controls -->
            <div class="nav-actions">
                <!-- Currency & Rates Group -->
                <div class="currency-nav-group">
                    @php
                        $curCode = $settings->currency ?? 'ILS';
                        $targetCode = ($curCode === 'ILS') ? 'SAR' : 'ILS';
                        $curRate = \App\Services\CurrencyService::getRate($curCode, $targetCode);
                    @endphp
                    <button type="button" class="rate-indicator-btn" onclick="openModal('currencyConverterModal')" title="محول العملات الذكي: اضغط للتحويل الفوري">
                        <span class="rate-icon">💱</span>
                        <span class="rate-value">1 {{ $curCode }} = {{ $curRate }} {{ $targetCode }}</span>
                    </button>

                    <form id="currencyForm" action="{{ route('settings.update') }}" method="POST" style="display:inline; margin: 0;">
                        @csrf
                        @method('PUT')
                        <select name="currency" id="currencySelect" class="currency-select-compact" onchange="this.form.submit()" title="تغيير العملة الأساسية">
                            @php
                                $selectedCurr = $settings->currency ?? 'ILS';
                                if ($selectedCurr === '₪') $selectedCurr = 'ILS';
                            @endphp
                            @foreach(\App\Services\CurrencyService::getCurrencies() as $currCode => $currMeta)
                                <option value="{{ $currCode }}" {{ $selectedCurr === $currCode ? 'selected' : '' }}>
                                    {{ $currMeta['flag'] }} {{ $currMeta['name'] }} ({{ $currCode }})
                                </option>
                            @endforeach
                        </select>
                    </form>
                </div>

                <!-- Subtle Offline/Network Status Indicator -->
                <div class="network-status-indicator online" id="networkStatusPill" onclick="openOfflineSyncModal()" title="حالة الاتصال ومزامنة العمليات المحلية (اضغط للمزامنة)">
                    <span class="net-status-dot"></span>
                    <span id="networkStatusText" class="net-status-text">متصل</span>
                    <span id="pendingSyncBadge" class="pending-count-badge" style="display: none;">0</span>
                </div>

                <!-- PWA Install Quick Action (Hidden until eligible) -->
                <button type="button" id="pwaInstallBtn" class="btn-pwa-install" onclick="triggerPwaInstall()" style="display: none;" title="تثبيت التطبيق على جهازك">
                    <span>📲</span>
                    <span>تثبيت</span>
                </button>

                <!-- Theme Toggle Button -->
                <button type="button" id="toggleThemeBtn" class="nav-action-icon-btn" title="تبديل المظهر (ليلي / نهاري)" onclick="toggleAppTheme()">
                    <span id="themeIcon">{{ ($settings->theme ?? 'dark') == 'dark' ? '🌙' : '☀️' }}</span>
                </button>

                @auth
                <!-- Quick 1-Click Screen Lock -->
                @if(auth()->user()->hasPin())
                    <form action="{{ route('pin.lock') }}" method="POST" style="display:inline; margin: 0;">
                        @csrf
                        <button type="submit" class="nav-action-icon-btn lock-btn" title="قفل الشاشة السريع (PIN Lock)">
                            <span>🔒</span>
                        </button>
                    </form>
                @endif

                <!-- User Profile & Luxury Dropdown Menu -->
                <div class="user-profile-menu-wrap" id="userProfileMenuWrap">
                    <button type="button" class="user-pill-btn" id="userPillBtn" onclick="toggleUserDropdown(event)" title="قائمة الحساب والإعدادات">
                        <span class="user-avatar">{{ auth()->user()->avatar ?? '👤' }}</span>
                        <div class="user-meta">
                            <span class="user-name">{{ auth()->user()->name }}</span>
                            <span class="user-badge {{ auth()->user()->isAdmin() ? 'badge-admin' : 'badge-member' }}">
                                {{ auth()->user()->isAdmin() ? 'مدير' : 'عضو' }}
                            </span>
                        </div>
                        <span class="user-dropdown-caret">▼</span>
                    </button>

                    <!-- Luxury Dropdown Panel -->
                    <div class="user-dropdown-panel" id="userDropdownPanel">
                        <div class="user-dropdown-header">
                            <span class="user-dropdown-avatar-lg">{{ auth()->user()->avatar ?? '👤' }}</span>
                            <div style="display: flex; flex-direction: column; overflow: hidden;">
                                <span class="user-dropdown-user-title">{{ auth()->user()->name }}</span>
                                <span class="user-dropdown-workspace-subtitle" title="{{ auth()->user()->workspace->name ?? 'المحفظة الرئيسية' }}">
                                    {{ auth()->user()->isAdmin() ? '👑 مدير النظام' : '👤 عضو' }} • {{ auth()->user()->workspace->name ?? 'المحفظة' }}
                                </span>
                            </div>
                        </div>

                        @if(auth()->user()->isAdmin())
                            <a href="{{ route('admin.users.index') }}" class="user-dropdown-item {{ request()->routeIs('admin.*') ? 'active' : '' }}">
                                <span class="dropdown-item-icon">👥</span>
                                <div class="dropdown-item-text">
                                    <span class="item-title">إدارة المستخدمين والمحافظ (النظام)</span>
                                    <span class="item-desc">الصلاحيات والحسابات المشتركة للكل</span>
                                </div>
                            </a>
                        @endif

                        <!-- Family Workspace Members (Accessible to all workspace users) -->
                        <button type="button" class="user-dropdown-item" onclick="closeUserDropdown(); openModal('familyWorkspaceModal');">
                            <span class="dropdown-item-icon">👨‍👩‍👧‍👦</span>
                            <div class="dropdown-item-text">
                                <span class="item-title">أفراد المحفظة (العائلة والشريك)</span>
                                <span class="item-desc">مشاركة المحفظة مع الزوجة أو أفراد الأسرة</span>
                            </div>
                        </button>

                        <button type="button" class="user-dropdown-item" onclick="closeUserDropdown(); openModal('changePinModal');">
                            <span class="dropdown-item-icon">🔑</span>
                            <div class="dropdown-item-text">
                                <span class="item-title">تغيير رمز الـ PIN</span>
                                <span class="item-desc">تحديث رمز الدخول المكون من 4 أرقام</span>
                            </div>
                        </button>

                        @if(auth()->user()->hasPin())
                            <form action="{{ route('pin.lock') }}" method="POST" style="margin: 0; width: 100%;">
                                @csrf
                                <button type="submit" class="user-dropdown-item">
                                    <span class="dropdown-item-icon">🔒</span>
                                    <div class="dropdown-item-text">
                                        <span class="item-title">قفل الشاشة السريع</span>
                                        <span class="item-desc">حماية الجلسة برمز الـ PIN</span>
                                    </div>
                                </button>
                            </form>
                        @endif

                        <div class="user-dropdown-divider"></div>

                        <form action="{{ route('logout') }}" method="POST" id="logoutHeaderForm" style="margin: 0; width: 100%;">
                            @csrf
                            <button type="button" class="user-dropdown-item danger" onclick="confirmLogout(event)">
                                <span class="dropdown-item-icon">🚪</span>
                                <div class="dropdown-item-text">
                                    <span class="item-title">تسجيل الخروج</span>
                                    <span class="item-desc">إنهاء الجلسة بأمان</span>
                                </div>
                            </button>
                        </form>
                    </div>
                </div>
                @endauth
            </div>
        </header>

        <!-- NAVIGATION TABS -->
        @if(request()->routeIs('admin.*'))
            <div class="admin-top-nav-bar" style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 2rem; background: var(--card-bg); border: 1px solid var(--card-border); padding: 0.75rem 1.4rem; border-radius: 16px; box-shadow: var(--shadow-card); flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.3); display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                        👥
                    </div>
                    <div>
                        <div style="font-weight: 800; font-size: 1rem; color: #f8fafc; display: flex; align-items: center; gap: 0.5rem;">
                            <span>إدارة المستخدمين والمحافظ المشتركة</span>
                            <span style="font-size: 0.7rem; background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); padding: 0.1rem 0.5rem; border-radius: 20px; font-weight: 700;">منطقة الإدارة 👑</span>
                        </div>
                        <div style="font-size: 0.78rem; color: var(--text-secondary);">التحكم بحسابات النظام ومساحات العمل المشتركة</div>
                    </div>
                </div>
                <a href="{{ route('dashboard') }}" class="btn-subtle" style="text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.55rem 1.1rem; border-radius: 12px; font-weight: 700; font-size: 0.88rem; background: var(--card-bg-subtle); border: 1px solid var(--card-border); color: #cbd5e1; transition: all 0.2s ease;">
                    <span>↩️</span>
                    <span>العودة للمحفظة والمصاريف</span>
                </a>
            </div>
        @else
        <nav class="main-tabs-bar" id="mainTabsNav">
            <button class="tab-btn {{ ($activeTab ?? 'tab-dashboard') === 'tab-dashboard' ? 'active' : '' }}" data-tab="tab-dashboard" id="tabBtnDashboard">
                <span>📊</span>
                <span>الرئيسية واليومي</span>
            </button>
            <button class="tab-btn {{ ($activeTab ?? '') === 'tab-envelopes' ? 'active' : '' }}" data-tab="tab-envelopes" id="tabBtnEnvelopes">
                <span>✉️</span>
                <span>الميزانية والأظرف</span>
            </button>
            <button class="tab-btn {{ ($activeTab ?? '') === 'tab-goals' ? 'active' : '' }}" data-tab="tab-goals" id="tabBtnGoals">
                <span>🎯</span>
                <span>صناديق التحويش</span>
            </button>
            <button class="tab-btn {{ ($activeTab ?? '') === 'tab-leaks' ? 'active' : '' }}" data-tab="tab-leaks" id="tabBtnLeaks">
                <span>⚡</span>
                <span>كاشف الهدر والتسريبات</span>
            </button>
            <button class="tab-btn {{ ($activeTab ?? '') === 'tab-ledger' ? 'active' : '' }}" data-tab="tab-ledger" id="tabBtnLedger">
                <span>📝</span>
                <span>سجل المعاملات</span>
            </button>
            <button class="tab-btn {{ ($activeTab ?? '') === 'tab-debts' ? 'active' : '' }}" data-tab="tab-debts" id="tabBtnDebts">
                <span>📑</span>
                <span>دفتر الديون (شكك/سلف)</span>
                @if(isset($totalDebtsOwed) && $totalDebtsOwed > 0)
                    <span class="tab-alert-badge" title="إجمالي الديون المستحقة للسداد">{{ number_format($totalDebtsOwed, 0) }}</span>
                @endif
            </button>
        </nav>
        @endif

        <!-- FLASH NOTIFICATIONS -->
        @if(session('success'))
            <div class="toast-container" id="toastContainer">
                <div class="toast toast-success">
                    <span>✅</span> <span>{{ session('success') }}</span>
                </div>
            </div>
            <script>
                setTimeout(() => {
                    const t = document.getElementById('toastContainer');
                    if(t) t.remove();
                }, 3500);
            </script>
        @endif

        <!-- MAIN YIELD CONTENT -->
        <main class="main-content-area">
            @yield('content')
        </main>
    </div>

    <!-- 💱 Smart Currency Converter Modal -->
    <div class="modal-backdrop hidden" id="currencyConverterModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>💱 محول العملات الذكي (أسعار الصرف المباشرة)</h3>
                <button class="modal-close-btn" onclick="closeModal('currencyConverterModal')">&times;</button>
            </div>
            <div class="modal-form">
                <p style="font-size: 0.9rem; color: var(--text-secondary); margin: 0; line-height: 1.5">
                    تحويل فوري ودقيق بين الشيكل والريال السعودي وبقية العملات، مع إمكانية تحويل ميزانياتك ومعاملاتك بنقرة واحدة!
                </p>

                <!-- Live Market Status & Instant Refresh -->
                <div class="live-sync-banner">
                    <div style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-primary); font-weight: 600;">
                        <span class="pulse-indicator-dot"></span>
                        <span>سعر البورصة المباشر: <span id="ratesLastUpdatedText" style="color: #10b981;">{{ \App\Services\CurrencyService::getLastUpdated() }}</span></span>
                    </div>
                    <button type="button" id="btnRefreshRates" onclick="refreshLiveRatesNow()" style="background: var(--card-bg); border: 1px solid var(--card-border); color: var(--text-primary); border-radius: 8px; padding: 0.3rem 0.65rem; font-size: 0.8rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.4rem; transition: all 0.2s;">
                        <span id="refreshSpinner" style="display: inline-block;">🔄</span>
                        <span id="refreshBtnLabel">تحديث الأسعار الآن</span>
                    </button>
                </div>

                <div class="form-group">
                    <label>المبلغ المراد تحويله:</label>
                    <input type="number" id="converterAmount" class="styled-input large-number" value="100" min="0" step="any" oninput="runLiveConversion()">
                </div>

                <div class="converter-selection-row">
                    <div class="form-group" style="flex: 1">
                        <label>من عملة:</label>
                        <select id="converterFrom" class="styled-select" onchange="runLiveConversion()">
                            <option value="ILS" {{ ($settings->currency ?? 'ILS') == 'ILS' ? 'selected' : '' }}>🇵🇸 شيكل (ILS)</option>
                            <option value="SAR" {{ ($settings->currency ?? 'ILS') == 'SAR' ? 'selected' : '' }}>🇸🇦 ريال (SAR)</option>
                            <option value="AED">🇦🇪 درهم (AED)</option>
                            <option value="JOD">🇯🇴 دينار (JOD)</option>
                            <option value="EGP">🇪🇬 جنيه (EGP)</option>
                            <option value="KWD">🇰🇼 دينار (KWD)</option>
                            <option value="QAR">🇶🇦 ريال (QAR)</option>
                            <option value="USD">🇺🇸 دولار (USD)</option>
                            <option value="EUR">🇪🇺 يورو (EUR)</option>
                        </select>
                    </div>

                    <button type="button" class="btn-swap-currency" onclick="swapConverterCurrencies()" title="تبديل العملتين">
                        ⇄
                    </button>

                    <div class="form-group" style="flex: 1">
                        <label>إلى عملة:</label>
                        <select id="converterTo" class="styled-select" onchange="runLiveConversion()">
                            <option value="SAR" {{ ($settings->currency ?? 'ILS') == 'ILS' ? 'selected' : '' }}>🇸🇦 ريال (SAR)</option>
                            <option value="ILS" {{ ($settings->currency ?? 'ILS') != 'ILS' ? 'selected' : '' }}>🇵🇸 شيكل (ILS)</option>
                            <option value="AED">🇦🇪 درهم (AED)</option>
                            <option value="JOD">🇯🇴 دينار (JOD)</option>
                            <option value="EGP">🇪🇬 جنيه (EGP)</option>
                            <option value="KWD">🇰🇼 دينار (KWD)</option>
                            <option value="QAR">🇶🇦 ريال (QAR)</option>
                            <option value="USD">🇺🇸 دولار (USD)</option>
                            <option value="EUR">🇪🇺 يورو (EUR)</option>
                        </select>
                    </div>
                </div>

                <!-- Live Result Card -->
                <div class="converter-result-card" id="converterResultBox">
                    <div class="converter-result-label">المبلغ بعد التحويل:</div>
                    <div class="converter-result-val">
                        <span id="converterOutputVal">--</span>
                        <span id="converterOutputCurrency" class="curr-tag">SAR</span>
                    </div>
                    <div class="converter-rate-badge" id="converterRateBadge">
                        سعر الصرف اللحظي
                    </div>
                </div>

                <!-- 1-Click Apply to entire account -->
                <form id="applyConvertedCurrencyForm" action="{{ route('settings.update') }}" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="currency" id="applyConvertedCurrencyInput" value="SAR">
                    <button type="submit" class="btn-primary" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 0.5rem">
                        <span>⚡ اعتماد هذه العملة وتحويل كافة ميزانياتي إليها</span>
                    </button>
                </form>

                <div class="modal-actions" style="margin-top: 0">
                    <button type="button" class="btn-subtle" onclick="closeModal('currencyConverterModal')">إغلاق</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 🔐 Professional 4-Digit PIN Change Modal -->
    <div class="modal-backdrop hidden" id="changePinModal">
        <div class="modal-card" style="max-width: 450px;">
            <div class="modal-header" style="padding: 0.75rem 1.25rem;">
                <div>
                    <h3 style="display: flex; align-items: center; gap: 0.4rem; margin: 0 0 0.15rem 0; font-size: 0.98rem;">
                        <span>🔐</span>
                        <span>تغيير رمز الـ PIN السريع</span>
                    </h3>
                    <p style="font-size: 0.78rem; color: var(--text-secondary); margin: 0;">
                        تحديث رمز الدخول المكون من 4 أرقام لتعزيز أمان محفظتك
                    </p>
                </div>
                <button type="button" class="modal-close-btn" onclick="closeModal('changePinModal')">&times;</button>
            </div>

            <form action="{{ route('pin.change') }}" method="POST" id="changePinForm" style="display: flex; flex-direction: column; margin: 0;">
                @csrf
                
                <div style="padding: 0.9rem 1.25rem; display: flex; flex-direction: column; gap: 0.7rem;">
                    <!-- 1. Identity Verification Method Tabs -->
                    <div>
                        <label style="display: block; font-size: 0.78rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.3rem;">
                            1️⃣ التحقق من هويتك أولاً:
                        </label>

                        <div class="pin-auth-tabs">
                            <button type="button" class="pin-auth-tab active" id="tabAuthPin" onclick="switchPinAuthMethod('current_pin')">
                                <span>🔢</span>
                                <span>بالـ PIN الحالي</span>
                            </button>
                            <button type="button" class="pin-auth-tab" id="tabAuthPassword" onclick="switchPinAuthMethod('password')">
                                <span>🔒</span>
                                <span>بكلمة مرور الحساب</span>
                            </button>
                        </div>
                        <input type="hidden" name="auth_method" id="inputAuthMethod" value="current_pin">
                    </div>

                    <!-- 1A: Current PIN Input -->
                    <div id="sectionCurrentPin">
                        <label style="display: block; font-size: 0.76rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.25rem;">
                            أدخل رمز الـ PIN الحالي:
                        </label>
                        <div class="pin-box-row">
                            <input type="password" maxlength="1" inputmode="numeric" class="pin-digit-box current-pin-box" data-idx="0" autocomplete="off">
                            <input type="password" maxlength="1" inputmode="numeric" class="pin-digit-box current-pin-box" data-idx="1" autocomplete="off">
                            <input type="password" maxlength="1" inputmode="numeric" class="pin-digit-box current-pin-box" data-idx="2" autocomplete="off">
                            <input type="password" maxlength="1" inputmode="numeric" class="pin-digit-box current-pin-box" data-idx="3" autocomplete="off">
                        </div>
                        <input type="hidden" name="current_pin" id="hiddenCurrentPin">
                    </div>

                    <!-- 1B: Current Password Input -->
                    <div id="sectionCurrentPassword" style="display: none;">
                        <label style="display: block; font-size: 0.76rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.25rem;">
                            أدخل كلمة مرور حسابك الحالية:
                        </label>
                        <div class="password-input-wrap">
                            <input type="password" name="current_password" id="inputCurrentPassword" class="styled-input" placeholder="كلمة المرور الحالية">
                            <button type="button" class="btn-password-toggle" onclick="togglePasswordVisibility('inputCurrentPassword', this)" title="إظهار كلمة المرور" aria-label="إظهار كلمة المرور">
                                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>

                    <div style="height: 1px; background: var(--card-border); margin: 0.1rem 0;"></div>

                    <!-- 2. New PIN -->
                    <div>
                        <label style="display: block; font-size: 0.78rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem;">
                            2️⃣ رمز الـ PIN الجديد (4 أرقام):
                        </label>
                        <div class="pin-box-row">
                            <input type="password" maxlength="1" inputmode="numeric" class="pin-digit-box new-pin-box" data-idx="0" autocomplete="off">
                            <input type="password" maxlength="1" inputmode="numeric" class="pin-digit-box new-pin-box" data-idx="1" autocomplete="off">
                            <input type="password" maxlength="1" inputmode="numeric" class="pin-digit-box new-pin-box" data-idx="2" autocomplete="off">
                            <input type="password" maxlength="1" inputmode="numeric" class="pin-digit-box new-pin-box" data-idx="3" autocomplete="off">
                        </div>
                        <input type="hidden" name="new_pin" id="hiddenNewPin">
                    </div>

                    <!-- 3. Confirm New PIN -->
                    <div>
                        <label style="display: block; font-size: 0.78rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem;">
                            3️⃣ تأكيد رمز الـ PIN الجديد:
                        </label>
                        <div class="pin-box-row">
                            <input type="password" maxlength="1" inputmode="numeric" class="pin-digit-box confirm-pin-box" data-idx="0" autocomplete="off">
                            <input type="password" maxlength="1" inputmode="numeric" class="pin-digit-box confirm-pin-box" data-idx="1" autocomplete="off">
                            <input type="password" maxlength="1" inputmode="numeric" class="pin-digit-box confirm-pin-box" data-idx="2" autocomplete="off">
                            <input type="password" maxlength="1" inputmode="numeric" class="pin-digit-box confirm-pin-box" data-idx="3" autocomplete="off">
                        </div>
                        <input type="hidden" name="new_pin_confirmation" id="hiddenConfirmPin">
                        
                        <!-- Live Match Badge -->
                        <div id="pinMatchStatus" style="text-align: center; font-size: 0.75rem; font-weight: 700; margin-top: 0.3rem; color: var(--text-muted);">
                            يرجى كتابة 4 أرقام متطابقة
                        </div>
                    </div>

                    <!-- Security Tip -->
                    <div style="background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.2); border-radius: 8px; padding: 0.4rem 0.6rem; font-size: 0.72rem; color: #93c5fd; display: flex; align-items: center; gap: 0.35rem;">
                        <span>💡</span>
                        <span>تجنب استخدام أرقام مكررة أو سهلة التخمين (مثل 1111 أو 1234).</span>
                    </div>
                </div>

                <!-- Pinned Actions Footer -->
                <div style="display: flex; gap: 0.65rem; padding: 0.75rem 1.25rem; border-top: 1px solid var(--card-border); background: var(--card-bg-subtle);">
                    <button type="button" class="btn-subtle" onclick="closeModal('changePinModal')" style="flex: 1; justify-content: center; padding: 0.55rem;">إلغاء</button>
                    <button type="submit" class="btn-primary" id="btnSubmitChangePin" style="flex: 2; background: linear-gradient(135deg, #10b981, #059669); font-weight: 800; justify-content: center; display: flex; align-items: center; gap: 0.4rem; padding: 0.55rem;">
                        <span>💾</span>
                        <span>حفظ رمز الـ PIN الجديد</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 👨‍👩‍👧‍👦 Family Workspace Members Modal (مشاركة المحفظة مع الزوجة أو أفراد العائلة) -->
    <div class="modal-backdrop hidden" id="familyWorkspaceModal">
        <div class="modal-card modal-card-lg" style="max-width: 680px;">
            <div class="modal-header">
                <div>
                    <h3 style="display: flex; align-items: center; gap: 0.5rem; margin: 0 0 0.25rem 0; font-size: 1.25rem; font-weight: 800;">
                        <span>👨‍👩‍👧‍👦</span>
                        <span>أفراد المحفظة المشتركة (العائلة / الشريك)</span>
                    </h3>
                    <p style="font-size: 0.82rem; color: var(--text-secondary); margin: 0;">
                        المحفظة المالية: <strong style="color: #60a5fa;">«{{ auth()->user()->workspace->name ?? 'المحفظة الحالية' }}»</strong> • مشاركة نفس الميزانية والمصاريف مع الزوجة أو أفراد الأسرة.
                    </p>
                </div>
                <button type="button" class="modal-close-btn" onclick="closeModal('familyWorkspaceModal')">&times;</button>
            </div>

            <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1.5rem;">
                <!-- 1. Current Members List -->
                <div>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.85rem;">
                        <h4 style="margin: 0; font-size: 0.95rem; font-weight: 800; color: #f8fafc; display: flex; align-items: center; gap: 0.4rem;">
                            <span>👥</span>
                            <span>الأعضاء المشاركون في هذه المحفظة:</span>
                        </h4>
                        <span style="font-size: 0.78rem; color: #94a3b8; background: rgba(255,255,255,0.06); padding: 0.2rem 0.6rem; border-radius: 6px; font-weight: 700;">
                            {{ auth()->user()->workspace?->users()->count() ?? 1 }} أفراد
                        </span>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 0.65rem;">
                        @php
                            $currentWsMembers = auth()->user()->workspace ? auth()->user()->workspace->users()->get() : collect([auth()->user()]);
                        @endphp
                        @foreach($currentWsMembers as $member)
                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.85rem 1rem; border-radius: 12px; background: rgba(255,255,255,0.03); border: 1px solid var(--card-border); gap: 0.75rem; flex-wrap: wrap;">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #10b981, #3b82f6); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; color: #fff; font-weight: 800; flex-shrink: 0;">
                                        {{ mb_substr($member->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <div style="display: flex; align-items: center; gap: 0.45rem;">
                                            <span style="font-weight: 800; color: #f8fafc; font-size: 0.92rem;">{{ $member->name }}</span>
                                            @if($member->id === auth()->id())
                                                <span style="font-size: 0.7rem; color: #10b981; background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); padding: 0.1rem 0.45rem; border-radius: 6px; font-weight: 700;">(أنت)</span>
                                            @endif
                                            @if($member->role === 'admin')
                                                <span style="font-size: 0.7rem; color: #f59e0b; background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.3); padding: 0.1rem 0.45rem; border-radius: 6px; font-weight: 700;">👑 مدير المحفظة</span>
                                            @else
                                                <span style="font-size: 0.7rem; color: #38bdf8; background: rgba(56, 189, 248, 0.12); border: 1px solid rgba(56, 189, 248, 0.3); padding: 0.1rem 0.45rem; border-radius: 6px; font-weight: 700;">👤 شريك / فرد عائلي</span>
                                            @endif
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 0.65rem; margin-top: 0.2rem; font-size: 0.78rem; color: #94a3b8; flex-wrap: wrap;">
                                            <span class="username-pill" dir="ltr" style="font-size: 0.75rem;">&#64;{{ $member->username }}</span>
                                            <span>✉️ {{ $member->email }}</span>
                                            @if($member->phone)
                                                <span dir="ltr">📱 {{ $member->phone }}</span>
                                            @endif
                                            @if($member->hasPin())
                                                <span style="color: #10b981; font-weight: 700;">🔒 PIN مفعل</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <!-- Actions for other members -->
                                @if($member->id !== auth()->id())
                                    <div style="display: flex; align-items: center; gap: 0.4rem;">
                                        @if($member->hasPin())
                                            <form id="famResetPin_{{ $member->id }}" action="{{ route('workspace.members.resetPin', $member) }}" method="POST" style="display: none;">
                                                @csrf
                                            </form>
                                            <button type="button" class="btn-user-action pin" onclick="confirmResetPin('famResetPin_{{ $member->id }}', '{{ $member->name }}')" title="إعادة تعيين رمز PIN للشريك">
                                                🔑
                                            </button>
                                        @endif

                                        <form action="{{ route('workspace.members.destroy', $member) }}" method="POST" style="display: inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="btn-user-action delete" onclick="confirmDeleteForm(event, 'هل أنت متأكد من إزالة «{{ $member->name }}» من محفظتك؟ لن يتمكن من تسجيل الدخول والمشاركة فيها.', 'تأكيد إزالة الشريك')" title="إزالة من المحفظة">
                                                🗑️
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- 2. Add New Member Form Section -->
                <div style="background: rgba(15, 23, 42, 0.65); border: 1px solid rgba(59, 130, 246, 0.25); border-radius: 16px; padding: 1.25rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.4rem;">
                        <span style="font-size: 1.2rem;">➕</span>
                        <h4 style="margin: 0; font-size: 1rem; font-weight: 800; color: #38bdf8;">إضافة شريك جديد للمحفظة (الزوجة / فرد من العائلة)</h4>
                    </div>
                    <p style="font-size: 0.8rem; color: #94a3b8; margin: 0 0 1rem 0; line-height: 1.45;">
                        الحساب الجديد سيرتبط بمحفظتك المالية تلقائياً، وسيتشارك معك في تسجيل المصاريف ومتابعة الميزانية والأظرف.
                    </p>

                    <form action="{{ route('workspace.members.store') }}" method="POST">
                        @csrf
                        <div class="form-row">
                            <div class="form-group">
                                <label>الاسم الكامل أو اللقب: *</label>
                                <input type="text" name="name" class="styled-input" required placeholder="مثال: أم محمد، سارة، ابني أحمد...">
                            </div>
                            <div class="form-group">
                                <label>اسم الدخول (Username فريد): *</label>
                                <input type="text" name="username" class="styled-input" required placeholder="مثال: sara, um_mohammed...">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>البريد الإلكتروني: *</label>
                                <input type="email" name="email" class="styled-input" required placeholder="sara@example.com">
                            </div>
                            <div class="form-group">
                                <label>رقم الجوال (اختياري):</label>
                                <input type="text" name="phone" class="styled-input" placeholder="0599123456">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>كلمة المرور الأولية: *</label>
                                <div class="password-input-wrap">
                                    <input type="password" name="password" id="familyMemberPassword" class="styled-input" required placeholder="•••••••• (9 خانات، أحرف A-z وأرقام)">
                                    <button type="button" class="btn-password-toggle" onclick="togglePasswordVisibility('familyMemberPassword', this)" title="إظهار كلمة المرور" aria-label="إظهار كلمة المرور">
                                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </button>
                                </div>
                                <div style="font-size: 0.73rem; color: #94a3b8; margin-top: 4px;">يجب ألا تقل عن 9 خانات وتحتوي على أرقام وأحرف كبيرة وصغيرة (A-Z, a-z, 0-9).</div>
                            </div>
                            <div class="form-group">
                                <label>تأكيد كلمة المرور: *</label>
                                <div class="password-input-wrap">
                                    <input type="password" name="password_confirmation" id="familyMemberPasswordConfirm" class="styled-input" required placeholder="••••••••">
                                    <button type="button" class="btn-password-toggle" onclick="togglePasswordVisibility('familyMemberPasswordConfirm', this)" title="إظهار كلمة المرور" aria-label="إظهار كلمة المرور">
                                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div style="margin-top: 1rem; display: flex; justify-content: flex-end;">
                            <button type="submit" class="btn-primary" style="background: linear-gradient(135deg, #10b981, #059669); padding: 0.7rem 1.4rem; font-size: 0.92rem; font-weight: 800; border-radius: 12px; display: inline-flex; align-items: center; gap: 0.45rem;">
                                <span>🚀</span>
                                <span>إنشاء الحساب والربط المباشر بالمحفظة</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- PIN Modal Interactive Script -->
    <script>
        function switchPinAuthMethod(method) {
            const inputAuth = document.getElementById('inputAuthMethod');
            if (inputAuth) inputAuth.value = method;
            const tabPin = document.getElementById('tabAuthPin');
            const tabPass = document.getElementById('tabAuthPassword');
            const secPin = document.getElementById('sectionCurrentPin');
            const secPass = document.getElementById('sectionCurrentPassword');

            if (method === 'current_pin') {
                if (tabPin) tabPin.classList.add('active');
                if (tabPass) tabPass.classList.remove('active');
                if (secPin) secPin.style.display = 'block';
                if (secPass) secPass.style.display = 'none';
            } else {
                if (tabPin) tabPin.classList.remove('active');
                if (tabPass) tabPass.classList.add('active');
                if (secPin) secPin.style.display = 'none';
                if (secPass) secPass.style.display = 'block';
            }
        }

        function togglePasswordVisibility(inputId, btn) {
            const input = document.getElementById(inputId);
            if (input) {
                if (input.type === 'password') {
                    input.type = 'text';
                    btn.textContent = '🙈';
                } else {
                    input.type = 'password';
                    btn.textContent = '👁️';
                }
            }
        }

        function setupPinBoxesGroup(className, hiddenInputId, onInputCallback) {
            const boxes = document.querySelectorAll('.' + className);
            const hidden = document.getElementById(hiddenInputId);

            boxes.forEach((box, idx) => {
                box.addEventListener('input', (e) => {
                    const val = e.target.value.replace(/\D/g, '');
                    e.target.value = val ? val[0] : '';
                    
                    if (val && idx < boxes.length - 1) {
                        boxes[idx + 1].focus();
                    }
                    updateHidden();
                    if (onInputCallback) onInputCallback();
                });

                box.addEventListener('keydown', (e) => {
                    if (e.key === 'Backspace' && !e.target.value && idx > 0) {
                        boxes[idx - 1].focus();
                    }
                });

                box.addEventListener('paste', (e) => {
                    e.preventDefault();
                    const text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 4);
                    for (let i = 0; i < boxes.length; i++) {
                        boxes[i].value = text[i] || '';
                    }
                    if (text.length >= 4) {
                        boxes[3].focus();
                    } else if (text.length > 0) {
                        boxes[text.length].focus();
                    }
                    updateHidden();
                    if (onInputCallback) onInputCallback();
                });
            });

            function updateHidden() {
                let full = '';
                boxes.forEach(b => full += b.value);
                if (hidden) hidden.value = full;
            }
        }

        function verifyPinMatch() {
            const newPin = document.getElementById('hiddenNewPin')?.value || '';
            const confirmPin = document.getElementById('hiddenConfirmPin')?.value || '';
            const status = document.getElementById('pinMatchStatus');

            if (!status) return;

            if (newPin.length === 4 && confirmPin.length === 4) {
                if (newPin === confirmPin) {
                    status.innerHTML = '✅ الرمزان متطابقان وجاهزان للحفظ!';
                    status.style.color = '#10b981';
                } else {
                    status.innerHTML = '❌ الرمزان غير متطابقين، يرجى التأكد';
                    status.style.color = '#ef4444';
                }
            } else {
                status.innerHTML = 'يرجى كتابة 4 أرقام متطابقة';
                status.style.color = 'var(--text-muted)';
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            setupPinBoxesGroup('current-pin-box', 'hiddenCurrentPin');
            setupPinBoxesGroup('new-pin-box', 'hiddenNewPin', verifyPinMatch);
            setupPinBoxesGroup('confirm-pin-box', 'hiddenConfirmPin', verifyPinMatch);

            const form = document.getElementById('changePinForm');
            if (form) {
                form.addEventListener('submit', (e) => {
                    const authMethod = document.getElementById('inputAuthMethod')?.value;
                    const currentPin = document.getElementById('hiddenCurrentPin')?.value;
                    const currentPass = document.getElementById('inputCurrentPassword')?.value;
                    const newPin = document.getElementById('hiddenNewPin')?.value;
                    const confirmPin = document.getElementById('hiddenConfirmPin')?.value;

                    if (authMethod === 'current_pin' && (!currentPin || currentPin.length !== 4)) {
                        e.preventDefault();
                        alert('يرجى إدخال رمز الـ PIN الحالي المكون من 4 أرقام');
                        return;
                    }

                    if (authMethod === 'password' && (!currentPass || currentPass.trim() === '')) {
                        e.preventDefault();
                        alert('يرجى إدخال كلمة المرور الحالية');
                        return;
                    }

                    if (!newPin || newPin.length !== 4) {
                        e.preventDefault();
                        alert('يرجى إدخال رمز الـ PIN الجديد المكون من 4 أرقام');
                        return;
                    }

                    if (newPin !== confirmPin) {
                        e.preventDefault();
                        alert('تأكيد رمز الـ PIN الجديد غير متطابق مع الرمز الجديد');
                        return;
                    }
                });
            }
        });
    </script>

    <!-- Live Rates JS -->
    <script>
        window.masroofiRates = @json(\App\Services\CurrencyService::getRates());
        window.masroofiCurrencies = @json(\App\Services\CurrencyService::getCurrencies());

        function getExchangeRate(from, to) {
            if (!window.masroofiRates) return 1.0;
            const rFrom = window.masroofiRates[from] || 1.0;
            const rTo = window.masroofiRates[to] || 1.0;
            if (rFrom <= 0) return 1.0;
            return rTo / rFrom;
        }

        function runLiveConversion() {
            const amount = parseFloat(document.getElementById('converterAmount')?.value) || 0;
            const from = document.getElementById('converterFrom')?.value || 'ILS';
            const to = document.getElementById('converterTo')?.value || 'SAR';
            const rate = getExchangeRate(from, to);
            const converted = (amount * rate).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            
            const outputVal = document.getElementById('converterOutputVal');
            const outputCurr = document.getElementById('converterOutputCurrency');
            const rateBadge = document.getElementById('converterRateBadge');
            const applyInput = document.getElementById('applyConvertedCurrencyInput');
            
            if (outputVal) outputVal.textContent = converted;
            if (outputCurr) outputCurr.textContent = to;
            if (applyInput) applyInput.value = to;
            if (rateBadge) {
                rateBadge.textContent = `سعر الصرف: 1 ${from} = ${rate.toFixed(4)} ${to}`;
            }
        }

        function swapConverterCurrencies() {
            const fromEl = document.getElementById('converterFrom');
            const toEl = document.getElementById('converterTo');
            if (fromEl && toEl) {
                const temp = fromEl.value;
                fromEl.value = toEl.value;
                toEl.value = temp;
                runLiveConversion();
            }
        }

        function refreshLiveRatesNow() {
            const btn = document.getElementById('btnRefreshRates');
            const spinner = document.getElementById('refreshSpinner');
            const label = document.getElementById('refreshBtnLabel');
            const updatedText = document.getElementById('ratesLastUpdatedText');

            if (btn) btn.disabled = true;
            if (spinner) spinner.style.animation = 'spin 0.8s linear infinite';
            if (label) label.textContent = 'جاري التحديث...';

            fetch('{{ route("currency.refresh") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.rates) {
                    window.masroofiRates = data.rates;
                    if (updatedText) updatedText.textContent = data.last_updated;
                    runLiveConversion();
                    if (label) label.textContent = 'تم التحديث! ✓';
                    setTimeout(() => {
                        if (label) label.textContent = 'تحديث الأسعار الآن';
                    }, 2500);
                }
            })
            .catch(err => {
                console.error('Failed to refresh rates:', err);
                if (label) label.textContent = 'تعذر الاتصال بالسوق';
                setTimeout(() => {
                    if (label) label.textContent = 'تحديث الأسعار الآن';
                }, 2000);
            })
            .finally(() => {
                if (btn) btn.disabled = false;
                if (spinner) spinner.style.animation = 'none';
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            runLiveConversion();
        });
    </script>


    <!-- 📲 PWA Bottom Installation Banner -->
    <div class="pwa-install-banner" id="pwaBanner" style="display: none;">
        <div class="pwa-banner-content">
            <div class="pwa-banner-icon">
                <img src="{{ asset('icons/icon-192.png') }}" alt="مصروفي Pro">
            </div>
            <div>
                <div class="pwa-banner-title">تثبيت تطبيق مصروفي Pro 📱</div>
                <div class="pwa-banner-desc">ثبّت التطبيق على شاشة هاتفك الرئيسية للوصول السريع بدون متجر وتجربة شاشة كاملة فائقة السرعة!</div>
            </div>
        </div>
        <div class="pwa-banner-actions">
            <button type="button" class="btn-pwa-primary" onclick="triggerPwaInstall()">
                <span>📲</span>
                <span>تثبيت الآن</span>
            </button>
            <button type="button" class="btn-pwa-dismiss" onclick="dismissPwaBanner()" title="إغلاق">
                ✕ لاحقاً
            </button>
        </div>
    </div>

    <!-- PWA Service Worker & Install Script -->
    <script>
        let deferredPrompt = null;
        const pwaInstallBtn = document.getElementById('pwaInstallBtn');
        const pwaBanner = document.getElementById('pwaBanner');

        // Detect if already installed / standalone
        const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;

            if (!isStandalone && !sessionStorage.getItem('pwa_banner_dismissed')) {
                if (pwaInstallBtn) pwaInstallBtn.style.display = 'inline-flex';
                if (pwaBanner) pwaBanner.style.display = 'flex';
            }
        });

        function triggerPwaInstall() {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    deferredPrompt = null;
                    if (pwaBanner) pwaBanner.style.display = 'none';
                    if (pwaInstallBtn) pwaInstallBtn.style.display = 'none';
                });
            } else {
                alert('لتثبيت التطبيق على جهازك:\n\n• على آيفون (Safari): اضغط زر المشاركة (Share) ثم اختر "إضافة إلى الشاشة الرئيسية (Add to Home Screen)".\n\n• على أندرويد (Chrome): اضغط قائمة الخيارات (⋮) بأعلى المتصفح ثم اختر "تثبيت التطبيق (Install App)".');
            }
        }

        function dismissPwaBanner() {
            if (pwaBanner) pwaBanner.style.display = 'none';
            sessionStorage.setItem('pwa_banner_dismissed', '1');
        }

        window.addEventListener('appinstalled', () => {
            if (pwaBanner) pwaBanner.style.display = 'none';
            if (pwaInstallBtn) pwaInstallBtn.style.display = 'none';
            deferredPrompt = null;
        });

        // Register Service Worker
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('{{ asset('sw.js') }}')
                    .then((reg) => {
                        console.log('Masroofi Pro Service Worker Registered:', reg.scope);
                    })
                    .catch((err) => {
                        console.warn('Service Worker registration failed:', err);
                    });
            });
        }

        // 🌓 Theme Switcher (Dark / Light - ليلي / نهاري)
        window.applyTheme = function(theme, save = true) {
            document.body.classList.remove('theme-dark', 'theme-light');
            document.body.classList.add('theme-' + theme);
            document.documentElement.classList.remove('theme-dark', 'theme-light');
            document.documentElement.classList.add('theme-' + theme);

            const themeIcon = document.getElementById('themeIcon');
            const toggleBtn = document.getElementById('toggleThemeBtn');

            if (themeIcon) {
                themeIcon.textContent = (theme === 'light') ? '☀️' : '🌙';
            }

            if (toggleBtn) {
                toggleBtn.title = (theme === 'light') 
                    ? 'الوضع النهاري مفعّل ☀️ (اضغط للتبديل إلى الوضع الليلي 🌙)' 
                    : 'الوضع الليلي مفعّل 🌙 (اضغط للتبديل إلى الوضع النهاري ☀️)';
            }

            // Update meta theme-color for browser address bar
            const metaThemeColor = document.querySelector('meta[name="theme-color"]');
            if (metaThemeColor) {
                metaThemeColor.setAttribute('content', (theme === 'light') ? '#f8fafc' : '#090d16');
            }

            if (save) {
                localStorage.setItem('masroofi_theme', theme);
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                if (token) {
                    fetch('{{ route('settings.update') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'X-HTTP-Method-Override': 'PUT'
                        },
                        body: JSON.stringify({
                            _method: 'PUT',
                            theme: theme
                        })
                    }).catch(e => console.warn('Theme server sync:', e));
                }
            }
        };

        window.toggleAppTheme = function() {
            const isDark = document.body.classList.contains('theme-dark');
            const nextTheme = isDark ? 'light' : 'dark';
            window.applyTheme(nextTheme, true);

            const toggleBtn = document.getElementById('toggleThemeBtn');
            if (toggleBtn) {
                toggleBtn.style.transform = 'scale(1.2) rotate(25deg)';
                setTimeout(() => { toggleBtn.style.transform = ''; }, 250);
            }
        };

        // Initialize Theme from localStorage or Server Default
        document.addEventListener('DOMContentLoaded', () => {
            const savedTheme = localStorage.getItem('masroofi_theme');
            if (savedTheme === 'light' || savedTheme === 'dark') {
                window.applyTheme(savedTheme, false);
            } else {
                const initialTheme = document.body.classList.contains('theme-light') ? 'light' : 'dark';
                window.applyTheme(initialTheme, false);
            }

            const toggleBtn = document.getElementById('toggleThemeBtn');
            if (toggleBtn && !toggleBtn.onclick) {
                toggleBtn.addEventListener('click', window.toggleAppTheme);
            }
        });
    </script>

    <!-- 🔄 Offline Sync Manager Modal -->
    <div class="modal-backdrop hidden" id="offlineSyncModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>🔄 مدير المزامنة والعمليات المسجلة محلياً (<span id="offlineModalCountBadge">0</span>)</h3>
                <button class="modal-close-btn" onclick="closeModal('offlineSyncModal')">&times;</button>
            </div>
            <div class="modal-form">
                <p style="font-size: 0.9rem; color: var(--text-secondary); margin: 0; line-height: 1.5">
                    العمليات المسجلة على هاتفك أثناء انقطاع الإنترنت. يتم رفعها وتحديث قاعدة البيانات تلقائياً بمجرد عودة الاتصال!
                </p>

                <div id="offlinePendingEmpty" style="display: none; text-align: center; padding: 2.5rem 1rem;">
                    <div style="font-size: 3rem; margin-bottom: 0.5rem;">🎉</div>
                    <h4 style="color: var(--text-primary); margin-bottom: 0.25rem;">لا توجد أي معاملات معلقة</h4>
                    <p style="color: var(--text-secondary); font-size: 0.85rem; margin: 0;">جميع مصاريفك ومعاملاتك متزامنة ومحفوظة بالكامل في السيرفر.</p>
                </div>

                <div class="offline-sync-container" id="offlinePendingList"></div>

                <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 1rem;">
                    <button type="button" class="btn-primary" id="btnTriggerOfflineSync" onclick="MasroofiOffline.syncTransactions(true)" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                        <span>🔄</span>
                        <span>مزامنة العمليات الآن مع السيرفر</span>
                    </button>
                    <button type="button" class="btn-subtle" onclick="closeModal('offlineSyncModal')">إغلاق</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 🛡️ Universal Custom Confirmation Modal (Modern Glassmorphism) -->
    <div class="modal-backdrop hidden" id="customConfirmModal" style="z-index: 99999;">
        <div class="modal-card modal-confirm-card">
            <div class="confirm-modal-body">
                <div class="confirm-icon-wrap" id="confirmModalIcon">
                    <span class="confirm-icon-symbol">🗑️</span>
                </div>
                <h3 class="confirm-title" id="confirmModalTitle">تأكيد حذف المعاملة</h3>
                <p class="confirm-message" id="confirmModalMessage">هل أنت متأكد من حذف هذه المعاملة؟ لا يمكن التراجع عن هذا الإجراء.</p>
                <div class="confirm-details-box hidden" id="confirmModalDetails"></div>
            </div>
            <div class="modal-actions confirm-actions">
                <button type="button" class="btn-subtle" id="confirmModalCancelBtn">تراجع</button>
                <button type="button" class="btn-danger-confirm" id="confirmModalActionBtn">
                    <span id="confirmModalActionText">نعم، احذف المعاملة</span>
                </button>
            </div>
        </div>
    </div>

    <script>
        window.togglePasswordVisibility = function(inputId, btn) {
            const input = typeof inputId === 'string' ? document.getElementById(inputId) : inputId;
            if (!input) return;
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            if (btn) {
                const eyeOpen = `<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>`;
                const eyeOff = `<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" y1="2" x2="22" y2="22"/></svg>`;
                btn.innerHTML = isPassword ? eyeOff : eyeOpen;
                btn.title = isPassword ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور';
                btn.setAttribute('aria-label', btn.title);
            }
        };

        window.showCustomConfirm = function({
            title = 'تأكيد الحذف',
            message = 'هل أنت متأكد من حذف هذه المعاملة؟',
            details = null,
            confirmText = 'نعم، احذف',
            cancelText = 'تراجع',
            icon = '🗑️',
            isDanger = true
        } = {}) {
            return new Promise((resolve) => {
                const modal = document.getElementById('customConfirmModal');
                if (!modal) {
                    resolve(window.confirm(message));
                    return;
                }

                document.getElementById('confirmModalIcon').innerHTML = `<span class="confirm-icon-symbol">${icon}</span>`;
                document.getElementById('confirmModalTitle').textContent = title;
                document.getElementById('confirmModalMessage').textContent = message;

                const detailsEl = document.getElementById('confirmModalDetails');
                if (details) {
                    detailsEl.innerHTML = details;
                    detailsEl.classList.remove('hidden');
                } else {
                    detailsEl.classList.add('hidden');
                }

                const actionBtn = document.getElementById('confirmModalActionBtn');
                const actionText = document.getElementById('confirmModalActionText');
                if (actionText) actionText.textContent = confirmText;
                actionBtn.className = isDanger ? 'btn-danger-confirm' : 'btn-primary-confirm';

                const cancelBtn = document.getElementById('confirmModalCancelBtn');
                cancelBtn.textContent = cancelText;

                modal.classList.remove('hidden');

                const cleanup = (result) => {
                    modal.classList.add('hidden');
                    actionBtn.removeEventListener('click', onConfirm);
                    cancelBtn.removeEventListener('click', onCancel);
                    modal.removeEventListener('click', onBackdrop);
                    document.removeEventListener('keydown', onKey);
                    resolve(result);
                };

                const onConfirm = () => cleanup(true);
                const onCancel = () => cleanup(false);
                const onBackdrop = (e) => { if (e.target === modal) cleanup(false); };
                const onKey = (e) => { if (e.key === 'Escape') cleanup(false); };

                actionBtn.addEventListener('click', onConfirm);
                cancelBtn.addEventListener('click', onCancel);
                modal.addEventListener('click', onBackdrop);
                document.addEventListener('keydown', onKey);
            });
        };

        window.confirmDeleteForm = async function(e, message = 'هل أنت متأكد من حذف هذه المعاملة؟ سيتم تحديث الرصيد ومتبقي اليومية فوراً.', title = 'تأكيد حذف المعاملة') {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            const form = e ? (e.currentTarget || e.target).closest('form') || e.target : null;
            const confirmed = await window.showCustomConfirm({
                title: title,
                message: message,
                confirmText: 'نعم، احذف',
                cancelText: 'تراجع',
                icon: '🗑️',
                isDanger: true
            });
            if (confirmed && form) {
                form.submit();
            }
            return false;
        };

        window.confirmResetPin = async function(formId, userName) {
            if (window.showCustomConfirm) {
                const confirmed = await window.showCustomConfirm({
                    title: 'إعادة تعيين رمز PIN',
                    message: `هل أنت متأكد من إعادة تعيين رمز الدخول السريع للمستخدم «${userName}»؟`,
                    details: 'سيتم مسح رمز الـ PIN الحالي لهذا المستخدم، وسيتمكن من تسجيل الدخول بكلمة المرور الخاصة به وتعيين رمز PIN جديد.',
                    confirmText: 'نعم، أعد التعيين',
                    cancelText: 'تراجع',
                    icon: '🔑',
                    isDanger: false
                });
                if (confirmed) {
                    const formEl = document.getElementById(formId);
                    if (formEl) formEl.submit();
                }
            } else {
                if (confirm(`هل أنت متأكد من إعادة تعيين رمز PIN للمستخدم «${userName}»؟`)) {
                    const formEl = document.getElementById(formId);
                    if (formEl) formEl.submit();
                }
            }
        };

        window.confirmLogout = async function(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            const form = e ? (e.currentTarget || e.target).closest('form') : document.getElementById('logoutHeaderForm');

            if (typeof window.showCustomConfirm === 'function') {
                const confirmed = await window.showCustomConfirm({
                    title: 'تسجيل الخروج من النظام',
                    message: 'هل أنت متأكد من رغبتك في تسجيل الخروج الآن؟ سيتم إنهاء جلستك الحالية بأمان والعودة لصفحة الدخول.',
                    confirmText: 'نعم، تسجيل الخروج',
                    cancelText: 'البقاء في النظام',
                    icon: '🚪',
                    isDanger: true
                });
                if (confirmed && form) {
                    form.submit();
                }
            } else {
                if (confirm('هل أنت متأكد من تسجيل الخروج؟')) {
                    if (form) form.submit();
                }
            }
            return false;
        };

        window.confirmActionForm = async function(e, {
            title = 'تأكيد الإجراء',
            message = 'هل أنت متأكد من المتابعة؟',
            confirmText = 'نعم، متابعة',
            cancelText = 'إلغاء',
            icon = '⚠️',
            isDanger = false
        } = {}) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            const form = e ? (e.currentTarget || e.target).closest('form') || e.target : null;
            if (typeof window.showCustomConfirm === 'function') {
                const confirmed = await window.showCustomConfirm({
                    title,
                    message,
                    confirmText,
                    cancelText,
                    icon,
                    isDanger
                });
                if (confirmed && form) {
                    form.submit();
                }
            } else {
                if (confirm(message)) {
                    if (form) form.submit();
                }
            }
            return false;
        };
    </script>

    <!-- Offline-First Client Engine -->
    <script src="{{ asset('js/offline-sync.js') }}"></script>

    @stack('scripts')
</body>
</html>
