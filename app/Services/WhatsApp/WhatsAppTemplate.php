<?php

namespace App\Services\WhatsApp;

class WhatsAppTemplate
{
    /**
     * Render message body for a given event and contextual parameters.
     *
     * @param string $eventType
     * @param array $data Contextual data strictly extracted from database
     * @return string
     */
    public static function render(string $eventType, array $data): string
    {
        return match ($eventType) {
            // Customer Events
            'customer_registered'        => self::customerRegistered($data),
            'order_created'              => self::orderCreated($data),
            'order_assigned_to_courier'  => self::orderAssignedToCourier($data),
            'order_courier_on_the_way'   => self::orderCourierOnTheWay($data),
            'order_picked_up'            => self::orderPickedUp($data),
            'order_arrived_at_store'     => self::orderArrivedAtStore($data),
            'order_weighed'              => self::orderWeighed($data),
            'order_waiting_payment'      => self::orderWaitingPayment($data),
            'order_processing'           => self::orderProcessing($data),
            'order_ready'                => self::orderReady($data),
            'order_out_for_delivery'     => self::orderOutForDelivery($data),
            'order_delivered'            => self::orderDelivered($data),
            'order_cancelled'            => self::orderCancelled($data),
            'payment_success'            => self::paymentSuccess($data),
            'payment_failed'             => self::paymentFailed($data),
            'refund_submitted'           => self::refundSubmitted($data),
            'refund_completed'           => self::refundCompleted($data),
            'refund_failed'              => self::refundFailed($data),

            // Store Owner Events
            'store_new_order'            => self::storeNewOrder($data),
            'store_payment_verified'     => self::storePaymentVerified($data),
            'store_order_cancelled'      => self::storeOrderCancelled($data),
            'store_status_changed'       => self::storeStatusChanged($data),
            'store_refund_requested'     => self::storeRefundRequested($data),
            'store_refund_processed'     => self::storeRefundProcessed($data),

            // Courier Events
            'courier_task_assigned'      => self::courierTaskAssigned($data),
            'courier_order_ready'        => self::courierOrderReady($data),
            'courier_task_cancelled'     => self::courierTaskCancelled($data),
            'courier_task_updated'       => self::courierTaskUpdated($data),

            // Platform Owner Events
            'platform_alert'             => self::platformAlert($data),

            default => self::defaultMessage($eventType, $data),
        };
    }

    /**
     * Daftar template pesan cepat siap pakai untuk UI Uji Coba Pengiriman.
     * Menggunakan placeholder: {nama_pelanggan}, {kode_pesanan}, {nama_toko}, {total}, {tanggal}
     *
     * @return array<string, array{label: string, text: string}>
     */
    public static function getQuickTemplates(): array
    {
        return [
            'order_confirmed' => [
                'label' => '1. Pesanan diterima / dikonfirmasi',
                'text'  => "Halo {nama_pelanggan}, pesanan laundry Anda dengan kode {kode_pesanan} telah diterima dan dikonfirmasi oleh {nama_toko}. Estimasi biaya: {total}. Terima kasih telah mempercayakan cucian Anda kepada kami!",
            ],
            'order_processing' => [
                'label' => '2. Cucian sedang diproses',
                'text'  => "Halo {nama_pelanggan}, cucian Anda untuk pesanan {kode_pesanan} sedang dalam proses pencucian & perawatan profesional di {nama_toko}. Kami akan mengabari Anda setelah selesai.",
            ],
            'order_ready' => [
                'label' => '3. Cucian selesai, siap diambil / diantar',
                'text'  => "Halo {nama_pelanggan}, cucian Anda dengan kode {kode_pesanan} di {nama_toko} sudah selesai dan siap diambil / diantar. Terima kasih!",
            ],
            'payment_reminder' => [
                'label' => '4. Pengingat pembayaran',
                'text'  => "Halo {nama_pelanggan}, ini adalah pengingat pembayaran untuk pesanan {kode_pesanan} di {nama_toko} sebesar {total}. Pembayaran dapat dilakukan via kasir atau QRIS resmi. Terima kasih.",
            ],
            'thank_you' => [
                'label' => '5. Ucapan terima kasih',
                'text'  => "Terima kasih {nama_pelanggan} telah menggunakan layanan {nama_toko}! Semoga Anda puas dengan hasil cucian kami. Sampai jumpa di pesanan berikutnya!",
            ],
            'promo_announcement' => [
                'label' => '6. Promo / pengumuman',
                'text'  => "Kabar gembira untuk {nama_pelanggan}! Nikmati promo spesial dan layanan laundry terbaik di {nama_toko} per tanggal {tanggal}. Kunjungi outlet kami atau pesan via aplikasi LaundryKu sekarang!",
            ],
            'manual' => [
                'label' => '7. Tulis manual (kosongkan isi)',
                'text'  => "",
            ],
        ];
    }

