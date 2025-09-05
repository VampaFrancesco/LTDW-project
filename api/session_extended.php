<?php
// Imposta l'header per la risposta JSON.
header('Content-Type: application/json');

// Includi il gestore di sessione.
require_once __DIR__ . '/../include/session_manager.php';

// Rispondi con errore se non è una richiesta POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'error' => 'Metodo non consentito.']);
    exit;
}

// La chiamata a isLoggedIn() fa due cose:
// 1. Controlla se l'utente è loggato.
// 2. Se è loggato, la funzione `validateSession()` al suo interno aggiorna già il timestamp `last_activity`.
if (!SessionManager::isLoggedIn()) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'error' => 'Non autenticato o sessione scaduta.']);
    exit;
}

try {
    // Prende il timeout direttamente dalla configurazione centralizzata.
    $timeout_seconds = SessionManager::getSessionInactivityTimeout();

    // Calcola il nuovo timestamp di scadenza per il client (in millisecondi).
    $new_expiry_time_ms = (time() + $timeout_seconds) * 1000;

    // Invia una risposta di successo.
    echo json_encode([
        'success' => true,
        'message' => 'Sessione estesa con successo.',
        'new_expiry_time' => $new_expiry_time_ms,
        'extended_by_seconds' => $timeout_seconds
    ]);

} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'success' => false,
        'error' => 'Errore interno del server durante l\'estensione della sessione.'
    ]);
    error_log('Errore API session_extended.php: ' . $e->getMessage());
}