<!-- 🤖 AI Financial Advisor Interactive Modal & Floating Launcher -->
<div class="modal-backdrop hidden" id="aiAdvisorModal" style="z-index: 99998;">
    <div class="modal-card modal-ai-advisor-card">
        <!-- Advisor Header -->
        <div class="ai-advisor-header">
            <div class="ai-advisor-brand">
                <div class="ai-avatar-ring">
                    <span class="ai-avatar-icon">🤖</span>
                    <span class="ai-avatar-glow"></span>
                </div>
                <div class="ai-brand-text">
                    <div class="ai-title-row">
                        <h3 class="ai-main-title">مستشار مصروفي الذكي</h3>
                        <span class="ai-online-badge">
                            <span class="ai-online-dot"></span>
                            <span>متصل ومطلع على ميزانيتك</span>
                        </span>
                    </div>
                    <p class="ai-subtitle">اسأله بالعامية ليحلل أرقامك الحية ويجيبك بقرار مالي دقيق ومدروس</p>
                </div>
            </div>
            <div class="ai-header-actions">
                <button type="button" class="btn-ai-header-action" onclick="clearAiAdvisorChat()" title="مسح سجل المحادثة">
                    <span>🗑️</span>
                    <span class="action-label">مسح</span>
                </button>
                <button type="button" class="modal-close-btn" onclick="closeModal('aiAdvisorModal')">&times;</button>
            </div>
        </div>

        <!-- Live Financial Context Strip -->
        <div class="ai-live-context-strip">
            <div class="ai-context-pill">
                <span class="pill-icon">💵</span>
                <span class="pill-label">المتاح للصرف:</span>
                <strong class="pill-val" id="aiSnapBalance">-- ₪</strong>
            </div>
            <div class="ai-context-pill">
                <span class="pill-icon">📅</span>
                <span class="pill-label">الأيام المتبقية:</span>
                <strong class="pill-val" id="aiSnapDays">-- يوم</strong>
            </div>
            <div class="ai-context-pill">
                <span class="pill-icon">⚡</span>
                <span class="pill-label">اليومية الآمنة:</span>
                <strong class="pill-val" id="aiSnapDaily">-- ₪/يوم</strong>
            </div>
        </div>

        <!-- Messages Stream -->
        <div class="ai-chat-body" id="aiAdvisorChatBody">
            <!-- Initial Welcome Message -->
            <div class="ai-chat-msg msg-ai" id="aiWelcomeMsg">
                <div class="msg-avatar">🤖</div>
                <div class="msg-bubble">
                    <div class="msg-content">
                        مرحباً بك! أنا <strong>مستشارك المالي الذكي</strong> في «مصروفي» 🤖✨<br><br>
                        أنا مطلع لحظياً على كامل أرقام ميزانيتك، مدخراتك، وديونك القادمة. اسألني باللغة العامية بكل أريحية:<br>
                        <em>«هل ميزانيتي تسمح بشراء شاشة بـ 800 شيكل هذا الأسبوع؟»</em><br>
                        أو <em>«كيف وضعي المالي وهل وتيرة صرفي آمنة؟»</em> لأحلل أرقامك وأعطيك القرار الأفضل لبيتك وأسرتك!
                    </div>
                    <span class="msg-timestamp">الآن</span>
                </div>
            </div>

            <div id="aiDynamicMessages"></div>

            <!-- Typing indicator -->
            <div class="ai-chat-msg msg-ai hidden" id="aiTypingIndicator">
                <div class="msg-avatar">🤖</div>
                <div class="msg-bubble typing-bubble">
                    <div class="typing-dots">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                    <span class="typing-text">المستشار يحلل أرقام ميزانيتك ومدخراتك... 🧠✨</span>
                </div>
            </div>
        </div>

        <!-- Quick Question Chips -->
        <div class="ai-quick-suggestions">
            <span class="suggestions-label">💡 أسئلة سريعة:</span>
            <div class="suggestions-scroll">
                <button type="button" class="suggestion-chip" onclick="askAiQuestion('هل ميزانيتي تسمح بشراء شاشة بـ 800 شيكل هذا الأسبوع؟')">
                    📺 شراء شاشة بـ 800 ₪ هذا الأسبوع؟
                </button>
                <button type="button" class="suggestion-chip" onclick="askAiQuestion('كيف وضعي المالي وهل وتيرة صرفي آمنة؟')">
                    📊 كيف وضعي المالي وهل وتيرة صرفي آمنة؟
                </button>
                <button type="button" class="suggestion-chip" onclick="askAiQuestion('كم أقدر أوفر وأحوش هالشهر؟')">
                    💰 كم أقدر أوفر وأحوش هالشهر؟
                </button>
                <button type="button" class="suggestion-chip" onclick="askAiQuestion('شو تنصحني أسدد من ديوني أولاً؟')">
                    🤝 شو أسدد من ديوني أولاً؟
                </button>
                <button type="button" class="suggestion-chip" onclick="askAiQuestion('وين أكثر مصروفي رايح هالشهر؟')">
                    🛒 وين أكثر مصروفي رايح هالشهر؟
                </button>
            </div>
        </div>

        <!-- Chat Input Form -->
        <form class="ai-chat-footer" id="aiChatForm" onsubmit="submitAiAdvisorChat(event)">
            <div class="ai-input-wrap">
                <textarea 
                    id="aiAdvisorInput" 
                    class="ai-chat-input" 
                    placeholder="اسألني بالعامية: هل ميزانيتي تسمح بشراء شاشة بـ 800 شيكل؟..." 
                    rows="1" 
                    required 
                    onkeydown="handleAiInputKeydown(event)"></textarea>
            </div>
            <button type="submit" class="btn-ai-send" id="aiSendBtn" title="إرسال السؤال">
                <span>إرسال</span>
                <span class="send-icon">🚀</span>
            </button>
        </form>
    </div>