    /**
     * Render teks template dengan mengganti placeholder secara aman.
     * Placeholder yang kosong diganti dengan nilai default yang wajar.
     */
    public static function replacePlaceholders(string $text, array $params = []): string
    {
        $defaultName     = 'Pelanggan';
        $defaultOrder    = '-';
        $defaultStore    = 'LaundryKu';
        $defaultTotal    = '-';
        $defaultDate     = date('d/m/Y');

        $replacements = [
            '{nama_pelanggan}' => !empty(trim($params['nama_pelanggan'] ?? '')) ? trim($params['nama_pelanggan']) : $defaultName,
            '{kode_pesanan}'   => !empty(trim($params['kode_pesanan'] ?? '')) ? trim($params['kode_pesanan']) : $defaultOrder,
            '{nama_toko}'      => !empty(trim($params['nama_toko'] ?? '')) ? trim($params['nama_toko']) : $defaultStore,
            '{total}'          => !empty(trim($params['total'] ?? '')) ? trim($params['total']) : $defaultTotal,
            '{tanggal}'        => !empty(trim($params['tanggal'] ?? '')) ? trim($params['tanggal']) : $defaultDate,
        ];

        return strtr($text, $replacements);
    }

    protected static function customerRegistered(array $d): string
    {
        $name = $d['name'] ?? 'Pelanggan';
        $email = $d['email'] ?? '';
        return "Halo {$name},\n\nSelamat datang di LaundryKu! Akun Anda" . ($email ? " ({$email})" : "") . " telah berhasil didaftarkan.\n\nNikmati kemudahan layanan laundry berkualitas dengan penjemputan dan pengantaran langsung ke lokasi Anda.\n\nTerima kasih telah bergabung bersama LaundryKu.";
    }

    protected static function orderCreated(array $d): string
    {
        $name = $d['customer_name'] ?? 'Pelanggan';
        $orderCode = $d['order_code'] ?? '-';
        $store = $d['store_name'] ?? 'Toko Laundry';
        $service = $d['service_name'] ?? 'Layanan Laundry';
        $amount = number_format((float)($d['total_amount'] ?? $d['estimated_amount'] ?? 0), 0, ',', '.');

        return "Halo {$name},\n\nPesanan laundry Anda berhasil dibuat.\n\nKode Pesanan: #{$orderCode}\nToko: {$store}\nLayanan: {$service}\nEstimasi Biaya: Rp {$amount}\nStatus: Menunggu Kurir Penjemputan\n\nTerima kasih telah menggunakan LaundryKu.";
    }

    protected static function orderAssignedToCourier(array $d): string
    {
        $name = $d['customer_name'] ?? 'Pelanggan';
        $orderCode = $d['order_code'] ?? '-';
        $courierName = $d['courier_name'] ?? 'Kurir';
        $courierPhone = $d['courier_phone'] ? " ({$d['courier_phone']})" : "";

        return "Halo {$name},\n\nKurir {$courierName}{$courierPhone} telah ditugaskan untuk menjemput cucian Anda pada pesanan #{$orderCode}.\n\nMohon siapkan pakaian kotor Anda di alamat penjemputan.\n\nTerima kasih.";
    }

