# SEQUENCE DIAGRAM: SMARTBOT AI ASSISTANT

Diagram sekuens ini menggambarkan alur pemrosesan pertanyaan pengguna pada modul AI, penerapan guardrail ketat (*Transaction Denial & Anti-Hallucination*), serta adapter LLM multi-model.

```mermaid
sequenceDiagram
    autonumber
    actor User as Pengguna (Pelanggan / Staf)
    participant Widget as UI Chat Widget
    participant AiCtrl as AiChatController
    participant AiSvc as AiAssistantService
    participant DataProv as AiDataProvider
    participant LLM as LlmClient (Gemini / Groq / OpenAI)
    participant DB as Database MySQL

    User->>Widget: Buka widget & ketik pertanyaan
    Widget->>AiCtrl: POST /api/ai/chat (message, csrf_token)
    AiCtrl->>AiSvc: sendMessage(user_id, message)

    AiSvc->>DB: countUserMessagesInLastHour(user_id)
    DB-->>AiSvc: Jumlah pesan jam ini
    alt Pesan >= 20 (Rate Limit Exceeded)
        AiSvc-->>AiCtrl: { success: false, rate_limited: true, reply: "Batas 20 pesan/jam terlampaui..." }
        AiCtrl-->>Widget: Render pesan peringatan rate limit (HTTP 429)
    else Kuota Masih Ada
        AiSvc->>DataProv: buildSystemPrompt(user_id)
        DataProv->>DB: Ambil nama user, role, toko, riwayat order aktif
        DataProv-->>AiSvc: Prompt Lengkap + Guardrail Anti-Transaksi Fiktif

        alt User meminta transaksi fiktif ("Batalkan pesananku")
            Note over AiSvc,LLM: Transaction Denial Guard Terpicu
            AiSvc-->>AiCtrl: Reply: "Saya tidak dapat membatalkan pesanan Anda secara langsung. Silakan klik tombol 'Batalkan Pesanan' pada halaman detail pesanan Anda."
        else Pertanyaan umum / perawatan pakaian / status
            AiSvc->>LLM: generateResponse(prompt, history, message)
            LLM-->>AiSvc: Teks balasan cerdas
            AiSvc->>DB: INSERT INTO ai_chat_messages (user_id, sender='user', message)
            AiSvc->>DB: INSERT INTO ai_chat_messages (user_id, sender='assistant', message)
            AiSvc-->>AiCtrl: { success: true, reply: "...", rate_limit: { remaining: 19, limit: 20 } }
        end
        AiCtrl-->>Widget: Render balasan teks AI di layar
    end
    Widget-->>User: Tampilkan jawaban asisten virtual
```
