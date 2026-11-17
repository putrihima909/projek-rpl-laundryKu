<?php
$files = glob('app/Services/Ai/*.php');
$files[] = 'app/Controllers/AiChatController.php';

foreach ($files as $file) {
    $cmd = 'C:\\xampp\\php\\php.exe -l "' . __DIR__ . '/../' . $file . '"';
    passthru($cmd);
}

