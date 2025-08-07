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
$stmt = $conn->prepare("SELECT id_utente, password, nome, cognome, email FROM utente WHERE email = ?");
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

        // DEBUG: Log per vedere cosa succede
        error_log("Login riuscito per: " . $email);
        error_log("User ID: " . $user['id_utente']);

        // Controlla se è un admin (email con @boxomnia.it)
        $isAdmin = false;
        if (str_ends_with(strtolower($email), '@boxomnia.it')) {
            error_log("Email @boxomnia.it detected, checking admin table");

            // Verifica che l'admin esista nella tabella admin
            $adminStmt = $conn->prepare("SELECT id_admin, livello_admin FROM admin WHERE fk_utente = ?");
            if ($adminStmt) {
                $adminStmt->bind_param("i", $user['id_utente']);
                $adminStmt->execute();
                $adminResult = $adminStmt->get_result();

                error_log("Admin query executed, rows found: " . $adminResult->num_rows);

                if ($adminResult->num_rows === 1) {
                    $adminData = $adminResult->fetch_assoc();
                    $isAdmin = true;
                    error_log("User is admin with level: " . $adminData['livello_admin']);
                } else {
                    // Email @boxomnia.it ma non è in tabella admin
                    error_log("Email @boxomnia.it but not in admin table");
                    SessionManager::setFlashMessage('Account non autorizzato come amministratore', 'danger');
                    SessionManager::set('login_form_data', ['email' => $email]);
                    $stmt->close();
                    $adminStmt->close();
                    $conn->close();
                    header('Location: ' . BASE_URL . '/pages/auth/login.php');
                    exit();
                }
                $adminStmt->close();
            } else {
                error_log("Error preparing admin query");
            }
        }

        error_log("Final isAdmin status: " . ($isAdmin ? 'TRUE' : 'FALSE'));

        // Salva i dati di sessione
        SessionManager::login($user['id_utente'], [
            'email' => $user['email'],
            'nome' => $user['nome'],
            'cognome' => $user['cognome'],
            'is_admin' => $isAdmin  // Questo salverà come user_is_admin
        ]);

        // Debug: controlla se è stato salvato
        error_log("Session data after login: " . print_r($_SESSION, true));

        // Determina dove reindirizzare
        if ($isAdmin) {
            // Se è admin, vai alla dashboard
            $final_redirect = BASE_URL . '/pages/dashboard/dashboard.php';
            error_log("Redirecting admin to dashboard: " . $final_redirect);
        } else {
            // Altrimenti usa la logica normale
            $redirect_url = SessionManager::get('redirect_after_login');

            if ($redirect_url && !strpos($redirect_url, 'logout')) {
                // Usa il redirect salvato
                SessionManager::remove('redirect_after_login');
                $final_redirect = $redirect_url;
            } else {
                // Default: home utente
                $final_redirect = BASE_URL . '/pages/home_utente.php';
            }
            error_log("Redirecting user to: " . $final_redirect);
        }

        $stmt->close();
        $conn->close();

        header('Location: ' . $final_redirect);
        exit();
    } else {
        error_log("Password verification failed for: " . $email);
    }
} else {
    error_log("User not found: " . $email);
}

// Login fallito
$stmt->close();
$conn->close();

SessionManager::setFlashMessage('Email o password non validi', 'danger');
SessionManager::set('login_form_data', ['email' => $email]);
header('Location: ' . BASE_URL . '/pages/auth/login.php');
exit();