    protected static function orderCourierOnTheWay(array $d): string
    {
        $name = $d['customer_name'] ?? 'Pelanggan';
        $orderCode = $d['order_code'] ?? '-';
        $courierName = $d['courier_name'] ?? 'Kurir';

        return "Halo {$name},\n\nKurir {$courierName} sedang dalam perjalanan menuju alamat Anda untuk menjemput pesanan #{$orderCode}.\n\nMohon pastikan nomor telepon Anda dapat dihubungi.\n\nTerima kasih.";
    }

    protected static function orderPickedUp(array $d): string
    {
        $name = $d['customer_name'] ?? 'Pelanggan';
        $orderCode = $d['order_code'] ?? '-';

        return "Halo {$name},\n\nCucian Anda untuk pesanan #{$orderCode} telah berhasil dijemput oleh kurir dan sedang dibawa menuju toko laundry.\n\nTerima kasih.";
    }

    protected static function orderArrivedAtStore(array $d): string
    {
        $name = $d['customer_name'] ?? 'Pelanggan';
        $orderCode = $d['order_code'] ?? '-';
        $store = $d['store_name'] ?? 'Toko Laundry';

        return "Halo {$name},\n\nCucian Anda untuk pesanan #{$orderCode} telah tiba di {$store} dan menunggu proses penimbangan berat aktual.\n\nTerima kasih.";
    }

    protected static function orderWeighed(array $d): string
    {
        $name = $d['customer_name'] ?? 'Pelanggan';
        $orderCode = $d['order_code'] ?? '-';
        $weight = $d['actual_weight'] ?? '0';
        $amount = number_format((float)($d['total_amount'] ?? 0), 0, ',', '.');

        return "Halo {$name},\n\nPenimbangan pesanan #{$orderCode} selesai.\n\nBerat Aktual: {$weight} kg\nTotal Biaya Final: Rp {$amount}\n\nSilakan lakukan pembayaran agar cucian dapat segera diproses.";
    }

    protected static function orderWaitingPayment(array $d): string
    {
        $name = $d['customer_name'] ?? 'Pelanggan';
        $orderCode = $d['order_code'] ?? '-';
        $amount = number_format((float)($d['total_amount'] ?? 0), 0, ',', '.');

        return "Halo {$name},\n\nTagihan pesanan #{$orderCode} sebesar Rp {$amount} telah diterbitkan dan siap dibayar.\n\nSilakan selesaikan pembayaran melalui QRIS atau metode pembayaran yang tersedia di LaundryKu.\n\nTerima kasih.";
    }

    protected static function orderProcessing(array $d): string
    {
        $name = $d['customer_name'] ?? 'Pelanggan';
        $orderCode = $d['order_code'] ?? '-';
        $store = $d['store_name'] ?? 'Toko Laundry';

        return "Halo {$name},\n\nCucian Anda untuk pesanan #{$orderCode} sedang dalam proses pencucian dan perawatan oleh {$store}.\n\nKami memastikan cucian Anda bersih, wangi, dan rapi sesuai standar terbaik.\n\nTerima kasih.";
    }

    protected static function orderReady(array $d): string
    {
        $name = $d['customer_name'] ?? 'Pelanggan';
        $orderCode = $d['order_code'] ?? '-';

        return "Halo {$name},\n\nPesanan #{$orderCode} telah selesai diproses dan siap untuk diambil atau dikirim sesuai metode layanan Anda.\n\nTerima kasih telah menggunakan LaundryKu.";
    }

