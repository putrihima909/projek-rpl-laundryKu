<?php
use App\Helpers\Auth;
$isLoggedIn = Auth::check();
$baseUrl = config('app.url', 'http://localhost/rpl/public');
?>
<!-- Floating AI Assistant Button & Modal Widget -->
<style>
.ai-chat-btn {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 1040;
    width: 58px;
    height: 58px;
    border-radius: 50%;
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    color: #ffffff;
    border: none;
    box-shadow: 0 8px 24px rgba(79, 70, 229, 0.35);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.ai-chat-btn:hover {
    transform: scale(1.08) translateY(-2px);
    box-shadow: 0 12px 28px rgba(79, 70, 229, 0.45);
    color: #ffffff;
}
.ai-chat-btn .badge-ai {
    position: absolute;
    top: -2px;
    right: -2px;
    background: #10b981;
    border: 2px solid #ffffff;
    border-radius: 50%;
    width: 14px;
    height: 14px;
}
.ai-chat-panel {
    position: fixed;
    bottom: 92px;
    right: 24px;
    width: 380px;
    max-width: calc(100vw - 48px);
    height: 520px;
    max-height: calc(100vh - 120px);
    background: #ffffff;
    border-radius: 18px;
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.16), 0 0 0 1px rgba(0, 0, 0, 0.05);
    z-index: 1045;
    display: none;
    flex-direction: column;
    overflow: hidden;
    animation: aiSlideUp 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
@keyframes aiSlideUp {
    from { opacity: 0; transform: translateY(20px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
.ai-header {
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    color: #ffffff;
    padding: 14px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.ai-body {
    flex: 1;
    padding: 16px;
    overflow-y: auto;
    background: #f8fafc;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.ai-msg {
    display: flex;
    gap: 8px;
    max-width: 85%;
    font-size: 0.88rem;
    line-height: 1.45;
}
.ai-msg.user {
    align-self: flex-end;
    flex-direction: row-reverse;
}
.ai-msg.assistant {
    align-self: flex-start;
}
.ai-bubble {
    padding: 10px 14px;
    border-radius: 14px;
    word-break: break-word;
}
.ai-msg.user .ai-bubble {
    background: #4f46e5;
    color: #ffffff;
    border-bottom-right-radius: 2px;
}
.ai-msg.assistant .ai-bubble {
    background: #ffffff;
    color: #1e293b;
    border: 1px solid #e2e8f0;
    border-bottom-left-radius: 2px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}
.ai-avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #e0e7ff;
    color: #4338ca;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    flex-shrink: 0;
}
.ai-typing {
    display: none;
    align-items: center;
    gap: 4px;
    padding: 8px 12px;
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    width: fit-content;
    font-size: 0.8rem;
    color: #64748b;
}
.ai-dot {
    width: 6px;
    height: 6px;
    background: #6366f1;
    border-radius: 50%;
    animation: aiBounce 1.4s infinite ease-in-out both;
}
.ai-dot:nth-child(1) { animation-delay: -0.32s; }
.ai-dot:nth-child(2) { animation-delay: -0.16s; }
@keyframes aiBounce {
    0%, 80%, 100% { transform: scale(0); }
    40% { transform: scale(1); }
}
.ai-suggestions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 8px;
}
.ai-chip {
    font-size: 0.75rem;
    padding: 4px 10px;
    background: #eef2ff;
    color: #4f46e5;
    border-radius: 12px;
    cursor: pointer;
    border: 1px solid #c7d2fe;
    transition: all 0.2s;
}
.ai-chip:hover {
    background: #4f46e5;
    color: #ffffff;
}
.ai-footer {
    padding: 12px;
    background: #ffffff;
    border-top: 1px solid #f1f5f9;
}
.ai-input-box {
    display: flex;
    gap: 8px;
    align-items: center;
}
.ai-input {
    flex: 1;
    border: 1px solid #cbd5e1;
    border-radius: 20px;
    padding: 8px 14px;
    font-size: 0.88rem;
    outline: none;
    transition: border-color 0.2s;
}
.ai-input:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}
.ai-send-btn {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #4f46e5;
    color: #ffffff;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background 0.2s;
}
.ai-send-btn:hover {
    background: #4338ca;
}
.ai-send-btn:disabled {
    background: #cbd5e1;
    cursor: not-allowed;
}
.ai-quota-bar {
    font-size: 0.72rem;
    color: #94a3b8;
    display: flex;
    justify-content: space-between;
    margin-top: 6px;
    padding: 0 4px;
}
</style>

<!-- Floating Trigger Button -->
<button type="button" id="aiChatToggleBtn" class="ai-chat-btn" title="Asisten LaundryKu">
    <i class="fa-solid fa-wand-magic-sparkles"></i>
    <span class="badge-ai" title="AI Ready"></span>
</button>

<!-- Chat Widget Panel -->
<div id="aiChatPanel" class="ai-chat-panel">
    <!-- Header -->
    <div class="ai-header">
        <div class="d-flex align-items-center gap-2">
            <div class="ai-avatar" style="background:#ffffff; color:#4f46e5;">
                <i class="fa-solid fa-robot"></i>
            </div>
            <div>
                <div class="fw-bold" style="font-size: 0.92rem; line-height: 1.2;">Asisten LaundryKu</div>
                <div style="font-size: 0.72rem; opacity: 0.85;">
                    <i class="fa-solid fa-circle text-success me-1" style="font-size: 8px;"></i>
                    <?= $isLoggedIn ? 'Online • Laundry Advisor' : 'Online • Silakan Masuk' ?>
                </div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-1">
            <?php if ($isLoggedIn): ?>
            <button type="button" id="aiClearBtn" class="btn btn-sm text-white p-1" title="Hapus Percakapan" style="opacity:0.8;">
                <i class="fa-solid fa-rotate-left"></i>
            </button>
            <?php endif; ?>
            <button type="button" id="aiCloseBtn" class="btn btn-sm text-white p-1 ms-1" title="Tutup Chat" style="opacity:0.8;">
                <i class="fa-solid fa-xmark fs-6"></i>
            </button>
        </div>
    </div>

    <!-- Message Body -->
    <div id="aiChatBody" class="ai-body">
        <?php if ($isLoggedIn): ?>
            <!-- Quick Prompts -->
            <div class="ai-suggestions" id="aiQuickChips">
                <span class="ai-chip" onclick="sendAiChip('Saya siapa?')">👤 Siapa Akun Saya</span>
                <span class="ai-chip" onclick="sendAiChip('Rekomendasi layanan untuk cucian banyak dan ingin selesai besok?')">⚡ Butuh Selesai Besok</span>
                <span class="ai-chip" onclick="sendAiChip('Bagaimana cara merawat pakaian bahan sutra & gaun?')">👗 Bahan Sutra & Gaun</span>
                <span class="ai-chip" onclick="sendAiChip('Bagaimana status pesanan laundry aktif saya?')">📦 Cek Status Pesanan</span>
            </div>

            <!-- Initial Welcome Message -->
            <div class="ai-msg assistant">
                <div class="ai-avatar"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
                <div class="ai-bubble">
                    Halo <?= htmlspecialchars(App\Helpers\Auth::user()['name'] ?? 'Pelanggan') ?>! Saya <strong>Asisten LaundryKu</strong>. Ada yang bisa saya bantu terkait informasi akun Anda, pilihan paket laundry, tips perawatan pakaian, atau status pesanan?
                </div>
            </div>

            <!-- Typing Indicator -->
            <div id="aiTypingIndicator" class="ai-typing">
                <span class="ai-dot"></span>
                <span class="ai-dot"></span>
                <span class="ai-dot"></span>
                <span class="ms-1">AI sedang mengetik...</span>
            </div>
        <?php else: ?>
            <!-- Guest Welcome -->
            <div class="ai-msg assistant">
                <div class="ai-avatar" style="background:#e0e7ff; color:#4f46e5;"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
                <div class="ai-bubble">
                    Halo! Selamat datang di <strong>LaundryKu</strong> 👋<br><br>
                    Saya adalah <strong>Asisten LaundryKu</strong> bertenaga AI. Saya siap membantu Anda memilih paket laundry yang tepat, tips merawat pakaian dan penanganan noda, serta mengecek status pesanan cucian Anda secara langsung.<br><br>
                    Untuk dapat mulai mengobrol dan mengakses riwayat pesanan Anda, silakan <strong>masuk ke akun Anda</strong> terlebih dahulu.
                    <div class="mt-3 d-flex gap-2">
                        <a href="<?= $baseUrl ?>/login" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
                            <i class="fa-solid fa-arrow-right-to-bracket me-1"></i> Masuk Sekarang
                        </a>
                        <a href="<?= $baseUrl ?>/login" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                            Daftar Akun
                        </a>
                    </div>
                </div>
            </div>
            <div class="p-3 border rounded-3 bg-white mt-2 small text-muted">
                <div class="fw-semibold text-dark mb-1"><i class="fa-solid fa-lightbulb text-warning me-1"></i>Fitur Unggulan Asisten AI:</div>
                <ul class="mb-0 ps-3">
                    <li>Rekomendasi paket (Kiloan, Express 1 Hari, Dry Clean)</li>
                    <li>Panduan perawatan bahan sensitif (sutra, gaun, wol)</li>
                    <li>Status live tracking cucian & kurir penjemput</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer Input -->
    <div class="ai-footer">
        <?php if ($isLoggedIn): ?>
            <form id="aiChatForm" onsubmit="handleAiSubmit(event)">
                <div class="ai-input-box">
                    <input type="text" id="aiMessageInput" class="ai-input" placeholder="Tanya rekomendasi paket / tips laundry..." autocomplete="off">
                    <button type="submit" id="aiSendBtn" class="ai-send-btn" title="Kirim Pesan">
                        <i class="fa-solid fa-paper-plane" style="font-size: 13px;"></i>
                    </button>
                </div>
                <div class="ai-quota-bar">
                    <span id="aiQuotaText">Batas kuota: 20 pesan/jam</span>
                    <span class="text-muted" style="font-size:0.7rem;"><i class="fa-solid fa-shield-halved me-1"></i>AI Advisor</span>
                </div>
            </form>
        <?php else: ?>
            <div class="ai-input-box">
                <input type="text" class="ai-input" placeholder="Silakan login untuk mulai mengobrol..." disabled>
                <a href="<?= $baseUrl ?>/login" class="ai-send-btn text-decoration-none" title="Masuk">
                    <i class="fa-solid fa-arrow-right-to-bracket" style="font-size: 13px;"></i>
                </a>
            </div>
            <div class="ai-quota-bar">
                <span class="text-muted"><i class="fa-solid fa-lock me-1"></i>Login diperlukan untuk chat</span>
                <span class="text-muted" style="font-size:0.7rem;"><i class="fa-solid fa-shield-halved me-1"></i>AI Advisor</span>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;
let aiChatLoaded = false;
const baseUrl = '<?= $baseUrl ?>';

function formatAiText(text) {
    if (!text) return '';
    // Basic Markdown parser for AI bubbles: Bold, bullet points, line breaks
    let formatted = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.*?)\*/g, '<em>$1</em>')
        .replace(/^-\s+(.*)$/gm, '• $1')
        .replace(/\n/g, '<br>');
    return formatted;
}

