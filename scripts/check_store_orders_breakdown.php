<?php
require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Helpers/Env.php';
\App\Helpers\Env::load(__DIR__ . '/../.env');

$db = \App\Core\Database::getInstance();
$rows = $db->query("SELECT status, COUNT(*) as cnt FROM orders WHERE store_id = 1 GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);

