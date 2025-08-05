<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../include/session_manager.php';

// Verifica che sia una richiesta POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Metodo non consentito']);
    exit;
}

// Verifica che l'utente sia loggato
if (!SessionManager::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autenticato']);
    exit;
}

try {
    // Estendi la sessione aggiornando last_activity
    SessionManager::set('last_activity', time());

    // Calcola il nuovo tempo di scadenza
    $session_timeout = 300; // 5 minuti
    $new_expiry_time = (time() + $session_timeout) * 1000; // In millisecondi per JavaScript

    echo json_encode([
        'success' => true,
        'message' => 'Sessione estesa con successo',
        'new_expiry_time' => $new_expiry_time,
        'extended_by_seconds' => $session_timeout
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Errore interno del server'
    ]);
    error_log('Errore estensione sessione: ' . $e->getMessage());
}