    protected static function orderOutForDelivery(array $d): string
    {
        $name = $d['customer_name'] ?? 'Pelanggan';
        $orderCode = $d['order_code'] ?? '-';
        $courierName = $d['courier_name'] ?? 'Kurir';

        return "Halo {$name},\n\nCucian bersih Anda untuk pesanan #{$orderCode} sedang dalam perjalanan diantar oleh kurir {$courierName}.\n\nMohon pastikan ada penerima di alamat tujuan.\n\nTerima kasih.";
    }

    protected static function orderDelivered(array $d): string
    {
        $name = $d['customer_name'] ?? 'Pelanggan';
        $orderCode = $d['order_code'] ?? '-';

        return "Halo {$name},\n\nPesanan #{$orderCode} telah berhasil diselesaikan dan diterima.\n\nTerima kasih banyak telah mempercayakan pakaian Anda kepada LaundryKu! Kami menantikan pesanan Anda berikutnya.";
    }

    protected static function orderCancelled(array $d): string
    {
        $name = $d['customer_name'] ?? 'Pelanggan';
        $orderCode = $d['order_code'] ?? '-';
        $notes = $d['notes'] ?? 'Dibatalkan oleh pengguna/sistem';

        return "Halo {$name},\n\nPesanan laundry #{$orderCode} telah dibatalkan.\n\nCatatan: {$notes}\n\nJika ada pertanyaan atau pesanan sudah dibayar, silakan hubungi layanan pelanggan.";
    }

    protected static function paymentSuccess(array $d): string
    {
        $name = $d['customer_name'] ?? 'Pelanggan';
        $orderCode = $d['order_code'] ?? '-';
        $amount = number_format((float)($d['amount'] ?? $d['total_amount'] ?? 0), 0, ',', '.');
        $method = strtoupper($d['payment_method'] ?? 'QRIS');

        return "Halo {$name},\n\nPembayaran pesanan #{$orderCode} telah berhasil diverifikasi.\n\nNominal: Rp {$amount}\nMetode: {$method}\n\nCucian Anda akan segera diproses oleh toko laundry. Terima kasih!";
    }

    protected static function paymentFailed(array $d): string
    {
        $name = $d['customer_name'] ?? 'Pelanggan';
        $orderCode = $d['order_code'] ?? '-';
        $amount = number_format((float)($d['amount'] ?? 0), 0, ',', '.');

        return "Halo {$name},\n\nPembayaran pesanan #{$orderCode} sebesar Rp {$amount} gagal diproses atau waktu transaksi telah kedaluwarsa.\n\nSilakan ulangi pembayaran melalui aplikasi LaundryKu.";
    }

    protected static function refundSubmitted(array $d): string
    {
        $name = $d['customer_name'] ?? 'Pelanggan';
        $orderCode = $d['order_code'] ?? '-';
        $amount = number_format((float)($d['amount'] ?? 0), 0, ',', '.');
        $reason = $d['reason'] ?? 'Permintaan pengguna';

        return "Halo {$name},\n\nPengajuan refund untuk pesanan #{$orderCode} telah diterima dan sedang diproses oleh pihak toko/admin.\n\nNominal: Rp {$amount}\nAlasan: {$reason}\n\nKami akan menginformasikan kembali setelah proses selesai.";
    }

    protected static function refundCompleted(array $d): string
    {
        $name = $d['customer_name'] ?? 'Pelanggan';
        $orderCode = $d['order_code'] ?? '-';
        $amount = number_format((float)($d['amount'] ?? 0), 0, ',', '.');
        $gwRef = $d['gateway_reference'] ?? '-';

        return "Halo {$name},\n\nPengembalian dana (refund) untuk pesanan #{$orderCode} telah berhasil diselesaikan.\n\nNominal: Rp {$amount}\nReferensi: {$gwRef}\n\nDana telah dikembalikan ke metode pembayaran Anda. Terima kasih atas kesabaran Anda.";
    }

