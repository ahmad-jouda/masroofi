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

    // Add or update a pending transaction in IndexedDB
    async addTransaction(tx) {
        const db = await this.initDB();
        return new Promise((resolve, reject) => {
            const transaction = db.transaction([this.STORE_NAME], 'readwrite');
            const store = transaction.objectStore(this.STORE_NAME);

            // If it's an update, check if an existing pending update or creation for this target exists
            if (tx.action === 'update' && tx.target_id) {
                const getAllReq = store.getAll();
                getAllReq.onsuccess = (e) => {
                    const allItems = e.target.result || [];
                    const existingUpdate = allItems.find(item => item.action === 'update' && item.target_id === tx.target_id);
                    if (existingUpdate) {
                        const updatedRecord = {
                            ...existingUpdate,
                            type: tx.type || 'expense',
                            amount: parseFloat(tx.amount),
                            envelope_id: tx.envelope_id ? parseInt(tx.envelope_id) : null,
                            envelope_name: tx.envelope_name || null,
                            goal_id: tx.goal_id ? parseInt(tx.goal_id) : null,
                            goal_name: tx.goal_name || null,
                            date: tx.date || new Date().toISOString().split('T')[0],
                            payment_method: tx.payment_method || 'transfer',
                            note: tx.note ? tx.note.trim() : '',
                            updatedAt: new Date().toISOString()
                        };
                        const putReq = store.put(updatedRecord);
                        putReq.onsuccess = () => {
                            MasroofiOffline.requestBackgroundSync();
                            resolve(existingUpdate.id);
                        };
                        putReq.onerror = (err) => reject(err);
                        return;
                    }

                    // Also check if this target_id is actually an existing offline pending creation
                    const existingPendingCreate = allItems.find(item => (item.action || 'create') === 'create' && (item.id === tx.target_id || item.temp_id === tx.target_id));
                    if (existingPendingCreate) {
                        const updatedCreate = {
                            ...existingPendingCreate,
                            type: tx.type || 'expense',
                            amount: parseFloat(tx.amount),
                            envelope_id: tx.envelope_id ? parseInt(tx.envelope_id) : null,
                            envelope_name: tx.envelope_name || null,
                            goal_id: tx.goal_id ? parseInt(tx.goal_id) : null,
                            goal_name: tx.goal_name || null,
                            date: tx.date || new Date().toISOString().split('T')[0],
                            payment_method: tx.payment_method || 'transfer',
                            note: tx.note ? tx.note.trim() : '',
                            updatedAt: new Date().toISOString()
                        };
                        const putReq = store.put(updatedCreate);
                        putReq.onsuccess = () => {
                            MasroofiOffline.requestBackgroundSync();
                            resolve(existingPendingCreate.id);
                        };
                        putReq.onerror = (err) => reject(err);
                        return;
                    }

                    const record = {
                        action: 'update',
                        target_id: tx.target_id ? parseInt(tx.target_id) : null,
                        type: tx.type || 'expense',
                        amount: parseFloat(tx.amount),
                        envelope_id: tx.envelope_id ? parseInt(tx.envelope_id) : null,
                        envelope_name: tx.envelope_name || null,
                        goal_id: tx.goal_id ? parseInt(tx.goal_id) : null,
                        goal_name: tx.goal_name || null,
                        date: tx.date || new Date().toISOString().split('T')[0],
                        payment_method: tx.payment_method || 'transfer',
                        note: tx.note ? tx.note.trim() : '',
                        old_amount: tx.old_amount ? parseFloat(tx.old_amount) : null,
                        old_type: tx.old_type || null,
                        createdAt: new Date().toISOString()
                    };

                    const addReq = store.add(record);
                    addReq.onsuccess = (ev) => {
                        MasroofiOffline.requestBackgroundSync();
                        resolve(ev.target.result);
                    };
                    addReq.onerror = (err) => reject(err);
                };
                getAllReq.onerror = (err) => reject(err);
                return;
            }

            const record = {
                action: 'create',
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
                MasroofiOffline.requestBackgroundSync();
                resolve(e.target.result);
            };
            request.onerror = (e) => reject(e);
        });
    },

    // Request Background Sync with Service Worker
    async requestBackgroundSync() {
        if ('serviceWorker' in navigator && 'SyncManager' in window) {
            try {
                const reg = await navigator.serviceWorker.ready;
                await reg.sync.register('sync-pending-transactions');
                console.log('Background sync registered with Service Worker');
            } catch (err) {
                console.warn('Background sync registration failed (fallback active):', err);
            }
        }
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
            const creates = pending.filter(item => (item.action || 'create') === 'create');
            const updates = pending.filter(item => item.action === 'update');

            const payload = {
                transactions: creates.map(item => ({
                    type: item.type,
                    amount: item.amount,
                    envelope_id: item.envelope_id || null,
                    goal_id: item.goal_id || null,
                    date: item.date,
                    payment_method: item.payment_method,
                    note: item.note || null
                })),
                updates: updates.map(item => ({
                    id: item.target_id,
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

                const countMsg = data.synced_count || pending.length;
                this.showToast(`🎉 تم مزامنة ${countMsg} معاملات وتعديلات بنجاح مع السيرفر! جاري تحديث البيانات...`, 'success');

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
            const isUpdate = item.action === 'update';
            const typeInfo = typeLabels[item.type] || typeLabels.expense;
            const payInfo = methodLabels[item.payment_method] || item.payment_method;
            const targetName = item.envelope_name || item.goal_name || (item.type === 'income' ? 'دخل / يومية' : 'مصروف عام');
            const actionBadge = isUpdate
                ? `<span style="font-size: 0.68rem; background: rgba(245, 158, 11, 0.2); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.4); padding: 1px 6px; border-radius: 4px; margin-right: 4px; font-weight: 700;">✏️ تعديل #${item.target_id}</span>`
                : '';

            html += `
                <div class="offline-item-card" id="offline-item-${item.id}">
                    <div class="offline-item-header">
                        <div class="offline-item-type-badge ${typeInfo.class}">
                            <span>${typeInfo.icon}</span>
                            <span>${typeInfo.label}</span>
                            ${actionBadge}
                        </div>
                        <div class="offline-item-amount">
                            ${item.amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        </div>
                    </div>
                    <div class="offline-item-details">
                        <div class="offline-item-target">📁 ${targetName}</div>
                        ${item.note ? `<div class="offline-item-note">📝 ${item.note}</div>` : ''}
                        ${isUpdate && item.old_amount ? `<div style="font-size: 0.72rem; color: #94a3b8;">المبلغ قبل التعديل: ${item.old_amount}</div>` : ''}
                    </div>
                    <div class="offline-item-footer">
                        <div class="offline-item-meta">
                            <span>📅 ${item.date}</span>
                            <span>•</span>
                            <span>${payInfo}</span>
                        </div>
                        <button type="button" class="btn-del-offline-item" onclick="MasroofiOffline.removePendingItem(${item.id})" title="حذف هذا البند المعلق">
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
                if (tx.action === 'update') {
                    this.applyEditToUI(tx, false);
                } else {
                    this.applyTransactionToUI(tx, false);
                }
            });
        } catch (e) {
            console.error('Error applying pending transactions to UI:', e);
        }
    },

    // Hook Form Submit for Instant Optimistic UI (Zero Lag even on slow networks)
    setupFormInterceptor() {
        const txForm = document.getElementById('txForm');
        if (!txForm) return;

        txForm.addEventListener('submit', async (e) => {
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

            // 1. Instant close and UI feedback (0ms perceived latency!)
            if (typeof closeModal === 'function') {
                closeModal('transactionModal');
            }
            txForm.reset();

            const tempId = 'temp_' + Date.now();
            record.id = tempId;

            // Immediately apply optimistic numbers & cards to UI
            this.applyTransactionToUI(record, true);

            if (typeof confetti === 'function') {
                confetti({ particleCount: 35, spread: 50, origin: { y: 0.7 } });
            }

            // 2. Perform background save
            if (navigator.onLine) {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                try {
                    const response = await fetch('/transactions', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            type: record.type,
                            amount: record.amount,
                            envelope_id: record.envelope_id || null,
                            goal_id: record.goal_id || null,
                            date: record.date,
                            payment_method: record.payment_method,
                            note: record.note || null
                        })
                    });

                    if (response.ok) {
                        const data = await response.json();
                        if (data.success && data.transaction) {
                            // Update temporary element IDs & remove pending tags
                            const realId = data.transaction.id;
                            const optimisticCard = document.getElementById(`optimistic-tx-card-${tempId}`);
                            if (optimisticCard) {
                                optimisticCard.id = `optimistic-tx-card-${realId}`;
                                const pending = optimisticCard.querySelector('.badge-offline-pending');
                                if (pending) pending.remove();
                                const meta = optimisticCard.querySelector('.tx-meta');
                                if (meta) meta.textContent = meta.textContent.replace('• ⚡ مسجل أوفلاين', '');
                            }
                            const optimisticRow = document.getElementById(`optimistic-tx-row-${tempId}`);
                            if (optimisticRow) {
                                optimisticRow.id = `optimistic-tx-row-${realId}`;
                                const pending = optimisticRow.querySelector('.badge-offline-pending');
                                if (pending) pending.remove();
                                const small = optimisticRow.querySelector('small');
                                if (small && small.textContent.includes('مسجل أوفلاين')) small.remove();
                            }
                            const msg = data.adjustment_message ? `⚡ تم حفظ المعاملة بنجاح! (${data.adjustment_message})` : '⚡ تم حفظ المعاملة وخصمها فورياً! ✅';
                            this.showToast(msg, 'success');
                            return;
                        }
                    }
                    throw new Error('Server non-OK status');
                } catch (err) {
                    console.warn('Online sync failed, saving offline:', err);
                    const idbId = await this.addTransaction(record);
                    this.showToast('📡 ضعف في الاتصال: تم حفظ المعاملة في جهازك وسيتم مزامنتها مع السيرفر تلقائياً.', 'warning');
                    await this.updateUI();
                }
            } else {
                // Completely offline
                const idbId = await this.addTransaction(record);
                this.showToast('⚡ تم تسجيل المعاملة محلياً وخصمها من اليومية فوراً! ستتم المزامنة تلقائياً عند عودة الاتصال.', 'success');
                await this.updateUI();
            }
        });
    },

    // Apply edited transaction optimistically to UI
    applyEditToUI(record, isNew = true) {
        const appCurrency = document.querySelector('meta[name="app-currency"]')?.content || 'ILS';
        const newAmount = parseFloat(record.amount) || 0;
        const oldAmount = parseFloat(record.old_amount) || newAmount;
        const newType = record.type || 'expense';
        const oldType = record.old_type || newType;

        // 1. Calculate impact on Today's Daily Remaining (if transaction date is today)
        const todayStr = new Date().toISOString().split('T')[0];
        if (record.date === todayStr) {
            const dailyEl = document.getElementById('safeDailyAmount');
            if (dailyEl) {
                const currentRem = parseFloat(dailyEl.getAttribute('data-current-val') || dailyEl.getAttribute('data-base-val') || dailyEl.textContent.replace(/,/g, '')) || 0;
                const currentSpent = parseFloat(dailyEl.getAttribute('data-current-spent') || dailyEl.getAttribute('data-base-spent')) || 0;

                let deltaSpent = 0;
                let deltaIncome = 0;

                if (oldType === 'expense') deltaSpent -= oldAmount;
                if (oldType === 'income') deltaIncome -= oldAmount;

                if (newType === 'expense') deltaSpent += newAmount;
                if (newType === 'income') deltaIncome += newAmount;

                const newSpent = Math.max(0, currentSpent + deltaSpent);
                const newRem = Math.max(0, currentRem - deltaSpent + deltaIncome);

                dailyEl.setAttribute('data-current-val', newRem);
                dailyEl.setAttribute('data-current-spent', newSpent);
                dailyEl.textContent = Math.round(newRem).toLocaleString();

                const spentDisp = document.getElementById('todaySpentDisplay');
                if (spentDisp) spentDisp.textContent = `${Math.round(newSpent).toLocaleString()} ${appCurrency}`;

                const remDisp = document.getElementById('todayRemainingSummaryDisplay');
                if (remDisp) remDisp.textContent = `${Math.round(newRem).toLocaleString()} ${appCurrency}`;
            }
        }

        // 2. Update Ledger Table Row if present
        const row = document.getElementById(`ledger-tx-row-${record.target_id}`) || document.querySelector(`tr[data-tx-id="${record.target_id}"]`);
        if (row) {
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
            const targetName = record.envelope_name || record.goal_name || (record.type === 'income' ? 'دخل / يومية' : 'عام');
            const sign = (record.type === 'income') ? '+' : ((record.type === 'savings') ? '🪙' : '-');

            const cells = row.children;
            if (cells.length >= 7) {
                // Date
                cells[0].textContent = record.date;
                // Type + Pending Tag
                cells[1].innerHTML = `${typeBadges[record.type] || typeBadges.expense} <span class="badge-offline-pending" style="font-size: 0.68rem; background: rgba(245, 158, 11, 0.18); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.35); padding: 1px 5px; border-radius: 4px; margin-right: 4px;">✏️ معدل محلياً</span>`;
                // Category / Envelope
                cells[2].innerHTML = `<strong>📁 ${this.escapeHtml(targetName)}</strong>`;
                // Note
                cells[3].innerHTML = `${this.escapeHtml(record.note || '—')} <small style="display: block; color: #f59e0b; font-size: 0.72rem; margin-top: 0.15rem;">⚡ تعديل أوفلاين</small>`;
                // Payment method
                cells[4].textContent = payLabels[record.payment_method] || record.payment_method;
                // Amount
                cells[5].className = `tx-amount-display ${record.type}`;
                cells[5].textContent = `${sign} ${newAmount.toFixed(2)} ${appCurrency}`;
                // Update edit button with new data
                const editBtn = cells[6].querySelector('.btn-edit-tx');
                if (editBtn) {
                    const updatedObj = {
                        id: record.target_id,
                        type: record.type,
                        amount: record.amount,
                        envelope_id: record.envelope_id,
                        goal_id: record.goal_id,
                        date: record.date,
                        payment_method: record.payment_method,
                        note: record.note
                    };
                    editBtn.setAttribute('onclick', `openEditTxModal(${JSON.stringify(updatedObj)})`);
                }
            }

            if (isNew) {
                row.style.transition = 'background-color 0.5s ease';
                row.style.backgroundColor = 'rgba(245, 158, 11, 0.15)';
                setTimeout(() => {
                    row.style.backgroundColor = '';
                }, 1200);
            }
        }

        // 3. Update Recent Tx Card if present on Home Tab
        const card = document.getElementById(`recent-tx-card-${record.target_id}`) || document.querySelector(`.tx-item[data-tx-id="${record.target_id}"]`);
        if (card) {
            const targetName = record.envelope_name || record.goal_name || (record.type === 'income' ? 'دخل / يومية' : 'عام');
            const sign = (record.type === 'income') ? '+' : ((record.type === 'savings') ? '🪙' : '-');

            const nameEl = card.querySelector('.tx-name');
            if (nameEl) {
                nameEl.innerHTML = `${this.escapeHtml(record.note || targetName)} <span class="badge-offline-pending" style="font-size: 0.68rem; background: rgba(245, 158, 11, 0.18); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.35); padding: 1px 5px; border-radius: 4px; margin-right: 4px;">✏️ معدل</span>`;
            }
            const metaEl = card.querySelector('.tx-meta');
            if (metaEl) {
                metaEl.textContent = `📁 ${targetName} • ${record.date} • ⚡ تعديل محلي`;
            }
            const amtEl = card.querySelector('.tx-amount-display');
            if (amtEl) {
                amtEl.className = `tx-amount-display ${record.type}`;
                amtEl.textContent = `${sign} ${newAmount.toFixed(2)} ${appCurrency}`;
            }
            const editBtn = card.querySelector('.btn-edit-tx');
            if (editBtn) {
                const updatedObj = {
                    id: record.target_id,
                    type: record.type,
                    amount: record.amount,
                    envelope_id: record.envelope_id,
                    goal_id: record.goal_id,
                    date: record.date,
                    payment_method: record.payment_method,
                    note: record.note
                };
                editBtn.setAttribute('onclick', `openEditTxModal(${JSON.stringify(updatedObj)})`);
            }
            if (isNew) {
                card.style.transition = 'background-color 0.5s ease';
                card.style.backgroundColor = 'rgba(245, 158, 11, 0.15)';
                setTimeout(() => {
                    card.style.backgroundColor = '';
                }, 1200);
            }
        }
    },

    // Hook Edit Form Submit for Instant Optimistic Edit & Offline Resilience
    setupEditFormInterceptor() {
        const editForm = document.getElementById('editTxForm');
        if (!editForm) return;

        editForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const txId = editForm.getAttribute('data-tx-id') || (editForm.action ? editForm.action.split('/').filter(Boolean).pop() : null);
            if (!txId) {
                editForm.submit();
                return;
            }

            const amountInput = editForm.querySelector('input[name="amount"]');
            const amount = parseFloat(amountInput ? amountInput.value : 0);
            if (!amount || amount <= 0) {
                alert('يرجى إدخال مبلغ صحيح.');
                return;
            }

            const type = (editForm.querySelector('input[name="type"]:checked')?.value) || 'expense';
            const date = (editForm.querySelector('input[name="date"]')?.value) || new Date().toISOString().split('T')[0];
            const payment_method = (editForm.querySelector('input[name="payment_method"]')?.value) || 'transfer';
            const note = (editForm.querySelector('input[name="note"]')?.value) || '';

            let envelope_id = null;
            let envelope_name = null;
            if (type === 'expense') {
                const envSelect = document.getElementById('editTxEnvelopeSelect');
                if (envSelect && envSelect.value) {
                    envelope_id = envSelect.value;
                    envelope_name = envSelect.options[envSelect.selectedIndex]?.text?.trim() || null;
                }
            }

            let goal_id = null;
            let goal_name = null;
            if (type === 'savings') {
                const goalSelect = document.getElementById('editTxGoalSelect');
                if (goalSelect && goalSelect.value) {
                    goal_id = goalSelect.value;
                    goal_name = goalSelect.options[goalSelect.selectedIndex]?.text?.trim() || null;
                }
            }

            const originalTxStr = editForm.getAttribute('data-original-tx');
            let originalTx = {};
            try {
                if (originalTxStr) originalTx = JSON.parse(originalTxStr);
            } catch (err) {}

            const oldAmount = parseFloat(originalTx.amount) || amount;
            const oldType = originalTx.type || type;

            const record = {
                action: 'update',
                target_id: isNaN(parseInt(txId)) ? txId : parseInt(txId),
                type,
                amount,
                envelope_id,
                envelope_name,
                goal_id,
                goal_name,
                date,
                payment_method,
                note,
                old_amount: oldAmount,
                old_type: oldType
            };

            // 1. Instant close and UI feedback
            if (typeof closeModal === 'function') {
                closeModal('editTransactionModal');
            }

            // 2. Immediately apply updated numbers & visuals to UI
            this.applyEditToUI(record, true);

            // 3. Perform network save or offline queue
            if (navigator.onLine) {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                try {
                    const response = await fetch(editForm.action || `/transactions/${txId}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            _method: 'PUT',
                            type: record.type,
                            amount: record.amount,
                            envelope_id: record.envelope_id || null,
                            goal_id: record.goal_id || null,
                            date: record.date,
                            payment_method: record.payment_method,
                            note: record.note || null
                        })
                    });

                    if (response.ok) {
                        const data = await response.json();
                        if (data.success) {
                            const msg = data.adjustment_message ? `⚡ تم تحديث المعاملة بنجاح! (${data.adjustment_message})` : '⚡ تم حفظ التعديلات وتحديث الرصيد فوراً! ✅';
                            this.showToast(msg, 'success');
                            return;
                        }
                    }
                    throw new Error('Server non-OK status');
                } catch (err) {
                    console.warn('Online edit failed, saving update offline:', err);
                    await this.addTransaction(record);
                    this.showToast('📡 ضعف في الاتصال: تم حفظ التعديل في جهازك وسيتم مزامنته تلقائياً.', 'warning');
                    await this.updateUI();
                }
            } else {
                // Completely offline
                await this.addTransaction(record);
                this.showToast('⚡ تم تعديل المعاملة محلياً وتحديث الرصيد فوراً! ستتم المزامنة مع السيرفر تلقائياً عند عودة الاتصال.', 'success');
                await this.updateUI();
            }
        });
    },

    // Setup Event Listeners
    init() {
        this.initDB().then(() => {
            this.updateUI();
            this.applyAllPendingOptimistically();
            this.setupFormInterceptor();
            this.setupEditFormInterceptor();

            // When network is restored, automatically sync!
            window.addEventListener('online', () => {
                console.log('Network connected. Triggering automatic sync...');
                this.updateUI();
                this.syncTransactions(false);
            });

            window.addEventListener('offline', () => {
                console.log('Network disconnected. Entering Offline-First mode.');
                this.updateUI();
            });

            // Listen to Service Worker messages (e.g., when background sync completes)
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.addEventListener('message', (event) => {
                    if (event.data && event.data.type === 'TRANSACTIONS_SYNCED') {
                        console.log('Background Sync completed by Service Worker:', event.data.count);
                        this.updateUI();
                        this.showToast(`🎉 تم رفع ومزامنة ${event.data.count} معاملات في الخلفية بنجاح!`, 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1200);
                    }
                });

                // Request Periodic Background Sync if available
                navigator.serviceWorker.ready.then(reg => {
                    if ('periodicSync' in reg) {
                        reg.periodicSync.register('sync-pending-transactions', {
                            minInterval: 15 * 60 * 1000 // every 15 minutes
                        }).catch(() => {});
                    }
                }).catch(() => {});
            }

            // Sync and refresh immediately when app is brought to foreground or phone is unlocked (essential for mobile PWAs)
            let lastVisibleTime = Date.now();
            let pageLoadedDate = new Date().toDateString();

            const checkAndRefreshForeground = () => {
                if (document.visibilityState !== 'visible' || !navigator.onLine) return;

                const now = Date.now();
                const isNewDay = new Date().toDateString() !== pageLoadedDate;
                const wasAwayLong = (now - lastVisibleTime) > (2 * 60 * 1000); // 2 minutes away
                lastVisibleTime = now;

                this.getAllPending().then(items => {
                    if (items.length > 0) {
                        this.syncTransactions(false);
                    } else if (isNewDay || wasAwayLong) {
                        // Check if user is actively interacting with an open modal or form input
                        const hasOpenModal = document.querySelector('.modal-backdrop:not(.hidden)');
                        const hasActiveInput = document.activeElement && ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName);
                        if (!hasOpenModal && !hasActiveInput) {
                            window.location.reload();
                        }
                    }
                });
            };

            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    checkAndRefreshForeground();
                } else {
                    lastVisibleTime = Date.now();
                }
            });

            window.addEventListener('focus', () => {
                checkAndRefreshForeground();
            });

            // Request Notification permission softly so system notification appears when synced in background
            if ('Notification' in window && Notification.permission === 'default') {
                setTimeout(() => {
                    Notification.requestPermission().catch(() => {});
                }, 4000);
            }

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
