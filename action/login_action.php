<?php
// Evita output prima degli header
ob_start();

require_once __DIR__.'/../include/session_manager.php';
require_once __DIR__.'/../include/config.inc.php';

// Inizializza sessione
SessionManager::startSecureSession();

// Verifica metodo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit();
}

// Recupera dati form
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Validazione base
if (empty($email) || empty($password)) {
    SessionManager::setFlashMessage('Inserire email e password', 'danger');
    SessionManager::set('login_form_data', ['email' => $email]);
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit();
}

// Connessione database
$db_config = $config['dbms']['localhost'];
$conn = new mysqli(
    $db_config['host'],
    $db_config['user'],
    $db_config['passwd'],
    $db_config['dbname']
);

if ($conn->connect_error) {
    SessionManager::setFlashMessage('Errore di connessione al database', 'danger');
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit();
}

// Query per verificare utente
$stmt = $conn->prepare("SELECT id_utente, password, nome, cognome FROM utente WHERE email = ?");
if (!$stmt) {
    SessionManager::setFlashMessage('Errore interno del server', 'danger');
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit();
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    // Verifica password
    if (password_verify($password, $user['password'])) {
        // Login riuscito
        SessionManager::login($user['id_utente'], [
            'email' => $email,
            'nome' => $user['nome'],
            'cognome' => $user['cognome']
        ]);

        // Determina dove reindirizzare
        $redirect_url = SessionManager::get('redirect_after_login');

        if ($redirect_url && !strpos($redirect_url, 'logout')) {
            // Usa il redirect salvato
            SessionManager::remove('redirect_after_login');
            $final_redirect = $redirect_url;
        } else {
            // Default: home utente
            $final_redirect = BASE_URL . '/pages/home_utente.php';
        }

        $stmt->close();
        $conn->close();

        header('Location: ' . $final_redirect);
        exit();
    }
}

// Login fallito
$stmt->close();
$conn->close();

SessionManager::setFlashMessage('Email o password non validi', 'danger');
SessionManager::set('login_form_data', ['email' => $email]);
header('Location: ' . BASE_URL . '/pages/auth/login.php');
exit();