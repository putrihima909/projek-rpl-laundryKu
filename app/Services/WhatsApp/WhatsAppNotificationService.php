<?php

namespace App\Services\WhatsApp;

use App\Core\Database;
use App\Helpers\Config;
use App\Helpers\PhoneHelper;
use App\Services\WhatsApp\Providers\CloudApiWhatsAppClient;
use App\Services\WhatsApp\Providers\FonnteWhatsAppClient;
use App\Services\WhatsApp\Providers\GenericGatewayWhatsAppClient;
use App\Services\WhatsApp\Providers\MockWhatsAppClient;
use PDO;
use Throwable;

class WhatsAppNotificationService
{
    protected ?PDO $db = null;
    protected WhatsAppClientInterface $client;
    protected bool $enabled = true;
    protected int $maxRetries = 3;

    public function __construct(?WhatsAppClientInterface $client = null)
    {
        try {
            $this->db = Database::getInstance();
        } catch (Throwable $e) {
            $this->db = null;
        }

        $this->enabled = (bool) Config::get('whatsapp.enabled', true);
        $this->maxRetries = (int) Config::get('whatsapp.max_retries', 3);

        if ($client !== null) {
            $this->client = $client;
        } else {
            $provider = Config::get('whatsapp.provider', 'fonnte');
            $this->client = match ($provider) {
                'fonnte'          => new FonnteWhatsAppClient(),
                'cloud_api'       => new CloudApiWhatsAppClient(),
                'generic_gateway' => new GenericGatewayWhatsAppClient(),
                default           => new MockWhatsAppClient(),
            };
        }
    }

    public function getClient(): WhatsAppClientInterface
    {
        return $this->client;
    }