    protected static function refundFailed(array $d): string
    {
        $name = $d['customer_name'] ?? 'Pelanggan';
        $orderCode = $d['order_code'] ?? '-';

        return "Halo {$name},\n\nProses pengembalian dana untuk pesanan #{$orderCode} gagal diverifikasi.\n\nSilakan hubungi layanan bantuan atau pihak toko untuk pemeriksaan lebih lanjut.";
    }

    // ── STORE OWNER TEMPLATES ──────────────────────────────────────────────────

    protected static function storeNewOrder(array $d): string
    {
        $owner = $d['owner_name'] ?? 'Owner Toko';
        $store = $d['store_name'] ?? 'Toko Anda';
        $orderCode = $d['order_code'] ?? '-';
        $customer = $d['customer_name'] ?? 'Pelanggan';
        $service = $d['service_name'] ?? 'Layanan';
        $estWeight = $d['estimated_weight'] ?? '0';
        $estAmount = number_format((float)($d['estimated_amount'] ?? $d['total_amount'] ?? 0), 0, ',', '.');

        return "Halo {$owner} ({$store}),\n\nPesanan baru masuk!\n\nKode Pesanan: #{$orderCode}\nPelanggan: {$customer}\nLayanan: {$service}\nEstimasi: {$estWeight} kg (Rp {$estAmount})\n\nSilakan cek dashboard toko untuk menugaskan kurir penjemputan.";
    }

    protected static function storePaymentVerified(array $d): string
    {
        $owner = $d['owner_name'] ?? 'Owner Toko';
        $store = $d['store_name'] ?? 'Toko Anda';
        $orderCode = $d['order_code'] ?? '-';
        $amount = number_format((float)($d['amount'] ?? 0), 0, ',', '.');
        $method = strtoupper($d['payment_method'] ?? 'ONLINE');

        return "Halo {$owner} ({$store}),\n\nPembayaran pelanggan untuk pesanan #{$orderCode} telah terverifikasi lunas.\n\nNominal: Rp {$amount}\nMetode: {$method}\n\nSilakan lanjutkan proses pencucian di outlet Anda.";
    }

    protected static function storeOrderCancelled(array $d): string
    {
        $owner = $d['owner_name'] ?? 'Owner Toko';
        $store = $d['store_name'] ?? 'Toko Anda';
        $orderCode = $d['order_code'] ?? '-';
        $customer = $d['customer_name'] ?? 'Pelanggan';
        $notes = $d['notes'] ?? 'Dibatalkan';

        return "Halo {$owner} ({$store}),\n\nPesanan #{$orderCode} oleh {$customer} telah dibatalkan.\n\nCatatan: {$notes}\n\nStatus pada sistem toko telah diperbarui.";
    }

    protected static function storeStatusChanged(array $d): string
    {
        $owner = $d['owner_name'] ?? 'Owner Toko';
        $store = $d['store_name'] ?? 'Toko Anda';
        $orderCode = $d['order_code'] ?? '-';
        $statusLabel = $d['status_label'] ?? ($d['status'] ?? '-');

        return "Halo {$owner} ({$store}),\n\nStatus pesanan #{$orderCode} diperbarui menjadi: {$statusLabel}.\n\nCatatan: " . ($d['notes'] ?? '-');
    }

    protected static function storeRefundRequested(array $d): string
    {
        $owner = $d['owner_name'] ?? 'Owner Toko';
        $store = $d['store_name'] ?? 'Toko Anda';
        $orderCode = $d['order_code'] ?? '-';
        $amount = number_format((float)($d['amount'] ?? 0), 0, ',', '.');
        $reason = $d['reason'] ?? '-';

        return "Halo {$owner} ({$store}),\n\nTerdapat pengajuan refund baru untuk pesanan #{$orderCode}.\n\nNominal: Rp {$amount}\nAlasan: {$reason}\n\nSilakan periksa menu Kelola Refund pada dashboard toko.";
    }

