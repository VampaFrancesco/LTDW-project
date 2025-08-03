<?php
global $config;
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// 1. INIZIO SESSIONE SICURA
require_once __DIR__.'/../include/session_manager.php';
SessionManager::startSecureSession();

$configPath = __DIR__ . '/../include/config.inc.php';
if (!file_exists($configPath)) {
    header('Location: ../pages/auth/login.php?error=' . urlencode('Errore interno del server'));
    exit;
}

require_once $configPath;
$db_config = $config['dbms']['localhost'];

$conn = new mysqli(
    $db_config['host'],
    $db_config['user'],
    $db_config['passwd'],
    $db_config['dbname']
);
if ($conn->connect_error) {
    header('Location: ../pages/auth/login.php?error=' . urlencode('Connessione al database fallita'));
    exit;
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/auth/login.php');
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

$error_message = '';

if (empty($email) || empty($password)) {
    $error_message = "Inserire email e password";
} else {
    try {
        $stmt = $conn->prepare("SELECT id_utente, password, nome, cognome FROM utente WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows === 1) {
                $stmt->bind_result($id_utente, $hashed_password, $nome, $cognome);
                $stmt->fetch();

                if (password_verify($password, $hashed_password)) {
                    // 2. REGISTRAZIONE DATI UTENTE IN SESSIONE
                    SessionManager::set('user_id', $id_utente);
                    SessionManager::set('user_email', $email);
                    $isLoggato = SessionManager::set('user_logged_in', true);
                    Sessionmanager::set('user_id', $id_utente);
                    SessionManager::set('user_name', $nome);
                    SessionManager::set('user_surname', $cognome);
                    SessionManager::set('last_activity', time());


                    // 3. REINDIRIZZAMENTO SICURO
                    $redirect_url = $_POST['redirect'] ?? '/LTDW-project/pages/home_utente.php';
                    header('Location: ' . filter_var($redirect_url, FILTER_SANITIZE_URL));
                    exit();
                } else {
                    $error_message = "Email o password non validi";
                }
            } else {
                $error_message = "Email o password non validi";
            }
            $stmt->close();
        } else {
            $error_message = "Si è verificato un errore";
        }
    } catch (mysqli_sql_exception $e) {
        $error_message = "Si è verificato un errore interno";
    }
}

$conn->close();

if (!empty($error_message)) {
    // 4. PULIZIA SESSIONE IN CASO DI ERRORE
    SessionManager::destroy();
    header('Location: ../pages/auth/login.php?error=' . urlencode($error_message));
    exit();
}

// 5. FALLBACK PER ERRORI IMPREVISTI
SessionManager::destroy();
header('Location: ../pages/auth/login.php?error=' . urlencode('Si è verificato un errore imprevisto'));
exit();