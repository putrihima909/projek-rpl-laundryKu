<?php
require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Helpers/Env.php';
\App\Helpers\Env::load(__DIR__ . '/../.env');

$db = \App\Core\Database::getInstance();
$users = $db->query("SELECT u.id, u.name, u.email, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE r.name IN ('owner', 'admin_toko', 'customer', 'kurir') GROUP BY r.name")->fetchAll(PDO::FETCH_ASSOC);
print_r($users);

