<?php
// Imposta l'header per indicare che la risposta è in formato JSON.
header('Content-Type: application/json');

// Includi il gestore di sessione unificato.
require_once __DIR__ . '/../include/session_manager.php';

// Avvia la sessione in modo sicuro. È sempre il primo passo.
SessionManager::startSecureSession();

// Utilizza il metodo isLoggedIn() che già include la validazione del timeout.
$isActive = SessionManager::isLoggedIn();

// Determina se la sessione è scaduta.
// La sessione è considerata "scaduta" se l'utente NON è più loggato (`!$isActive`)
// ma esiste ancora un timestamp di 'last_activity', indicando che c'era una sessione prima.
$isExpired = !$isActive && isset($_SESSION['last_activity']);

// Invia la risposta JSON al client.
echo json_encode([
    'active' => $isActive,
    'expired' => $isExpired
]);