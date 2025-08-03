<?php
require_once __DIR__.'/../include/session_manager.php';
SessionManager::startSecureSession();

header('Content-Type: application/json');
echo json_encode([
    'active' => isset($_SESSION['user_id']),
    'expired' => isset($_SESSION['last_activity']) &&
        (time() - $_SESSION['last_activity'] > 1500) // 25 minuti
]);