    protected static function storeRefundProcessed(array $d): string
    {
        $owner = $d['owner_name'] ?? 'Owner Toko';
        $store = $d['store_name'] ?? 'Toko Anda';
        $orderCode = $d['order_code'] ?? '-';
        $amount = number_format((float)($d['amount'] ?? 0), 0, ',', '.');
        $status = ucfirst($d['refund_status'] ?? 'completed');

        return "Halo {$owner} ({$store}),\n\nProses refund untuk pesanan #{$orderCode} sebesar Rp {$amount} telah selesai dengan status: {$status}.";
    }

    // ── COURIER TEMPLATES ──────────────────────────────────────────────────────

    protected static function courierTaskAssigned(array $d): string
    {
        $courier = $d['courier_name'] ?? 'Kurir';
        $orderCode = $d['order_code'] ?? '-';
        $store = $d['store_name'] ?? 'Toko Laundry';
        $customer = $d['customer_name'] ?? 'Pelanggan';
        $phone = $d['customer_phone'] ?? '-';
        $address = $d['address'] ?? 'Alamat pelanggan';

        return "Halo {$courier},\n\nAnda mendapat tugas penjemputan cucian baru!\n\nKode Pesanan: #{$orderCode}\nToko: {$store}\nPelanggan: {$customer} ({$phone})\nAlamat: {$address}\n\nSilakan konfirmasi dan jalankan tugas melalui dashboard kurir.";
    }

    protected static function courierOrderReady(array $d): string
    {
        $courier = $d['courier_name'] ?? 'Kurir';
        $orderCode = $d['order_code'] ?? '-';
        $store = $d['store_name'] ?? 'Toko Laundry';
        $customer = $d['customer_name'] ?? 'Pelanggan';
        $address = $d['address'] ?? 'Alamat pelanggan';

        return "Halo {$courier},\n\nPesanan #{$orderCode} di {$store} sudah selesai dicuci dan siap diantar ke pelanggan:\n\nPelanggan: {$customer}\nAlamat Antar: {$address}\n\nSilakan ambil cucian di toko untuk diantar.";
    }

    protected static function courierTaskCancelled(array $d): string
    {
        $courier = $d['courier_name'] ?? 'Kurir';
        $orderCode = $d['order_code'] ?? '-';

        return "Halo {$courier},\n\nTugas untuk pesanan #{$orderCode} telah dibatalkan.\n\nAnda tidak perlu melanjutkan penjemputan/pengantaran untuk pesanan ini.";
    }

    protected static function courierTaskUpdated(array $d): string
    {
        $courier = $d['courier_name'] ?? 'Kurir';
        $orderCode = $d['order_code'] ?? '-';
        $notes = $d['notes'] ?? 'Informasi tugas diperbarui';

        return "Halo {$courier},\n\nInformasi tugas untuk pesanan #{$orderCode} telah diperbarui.\n\nCatatan: {$notes}\n\nSilakan periksa detail pesanan di aplikasi.";
    }

    // ── PLATFORM OWNER TEMPLATES ───────────────────────────────────────────────

    protected static function platformAlert(array $d): string
    {
        $alertType = $d['alert_type'] ?? 'SYSTEM_ALERT';
        $time = $d['time'] ?? date('Y-m-d H:i:s');
        $msg = $d['message'] ?? 'Peristiwa operasional terdeteksi';
        $detail = $d['detail'] ?? '-';

        return "[PERINGATAN SISTEM LAUNDRYKU]\n\nJenis: {$alertType}\nWaktu: {$time}\nPesan: {$msg}\nDetail: {$detail}\n\nSegera periksa sistem platform.";
    }

    protected static function defaultMessage(string $event, array $d): string
    {
        $orderCode = $d['order_code'] ?? '-';
        return "Halo, Anda menerima notifikasi dari LaundryKu untuk pesanan #{$orderCode} (Event: {$event}). Terima kasih telah menggunakan LaundryKu.";
    }
}

