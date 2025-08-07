<?php
// admin_check.php
// File per verificare che l'utente sia un amministratore

require_once __DIR__ . '/../../include/session_manager.php';
require_once __DIR__ . '/../../include/config.inc.php';

// Inizializza sessione se non già fatto
SessionManager::startSecureSession();

// DEBUG: Aggiungi temporaneamente per vedere cosa succede
error_log("Admin check - User logged in: " . (SessionManager::isLoggedIn() ? 'SI' : 'NO'));
error_log("Admin check - Is admin: " . (SessionManager::get('user_is_admin', false) ? 'SI' : 'NO'));
error_log("Admin check - Session data: " . print_r($_SESSION, true));

// Verifica se l'utente è loggato
if (!SessionManager::isLoggedIn()) {
    // Salva la pagina corrente per redirect dopo login
    SessionManager::set('redirect_after_login', $_SERVER['REQUEST_URI']);
    SessionManager::setFlashMessage('Devi effettuare il login per accedere a questa pagina', 'warning');
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit();
}

// Verifica se l'utente è admin - NOTA: uso user_is_admin invece di is_admin
$isAdmin = SessionManager::get('user_is_admin', false);

if (!$isAdmin) {
    // Non è un admin, redirect alla home utente con messaggio
    SessionManager::setFlashMessage('Accesso negato. Area riservata agli amministratori.', 'danger');
    header('Location: ' . BASE_URL . '/pages/home_utente.php');
    exit();
}

// Se arriviamo qui, l'utente è un admin autenticato
