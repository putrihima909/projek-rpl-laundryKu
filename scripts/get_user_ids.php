<?php
require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Helpers/Env.php';
\App\Helpers\Env::load(__DIR__ . '/../.env');

$db = \App\Core\Database::getInstance();
$stmt = $db->query("SELECT u.id, u.name, u.email, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.email IN ('admin.toko@laundry.test', 'customer@laundry.test')");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
