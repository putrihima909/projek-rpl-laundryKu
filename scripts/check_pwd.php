<?php
require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Helpers/Env.php';
\App\Helpers\Env::load(__DIR__ . '/../.env');

$db = \App\Core\Database::getInstance();
$stmt = $db->query("SELECT id, name, email, password FROM users WHERE email = 'admin.toko@laundry.test'");
$user = $stmt->fetch(PDO::FETCH_ASSOC);
echo "User: " . $user['email'] . "\n";
echo "Password hash starts with: " . substr($user['password'], 0, 10) . "...\n";

// Test common passwords
foreach (['password', 'password123', 'admin', 'admin123', 'secret', '123456', '12345678', 'LaundryKu123!'] as $pwd) {
    if (password_verify($pwd, $user['password'])) {
        echo "MATCHED PASSWORD: $pwd\n";
        break;
    }
}