function toggleAiChat() {
    const panel = document.getElementById('aiChatPanel');
    if (!panel) return;
    if (panel.style.display === 'flex') {
        panel.style.display = 'none';
    } else {
        panel.style.display = 'flex';
        const input = document.getElementById('aiMessageInput');
        if (input) input.focus();
        if (isLoggedIn && !aiChatLoaded) {
            loadAiHistory();
        }
    }
}

document.getElementById('aiChatToggleBtn')?.addEventListener('click', toggleAiChat);
document.getElementById('aiCloseBtn')?.addEventListener('click', toggleAiChat);

document.getElementById('aiClearBtn')?.addEventListener('click', async function() {
    if (!confirm('Hapus seluruh riwayat percakapan dengan AI?')) return;
    try {
        const res = await fetch(`${baseUrl}/api/ai/clear`, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const json = await res.json();
        if (json.success) {
            const body = document.getElementById('aiChatBody');
            body.innerHTML = `
                <div class="ai-suggestions" id="aiQuickChips">
                    <span class="ai-chip" onclick="sendAiChip('Saya siapa?')">👤 Siapa Akun Saya</span>
                    <span class="ai-chip" onclick="sendAiChip('Rekomendasi layanan untuk cucian banyak dan ingin selesai besok?')">⚡ Butuh Selesai Besok</span>
                    <span class="ai-chip" onclick="sendAiChip('Bagaimana cara merawat pakaian bahan sutra & gaun?')">👗 Bahan Sutra & Gaun</span>
                    <span class="ai-chip" onclick="sendAiChip('Bagaimana status pesanan laundry aktif saya?')">📦 Cek Status Pesanan</span>
                </div>
                <div class="ai-msg assistant">
                    <div class="ai-avatar"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
                    <div class="ai-bubble">
                        Riwayat percakapan telah dibersihkan. Ada yang bisa saya bantu mengenai layanan laundry atau perawatan pakaian Anda?
                    </div>
                </div>
                <div id="aiTypingIndicator" class="ai-typing">
                    <span class="ai-dot"></span><span class="ai-dot"></span><span class="ai-dot"></span>
                    <span class="ms-1">AI sedang mengetik...</span>
                </div>
            `;
        }
    } catch (e) {
        console.error('Failed to clear chat history', e);
    }
});

async function loadAiHistory() {
    if (!isLoggedIn) return;
    try {
        const res = await fetch(`${baseUrl}/api/ai/history`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const json = await res.json();
        if (json.success && json.messages && json.messages.length > 0) {
            const body = document.getElementById('aiChatBody');
            const typingInd = document.getElementById('aiTypingIndicator');
            
            // Render existing messages
            json.messages.forEach(msg => {
                const div = document.createElement('div');
                div.className = `ai-msg ${msg.sender}`;
                if (msg.sender === 'user') {
                    div.innerHTML = `<div class="ai-bubble">${formatAiText(msg.message)}</div>`;
                } else {
                    div.innerHTML = `
                        <div class="ai-avatar"><i class="fa-solid fa-robot"></i></div>
                        <div class="ai-bubble">${formatAiText(msg.message)}</div>
                    `;
                }
                if (typingInd) {
                    body.insertBefore(div, typingInd);
                } else {
                    body.appendChild(div);
                }
            });
            body.scrollTop = body.scrollHeight;
        }
        if (json.rate_limit) {
            updateQuotaUI(json.rate_limit.remaining, json.rate_limit.limit);
        }
        aiChatLoaded = true;
    } catch (e) {
        console.error('Failed to load history', e);
    }
}

function updateQuotaUI(remaining, limit) {
    const el = document.getElementById('aiQuotaText');
    if (el) {
        el.innerText = `Sisa kuota: ${remaining}/${limit} pesan/jam`;
        if (remaining <= 2) {
            el.className = 'text-danger fw-semibold';
        } else {
            el.className = 'text-muted';
        }
    }
}

function sendAiChip(text) {
    const input = document.getElementById('aiMessageInput');
    if (!input) {
        window.location.href = `${baseUrl}/login`;
        return;
    }
    input.value = text;
    handleAiSubmit(new Event('submit'));
}

async function handleAiSubmit(e) {
    e.preventDefault();
    const input = document.getElementById('aiMessageInput');
    if (!input) return;
    const msg = input.value.trim();
    if (!msg) return;

    input.value = '';
    const body = document.getElementById('aiChatBody');
    const typing = document.getElementById('aiTypingIndicator');
    const sendBtn = document.getElementById('aiSendBtn');

    // Append User Message
    const userDiv = document.createElement('div');
    userDiv.className = 'ai-msg user';
    userDiv.innerHTML = `<div class="ai-bubble">${formatAiText(msg)}</div>`;
    if (typing) {
        body.insertBefore(userDiv, typing);
    } else {
        body.appendChild(userDiv);
    }
    body.scrollTop = body.scrollHeight;

    // Show typing indicator & disable send
    if (typing) typing.style.display = 'flex';
    if (sendBtn) sendBtn.disabled = true;
    body.scrollTop = body.scrollHeight;

    try {
        const res = await fetch(`${baseUrl}/api/ai/chat`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ message: msg })
        });

        const data = await res.json();
        if (typing) typing.style.display = 'none';
        if (sendBtn) sendBtn.disabled = false;

        const replyDiv = document.createElement('div');
        replyDiv.className = 'ai-msg assistant';

        if (res.status === 429) {
            replyDiv.innerHTML = `
                <div class="ai-avatar" style="background:#fee2e2; color:#ef4444;"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div class="ai-bubble text-danger" style="background:#fff1f2; border-color:#fecdd3;">${formatAiText(data.reply || data.message)}</div>
            `;
        } else if (data.ai_disabled) {
            replyDiv.innerHTML = `
                <div class="ai-avatar" style="background:#fef3c7; color:#d97706;"><i class="fa-solid fa-power-off"></i></div>
                <div class="ai-bubble text-warning-emphasis" style="background:#fffbeb; border-color:#fde68a;">${formatAiText(data.reply || data.message)}</div>
            `;
        } else {
            replyDiv.innerHTML = `
                <div class="ai-avatar"><i class="fa-solid fa-robot"></i></div>
                <div class="ai-bubble">${formatAiText(data.reply || 'Maaf, tidak ada respon.')}</div>
            `;
        }
        if (typing) {
            body.insertBefore(replyDiv, typing);
        } else {
            body.appendChild(replyDiv);
        }
        body.scrollTop = body.scrollHeight;

        if (data.rate_limit) {
            updateQuotaUI(data.rate_limit.remaining, data.rate_limit.limit);
        }
    } catch (err) {
        if (typing) typing.style.display = 'none';
        if (sendBtn) sendBtn.disabled = false;
        const errDiv = document.createElement('div');
        errDiv.className = 'ai-msg assistant';
        errDiv.innerHTML = `
            <div class="ai-avatar" style="background:#fee2e2; color:#ef4444;"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div class="ai-bubble text-danger" style="background:#fff1f2; border-color:#fecdd3;">Koneksi terputus. Silakan coba beberapa saat lagi.</div>
        `;
        if (typing) {
            body.insertBefore(errDiv, typing);
        } else {
            body.appendChild(errDiv);
        }
        body.scrollTop = body.scrollHeight;
    }
}
</script>
