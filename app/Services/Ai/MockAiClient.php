<?php

namespace App\Services\Ai;

class MockAiClient implements LlmClientInterface
{
    private bool $simulateFailure = false;

    /**
     * Detect if the user message is asking about the assistant's identity.
     * Returns true for questions like "anda siapa", "kamu siapa", etc.
     */
    private function isAssistantIdentityQuestion(string $text): bool
    {
        $t = mb_strtolower($text);
        $t = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $t);
        $t = trim(preg_replace('/\s+/u', ' ', $t));
        if ($t === '') {
            return false;
        }
        $words = explode(' ', $t);

        // Too long to be a simple identity question
        if (count($words) > 6) {
            return false;
        }
        // Contains first‑person pronouns => it's user identity, not assistant
        if (array_intersect($words, ['saya', 'aku', 'gue', 'gw', 'ane'])) {
            return false;
        }

        $assistantWords = ['kamu', 'anda', 'dirimu', 'namamu', 'lu', 'lo', 'elu', 'engkau'];
        $askWords = ['siapa', 'apa', 'nama', 'namamu'];

        $pattern = '/\b(anda|kamu|dirimu|namamu|lu|lo|elu|engkau)\s+(siapa|apa|nama|namamu)\b|\b(siapa|apa|nama|namamu)\s+(anda|kamu|dirimu|namamu|lu|lo|elu|engkau)\b/i';
        return (bool) preg_match($pattern, $t);
    }
    private ?string $forcedReply = null;

    public function __construct(bool $simulateFailure = false, ?string $forcedReply = null)
    {
        $this->simulateFailure = $simulateFailure;
        $this->forcedReply = $forcedReply;
    }

    public function setSimulateFailure(bool $flag): void
    {
        $this->simulateFailure = $flag;
    }

    public function setForcedReply(?string $reply): void
    {
        $this->forcedReply = $reply;
    }

    public function generateResponse(string $systemPrompt, array $conversationHistory, string $userMessage): array
    {
        if ($this->simulateFailure) {
            return [
                'success' => false,
                'reply' => 'Maaf, sistem asisten AI sedang mengalami gangguan sementara. Silakan coba kembali nanti.',
                'error' => 'Simulated MockAiClient error.'
            ];
        }

        if ($this->forcedReply !== null) {
            return [
                'success' => true,
                'reply' => $this->forcedReply,
                'error' => null
            ];
        }

        $trimmedMessage = trim($userMessage);
        $lower = mb_strtolower($trimmedMessage);

        // Normalisasi teks untuk deteksi maksud (lowercase, hapus tanda baca, rapikan spasi)
        $clean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $lower);
        $clean = preg_replace('/\s+/', ' ', trim($clean));

        // 1. Assistant identity question detection (executed before other guardrails)
        if ($this->isAssistantIdentityQuestion($userMessage)) {
            return [
                'success' => true,
                'reply' => 'Saya Asisten LaundryKu, asisten virtual yang membantu seputar laundry: rekomendasi layanan, tips perawatan pakaian, dan status pesanan.',
                'error' => null
            ];
        }
        // 1. Guardrail: user requests transactional actions (Cancel, Refund, Change Address, etc.)
        $isRefundDataQuery = preg_match('/\b(berapa|jumlah|total|status|cek|lihat|laporan)\s+.*?\b(refund|pengembalian)/i', $clean) ||
                             preg_match('/\b(refund|pengembalian).*?\b(menunggu|selesai|pending|completed|berhasil|berapa|jumlah)/i', $clean);
        if (!$isRefundDataQuery && preg_match('/(batalkan|batalin|cancel|minta refund|ajukan refund|tolong refund|refund uang|minta uang kembali|kembalikan uang|ganti alamat|ubah pesanan|ganti pesanan)/i', $clean)) {
            return [
                'success' => true,
                'reply' => "Sebagai asisten AI, saya tidak memiliki akses untuk mengeksekusi pembatalan atau transaksi refund secara langsung di sistem demi keamanan akun Anda.\n\n" .
                           "💡 **Panduan Tindakan:**\n" .
                           "- **Pembatalan Pesanan**: Silakan buka menu **Riwayat Pesanan** (`/orders`), pilih pesanan terkait, lalu klik tombol **Batalkan Pesanan**.\n" .
                           "- **Pengajuan Refund**: Jika pesanan telah dibatalkan dan memenuhi syarat, Anda dapat melihat rincian pengembalian dana pada halaman detail pesanan atau menu **Refunds**.",
                'error' => null
            ];
        }

        // 2. Guardrail: Inquiries asking for OTHER users' private data ("siapa dia", "siapa user lain", "data si X", dsb.)
        if (preg_match('/(user lain|orang lain|pengguna lain|data orang|akun orang|profil orang|pelanggan lain)/i', $clean) ||
            preg_match('/\b(siapa|data|profil|akun)\s+(dia|mereka|si\s+\w+|user\s+\w+|orang)\b/i', $clean)) {
            return [
                'success' => true,
                'reply' => "Mohon maaf, demi menjaga privasi dan keamanan data pengguna, saya hanya dapat memberikan informasi mengenai profil dan akun Anda sendiri yang sedang login.",
                'error' => null
            ];
        }

        // 3. User Identity & Profile Inquiry (Deteksi kombinasi kata ganti + kata identitas)
        $hasSuffixKu = preg_match('/\b(akun|nama|profil|data|peran|role)ku\b/i', $clean);
        $isFirstPerson = $hasSuffixKu || preg_match('/\b(saya|aku|gue|gw|ane|diriku)\b/i', $clean);
        $isIdentityTerm = $hasSuffixKu || preg_match('/\b(siapa|nama|akun|profil|data|identitas|peran|role|status akun|terdaftar|login)\b/i', $clean);
        $isOrderSpecific = preg_match('/\b(pesanan|order|lacak|paket|tarif|harga|ongkir|refund|batal|cuci)\b/i', $clean);

        if (($isFirstPerson && $isIdentityTerm && !$isOrderSpecific) ||
            preg_match('/\b(siapa saya|siapa aku|siapa gue|saya siapa|aku siapa|gue siapa|siapa gw|gw siapa)\b/i', $clean)) {
            if (preg_match('/\[USER_PROFILE_START\](.*?)\[USER_PROFILE_END\]/s', $systemPrompt, $matches)) {
                $profileText = $matches[1];
                $name = '';
                $role = 'Customer';
                $email = '';
                $phone = 'Belum diisi';
                $address = 'Belum diisi';
                $store = '';

                if (preg_match('/Nama Lengkap:\s*([^\r\n]+)/', $profileText, $m)) {
                    $name = trim($m[1]);
                }
                if (preg_match('/Peran \(Role\):\s*([^\r\n]+)/', $profileText, $m)) {
                    $role = trim($m[1]);
                }
                if (preg_match('/Email:\s*([^\r\n]+)/', $profileText, $m)) {
                    $email = trim($m[1]);
                }
                if (preg_match('/Nomor Telepon:\s*([^\r\n]+)/', $profileText, $m)) {
                    $phone = trim($m[1]);
                }
                if (preg_match('/Alamat Utama:\s*([^\r\n]+)/', $profileText, $m)) {
                    $address = trim($m[1]);
                }
                if (preg_match('/Nama Toko:\s*([^\r\n]+)/', $profileText, $m)) {
                    $store = trim($m[1]);
                }

                // Penyesuaian gaya bahasa:
                // Jika user memakai "aku/gue/gw/kamu" atau kata berakhiran "ku", jawab santai "kamu"
                // Jika user memakai "saya", jawab formal "Anda"
                $isCasual = preg_match('/\b(aku|gue|gw|kamu|lu|lo|elo)\b|\b\w*ku\b/i', $clean);
                $pronounSubject = $isCasual ? 'Kamu' : 'Anda';
                $pronounPossessive = $isCasual ? 'kamu' : 'Anda';

                $reply = "{$pronounSubject} terdaftar sebagai **{$name}**, dengan peran **{$role}** di LaundryKu.\n\n" .
                         "📋 **Informasi Akun {$pronounSubject}:**\n" .
                         "- **Nama**: {$name}\n" .
                         "- **Peran**: {$role}\n" .
                         "- **Email**: {$email}\n";

                if ($phone !== '' && $phone !== 'Belum diisi') {
                    $reply .= "- **Nomor Telepon**: {$phone}\n";
                } else {
                    $reply .= "- **Nomor Telepon**: Belum diisi (Silakan lengkapi di halaman Profil)\n";
                }

                if ($address !== '' && $address !== 'Belum diisi') {
                    $reply .= "- **Alamat Utama**: {$address}\n";
                } else {
                    $reply .= "- **Alamat Utama**: Belum diisi (Silakan lengkapi di halaman Profil)\n";
                }

                if (!empty($store) && $store !== 'Belum terdaftar') {
                    $reply .= "- **Toko Laundry**: {$store}\n";
                }

                $reply .= "\nJika ada data yang ingin diperbarui, {$pronounPossessive} dapat membuka menu Profil Pengguna.";

                return [
                    'success' => true,
                    'reply' => $reply,
                    'error' => null
                ];
            }

            return [
                'success' => true,
                'reply' => "Anda saat ini terhubung di LaundryKu. Namun, data profil Anda belum dapat dimuat dari konteks login saat ini.",
                'error' => null
            ];
        }

        // 4. Guardrail: Out of scope / non-laundry domain refusal
        if (preg_match('/(resep|masak|presiden|politik|coding|saham|cuaca|film|game|anime|sepak bola|php|python|java|c\+\+|javascript|js|html|css|sql|mysql|react|laravel|api|json|github|git|framework|kode|script|debug|error|bug|function|variabel|compile|query)/i', $clean)) {
            return [
                'success' => true,
                'reply' => "Mohon maaf, sebagai Asisten LaundryKu, saya hanya dapat membantu pertanyaan seputar layanan laundry, rekomendasi paket cuci, perawatan pakaian/kain, dan informasi pesanan Anda di platform LaundryKu.",
                'error' => null
            ];
        }

        // 3. PRD Scenario: Banyak baju & ingin selesai besok (Express / Kilat recommendation)
        if (preg_match('/(banyak|besok|cepat|kilat|express|urgent|buru-buru|mendesak|secepatnya)/i', $lower) && 
            preg_match('/(pilih|layanan|rekomendasi|paket|cuci|baju|pakaian|recom)/i', $lower)) {
            return [
                'success' => true,
                'reply' => "Untuk cucian dalam jumlah banyak yang ingin selesai besok, saya sangat menyarankan memilih layanan **Cuci Komplit Express / Kilat (1 Hari)**!\n\n" .
                           "✨ **Keunggulan Layanan Express:**\n" .
                           "- **Durasi Pengerjaan**: Maksimal 24 jam selesai.\n" .
                           "- **Proses Lengkap**: Pencucian higienis, pengeringan mesin optimal, dan setrika uap rapi berstandar premium.\n" .
                           "- **Pickup & Delivery**: Anda dapat memanfaatkan layanan kurir jemput-antar agar pakaian langsung diproses hari ini juga.\n\n" .
                           "Silakan pilih toko terdekat dengan estimasi durasi Express di halaman utama pemesanan.",
                'error' => null
            ];
        }

        // 3a. Deteksi jika fitur data AI dinonaktifkan untuk toko tertentu
        if (preg_match('/STATUS FITUR DATA AI TOKO.*?dinonaktifkan/s', $systemPrompt)) {
            if (preg_match('/(omzet|omset|pendapatan|pesanan|order|penjualan|statistik|laporan|angka)/i', $clean)) {
                return [
                    'success' => true,
                    'reply' => "Mohon maaf, fitur data operasional AI dinonaktifkan untuk toko ini oleh pengelola toko. Silakan periksa dashboard kasir utama Anda.",
                    'error' => null
                ];
            }
        }

        // 3b. Deteksi Pertanyaan Informasi Resmi Toko ([INFO_TOKO_RESMI])
        if (preg_match('/\[INFO_TOKO_RESMI\](.*?)\[INFO_TOKO_RESMI_END\]/s', $systemPrompt, $storeInfoMatches)) {
            $infoBody = trim($storeInfoMatches[1]);
            // (i) Jam operasional
            if (preg_match('/(jam|waktu|buka|tutup|operasional|jam buka|jam operasional)/i', $clean) && !preg_match('/(pesanan|order|selesai|cuci)/i', $clean)) {
                if (preg_match('/-\s*Jam Operasional:\s*(.+)/i', $infoBody, $mJam)) {
                    $val = trim($mJam[1]);
                    if ($val === "informasi itu belum diisi oleh pengelola toko") {
                        return [
                            'success' => true,
                            'reply' => "Untuk informasi jam operasional, informasi itu belum diisi oleh pengelola toko.",
                            'error' => null
                        ];
                    }
                    return [
                        'success' => true,
                        'reply' => "Berdasarkan informasi resmi toko, jam operasional toko adalah: {$val}.",
                        'error' => null
                    ];
                }
            }

            // (ii) Ketentuan Antar Jemput
            if (preg_match('/(antar jemput|jemput|delivery|kurir|ketentuan antar|ongkir antar)/i', $clean) && !preg_match('/(tugas kurir|tugas saya)/i', $clean)) {
                if (preg_match('/-\s*Ketentuan Layanan Antar Jemput:\s*(.+)/i', $infoBody, $mAntar)) {
                    $val = trim($mAntar[1]);
                    if ($val === "informasi itu belum diisi oleh pengelola toko") {
                        return [
                            'success' => true,
                            'reply' => "Mengenai layanan antar jemput, informasi itu belum diisi oleh pengelola toko.",
                            'error' => null
                        ];
                    }
                    return [
                        'success' => true,
                        'reply' => "Berdasarkan informasi resmi toko, ketentuan antar jemput: {$val}.",
                        'error' => null
                    ];
                }
            }

            // (iii) Kebijakan Toko
            if (preg_match('/(kebijakan|syarat|aturan|ketentuan|garansi|hilang|ganti rugi)/i', $clean)) {
                if (preg_match('/-\s*Kebijakan Toko:\s*(.+)/i', $infoBody, $mKebijakan)) {
                    $val = trim($mKebijakan[1]);
                    if ($val === "informasi itu belum diisi oleh pengelola toko") {
                        return [
                            'success' => true,
                            'reply' => "Untuk kebijakan toko, informasi itu belum diisi oleh pengelola toko.",
                            'error' => null
                        ];
                    }
                    return [
                        'success' => true,
                        'reply' => "Kebijakan resmi toko kami: {$val}.",
                        'error' => null
                    ];
                }
            }
        }

        // 3b. Business Metrics & Operational Data Inquiries
        $hasDataOperasional = (bool) preg_match('/\[DATA_OPERASIONAL_START\](.*?)\[DATA_OPERASIONAL_END\]/s', $systemPrompt, $opMatches);
        $opText = $hasDataOperasional ? $opMatches[1] : '';

        $extractOp = function(string $pattern, string $default = '-') use ($opText): string {
            if (preg_match($pattern, $opText, $m)) {
                return trim($m[1]);
            }
            return $default;
        };

        // Deteksi apakah user menanyakan angka operasional platform/toko
        $isOperationalQuery = (bool) (
            preg_match('/\b(omset|omzet|pendapatan|penghasilan)\b/i', $clean) ||
            preg_match('/\b(pesanan|order).*?(hari ini|kemarin|bulan ini|aktif|menunggu|status|total|perlu|siap|selesai|proses)\b/i', $clean) ||
            preg_match('/\b(perlu diproses|perlu di proses|sedang diproses|siap diantar|siap diambil|selesai terkirim)\b/i', $clean) ||
            preg_match('/\b(berapa pesanan hari ini|total pesanan hari ini)\b/i', $clean) ||
            preg_match('/\b(berapa pelanggan|jumlah pelanggan|pelanggan aktif|pelanggan terdaftar|customer aktif|customer terdaftar)\b/i', $clean) ||
            preg_match('/\b(berapa refund|refund pending|status refund)\b/i', $clean) ||
            preg_match('/\b(berapa toko|jumlah toko|toko aktif)\b/i', $clean) ||
            preg_match('/\b(berapa kurir|jumlah kurir|kurir aktif)\b/i', $clean)
        );

        $isPersonalOrderQuery = (bool) preg_match('/(pesanan saya|cucian saya|baju saya|tugas saya|antar dan jemput|lacak pesanan)/i', $clean);

        // Jika user adalah role non-staf (customer atau kurir) dan menanyakan data operasional platform/toko: TOLAK
        if (!$hasDataOperasional && $isOperationalQuery && !$isPersonalOrderQuery) {
            return [
                'success' => true,
                'reply' => "Mohon maaf, akun Anda tidak memiliki akses ke data pesanan atau operasional tersebut. Informasi ini hanya dapat diakses oleh staf berwenang.",
                'error' => null
            ];
        }

        // Jika blok [DATA_OPERASIONAL_START] tersedia, jawab dari blok data operasional
        if ($hasDataOperasional) {
            $cakupanLine = $extractOp('/-\s*Cakupan Data:\s*([^\r\n]+)/', 'Seluruh Platform');
            $isTokoScope = str_contains($cakupanLine, 'Toko:');
            $storeName = $isTokoScope ? trim(str_replace('Toko:', '', $cakupanLine)) : 'Seluruh Platform';

            // Penolakan isolasi toko jika bertanya tentang toko aktif, kurir, user per role, toko lain, atau seluruh platform
            if ($isTokoScope && (
                preg_match('/\b(seluruh platform|platform|semua toko|toko lain)\b/i', $clean) ||
                preg_match('/\b(toko aktif|jumlah toko|berapa toko)\b/i', $clean) ||
                preg_match('/\b(kurir|jumlah kurir|kurir aktif)\b/i', $clean) ||
                preg_match('/\b(user per role|pengguna per role|role user|role pengguna)\b/i', $clean)
            )) {
                return [
                    'success' => true,
                    'reply' => "Mohon maaf, sebagai staf toko, Anda hanya memiliki akses untuk melihat data operasional toko Anda sendiri ({$storeName}).",
                    'error' => null
                ];
            }

            // (0a) Kartu Dashboard: Pesanan Perlu Diproses
            if (preg_match('/(perlu diproses|perlu di proses|perlu dikerjakan|antrean cuci)/i', $clean) ||
                (preg_match('/(sedang diproses|dalam proses|sedang dikerjakan)/i', $clean) && $isTokoScope && !preg_match('/(baju saya|pesanan saya|lacak)/i', $clean))) {
                $needProcessNum = $extractOp('/-\s*Pesanan Perlu Diproses:\s*([0-9]+)\s*Order/', '');
                if ($needProcessNum !== '') {
                    $prefix = $isTokoScope ? "Berdasarkan kartu dashboard toko {$storeName}" : "Berdasarkan data operasional sistem";
                    return [
                        'success' => true,
                        'reply' => "{$prefix}:\n\n🔄 **Pesanan Perlu Diproses:** {$needProcessNum} order.\n\nStatus pesanan yang dihitung mencakup antrean pesanan yang perlu dikerjakan toko (mulai dari pesanan masuk, penjemputan, penimbangan, pembayaran, hingga proses pencucian).",
                        'error' => null
                    ];
                }
            }

            // (0b) Kartu Dashboard: Siap Diambil / Diantar
            if (preg_match('/(siap diambil|siap diantar|siap ambil|siap antar|siap kirim)/i', $clean)) {
                $readyStr = $extractOp('/-\s*Siap Diambil \/ Diantar:\s*([0-9]+\s*Paket[^\r\n]*)/', '');
                if ($readyStr !== '') {
                    $prefix = $isTokoScope ? "Berdasarkan kartu dashboard toko {$storeName}" : "Berdasarkan data operasional sistem";
                    return [
                        'success' => true,
                        'reply' => "{$prefix}:\n\n📦 **Siap Diambil / Diantar:** {$readyStr}.\n\nPesanan ini telah selesai dikerjakan dan siap diserahkan kepada pelanggan atau diantar oleh kurir.",
                        'error' => null
                    ];
                }
            }

            // (0c) Kartu Dashboard: Selesai Terkirim
            if (preg_match('/(selesai terkirim|pesanan selesai|order selesai)/i', $clean) && !preg_match('/(refund|baju saya|pesanan saya|lacak)/i', $clean)) {
                $deliveredNum = $extractOp('/-\s*Selesai Terkirim:\s*([0-9]+)\s*Order/', '');
                if ($deliveredNum !== '') {
                    $prefix = $isTokoScope ? "Berdasarkan kartu dashboard toko {$storeName}" : "Berdasarkan data operasional sistem";
                    return [
                        'success' => true,
                        'reply' => "{$prefix}:\n\n✅ **Selesai Terkirim:** {$deliveredNum} order.\n\nSeluruh pesanan ini telah berhasil diserahkan kepada pelanggan dengan status delivered.",
                        'error' => null
                    ];
                }
            }

            // (0d) Kartu Dashboard: OMSET KASIR TERBAYAR / Omset Toko Terbayar
            if (preg_match('/(omset kasir|omzet kasir|omset kasir terbayar|omset toko terbayar|omzet toko terbayar)/i', $clean)) {
                $omsetKasirStr = $extractOp('/-\s*OMSET KASIR TERBAYAR:\s*([^\r\n]+)/', '');
                if ($omsetKasirStr === '') {
                    $omsetKasirStr = $extractOp('/-\s*Omset Toko \(Terbayar\):\s*([^\r\n]+)/', '');
                }
                if ($omsetKasirStr !== '') {
                    $prefix = $isTokoScope ? "Berdasarkan kartu dashboard toko {$storeName}" : "Berdasarkan data operasional sistem";
                    return [
                        'success' => true,
                        'reply' => "{$prefix}:\n\n💰 **OMSET KASIR TERBAYAR:** {$omsetKasirStr}.\n\nTotal ini mengakumulasikan seluruh transaksi yang telah terkonfirmasi lunas.",
                        'error' => null
                    ];
                }
            }

            // (0e) Pesanan Aktif Saat Ini (dibedakan jelas dari Perlu Diproses)
            if (preg_match('/(pesanan aktif|order aktif|aktif saat ini)/i', $clean)) {
                $activeNowNum = $extractOp('/-\s*Pesanan Aktif Saat Ini:\s*([0-9]+)/', '');
                if ($activeNowNum !== '') {
                    $prefix = $isTokoScope ? "Berdasarkan data operasional toko {$storeName}" : "Berdasarkan data operasional platform";
                    return [
                        'success' => true,
                        'reply' => "{$prefix}:\n\n📋 **Pesanan Aktif Saat Ini:** {$activeNowNum} pesanan.\n\n(Definisi: Seluruh pesanan yang belum selesai dan belum dibatalkan, yaitu gabungan dari pesanan perlu diproses dan pesanan siap/diantar).",
                        'error' => null
                    ];
                }
            }

            // (1) Pesanan Hari Ini
            if (!preg_match('/(belum dibayar|belum bayar|sedang diproses|dalam proses|perlu diproses|perlu di proses|status|lacak|pesanan saya|sampai mana)/i', $clean) &&
                (preg_match('/(pesanan|order).*?hari ini|hari ini.*?(pesanan|order)|\b(berapa pesanan hari ini|total pesanan hari ini)\b/i', $clean) || preg_match('/^berapa pesanan(nya)?$/i', $clean))) {
                $ordersTodayNum = $extractOp('/-\s*Pesanan Hari Ini:\s*([0-9]+)/', '0');
                $prefix = $isTokoScope ? "Berdasarkan data operasional toko {$storeName}" : "Berdasarkan data operasional platform";
                return [
                    'success' => true,
                    'reply' => "{$prefix}:\n\n📦 **Jumlah Pesanan Hari Ini:** {$ordersTodayNum} pesanan.\n\nData ini terhubung langsung secara real-time dengan status transaksi di sistem LaundryKu.",
                    'error' => null
                ];
            }

            // (2) Omset / Omzet Hari Ini
            if (preg_match('/(omzet|omset|pendapatan|penghasilan).*?hari ini|hari ini.*?(omzet|omset|pendapatan)|\b(berapa omset hari ini|berapa omzet hari ini)\b/i', $clean)) {
                $omsetTodayStr = $extractOp('/-\s*Omset Hari Ini:\s*(Rp[0-9.,]+)/', 'Rp0');
                $prefix = $isTokoScope ? "Berdasarkan data operasional toko {$storeName}" : "Berdasarkan data operasional platform";
                return [
                    'success' => true,
                    'reply' => "{$prefix}:\n\n💰 **Omzet Terbayar Hari Ini:** {$omsetTodayStr}.\n\nTotal ini hanya mengakumulasikan transaksi yang status pembayarannya telah terkonfirmasi lunas.",
                    'error' => null
                ];
            }

            // (3) Omset / Omzet Bulan Ini
            if (preg_match('/(omzet|omset|pendapatan|penghasilan).*?(bulan ini|sebulan)|bulan ini.*?(omzet|omset|pendapatan)|\b(berapa omset bulan ini|berapa omzet bulan ini)\b/i', $clean)) {
                $omsetMonthStr = $extractOp('/-\s*Omset Bulan Ini:\s*(Rp[0-9.,]+)/', 'Rp0');
                $prefix = $isTokoScope ? "Berdasarkan data operasional toko {$storeName}" : "Berdasarkan data operasional platform";
                return [
                    'success' => true,
                    'reply' => "{$prefix}:\n\n📈 **Omzet Terbayar Bulan Ini:** {$omsetMonthStr}.\n\nAngka ini merupakan akumulasi riil dari semua pesanan lunas pada bulan berjalan.",
                    'error' => null
                ];
            }

            // (3a) Total Omset All-Time / Omset Seluruh LaundryKu / Omset Toko
            if (preg_match('/(omzet|omset|pendapatan|penghasilan).*?(seluruh|total|semua|akumulasi|keseluruhan|toko saya|laundryku)|\b(berapa omset|berapa omzet|total omset|total omzet)\b/i', $clean) && !preg_match('/(kemarin|7 hari|minggu|per metode|qris|tunai|cash)/i', $clean)) {
                $omsetTotalStr = $extractOp('/-\s*Total Omset Terbayar \(All-Time\):\s*(Rp[0-9.,]+)/', 'Rp0');
                $omsetMonthStr = $extractOp('/-\s*Omset Bulan Ini:\s*(Rp[0-9.,]+)/', 'Rp0');
                $prefix = $isTokoScope ? "Berdasarkan data operasional toko {$storeName}" : "Berdasarkan data operasional platform LaundryKu";
                return [
                    'success' => true,
                    'reply' => "{$prefix}:\n\n💰 **Total Omzet Terbayar (All-Time):** {$omsetTotalStr}\n📈 **Omzet Bulan Ini:** {$omsetMonthStr}\n\nTotal ini dihitung dari seluruh pesanan yang status pembayarannya telah terkonfirmasi lunas.",
                    'error' => null
                ];
            }

            // (3b) Omset Toko Tertinggi / Terbesar / Omset Setiap Toko
            if (preg_match('/(omzet|omset).*?(terbesar|tertinggi|paling banyak|paling besar|setiap toko|tiap toko|masing masing)/i', $clean) ||
                preg_match('/(toko|outlet).*?(omzet|omset).*?(terbesar|tertinggi)/i', $clean)) {
                if ($isTokoScope) {
                    return [
                        'success' => true,
                        'reply' => "Mohon maaf, sebagai staf toko Anda hanya memiliki akses ke data operasional toko Anda sendiri ({$storeName}) dan tidak dapat melihat omzet toko lain.",
                        'error' => null
                    ];
                }
                $top3Toko = $extractOp('/-\s*3 Toko Omset Tertinggi Bulan Ini:\s*([^\r\n]+)/', '-');
                if ($top3Toko !== '-' && !empty($top3Toko)) {
                    return [
                        'success' => true,
                        'reply' => "Berdasarkan data operasional platform LaundryKu bulan ini:\n\n🏆 **Toko dengan Omzet Tertinggi:**\n{$top3Toko}\n\nUntuk rincian omzet lengkap setiap outlet, silakan buka menu **Kelola Toko** (`/stores/manage`) pada dashboard.",
                        'error' => null
                    ];
                }
                return [
                    'success' => true,
                    'reply' => "Rincian omzet per toko tidak tercatat pada ringkasan operasional saat ini. Silakan periksa menu **Kelola Toko** pada dashboard.",
                    'error' => null
                ];
            }

            // (3c) Pertanyaan Rincian Metode Pembayaran Spesifik (QRIS, Tunai, Transfer)
            if (preg_match('/\b(qris|tunai|cash|transfer)\b/i', $clean)) {
                return [
                    'success' => true,
                    'reply' => "Mohon maaf, rincian pembayaran per metode spesifik (seperti QRIS atau Tunai) tidak tersedia pada ringkasan operasional asisten AI saat ini. Silakan periksa menu **Pembayaran / Transaksi** pada dashboard utama.",
                    'error' => null
                ];
            }

            // (4) Pelanggan Aktif / Terdaftar & Toko
            if (preg_match('/(pelanggan|customer|toko|outlet).*?(aktif|terdaftar|jumlah|berapa)/i', $clean) && !preg_match('/(pesanan|paket)/i', $clean)) {
                $custAktif = $extractOp('/-\s*(?:Pelanggan Toko Ini dengan Akun Aktif|Pelanggan Platform dengan Akun Aktif):\s*([0-9]+)/', '0');
                $custTerdaftar = $extractOp('/-\s*(?:Total Pelanggan Pernah Memesan di Toko Ini|Total Pelanggan Terdaftar di Platform):\s*([0-9]+)/', '0');
                $tokoAktif = $extractOp('/-\s*Toko:\s*([0-9]+)\s*aktif/', '');
                $prefix = $isTokoScope ? "Berdasarkan data operasional toko {$storeName}" : "Berdasarkan data operasional platform";

                if (preg_match('/(toko|outlet)/i', $clean)) {
                    if ($tokoAktif !== '') {
                        return [
                            'success' => true,
                            'reply' => "{$prefix}:\n\n- **Pelanggan Aktif**: {$custAktif} pelanggan\n- **Toko Laundry Aktif**: {$tokoAktif} toko aktif\n\nData ini dikelola langsung dari database platform LaundryKu.",
                            'error' => null
                        ];
                    }
                    return [
                        'success' => true,
                        'reply' => "{$prefix}:\n\nData jumlah toko aktif tidak tersedia pada blok operasional saat ini. Silakan periksa dashboard utama.",
                        'error' => null
                    ];
                }

                if (preg_match('/(terdaftar)\b/i', $clean) && !preg_match('/(aktif)\b/i', $clean)) {
                    return [
                        'success' => true,
                        'reply' => "{$prefix}:\n\n👥 **Total Pelanggan Terdaftar:** {$custTerdaftar} pelanggan.",
                        'error' => null
                    ];
                }
                return [
                    'success' => true,
                    'reply' => "{$prefix}:\n\n👥 **Pelanggan Aktif:** {$custAktif} pelanggan (dari total {$custTerdaftar} pelanggan terdaftar).",
                    'error' => null
                ];
            }

            // (5) Refund Pending
            if (preg_match('/(refund|pengembalian dana)/i', $clean) && !preg_match('/(minta|ajukan|kembalikan|batalkan|bersih|setelah|sudah selesai)/i', $clean)) {
                $refPending = $extractOp('/-\s*Refund Pending:\s*([^\r\n]+)/', '0 permohonan (Rp0)');
                return [
                    'success' => true,
                    'reply' => "Berikut adalah data aktual pengembalian dana (refund) pada sistem:\n\n" .
                               "- **Refund Pending**: {$refPending}\n\n" .
                               "Proses verifikasi pengembalian dana dilakukan melalui menu kelola Refund sesuai prosedur keamanan.",
                    'error' => null
                ];
            }
        }

        // 3c. Business Metrics & Database Intelligence Inquiries (Legacy Fallback)
        $hasMetricsBlock = (bool) preg_match('/\[BUSINESS_METRICS_START\](.*?)\[BUSINESS_METRICS_END\]/s', $systemPrompt, $bMatches);
        $metricsText = $hasMetricsBlock ? $bMatches[1] : '';

        // Extract metrics helper
        $extractMetric = function(string $pattern, string $default = '-') use ($metricsText): string {
            if (preg_match($pattern, $metricsText, $m)) {
                return trim($m[1]);
            }
            return $default;
        };

        // (a) Pesanan Hari Ini
        if (!preg_match('/(belum dibayar|belum bayar|sedang diproses|dalam proses|perlu diproses|status|lacak|pesanan saya|sampai mana)/i', $clean) &&
            (preg_match('/(pesanan|order).*?hari ini|hari ini.*?(pesanan|order)|\b(berapa pesanan hari ini|total pesanan hari ini)\b/i', $clean) || preg_match('/^berapa pesanan(nya)?$/i', $clean))) {
            $ordersTodayLine = $extractMetric('/-\s*(?:Pesanan Masuk Hari Ini|Pesanan Hari Ini|Total Pesanan Dibuat Hari Ini):\s*([^\r\n]+)/', '0 pesanan');
            return [
                'success' => true,
                'reply' => "Berdasarkan data aktual database hari ini:\n\n" .
                           "📦 **Jumlah Pesanan Hari Ini:** {$ordersTodayLine}.\n\n" .
                           "Data ini terhubung langsung secara real-time dengan status transaksi di sistem LaundryKu.",
                'error' => null
            ];
        }

        // (b) Omzet / Pembayaran Hari Ini
        if (preg_match('/(omzet|omset|pendapatan|penghasilan|pembayaran|dana masuk).*?hari ini|hari ini.*?(omzet|omset|pendapatan|pembayaran|dana masuk)/i', $clean)) {
            $revTodayLine = $extractMetric('/-\s*(?:Omzet Terbayar Hari Ini|Omzet Toko Hari Ini|Pembayaran Diterima Hari Ini):\s*([^\r\n]+)/', 'Rp 0 (0 transaksi)');
            return [
                'success' => true,
                'reply' => "Berdasarkan data aktual transaksi terbayar hari ini:\n\n" .
                           "💰 **Omzet Terbayar Hari Ini:** {$revTodayLine}.\n\n" .
                           "Total ini hanya mengakumulasikan transaksi yang status pembayarannya telah terkonfirmasi lunas.",
                'error' => null
            ];
        }

        // (c) Omzet / Pembayaran Bulan Ini
        if (preg_match('/(omzet|omset|pendapatan|penghasilan|pembayaran|dana masuk).*?(bulan ini|sebulan)|bulan ini.*?(omzet|omset|pendapatan|pembayaran|dana masuk)/i', $clean)) {
            $revMonthLine = $extractMetric('/-\s*(?:Omzet Terbayar Bulan Ini|Omzet Toko Bulan Ini|Pembayaran Diterima Bulan Ini):\s*([^\r\n]+)/', 'Rp 0 (0 transaksi)');
            return [
                'success' => true,
                'reply' => "Berdasarkan data keuangan bulan berjalan saat ini:\n\n" .
                           "📈 **Omzet Terbayar Bulan Ini:** {$revMonthLine}.\n\n" .
                           "Angka ini merupakan akumulasi riil dari semua pesanan lunas pada bulan berjalan.",
                'error' => null
            ];
        }

        // (d) Pesanan Belum Dibayar
        if (preg_match('/(belum dibayar|belum bayar|menunggu pembayaran|unpaid)/i', $clean)) {
            $unpaidLine = $extractMetric('/-\s*Pesanan Belum Dibayar:\s*([^\r\n]+)/', '0 pesanan (Nilai: Rp 0)');
            return [
                'success' => true,
                'reply' => "Berdasarkan catatan sistem pembayaran:\n\n" .
                           "⏳ **Pesanan Belum Dibayar:** {$unpaidLine}.\n\n" .
                           "Pelanggan dapat melakukan pelunasan tagihan melalui gateway QRIS atau metode tunai sesuai ketentuan.",
                'error' => null
            ];
        }

        // (e) Pesanan Sedang Diproses
        if (preg_match('/(sedang diproses|dalam proses|perlu diproses|sedang dicuci|antrian|sedang dikerjakan)/i', $clean) && !preg_match('/(baju saya|pesanan saya|lacak)/i', $clean)) {
            $procLine = $extractMetric('/-\s*(?:Pesanan Sedang Diproses|Pesanan Sedang Dikerjakan|Pesanan Aktif Sedang Diproses):\s*([^\r\n]+)/', '0 pesanan');
            return [
                'success' => true,
                'reply' => "Berdasarkan antrean operasional saat ini:\n\n" .
                           "🔄 **Pesanan Sedang Diproses:** {$procLine}.\n\n" .
                           "Semua pesanan aktif sedang ditangani oleh staf outlet dan kurir penjemputan/pengantaran.",
                'error' => null
            ];
        }

        // (f) Pendapatan Bersih (Net Revenue) - Evaluated before Refund
        if (preg_match('/(pendapatan bersih|omzet bersih|omset bersih|profit bersih|keuntungan bersih|setelah refund)/i', $clean)) {
            $netRevLine = $extractMetric('/-\s*(?:Pendapatan Bersih(?: \(Net Revenue\))?|Pendapatan Bersih Toko):\s*([^\r\n]+)/', 'Rp 0');
            return [
                'success' => true,
                'reply' => "Berdasarkan kalkulasi pendapatan bersih (omzet kotor terbayar dikurangi total refund selesai):\n\n" .
                           "💵 **Pendapatan Bersih:** {$netRevLine}.\n\n" .
                           "Angka ini mencerminkan pendapatan riil yang telah diamankan sistem setelah penyelesaian retur/refund.",
                'error' => null
            ];
        }

        // (g) Refund
        if (preg_match('/(refund|pengembalian dana)/i', $clean) && !preg_match('/(minta|ajukan|kembalikan|batalkan)/i', $clean)) {
            $refPending = $extractMetric('/-\s*(?:Refund Menunggu \/ Diproses|Refund Toko Ini \(Menunggu\)):\s*([^\r\n]+)/', '0 refund (Rp 0)');
            $refCompleted = $extractMetric('/-\s*(?:Refund Selesai|Refund Toko Ini \(Selesai\)):\s*([^\r\n]+)/', '0 refund (Rp 0)');
            return [
                'success' => true,
                'reply' => "Berikut adalah data aktual pengembalian dana (refund) pada sistem:\n\n" .
                           "- **Refund Menunggu / Diproses**: {$refPending}\n" .
                           "- **Refund Selesai**: {$refCompleted}\n\n" .
                           "Proses verifikasi pengembalian dana dilakukan melalui menu kelola Refund sesuai prosedur keamanan.",
                'error' => null
            ];
        }

        // (h) Jumlah Pelanggan dan Toko Aktif
        if (preg_match('/(pelanggan|customer|toko|outlet|mitra).*?(aktif|terdaftar|jumlah|berapa)/i', $clean) && !preg_match('/(pesanan|paket)/i', $clean)) {
            $custLine = $extractMetric('/-\s*Total Pelanggan Aktif:\s*([^\r\n]+)/', 'Data tidak tersedia untuk role Anda');
            $storeLine = $extractMetric('/-\s*Toko Laundry Aktif:\s*([^\r\n]+)/', 'Data tidak tersedia untuk role Anda');
            $courierLine = $extractMetric('/-\s*Kurir Aktif:\s*([^\r\n]+)/', 'Data tidak tersedia untuk role Anda');

            return [
                'success' => true,
                'reply' => "Berikut data pengguna dan ekosistem platform terdaftar:\n\n" .
                           "- **Pelanggan Aktif**: {$custLine}\n" .
                           "- **Toko Laundry Aktif**: {$storeLine}\n" .
                           "- **Kurir Aktif**: {$courierLine}\n\n" .
                           "Data ini dikelola langsung dari database platform LaundryKu.",
                'error' => null
            ];
        }

        // (i) Tugas Kurir
        if (preg_match('/(tugas|pickup|antar|jemput|delivery).*?(kurir|saya|hari ini)/i', $clean) || preg_match('/(tugas saya|tugas kurir)/i', $clean)) {
            $pickupLine = $extractMetric('/-\s*Tugas Penjemputan \(Pickup\) Aktif:\s*([^\r\n]+)/', '0 tugas');
            $delivLine = $extractMetric('/-\s*Tugas Pengantaran \(Delivery\) Aktif:\s*([^\r\n]+)/', '0 tugas');
            $doneTodayLine = $extractMetric('/-\s*Tugas Selesai Hari Ini:\s*([^\r\n]+)/', '0 paket');
            $totalDoneLine = $extractMetric('/-\s*Total Paket Berhasil Diantar:\s*([^\r\n]+)/', '0 paket');

            return [
                'success' => true,
                'reply' => "Berikut rincian tugas operasional kurir Anda:\n\n" .
                           "- 🚚 **Penjemputan (Pickup) Aktif**: {$pickupLine}\n" .
                           "- 📦 **Pengantaran (Delivery) Aktif**: {$delivLine}\n" .
                           "- ✅ **Selesai Hari Ini**: {$doneTodayLine}\n" .
                           "- 🏆 **Total Berhasil Diantar**: {$totalDoneLine}\n\n" .
                           "Pastikan untuk memperbarui status penjemputan dan pengantaran melalui tombol pada dashboard kurir Anda.",
                'error' => null
            ];
        }

        // (j) Ringkasan Umum / Statistik Bisnis
        if (preg_match('/(ringkasan|laporan|statistik|performa|dashboard|rekap).*?(bisnis|toko|hari ini|bulan ini|penjualan)/i', $clean)) {
            $ordersTodayLine = $extractMetric('/-\s*(?:Pesanan Masuk Hari Ini|Pesanan Hari Ini):\s*([^\r\n]+)/', '0 pesanan');
            $revTodayLine = $extractMetric('/-\s*(?:Omzet Terbayar Hari Ini|Omzet Toko Hari Ini):\s*([^\r\n]+)/', 'Rp 0');
            $revMonthLine = $extractMetric('/-\s*(?:Omzet Terbayar Bulan Ini|Omzet Toko Bulan Ini):\s*([^\r\n]+)/', 'Rp 0');
            $procLine = $extractMetric('/-\s*(?:Pesanan Sedang Diproses|Pesanan Sedang Dikerjakan):\s*([^\r\n]+)/', '0 pesanan');

            return [
                'success' => true,
                'reply' => "📊 **Ringkasan Performa & Statistik Bisnis:**\n\n" .
                           "- **Pesanan Hari Ini**: {$ordersTodayLine}\n" .
                           "- **Omzet Hari Ini**: {$revTodayLine}\n" .
                           "- **Omzet Bulan Ini**: {$revMonthLine}\n" .
                           "- **Pesanan Sedang Dikerjakan**: {$procLine}\n\n" .
                           "Semua data di atas tersinkronisasi secara real-time dari database sistem.",
                'error' => null
            ];
        }

        // 4. Status & Order Check Inquiry
        if (preg_match('/(status|lacak|sampai mana|pesanan|order|transaksi|riwayat|proses cucian)/i', $lower)) {
            if (preg_match('/\[ORDER_INFO_START\](.*?)\[ORDER_INFO_END\]/s', $systemPrompt, $matches)) {
                $orderInfo = trim($matches[1]);
                return [
                    'success' => true,
                    'reply' => "Berikut adalah ringkasan status pesanan aktif Anda saat ini:\n\n" .
                               $orderInfo . "\n\n" .
                               "Untuk pembaruan lokasi kurir secara langsung, silakan buka menu **Lacak Pesanan** pada detail pesanan Anda.",
                    'error' => null
                ];
            } else {
                return [
                    'success' => true,
                    'reply' => "Saat me-refresh status: Anda belum memiliki pesanan aktif yang sedang diproses. Anda dapat membuat pesanan baru kapan saja melalui tombol **Buat Pesanan Baru** di dashboard.",
                    'error' => null
                ];
            }
        }

        // 5. Fabric Care & Stain Removal Tips
        if (preg_match('/(sutra|silk|wol|wool|jas|kebaya|gaun|batik)/i', $lower)) {
            return [
                'success' => true,
                'reply' => "Untuk pakaian berbahan sensitif seperti sutra, wol, jas, kebaya, atau gaun, sangat disarankan memilih layanan **Dry Clean**.\n\n" .
                           "💡 **Tips Perawatan:**\n" .
                           "- Hindari perasan mesin cuci putaran tinggi.\n" .
                           "- Gunakan deterjen ber-pH netral dan air bersuhu dingin jika dicuci manual.\n" .
                           "- Layanan Dry Clean di mitra laundry kami menggunakan pelarut khusus yang menjaga serat dan warna kain tetap mewah.",
                'error' => null
            ];
        }

        if (preg_match('/(noda|minyak|darah|tinta|luntur|kotoran|jamur|kunyit)/i', $lower)) {
            return [
                'success' => true,
                'reply' => "Untuk penanganan noda membandel:\n" .
                           "1. **Noda Minyak**: Oleskan sedikit sabun cuci piring lembut pada area noda sebelum dicuci.\n" .
                           "2. **Noda Darah/Tinta**: Gunakan air dingin (jangan air panas karena akan mengunci noda).\n" .
                           "3. Berikan catatan khusus pada pesanan Anda agar petugas laundry dapat melakukan proses *pre-treatment stain removal*.",
                'error' => null
            ];
        }

        // 6. Pricing & Catalog Inquiries
        if (preg_match('/(harga|biaya|tarif|ongkir|bayar|berapa|murah|pricelist|katalog)/i', $lower)) {
            // Check if dynamic catalog block is present
            if (preg_match('/\[KATALOG_LAYANAN_START\](.*?)\[KATALOG_LAYANAN_END\]/s', $systemPrompt, $catMatches)) {
                $catBody = trim($catMatches[1]);
                if (stripos($catBody, 'Data layanan belum tersedia di database') !== false) {
                    return [
                        'success' => true,
                        'reply' => "Data tidak ditemukan di database. Toko ini belum memiliki daftar layanan aktif.",
                        'error' => null
                    ];
                }

                // If specific service inquired
                $lines = explode("\n", $catBody);
                $matchedServices = [];
                foreach ($lines as $line) {
                    $trimmedLine = trim($line);
                    if (str_starts_with($trimmedLine, '- ')) {
                        $matchedServices[] = $trimmedLine;
                    }
                }

                if (!empty($matchedServices)) {
                    // Check if asking for specific service that doesn't exist
                    if (preg_match('/(sepatu|karpet|helm|boneka|gorden|tas)/i', $lower)) {
                        $foundSpecific = false;
                        foreach ($matchedServices as $ms) {
                            if (preg_match('/(sepatu|karpet|helm|boneka|gorden|tas)/i', $ms)) {
                                $foundSpecific = true;
                                break;
                            }
                        }
                        if (!$foundSpecific) {
                            return [
                                'success' => true,
                                'reply' => "Data tidak ditemukan di database untuk layanan tersebut.",
                                'error' => null
                            ];
                        }
                    }

                    $serviceListStr = implode("\n", $matchedServices);
                    return [
                        'success' => true,
                        'reply' => "Berikut adalah daftar layanan dan harga resmi dari database:\n\n" .
                                   $serviceListStr . "\n\n" .
                                   "Tarif di atas merupakan data resmi yang terdaftar langsung di sistem LaundryKu.",
                        'error' => null
                    ];
                }
            }

            return [
                'success' => true,
                'reply' => "Berikut perkiraan katalog dan estimasi tarif layanan laundry di platform kami:\n\n" .
                           "- **Cuci Komplit Reguler**: Rp 6.000 - Rp 8.000/kg (Estimasi 2-3 hari)\n" .
                           "- **Cuci Komplit Express / Kilat**: Rp 10.000 - Rp 15.000/kg (Selesai 24 jam)\n" .
                           "- **Dry Clean Jas / Gaun / Kebaya**: Rp 25.000 - Rp 50.000/pcs\n" .
                           "- **Cuci Bed Cover / Selimut**: Rp 20.000 - Rp 35.000/pcs\n\n" .
                           "Tarif persis dapat bervariasi sesuai toko laundry mitra yang Anda pilih saat checkout.",
                'error' => null
            ];
        }

        // 7. How-To & Application Guidance
        if (preg_match('/(cara|bagaimana|langkah|buka|pesan|order baru|membuat pesanan|buat pesanan)/i', $lower)) {
            return [
                'success' => true,
                'reply' => "Berikut langkah mudah melakukan pemesanan di LaundryKu:\n\n" .
                           "1. Buka halaman utama dan pilih **Toko Laundry** terdekat.\n" .
                           "2. Pilih jenis layanan (Reguler, Express, atau Dry Clean) dan atur estimasi berat/jumlah.\n" .
                           "3. Pilih metode penjemputan (Kurir Jemput-Antar atau Self-Dropoff).\n" .
                           "4. Lakukan konfirmasi dan bayar pesanan secara instan.",
                'error' => null
            ];
        }

        // 8. Affirmations & Conversational Short Inputs
        if (preg_match('/^(ya|yaa|yep|yes|ok|oke|okay|siap|baik|paham|ngerti|mengerti|betul|setuju)[\!\?\.\s]*$/i', $lower)) {
            return [
                'success' => true,
                'reply' => "Baik! Ada yang bisa saya bantu lagi mengenai pilihan layanan laundry, tips perawatan pakaian, atau informasi pesanan Anda?",
                'error' => null
            ];
        }

        // 9. Gratitude
        if (preg_match('/(terima kasih|makasih|makasi|thanks|thx)/i', $lower)) {
            return [
                'success' => true,
                'reply' => "Sama-sama! Senang bisa membantu Anda. Jika ada pertanyaan lain seputar layanan laundry atau pesanan Anda, silakan tanyakan kapan saja!",
                'error' => null
            ];
        }

        // 10. Greetings
        if (preg_match('/(halo|hai|helo|selamat pagi|selamat siang|selamat malam|permisi)/i', $lower)) {
            return [
                'success' => true,
                'reply' => "Halo! Ada yang bisa saya bantu hari ini seputar pilihan layanan laundry, rekomendasi paket cuci, atau cek status pesanan Anda?",
                'error' => null
            ];
        }

        // 10. Out of scope / domain guardrail
        // 10. Out of scope / domain guardrail with coding detection
        if (preg_match('/(php|python|java|c\+\+|javascript|js|html|css|sql|mysql|react|laravel|api|json|github|git|framework|kode|script|debug|error|bug|function|variabel|compile|query|coding)/i', $clean)) {
            return [
                'success' => true,
                'reply' => "Maaf, saya tidak bisa membantu pertanyaan seputar coding atau pemrograman. Saya hanya bisa membantu seputar layanan laundry, perawatan pakaian, harga, estimasi, status pesanan, atau cara menggunakan platform.",
                'error' => null
            ];
        }
        if (preg_match('/(resep|masak|presiden|politik|saham|cuaca|film|game|anime|sepak bola)/i', $clean)) {
            return [
                'success' => true,
                'reply' => "Mohon maaf, sebagai Asisten LaundryKu, saya hanya dapat membantu pertanyaan seputar layanan laundry, rekomendasi paket cuci, perawatan pakaian/kain, dan informasi pesanan Anda di platform LaundryKu.",
                'error' => null
            ];
        }

        // 11. Context-Aware Dynamic Default Fallback (never returns static welcome greeting)
        $replyFallback = "Mengenai \"{$trimmedMessage}\", saya belum memiliki informasi spesifik tentang hal tersebut saat ini. Namun, saya dapat membantu Anda seputar rekomendasi layanan laundry, tips perawatan pakaian, estimasi harga, cek status pesanan, atau data operasional dan statistik bisnis di platform LaundryKu.";
        return [
            'success' => true,
            'reply' => $replyFallback,
            'error' => null
        ];
    }
}