</div>

<!-- Floating Quick Launcher Button (Always visible bottom-left) -->
<button type="button" class="ai-advisor-floating-btn" id="aiAdvisorFloatingTrigger" onclick="openAiAdvisorModal()" title="تحدث مع مستشارك المالي الذكي (AI Financial Advisor)">
    <div class="ai-floating-inner">
        <span class="ai-float-bot">🤖</span>
        <span class="ai-float-text">المستشار الذكي</span>
        <span class="ai-float-pulse"></span>
    </div>
</button>

<script>
    window.aiChatLoaded = false;

    window.openAiAdvisorModal = function(prefillQuestion = null) {
        openModal('aiAdvisorModal');

        // Fetch messages & snapshot if not loaded yet
        if (!window.aiChatLoaded) {
            loadAiAdvisorHistory();
        } else {
            // Refresh snapshot pills
            refreshAiSnapshotPills();
        }

        const input = document.getElementById('aiAdvisorInput');
        if (prefillQuestion && input) {
            input.value = prefillQuestion;
            setTimeout(() => {
                submitAiAdvisorChat();
            }, 200);
        } else if (input) {
            setTimeout(() => input.focus(), 250);
        }
    };

    window.loadAiAdvisorHistory = async function() {
        try {
            const res = await fetch("{{ route('ai-advisor.messages') }}", {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            });
            const data = await res.json();
            if (data.success) {
                window.aiChatLoaded = true;
                if (data.snapshot) {
                    renderAiSnapshotPills(data.snapshot);
                }
                if (data.messages && data.messages.length > 0) {
                    const container = document.getElementById('aiDynamicMessages');
                    container.innerHTML = '';
                    data.messages.forEach(msg => appendAiChatMessage(msg, false));
                    scrollAiChatToBottom();
                }
            }
        } catch(e) {
            console.error('Error loading AI advisor history:', e);
        }
    };

    window.refreshAiSnapshotPills = async function() {
        try {
            const res = await fetch("{{ route('ai-advisor.messages') }}", {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (data.success && data.snapshot) {
                renderAiSnapshotPills(data.snapshot);
            }
        } catch(e) {}
    };

    function renderAiSnapshotPills(snap) {
        const sym = snap.currency_symbol || '₪';
        const balEl = document.getElementById('aiSnapBalance');
        const daysEl = document.getElementById('aiSnapDays');
        const dailyEl = document.getElementById('aiSnapDaily');

        if (balEl) balEl.textContent = `${Number(snap.app_spendable_balance || 0).toLocaleString()} ${sym}`;
        if (daysEl) daysEl.textContent = `${snap.days_remaining || 0} يوم`;
        if (dailyEl) dailyEl.textContent = `${Number(snap.safe_daily || 0).toLocaleString()} ${sym}/يوم`;
    }

    window.handleAiInputKeydown = function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            submitAiAdvisorChat(e);
        }
    };

    window.askAiQuestion = function(text) {
        const input = document.getElementById('aiAdvisorInput');
        if (input) {
            input.value = text;
            submitAiAdvisorChat();
        }
    };

    window.submitAiAdvisorChat = async function(e) {
        if (e && typeof e.preventDefault === 'function') e.preventDefault();

        const input = document.getElementById('aiAdvisorInput');
        const sendBtn = document.getElementById('aiSendBtn');
        const text = input ? input.value.trim() : '';

        if (!text) return;

        // 1. Immediately render User message in chat
        appendAiChatMessage({
            role: 'user',
            message: text,
            created_at: new Date().toISOString()
        }, true);

        input.value = '';
        input.style.height = 'auto';

        // 2. Show Typing Indicator
        const typing = document.getElementById('aiTypingIndicator');
        if (typing) typing.classList.remove('hidden');
        if (sendBtn) sendBtn.disabled = true;
        scrollAiChatToBottom();

        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const res = await fetch("{{ route('ai-advisor.chat') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token
                },
                body: JSON.stringify({ message: text })
            });

            const data = await res.json();
            if (data.success && data.assistant_message) {
                if (data.snapshot) {
                    renderAiSnapshotPills(data.snapshot);
                }
                appendAiChatMessage(data.assistant_message, true);
                playAiAdvisorChime();
            } else {
                appendAiChatMessage({
                    role: 'assistant',
                    message: '⚠️ عذراً، حدث خطأ أثناء تحليل السؤال. يُرجى المحاولة مرة أخرى.',
                    verdict: 'danger'
                }, true);
            }
        } catch(err) {
            console.error('AI chat error:', err);
            appendAiChatMessage({
                role: 'assistant',
                message: '⚠️ تعذر الاتصال بالمستشار المالي حالياً. تأكد من اتصالك بالإنترنت وحاول مجدداً.',
                verdict: 'danger'
            }, true);
        } finally {
            if (typing) typing.classList.add('hidden');
            if (sendBtn) sendBtn.disabled = false;
            scrollAiChatToBottom();
            if (input) input.focus();
        }
    };

    function appendAiChatMessage(msg, animate = false) {
        const container = document.getElementById('aiDynamicMessages');
        if (!container) return;

        const isUser = msg.role === 'user';
        const msgDiv = document.createElement('div');
        msgDiv.className = `ai-chat-msg ${isUser ? 'msg-user' : 'msg-ai'} ${animate ? 'msg-animate-in' : ''}`;

        let html = '';
        if (!isUser) {
            html += `<div class="msg-avatar">🤖</div>`;
        }

        html += `<div class="msg-bubble">`;

        // Render verdict badge if present on assistant response
        if (!isUser && msg.verdict) {
            const verdictMeta = getVerdictMeta(msg.verdict);
            html += `
                <div class="ai-verdict-tag ${verdictMeta.class}">
                    <span class="v-icon">${verdictMeta.icon}</span>
                    <span class="v-label">${verdictMeta.label}</span>
                </div>
            `;
        }

        // Render formatted message text (Markdown-friendly bold/lists)
        const formatted = formatAiMarkdown(msg.message);
        html += `<div class="msg-content">${formatted}</div>`;

        // Render Impact summary card for purchase decisions
        if (!isUser && msg.context_data && msg.context_data.type === 'purchase_verdict') {
            const ctx = msg.context_data;
            const sym = '₪';
            html += `
                <div class="ai-impact-card">
                    <div class="impact-card-title">
                        <span>📊</span>
                        <span>بطاقة الأثر المالي للشراء:</span>
                    </div>
                    <div class="impact-grid">
                        <div class="impact-stat">
                            <span class="stat-lbl">السعر:</span>
                            <strong class="stat-val text-amber">${Number(ctx.amount || 0).toLocaleString()} ${sym}</strong>
                        </div>
                        <div class="impact-stat">
                            <span class="stat-lbl">المتبقي بعد الشراء:</span>
                            <strong class="stat-val ${ctx.balance_after < 0 ? 'text-red' : 'text-emerald'}">${Number(ctx.balance_after || 0).toLocaleString()} ${sym}</strong>
                        </div>
                        <div class="impact-stat">
                            <span class="stat-lbl">اليومية الجديدة:</span>
                            <strong class="stat-val ${ctx.new_daily < (ctx.current_daily * 0.5) ? 'text-amber' : 'text-emerald'}">${Number(ctx.new_daily || 0).toLocaleString()} ${sym}/يوم</strong>
                        </div>
                        <div class="impact-stat">
                            <span class="stat-lbl">الأيام المتبقية:</span>
                            <strong class="stat-val">${ctx.days_remaining} يوماً</strong>
                        </div>
                    </div>
                </div>
            `;
        }

        const timeStr = msg.created_at ? formatMsgTime(msg.created_at) : 'الآن';
        html += `<span class="msg-timestamp">${timeStr}</span>`;
        html += `</div>`;

        msgDiv.innerHTML = html;
        container.appendChild(msgDiv);
    }

    function getVerdictMeta(v) {
        switch(v) {
            case 'safe':
                return { label: 'قرار مالي مسموح وآمن وميزانيتك ممتازة', icon: '🟢', class: 'v-safe' };
            case 'caution':
                return { label: 'قرار مالي ممكن ولكن بحذر وتضحية في اليومية', icon: '🟡', class: 'v-caution' };
            case 'danger':
                return { label: 'غير مستحسن نهائياً — مخاطرة مالية عالية', icon: '🔴', class: 'v-danger' };
            default:
                return { label: 'استشارة وتوجيه مالي ذكي', icon: '💡', class: 'v-info' };
        }
    }

    function formatAiMarkdown(text) {
        if (!text) return '';
        let escaped = text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

        // Bold **text**
        escaped = escaped.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        // Italic *text*
        escaped = escaped.replace(/\*(.*?)\*/g, '<em>$1</em>');
        // Bullet points
        escaped = escaped.replace(/(?:^|\n)-\s+(.*)/g, '<br>• $1');
        // Numbered list
        escaped = escaped.replace(/(?:^|\n)(\d+)\.\s+(.*)/g, '<br><strong>$1.</strong> $2');
        // Newlines
        escaped = escaped.replace(/\n\n/g, '<br><br>').replace(/\n/g, '<br>');

        return escaped;
    }

    function formatMsgTime(dateStr) {
        try {
            const d = new Date(dateStr);
            return d.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });
        } catch(e) {
            return 'الآن';
        }
    }

    function scrollAiChatToBottom() {
        const body = document.getElementById('aiAdvisorChatBody');
        if (body) {
            setTimeout(() => {
                body.scrollTop = body.scrollHeight;
            }, 50);
        }
    }

    window.clearAiAdvisorChat = async function() {
        const confirmed = await (window.showCustomConfirm ? window.showCustomConfirm({
            title: 'مسح محادثة المستشار المالي',
            message: 'هل تريد مسح سجل الأسئلة والإجابات للمستشار المالي الذكي؟',
            confirmText: 'نعم، امسح المحادثة',
            cancelText: 'تراجع',
            icon: '🗑️',
            isDanger: true
        }) : Promise.resolve(confirm('هل تريد مسح المحادثة؟')));

        if (!confirmed) return;

        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const res = await fetch("{{ route('ai-advisor.clear') }}", {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token
                }
            });
            const data = await res.json();
            if (data.success) {
                const dyn = document.getElementById('aiDynamicMessages');
                if (dyn) dyn.innerHTML = '';
            }
        } catch(e) {
            console.error('Clear chat error:', e);
        }
    };

    function playAiAdvisorChime() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            if (ctx.state === 'suspended') ctx.resume();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
            osc.frequency.setValueAtTime(880.00, ctx.currentTime + 0.08); // A5
            gain.gain.setValueAtTime(0.08, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.32);
        } catch(e) {}
    }
</script>