    public function setClient(WhatsAppClientInterface $client): self
    {
        $this->client = $client;
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Check if a specific user has opted-in to receive WhatsApp notifications.
     */
    public function isUserOptedIn(int $userId): bool
    {
        if (!$this->db || $userId <= 0) {
            return true;
        }

        try {
            $stmt = $this->db->prepare("SELECT wa_notifications_enabled FROM users WHERE id = :uid LIMIT 1");
            $stmt->execute(['uid' => $userId]);
            $val = $stmt->fetchColumn();
            if ($val === false || $val === null) {
                return true;
            }
            return (bool)$val;
        } catch (Throwable $e) {
            return true;
        }
    }

    /**
     * Dispatch notification to a Customer.
     *
     * @param int $customerId
     * @param string $eventType
     * @param array $context Order/payment/user data
     * @param string|null $customIdempotencyKey
     * @return array Result summary
     */
    public function sendCustomerNotification(int $customerId, string $eventType, array $context, ?string $customIdempotencyKey = null): array
    {
        try {
            $user = $this->getUser($customerId);
            if (!$user) {
                return ['success' => false, 'status' => 'skipped', 'message' => 'Customer user not found'];
            }

            $phone = $context['customer_phone'] ?? $user['phone'] ?? null;
            $data = array_merge($context, [
                'customer_name' => $context['customer_name'] ?? $user['name'] ?? 'Pelanggan',
                'customer_phone' => $phone,
            ]);

            $orderCode = $data['order_code'] ?? null;
            $idempotencyKey = $customIdempotencyKey ?: "cust:{$eventType}:" . ($orderCode ?: $customerId) . ":{$customerId}";

            return $this->dispatch(
                userId: $customerId,
                recipientPhone: (string)$phone,
                recipientRole: 'customer',
                eventType: $eventType,
                orderCode: $orderCode,
                templateData: $data,
                idempotencyKey: $idempotencyKey
            );
        } catch (Throwable $e) {
            error_log("[WhatsAppNotificationService] Error in sendCustomerNotification: " . $e->getMessage());
            return ['success' => false, 'status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * Dispatch notification to a Store Owner.
     * Resolves store owner user ID and phone from stores table.
     *
     * @param int $storeId
     * @param string $eventType
     * @param array $context
     * @param string|null $customIdempotencyKey
     * @return array Result summary
     */
    public function sendStoreOwnerNotification(int $storeId, string $eventType, array $context, ?string $customIdempotencyKey = null): array
    {
        try {
            $storeInfo = $this->getStoreWithOwner($storeId);
            if (!$storeInfo) {
                return ['success' => false, 'status' => 'skipped', 'message' => "Store #{$storeId} not found"];
            }

            $ownerId = (int)$storeInfo['user_id'];
            $ownerPhone = $storeInfo['owner_phone'] ?? $storeInfo['store_phone'] ?? null;

            $data = array_merge($context, [
                'owner_name' => $storeInfo['owner_name'] ?? 'Owner Toko',
                'store_name' => $storeInfo['store_name'] ?? 'Toko Laundry',
                'store_phone' => $storeInfo['store_phone'] ?? null,
            ]);

            $orderCode = $data['order_code'] ?? null;
            $idempotencyKey = $customIdempotencyKey ?: "store:{$eventType}:" . ($orderCode ?: $storeId) . ":{$ownerId}";

            return $this->dispatch(
                userId: $ownerId,
                recipientPhone: (string)$ownerPhone,
                recipientRole: 'owner_toko',
                eventType: $eventType,
                orderCode: $orderCode,
                templateData: $data,
                idempotencyKey: $idempotencyKey
            );
        } catch (Throwable $e) {
            error_log("[WhatsAppNotificationService] Error in sendStoreOwnerNotification: " . $e->getMessage());
            return ['success' => false, 'status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * Dispatch notification to a Courier.
     *
     * @param int $courierId
     * @param string $eventType
     * @param array $context
     * @param string|null $customIdempotencyKey
     * @return array Result summary
     */
    public function sendCourierNotification(int $courierId, string $eventType, array $context, ?string $customIdempotencyKey = null): array
    {
        try {
            $courier = $this->getUser($courierId);
            if (!$courier) {
                return ['success' => false, 'status' => 'skipped', 'message' => "Courier #{$courierId} not found"];
            }

            $phone = $context['courier_phone'] ?? $courier['phone'] ?? null;
            $data = array_merge($context, [
                'courier_name' => $courier['name'] ?? 'Kurir',
                'courier_phone' => $phone,
            ]);

            $orderCode = $data['order_code'] ?? null;
            $idempotencyKey = $customIdempotencyKey ?: "courier:{$eventType}:" . ($orderCode ?: $courierId) . ":{$courierId}";

            return $this->dispatch(
                userId: $courierId,
                recipientPhone: (string)$phone,
                recipientRole: 'kurir',
                eventType: $eventType,
                orderCode: $orderCode,
                templateData: $data,
                idempotencyKey: $idempotencyKey
            );
        } catch (Throwable $e) {
            error_log("[WhatsAppNotificationService] Error in sendCourierNotification: " . $e->getMessage());
            return ['success' => false, 'status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * Dispatch alert notification to Platform Owner.
     * Only for critical platform events.
     *
     * @param string $alertType
     * @param string $message
     * @param array $context
     * @return array
     */
    public function sendPlatformOwnerAlert(string $alertType, string $message, array $context = []): array
    {
        try {
            $owners = $this->getPlatformOwners();
            if (empty($owners)) {
                return ['success' => false, 'status' => 'skipped', 'message' => 'No active Platform Owner found'];
            }

            $data = array_merge($context, [
                'alert_type' => $alertType,
                'message'    => $message,
                'detail'     => $context['detail'] ?? '-',
                'time'       => date('Y-m-d H:i:s'),
            ]);

            $results = [];
            foreach ($owners as $owner) {
                $idempotencyKey = "platform:alert:{$alertType}:" . date('YmdH') . ":{$owner['id']}";
                $res = $this->dispatch(
                    userId: (int)$owner['id'],
                    recipientPhone: (string)$owner['phone'],
                    recipientRole: 'owner',
                    eventType: 'platform_alert',
                    orderCode: null,
                    templateData: $data,
                    idempotencyKey: $idempotencyKey
                );
                $results[] = $res;
            }

            return ['success' => true, 'dispatched_count' => count($results), 'results' => $results];
        } catch (Throwable $e) {
            error_log("[WhatsAppNotificationService] Error in sendPlatformOwnerAlert: " . $e->getMessage());
            return ['success' => false, 'status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * Core dispatch engine with validation, opt-in check, idempotency check, logging, and retries.
     */
    protected function dispatch(
        int $userId,
        string $recipientPhone,
        string $recipientRole,
        string $eventType,
        ?string $orderCode,
        array $templateData,
        ?string $idempotencyKey = null
    ): array {
        // 1. Check master feature switch
        if (!$this->enabled) {
            return [
                'success' => false,
                'status'  => 'skipped',
                'message' => 'WhatsApp notifications are disabled by system configuration',
            ];
        }

        // 2. Validate and normalize phone number
        if (!PhoneHelper::isValid($recipientPhone)) {
            $this->logEntry([
                'user_id'          => $userId,
                'recipient_phone'  => $recipientPhone ?: 'UNKNOWN',
                'recipient_role'   => $recipientRole,
                'event_type'       => $eventType,
                'order_code'       => $orderCode,
                'message'          => WhatsAppTemplate::render($eventType, $templateData),
                'status'           => 'failed',
                'provider'         => $this->client->getName(),
                'provider_response'=> ['error' => 'Invalid phone number format for WhatsApp delivery'],
                'attempts'         => 1,
                'idempotency_key'  => $idempotencyKey,
            ]);

            return [
                'success' => false,
                'status'  => 'failed',
                'message' => "Nomor telepon WhatsApp penerima tidak valid: '{$recipientPhone}'",
            ];
        }

        $normalizedPhone = PhoneHelper::normalize($recipientPhone);

        // 3. User opt-in check
        if (!$this->isUserOptedIn($userId)) {
            $this->logEntry([
                'user_id'          => $userId,
                'recipient_phone'  => $normalizedPhone,
                'recipient_role'   => $recipientRole,
                'event_type'       => $eventType,
                'order_code'       => $orderCode,
                'message'          => WhatsAppTemplate::render($eventType, $templateData),
                'status'           => 'skipped',
                'provider'         => $this->client->getName(),
                'provider_response'=> ['note' => 'User opted out from receiving WhatsApp notifications'],
                'attempts'         => 0,
                'idempotency_key'  => $idempotencyKey,
            ]);

            return [
                'success' => true,
                'status'  => 'skipped',
                'message' => 'Penerima memilih untuk tidak menerima notifikasi WhatsApp (opt-out)',
            ];
        }

        // 4. Idempotency Check: prevent duplicate messages for the same event
        if ($idempotencyKey !== null && $this->isAlreadySent($idempotencyKey)) {
            return [
                'success' => true,
                'status'  => 'skipped',
                'message' => "Notifikasi sudah pernah terkirim untuk kunci idempoten: '{$idempotencyKey}'",
            ];
        }

        // 5. Render message body from template
        $messageBody = WhatsAppTemplate::render($eventType, $templateData);

        // 6. Attempt transmission with retry logic
        $attempts = 0;
        $lastResult = null;
        $success = false;

        while ($attempts < $this->maxRetries && !$success) {
            $attempts++;
            $lastResult = $this->client->sendMessage(
                recipientPhone: $normalizedPhone,
                message: $messageBody,
                metadata: [
                    'event_type'     => $eventType,
                    'order_code'     => $orderCode,
                    'recipient_role' => $recipientRole,
                    'user_id'        => $userId,
                    'attempt'        => $attempts,
                ]
            );

            if (!empty($lastResult['success'])) {
                $success = true;
                break;
            }

            // Brief pause before retry if more attempts remain
            if ($attempts < $this->maxRetries) {
                usleep(50000); // 50ms pause
            }
        }

        $status = $success ? 'sent' : 'failed';
        $providerMsgId = $lastResult['message_id'] ?? null;
        $providerResp = $lastResult['response'] ?? ['error' => $lastResult['error'] ?? 'Unknown error'];
        $httpCode = $lastResult['http_code'] ?? ($success ? 200 : null);

        // 7. Write to whatsapp_logs table
        $logId = $this->logEntry([
            'user_id'             => $userId,
            'recipient_phone'     => $normalizedPhone,
            'recipient_role'      => $recipientRole,
            'event_type'          => $eventType,
            'order_code'          => $orderCode,
            'message'             => $messageBody,
            'status'              => $status,
            'http_code'           => $httpCode,
            'provider'            => $this->client->getName(),
            'provider_message_id' => $providerMsgId,
            'provider_response'   => $providerResp,
            'attempts'            => $attempts,
            'idempotency_key'     => $idempotencyKey,
            'sent_at'             => $success ? date('Y-m-d H:i:s') : null,
        ]);

        return [
            'success'             => $success,
            'status'              => $status,
            'log_id'              => $logId,
            'provider'            => $this->client->getName(),
            'provider_message_id' => $providerMsgId,
            'attempts'            => $attempts,
            'message'             => $success ? 'Pesan WhatsApp berhasil dikirim' : ($lastResult['error'] ?? 'Gagal mengirim pesan WhatsApp'),
        ];
    }

    /**
     * Check if a successful dispatch already exists for this idempotency key.
     */
    protected function isAlreadySent(string $key): bool
    {
        if (!$this->db) {
            return false;
        }

        try {
            $stmt = $this->db->prepare("SELECT id FROM whatsapp_logs WHERE idempotency_key = :key AND status = 'sent' LIMIT 1");
            $stmt->execute(['key' => $key]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Log delivery details to database.
     */
    protected function logEntry(array $data): ?int
    {
        if (!$this->db) {
            return null;
        }

        try {
            // Strip any sensitive keys (access tokens, credentials) from logged payload
            $resp = $data['provider_response'] ?? null;
            if (is_array($resp)) {
                unset($resp['access_token'], $resp['token'], $resp['api_key'], $resp['secret']);
                $resp = json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $sql = "INSERT INTO whatsapp_logs 
                    (notification_id, user_id, store_id, recipient_phone, recipient_role, event_type, order_code, message, status, http_code, provider, provider_message_id, provider_response, attempts, idempotency_key, created_at, sent_at)
                    VALUES 
                    (:nid, :uid, :sid, :phone, :role, :ev, :ocode, :msg, :st, :hcode, :pv, :pmid, :presp, :att, :idem, NOW(), :sat)
                    ON DUPLICATE KEY UPDATE
                    attempts = attempts + 1,
                    status = VALUES(status),
                    http_code = VALUES(http_code),
                    store_id = COALESCE(VALUES(store_id), store_id),
                    provider_message_id = COALESCE(VALUES(provider_message_id), provider_message_id),
                    provider_response = VALUES(provider_response),
                    sent_at = COALESCE(VALUES(sent_at), sent_at),
                    updated_at = NOW()";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'nid'   => $data['notification_id'] ?? null,
                'uid'   => $data['user_id'],
                'sid'   => $data['store_id'] ?? null,
                'phone' => $data['recipient_phone'],
                'role'  => $data['recipient_role'],
                'ev'    => $data['event_type'],
                'ocode' => $data['order_code'] ?? null,
                'msg'   => $data['message'],
                'st'    => $data['status'],
                'hcode' => $data['http_code'] ?? null,
                'pv'    => $data['provider'],
                'pmid'  => $data['provider_message_id'] ?? null,
                'presp' => $resp,
                'att'   => $data['attempts'] ?? 1,
                'idem'  => $data['idempotency_key'] ?? null,
                'sat'   => $data['sent_at'] ?? null,
            ]);

            return (int)$this->db->lastInsertId();
        } catch (Throwable $e) {
            error_log("[WhatsAppNotificationService] logEntry failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Retry a previously failed notification from logs.
     */
    public function retryFailed(int $logId): array
    {
        if (!$this->db) {
            return ['success' => false, 'message' => 'Database connection unavailable'];
        }

        try {
            $stmt = $this->db->prepare("SELECT * FROM whatsapp_logs WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $logId]);
            $log = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$log) {
                return ['success' => false, 'message' => "Log #{$logId} tidak ditemukan"];
            }

            if ($log['status'] === 'sent') {
                return ['success' => true, 'message' => "Log #{$logId} sudah terkirim sebelumnya (idempoten)"];
            }

            $phone = PhoneHelper::normalize($log['recipient_phone']);
            if (!$phone || !PhoneHelper::isValid($phone)) {
                return ['success' => false, 'message' => "Nomor telepon tidak valid: {$log['recipient_phone']}"];
            }

            $result = $this->client->sendMessage(
                recipientPhone: $phone,
                message: $log['message'],
                metadata: [
                    'event_type' => $log['event_type'],
                    'order_code' => $log['order_code'],
                    'recipient_role' => $log['recipient_role'],
                    'user_id' => $log['user_id'],
                    'retry_of_log_id' => $logId,
                ]
            );

            $newStatus = !empty($result['success']) ? 'sent' : 'failed';
            $attempts = (int)$log['attempts'] + 1;
            $msgId = $result['message_id'] ?? $log['provider_message_id'];
            $sentAt = $newStatus === 'sent' ? date('Y-m-d H:i:s') : null;

            $updateStmt = $this->db->prepare("
                UPDATE whatsapp_logs 
                SET status = :st, 
                    attempts = :att, 
                    provider_message_id = :pmid, 
                    provider_response = :presp,
                    sent_at = :sat,
                    updated_at = NOW()
                WHERE id = :id
            ");

            $resp = json_encode($result['response'] ?? ['error' => $result['error'] ?? 'Unknown']);
            $updateStmt->execute([
                'st'    => $newStatus,
                'att'   => $attempts,
                'pmid'  => $msgId,
                'presp' => $resp,
                'sat'   => $sentAt,
                'id'    => $logId,
            ]);

            return [
                'success' => !empty($result['success']),
                'log_id'  => $logId,
                'status'  => $newStatus,
                'attempts'=> $attempts,
                'message' => !empty($result['success']) ? 'Retry berhasil dikirim' : ($result['error'] ?? 'Retry gagal'),
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Error retrying log: ' . $e->getMessage()];
        }
    }

    /**
     * Lookup user record with role name.
     */
    protected function getUser(int $userId): ?array
    {
        if (!$this->db) {
            return null;
        }

        try {
            $stmt = $this->db->prepare("
                SELECT u.*, r.name as role_name 
                FROM users u 
                LEFT JOIN roles r ON u.role_id = r.id 
                WHERE u.id = :id 
                LIMIT 1
            ");
            $stmt->execute(['id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            return $user ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Lookup store and owner information.
     */
    protected function getStoreWithOwner(int $storeId): ?array
    {
        if (!$this->db) {
            return null;
        }

        try {
            $stmt = $this->db->prepare("
                SELECT s.id as store_id, s.name as store_name, s.phone as store_phone, s.user_id,
                       u.name as owner_name, u.phone as owner_phone, u.email as owner_email
                FROM stores s
                LEFT JOIN users u ON s.user_id = u.id
                WHERE s.id = :id
                LIMIT 1
            ");
            $stmt->execute(['id' => $storeId]);
            $store = $stmt->fetch(PDO::FETCH_ASSOC);
            return $store ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Retrieve platform owners.
     */
    protected function getPlatformOwners(): array
    {
        if (!$this->db) {
            return [];
        }

        try {
            $stmt = $this->db->prepare("
                SELECT u.id, u.name, u.phone, u.email 
                FROM users u 
                JOIN roles r ON u.role_id = r.id 
                WHERE r.name = 'owner' AND u.is_active = 1
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Get aggregate statistics for the WhatsApp Dashboard.
     * Optionally filtered by storeId for store-level isolation.
     *
     * @param int|null $storeId
     * @return array
     */
    public function getStats(?int $storeId = null): array
    {
        $default = [
            'total' => 0,
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0,
            'last_sent_at' => null,
            'provider' => $this->client->getName(),
            'is_configured' => method_exists($this->client, 'isConfigured') ? $this->client->isConfigured() : true,
            'masked_token' => method_exists($this->client, 'getMaskedToken') ? $this->client->getMaskedToken() : null,
            'device' => null,
        ];

        if (!$this->db) {
            return $default;
        }

        try {
            $whereClause = $storeId !== null ? "WHERE store_id = :sid" : "";
            $sql = "
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END) as skipped,
                    MAX(CASE WHEN status = 'sent' THEN sent_at ELSE NULL END) as last_sent_at
                FROM whatsapp_logs
                {$whereClause}
            ";
            $stmt = $this->db->prepare($sql);
            if ($storeId !== null) {
                $stmt->execute(['sid' => $storeId]);
            } else {
                $stmt->execute();
            }
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $default['total'] = (int)($row['total'] ?? 0);
                $default['sent'] = (int)($row['sent'] ?? 0);
                $default['failed'] = (int)($row['failed'] ?? 0);
                $default['skipped'] = (int)($row['skipped'] ?? 0);
                $default['last_sent_at'] = $row['last_sent_at'] ?? null;
            }

            // Check device status if provider is Fonnte
            if ($this->client instanceof FonnteWhatsAppClient) {
                $default['device'] = $this->client->checkDeviceStatus();
            }

            return $default;
        } catch (Throwable $e) {
            return $default;
        }
    }

    /**
     * Get paginated logs for the WhatsApp Dashboard.
     * Optionally filtered by storeId for store-level isolation.
     *
     * @param int $limit
     * @param int $offset
     * @param int|null $storeId
     * @return array
     */
    public function getLogs(int $limit = 20, int $offset = 0, ?int $storeId = null): array
    {
        if (!$this->db) {
            return [];
        }

        try {
            $whereClause = $storeId !== null ? "WHERE wl.store_id = :sid" : "";
            $sql = "
                SELECT wl.*, u.name as user_name, u.email as user_email
                FROM whatsapp_logs wl
                LEFT JOIN users u ON wl.user_id = u.id
                {$whereClause}
                ORDER BY wl.id DESC
                LIMIT :limit OFFSET :offset
            ";
            $stmt = $this->db->prepare($sql);
            if ($storeId !== null) {
                $stmt->bindValue(':sid', $storeId, PDO::PARAM_INT);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}
