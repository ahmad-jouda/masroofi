/**
 * Masroofi Pro - Offline-First Engine (IndexedDB & Background Auto-Sync)
 * Enables seamless offline expense tracking with automatic cloud/server synchronization.
 */

const MasroofiOffline = {
    DB_NAME: 'MasroofiOfflineDB',
    DB_VERSION: 1,
    STORE_NAME: 'pending_transactions',
    db: null,

    // Initialize IndexedDB
    async initDB() {
        if (this.db) return this.db;

        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.DB_NAME, this.DB_VERSION);

            request.onupgradeneeded = (e) => {
                const db = e.target.result;
                if (!db.objectStoreNames.contains(this.STORE_NAME)) {
                    const store = db.createObjectStore(this.STORE_NAME, { keyPath: 'id', autoIncrement: true });
                    store.createIndex('createdAt', 'createdAt', { unique: false });
                }
            };

            request.onsuccess = (e) => {
                this.db = e.target.result;
                resolve(this.db);
            };

            request.onerror = (e) => {
                console.error('Failed to open Masroofi Offline DB:', e);
                reject(e);
            };
        });
    },

    // Add a pending transaction to IndexedDB
    async addTransaction(tx) {
        const db = await this.initDB();
        return new Promise((resolve, reject) => {
            const transaction = db.transaction([this.STORE_NAME], 'readwrite');
            const store = transaction.objectStore(this.STORE_NAME);

            const record = {
                type: tx.type || 'expense',
                amount: parseFloat(tx.amount),
                envelope_id: tx.envelope_id ? parseInt(tx.envelope_id) : null,
                envelope_name: tx.envelope_name || null,
                goal_id: tx.goal_id ? parseInt(tx.goal_id) : null,
                goal_name: tx.goal_name || null,
                date: tx.date || new Date().toISOString().split('T')[0],
                payment_method: tx.payment_method || 'transfer',
                note: tx.note ? tx.note.trim() : '',
                createdAt: new Date().toISOString()
            };

            const request = store.add(record);
            request.onsuccess = (e) => {
                resolve(e.target.result);
            };
            request.onerror = (e) => reject(e);
        });
    },

    // Get all pending transactions
    async getAllPending() {
        const db = await this.initDB();
        return new Promise((resolve, reject) => {
            const transaction = db.transaction([this.STORE_NAME], 'readonly');
            const store = transaction.objectStore(this.STORE_NAME);
            const request = store.getAll();

            request.onsuccess = (e) => resolve(e.target.result || []);
            request.onerror = (e) => reject(e);
        });
    },

    // Delete a pending transaction by ID
    async deleteTransaction(id) {
        const db = await this.initDB();
        return new Promise((resolve, reject) => {
            const transaction = db.transaction([this.STORE_NAME], 'readwrite');
            const store = transaction.objectStore(this.STORE_NAME);
            const request = store.delete(id);

            request.onsuccess = () => resolve(true);
            request.onerror = (e) => reject(e);
        });
    },

    // Clear all pending transactions
    async clearAll() {
        const db = await this.initDB();
        return new Promise((resolve, reject) => {
            const transaction = db.transaction([this.STORE_NAME], 'readwrite');
            const store = transaction.objectStore(this.STORE_NAME);
            const request = store.clear();

            request.onsuccess = () => resolve(true);
            request.onerror = (e) => reject(e);
        });
    },

    // Update UI Indicators
    async updateUI() {
        const pill = document.getElementById('networkStatusPill');
        const text = document.getElementById('networkStatusText');
        const badge = document.getElementById('pendingSyncBadge');
        if (!pill || !text || !badge) return;

        let pending = [];
        try {
            pending = await this.getAllPending();
        } catch (err) {
            console.error('Failed to read offline pending transactions:', err);
        }

        const isOnline = navigator.onLine;
        const pendingCount = pending.length;

        pill.classList.remove('online', 'offline', 'has-pending');

        if (!isOnline) {
            pill.classList.add('offline');
            text.textContent = 'أوفلاين';
            if (pendingCount > 0) {
                pill.classList.add('has-pending');
                badge.style.display = 'inline-flex';
                badge.textContent = `${pendingCount} معلقة`;
            } else {
                badge.style.display = 'none';
            }
        } else {
            pill.classList.add('online');
            text.textContent = 'متصل';
            if (pendingCount > 0) {
                pill.classList.add('has-pending');
                badge.style.display = 'inline-flex';
                badge.textContent = `${pendingCount} معلقة 🔄`;
            } else {
                badge.style.display = 'none';
            }
        }

        // Render pending list in modal if modal is currently open
        this.renderPendingModalList(pending);
    },

    // Sync all pending transactions to Laravel Server
    async syncTransactions(manualTrigger = false) {
        if (!navigator.onLine) {
            if (manualTrigger) {
                this.showToast('⚠️ لا يوجد اتصال بالإنترنت حالياً للمزامنة. ستتم المزامنة تلقائياً فور عودة الاتصال.', 'warning');
            }
            return;
        }

        let pending = [];
        try {
            pending = await this.getAllPending();
        } catch (e) {
            console.error('Error reading pending items:', e);
            return;
        }

        if (pending.length === 0) {
            if (manualTrigger) {
                this.showToast('✨ جميع المعاملات متزامنة مع السيرفر بالفعل!', 'success');
            }
            return;
        }

        const syncBtn = document.getElementById('btnTriggerOfflineSync');
        if (syncBtn) {
            syncBtn.disabled = true;
            syncBtn.innerHTML = '<span>🔄</span> <span>جاري المزامنة...</span>';
        }

        try {
            const payload = {
                transactions: pending.map(item => ({
                    type: item.type,
                    amount: item.amount,
                    envelope_id: item.envelope_id || null,
                    goal_id: item.goal_id || null,
                    date: item.date,
                    payment_method: item.payment_method,
                    note: item.note || null
                }))
            };

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            const response = await fetch('/transactions/sync', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(payload)
            });

            if (!response.ok) {
                throw new Error(`Server responded with HTTP ${response.status}`);
            }

            const data = await response.json();

            if (data.success) {
                // Clear from IndexedDB
                await this.clearAll();
                await this.updateUI();

                if (typeof closeModal === 'function') {
                    closeModal('offlineSyncModal');
                }

                this.showToast(`🎉 تم مزامنة ${data.synced_count} معاملات بنجاح مع السيرفر! جاري تحديث البيانات...`, 'success');

                if (typeof confetti === 'function') {
                    confetti({ particleCount: 60, spread: 70, origin: { y: 0.6 } });
                }

                setTimeout(() => {
                    window.location.reload();
                }, 1200);
            } else {
                throw new Error(data.message || 'فشلت المزامنة');
            }
        } catch (error) {
            console.error('Offline Sync Error:', error);
            if (manualTrigger) {
                this.showToast('❌ تعذر إتمام المزامنة مع السيرفر: ' + (error.message || 'خطأ اتصال'), 'error');
            }
        } finally {
            if (syncBtn) {
                syncBtn.disabled = false;
                syncBtn.innerHTML = '<span>🔄</span> <span>مزامنة العمليات الآن مع السيرفر</span>';
            }
        }
    },

    // Render list inside Offline Sync Modal
    renderPendingModalList(pending) {
        const container = document.getElementById('offlinePendingList');
        const emptyState = document.getElementById('offlinePendingEmpty');
        const countBadge = document.getElementById('offlineModalCountBadge');
        if (!container) return;

        if (countBadge) countBadge.textContent = pending.length;

        if (pending.length === 0) {
            container.innerHTML = '';
            if (emptyState) emptyState.style.display = 'block';
            return;
        }

        if (emptyState) emptyState.style.display = 'none';

        const typeLabels = {
            expense: { label: 'مصروف', icon: '🔴', class: 'type-expense' },
            income: { label: 'دخل', icon: '🟢', class: 'type-income' },
            savings: { label: 'تحويش', icon: '🔵', class: 'type-savings' }
        };

        const methodLabels = {
            transfer: '📱 تطبيق',
            cash: '💵 كاش',
            card: '💳 بطاقة',
            credit: '📑 دين'
        };

        let html = '';
        pending.forEach((item) => {
            const typeInfo = typeLabels[item.type] || typeLabels.expense;
            const payInfo = methodLabels[item.payment_method] || item.payment_method;
            const targetName = item.envelope_name || item.goal_name || (item.type === 'income' ? 'دخل / يومية' : 'مصروف عام');

            html += `
                <div class="offline-item-card" id="offline-item-${item.id}">
                    <div class="offline-item-header">
                        <div class="offline-item-type-badge ${typeInfo.class}">
                            <span>${typeInfo.icon}</span>
                            <span>${typeInfo.label}</span>
                        </div>
                        <div class="offline-item-amount">
                            ${item.amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        </div>
                    </div>
                    <div class="offline-item-details">
                        <div class="offline-item-target">📁 ${targetName}</div>
                        ${item.note ? `<div class="offline-item-note">📝 ${item.note}</div>` : ''}
                    </div>
                    <div class="offline-item-footer">
                        <div class="offline-item-meta">
                            <span>📅 ${item.date}</span>
                            <span>•</span>
                            <span>${payInfo}</span>
                        </div>
                        <button type="button" class="btn-del-offline-item" onclick="MasroofiOffline.removePendingItem(${item.id})" title="حذف هذه المعاملة">
                            🗑️ حذف
                        </button>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    },

    // Remove single pending item
    async removePendingItem(id) {
        let confirmed = false;
        if (typeof window.showCustomConfirm === 'function') {
            confirmed = await window.showCustomConfirm({
                title: 'حذف معاملة أوفلاين معلقة',
                message: 'هل أنت متأكد من حذف هذه المعاملة المسجلة محلياً؟ سيتم التراجع عن خصمها من اليومية.',
                confirmText: 'نعم، احذف',
                cancelText: 'تراجع',
                icon: '🗑️',
                isDanger: true
            });
        } else {
            confirmed = confirm('هل أنت متأكد من حذف هذه المعاملة المعلقة؟');
        }
        if (!confirmed) return;
        await this.deleteTransaction(id);
        const tr = document.getElementById('optimistic-tx-row-' + id);
        if (tr) tr.remove();
        const card = document.getElementById('optimistic-tx-card-' + id);
        if (card) card.remove();
        this.showToast('تم حذف المعاملة من الذاكرة المحلية 🗑️', 'info');
        await this.applyAllPendingOptimistically();
        await this.updateUI();
    },

    // Reusable Toast helper
    showToast(message, type = 'success') {
        const existing = document.getElementById('masroofiOfflineToast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.id = 'masroofiOfflineToast';
        toast.className = `masroofi-offline-toast toast-${type}`;
        toast.innerHTML = `
            <div class="offline-toast-body">
                <span>${type === 'success' ? '✅' : (type === 'warning' ? '⚠️' : (type === 'error' ? '❌' : 'ℹ️'))}</span>
                <span>${message}</span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="offline-toast-close">&times;</button>
        `;

        document.body.appendChild(toast);
        setTimeout(() => {
            if (toast && toast.parentElement) toast.remove();
        }, 4500);
    },

    // -------------------------------------------------------------------------
    // ⚡ Optimistic UI Engine (تنفيذ العمليات فوراً في الواجهة بدون إنترنت)
    // -------------------------------------------------------------------------

    escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>'"]/g, tag => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            "'": '&#39;',
            '"': '&quot;'
        }[tag] || tag));
    },

    resetBaseValues() {
        const appCurrency = document.querySelector('meta[name="app-currency"]')?.content || 'ILS';
        const dailyEl = document.getElementById('safeDailyAmount');
        if (dailyEl && dailyEl.hasAttribute('data-base-val')) {
            const baseRem = parseFloat(dailyEl.getAttribute('data-base-val')) || 0;
            const baseSpent = parseFloat(dailyEl.getAttribute('data-base-spent')) || 0;
            const baseBudget = parseFloat(dailyEl.getAttribute('data-base-budget')) || (baseRem + baseSpent);

            dailyEl.setAttribute('data-current-val', baseRem);
            dailyEl.setAttribute('data-current-spent', baseSpent);
            dailyEl.textContent = Math.round(baseRem).toLocaleString();

            const spentDisp = document.getElementById('todaySpentDisplay');
            if (spentDisp) spentDisp.textContent = `${Math.round(baseSpent).toLocaleString()} ${appCurrency}`;

            const remDisp = document.getElementById('todayRemainingSummaryDisplay');
            if (remDisp) remDisp.textContent = `${Math.round(baseRem).toLocaleString()} ${appCurrency}`;

            const burnFill = document.getElementById('todayBurnFill');
            if (burnFill && baseBudget > 0) {
                const pct = Math.min(100, Math.round((baseSpent / baseBudget) * 100));
                burnFill.style.width = `${pct}%`;
            }
        }

        ['statTotalExpenses', 'statNetRemaining', 'statTotalIncome', 'statTotalSavings'].forEach(id => {
            const el = document.getElementById(id);
            if (el && el.hasAttribute('data-base-val')) {
                const base = parseFloat(el.getAttribute('data-base-val')) || 0;
                el.setAttribute('data-current-val', base);
                el.textContent = Math.round(base).toLocaleString();
            }
        });
    },

    // Prepend and render transaction immediately on the screen
    applyTransactionToUI(tx, isNew = true) {
        const appCurrency = document.querySelector('meta[name="app-currency"]')?.content || 'ILS';
        const amount = parseFloat(tx.amount) || 0;
        if (amount <= 0) return;

        // 1. Deduct immediately from Today's Remaining Spend (متبقي يومية اليوم)
        const dailyEl = document.getElementById('safeDailyAmount');
        if (dailyEl) {
            const currentRem = parseFloat(dailyEl.getAttribute('data-current-val') || dailyEl.getAttribute('data-base-val') || dailyEl.textContent.replace(/,/g, '')) || 0;
            const currentSpent = parseFloat(dailyEl.getAttribute('data-current-spent') || dailyEl.getAttribute('data-base-spent')) || 0;
            const baseBudget = parseFloat(dailyEl.getAttribute('data-base-budget')) || (currentRem + currentSpent);

            let newRemaining = currentRem;
            let newSpent = currentSpent;

            if (tx.type === 'expense') {
                newRemaining = Math.max(0, currentRem - amount);
                newSpent = currentSpent + amount;
            } else if (tx.type === 'income') {
                newRemaining = currentRem + amount;
            }

            dailyEl.setAttribute('data-current-val', newRemaining);
            dailyEl.setAttribute('data-current-spent', newSpent);
            dailyEl.textContent = Math.round(newRemaining).toLocaleString();

            // Update Trio displays
            const spentDisp = document.getElementById('todaySpentDisplay');
            if (spentDisp) spentDisp.textContent = `${Math.round(newSpent).toLocaleString()} ${appCurrency}`;

            const remDisp = document.getElementById('todayRemainingSummaryDisplay');
            if (remDisp) remDisp.textContent = `${Math.round(newRemaining).toLocaleString()} ${appCurrency}`;

            // Update Burn Progress Bar & Message
            const burnFill = document.getElementById('todayBurnFill');
            const burnPctVal = document.getElementById('todayBurnPercentVal');
            const burnStatus = document.getElementById('todayBurnStatusText');
            if (burnFill && baseBudget > 0) {
                const pct = Math.min(100, Math.round((newSpent / baseBudget) * 100));
                burnFill.style.width = `${pct}%`;
                if (burnPctVal) burnPctVal.textContent = `${pct}%`;

                if (newSpent > baseBudget) {
                    const over = Math.round(newSpent - baseBudget);
                    burnFill.className = 'today-burn-fill fill-danger';
                    if (burnStatus) burnStatus.innerHTML = `<span class="badge-overspent">⚠️ تجاوزت يومية اليوم بـ ${over.toLocaleString()} ${appCurrency}</span>`;
                } else {
                    burnFill.className = pct > 85 ? 'today-burn-fill fill-danger' : (pct > 50 ? 'today-burn-fill fill-warning' : 'today-burn-fill fill-primary');
                    if (burnStatus) burnStatus.innerHTML = `<span>استهلكت <strong>${pct}%</strong> من يومية اليوم</span>`;
                }
            }

            // Update modal impact live preview if present
            const liveCurr = document.getElementById('impactLiveCurrent');
            if (liveCurr) liveCurr.textContent = `${Math.round(newRemaining).toLocaleString()} ${appCurrency}`;

            if (isNew) {
                dailyEl.style.transition = 'transform 0.25s ease, color 0.25s ease';
                dailyEl.style.transform = 'scale(1.18)';
                dailyEl.style.color = (tx.type === 'expense') ? '#ef4444' : '#10b981';
                setTimeout(() => {
                    dailyEl.style.transform = '';
                    dailyEl.style.color = '';
                }, 600);
            }
        }

        // 2. Update Overview Stats Cards (إجمالي المصروفات، المتبقي، الدخل)
        const spentEl = document.getElementById('statTotalExpenses');
        if (spentEl && tx.type === 'expense') {
            const curr = parseFloat(spentEl.getAttribute('data-current-val') || spentEl.textContent.replace(/,/g, '')) || 0;
            const updated = curr + amount;
            spentEl.setAttribute('data-current-val', updated);
            spentEl.textContent = Math.round(updated).toLocaleString();
        }

        const remEl = document.getElementById('statNetRemaining');
        if (remEl) {
            const curr = parseFloat(remEl.getAttribute('data-current-val') || remEl.textContent.replace(/,/g, '')) || 0;
            let updated = curr;
            if (tx.type === 'expense') updated -= amount;
            else if (tx.type === 'income') updated += amount;
            else if (tx.type === 'savings') updated -= amount;
            remEl.setAttribute('data-current-val', updated);
            remEl.textContent = Math.round(updated).toLocaleString();
        }

        const incEl = document.getElementById('statTotalIncome');
        if (incEl && tx.type === 'income') {
            const curr = parseFloat(incEl.getAttribute('data-current-val') || incEl.textContent.replace(/,/g, '')) || 0;
            const updated = curr + amount;
            incEl.setAttribute('data-current-val', updated);
            incEl.textContent = Math.round(updated).toLocaleString();
        }

        const savEl = document.getElementById('statTotalSavings');
        if (savEl && tx.type === 'savings') {
            const curr = parseFloat(savEl.getAttribute('data-current-val') || savEl.textContent.replace(/,/g, '')) || 0;
            const updated = curr + amount;
            savEl.setAttribute('data-current-val', updated);
            savEl.textContent = Math.round(updated).toLocaleString();
        }

        // 3. Prepend into the Transactions Ledger Table (سجل المعاملات والمصروفات)
        const tbody = document.getElementById('ledgerTableBody');
        if (tbody) {
            // Remove empty placeholder row if present
            const emptyRow = tbody.querySelector('.empty-state-card');
            if (emptyRow) {
                emptyRow.closest('tr')?.remove();
            }

            // Avoid duplicate row if already exists
            const existingRow = tx.id ? document.getElementById(`optimistic-tx-row-${tx.id}`) : null;
            if (!existingRow) {
                const tr = document.createElement('tr');
                if (tx.id) tr.id = `optimistic-tx-row-${tx.id}`;
                tr.className = 'optimistic-tx-row';

                const typeBadges = {
                    expense: '<span style="color:#ef4444; font-weight:700">🔴 مصروف</span>',
                    income: '<span style="color:#10b981; font-weight:700">🟢 دخل إضافي</span>',
                    savings: '<span style="color:#f59e0b; font-weight:700">🔵 تحويل لحصالة</span>'
                };

                const payLabels = {
                    transfer: '📱 تطبيق',
                    cash: '💵 كاش',
                    card: '💳 بطاقة',
                    credit: '📑 دين/آجل'
                };

                const targetName = tx.envelope_name || tx.goal_name || (tx.type === 'income' ? 'دخل / يومية' : 'عام');
                const sign = (tx.type === 'income') ? '+' : ((tx.type === 'savings') ? '🪙' : '-');

                tr.innerHTML = `
                    <td style="font-family: var(--font-numbers); font-weight: 600">${tx.date}</td>
                    <td>
                        ${typeBadges[tx.type] || typeBadges.expense}
                        <span class="badge-offline-pending" title="معاملة مسجلة محلياً بدون إنترنت، ستتم المزامنة تلقائياً عند عودة الاتصال">🔄 معلق</span>
                    </td>
                    <td><strong>📁 ${this.escapeHtml(targetName)}</strong></td>
                    <td>
                        ${this.escapeHtml(tx.note || '—')}
                        <small style="display: block; color: #f59e0b; font-size: 0.72rem; margin-top: 0.15rem;">⚡ مسجل أوفلاين (محلياً)</small>
                    </td>
                    <td style="font-size: 0.85rem">${payLabels[tx.payment_method] || tx.payment_method}</td>
                    <td class="tx-amount-display ${tx.type}">
                        ${sign} ${amount.toFixed(2)} ${appCurrency}
                    </td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 0.35rem;">
                            <button type="button" class="btn-delete-tx" onclick="MasroofiOffline.removePendingItem(${tx.id})" title="حذف من المعاملات المحلية المعلقة">🗑️</button>
                        </div>
                    </td>
                `;

                tbody.insertBefore(tr, tbody.firstChild);

                if (isNew) {
                    tr.style.transform = 'translateY(-8px)';
                    tr.style.opacity = '0';
                    requestAnimationFrame(() => {
                        tr.style.transform = 'translateY(0)';
                        tr.style.opacity = '1';
                    });
                }
            }
        }

        // 4. Prepend into Recent Transactions Widget list (⚡ آخر المعاملات المسجلة) on the Home tab
        const recentList = document.getElementById('recentTxList');
        if (recentList) {
            const emptyCard = recentList.querySelector('.empty-state-card');
            if (emptyCard) emptyCard.remove();

            const existingCard = tx.id ? document.getElementById(`optimistic-tx-card-${tx.id}`) : null;
            if (!existingCard) {
                const card = document.createElement('div');
                if (tx.id) card.id = `optimistic-tx-card-${tx.id}`;
                card.className = 'tx-item optimistic-tx-card';

                const targetName = tx.envelope_name || tx.goal_name || (tx.type === 'income' ? 'دخل / يومية' : 'عام');
                const sign = (tx.type === 'income') ? '+' : ((tx.type === 'savings') ? '🪙' : '-');

                card.innerHTML = `
                    <div class="tx-item-left">
                        <div class="tx-category-icon">💸</div>
                        <div class="tx-details">
                            <span class="tx-name">
                                ${this.escapeHtml(tx.note || targetName)}
                                <span class="badge-offline-pending" title="معاملة مسجلة محلياً بدون إنترنت">🔄 معلق</span>
                            </span>
                            <span class="tx-meta">📁 ${this.escapeHtml(targetName)} • ${tx.date} • ⚡ مسجل أوفلاين</span>
                        </div>
                    </div>
                    <div class="tx-item-right">
                        <span class="tx-amount-display ${tx.type}">
                            ${sign} ${amount.toFixed(2)} ${appCurrency}
                        </span>
                        <div style="display: flex; align-items: center; gap: 0.35rem;">
                            <button type="button" class="btn-delete-tx" onclick="MasroofiOffline.removePendingItem(${tx.id})" title="حذف من المعاملات المحلية المعلقة">🗑️</button>
                        </div>
                    </div>
                `;

                recentList.insertBefore(card, recentList.firstChild);

                if (isNew) {
                    card.style.transform = 'translateY(-6px)';
                    card.style.opacity = '0';
                    card.style.transition = 'all 0.3s ease';
                    requestAnimationFrame(() => {
                        card.style.transform = 'translateY(0)';
                        card.style.opacity = '1';
                    });
                }
            }
        }

        // 5. Update Envelope spent amount if envelope was selected
        if (tx.envelope_id && tx.type === 'expense') {
            const envCard = document.querySelector(`.envelope-card[data-env-id="${tx.envelope_id}"]`);
            if (envCard) {
                const spentValEl = envCard.querySelector('.env-amount-val');
                if (spentValEl) {
                    const currentSpent = parseFloat(spentValEl.getAttribute('data-current-val') || spentValEl.textContent.replace(/,/g, '')) || 0;
                    const newSpent = currentSpent + amount;
                    spentValEl.setAttribute('data-current-val', newSpent);
                    spentValEl.textContent = `${Math.round(newSpent).toLocaleString()} ${appCurrency}`;
                }
            }
        }
    },

    // Apply all stored offline transactions optimistically on page boot
    async applyAllPendingOptimistically() {
        try {
            // Remove any previously rendered optimistic elements to prevent duplication
            document.querySelectorAll('.optimistic-tx-row, .optimistic-tx-card').forEach(el => el.remove());

            const pending = await this.getAllPending();
            if (!pending || pending.length === 0) return;

            // Reset base values before applying
            this.resetBaseValues();

            // Apply each pending item in chronological order
            pending.forEach(tx => {
                this.applyTransactionToUI(tx, false);
            });
        } catch (e) {
            console.error('Error applying pending transactions to UI:', e);
        }
    },

    // Hook Form Submit
    setupFormInterceptor() {
        const txForm = document.getElementById('txForm');
        if (!txForm) return;

        txForm.addEventListener('submit', async (e) => {
            // Check if user is offline
            if (!navigator.onLine) {
                e.preventDefault();

                const amountInput = txForm.querySelector('input[name="amount"]');
                const amount = parseFloat(amountInput ? amountInput.value : 0);
                if (!amount || amount <= 0) {
                    alert('يرجى إدخال مبلغ صحيح.');
                    return;
                }

                const type = (txForm.querySelector('input[name="type"]:checked')?.value) || 'expense';
                const date = (txForm.querySelector('input[name="date"]')?.value) || new Date().toISOString().split('T')[0];
                const payment_method = (txForm.querySelector('input[name="payment_method"]')?.value) || 'transfer';
                const note = (txForm.querySelector('input[name="note"]')?.value) || '';

                let envelope_id = null;
                let envelope_name = null;
                if (type === 'expense') {
                    const envSelect = document.getElementById('txEnvelopeSelect');
                    if (envSelect && envSelect.value) {
                        envelope_id = envSelect.value;
                        envelope_name = envSelect.options[envSelect.selectedIndex]?.text?.trim() || null;
                    }
                }

                let goal_id = null;
                let goal_name = null;
                if (type === 'savings') {
                    const goalSelect = txForm.querySelector('select[name="goal_id"]');
                    if (goalSelect && goalSelect.value) {
                        goal_id = goalSelect.value;
                        goal_name = goalSelect.options[goalSelect.selectedIndex]?.text?.trim() || null;
                    }
                }

                try {
                    const record = {
                        type,
                        amount,
                        envelope_id,
                        envelope_name,
                        goal_id,
                        goal_name,
                        date,
                        payment_method,
                        note
                    };

                    const txId = await this.addTransaction(record);
                    record.id = txId;

                    // Immediately execute optimistic update to UI!
                    this.applyTransactionToUI(record, true);

                    // Close modal & reset form
                    if (typeof closeModal === 'function') {
                        closeModal('transactionModal');
                    }
                    txForm.reset();

                    // Sound / Confetti
                    if (typeof confetti === 'function') {
                        confetti({ particleCount: 35, spread: 50, origin: { y: 0.7 } });
                    }

                    this.showToast('⚡ تم تسجيل المعاملة وخصمها من اليومية وسجل الحركات فوراً! ستتم المزامنة تلقائياً مع السيرفر فور عودة الاتصال.', 'success');
                    await this.updateUI();
                } catch (err) {
                    console.error('Failed to save transaction offline:', err);
                    alert('تعذر حفظ المعاملة محلياً: ' + err.message);
                }
            }
        });
    },

    // Setup Event Listeners
    init() {
        this.initDB().then(() => {
            this.updateUI();
            this.applyAllPendingOptimistically();
            this.setupFormInterceptor();

            // When network is restored, automatically sync!
            window.addEventListener('online', () => {
                console.log('Network connected. Triggering automatic background sync...');
                this.updateUI();
                this.syncTransactions(false);
            });

            window.addEventListener('offline', () => {
                console.log('Network disconnected. Entering Offline-First mode.');
                this.updateUI();
            });

            // Periodically check every 20s if pending items exist and we are online
            setInterval(() => {
                if (navigator.onLine) {
                    this.getAllPending().then(items => {
                        if (items.length > 0) {
                            this.syncTransactions(false);
                        }
                    });
                }
            }, 20000);
        });
    }
};

// Global helper for opening offline modal
function openOfflineSyncModal() {
    if (typeof openModal === 'function') {
        openModal('offlineSyncModal');
        MasroofiOffline.getAllPending().then(items => {
            MasroofiOffline.renderPendingModalList(items);
        });
    }
}

// Auto init on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    MasroofiOffline.init();
});
