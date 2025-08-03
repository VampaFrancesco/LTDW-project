<?php
require_once __DIR__.'/../include/session_manager.php';
SessionManager::startSecureSession();

$_SESSION['last_activity'] = time();
header('Content-Type: application/json');
echo json_encode(['success' => true]);