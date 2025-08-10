<?php
// action/login_action.php - Versione robusta anti-errori
error_reporting(E_ALL);
ini_set('display_errors', 0); // Non mostrare errori all'utente
ini_set('log_errors', 1);     // Ma loggarli

// ✅ BUFFER OUTPUT per evitare "headers already sent"
ob_start();

try {
    require_once __DIR__ . '/../include/session_manager.php';
    require_once __DIR__ . '/../include/config.inc.php';

    // ✅ VERIFICA CHE BASE_URL SIA DEFINITO
    if (!defined('BASE_URL')) {
        define('BASE_URL', '/LTDW-project');
    }

    // ✅ INIZIALIZZA SESSIONE IN MODO SICURO
    SessionManager::startSecureSession();

    // ✅ VERIFICA METODO POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ' . BASE_URL . '/pages/auth/login.php');
        exit();
    }

    // ✅ RECUPERA E SANITIZZA DATI
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // ✅ VALIDAZIONE BASE
    if (empty($email) || empty($password)) {
        SessionManager::setFlashMessage('Inserisci email e password', 'danger');
        SessionManager::set('login_form_data', ['email' => $email]);
        header('Location: ' . BASE_URL . '/pages/auth/login.php');
        exit();
    }

    // ✅ VALIDAZIONE EMAIL
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        SessionManager::setFlashMessage('Formato email non valido', 'danger');
        SessionManager::set('login_form_data', ['email' => $email]);
        header('Location: ' . BASE_URL . '/pages/auth/login.php');
        exit();
    }

    // ✅ CONNESSIONE DATABASE CON ERROR HANDLING
    $db_config = $config['dbms']['localhost'];
    $conn = new mysqli(
        $db_config['host'],
        $db_config['user'],
        $db_config['passwd'],
        $db_config['dbname']
    );

    if ($conn->connect_error) {
        error_log("Login - Database connection failed: " . $conn->connect_error);
        SessionManager::setFlashMessage('Servizio temporaneamente non disponibile. Riprova più tardi.', 'danger');
        header('Location: ' . BASE_URL . '/pages/auth/login.php');
        exit();
    }

    // ✅ QUERY UTENTE CON PREPARED STATEMENT
    $stmt = $conn->prepare("SELECT id_utente, password, nome, cognome, email FROM utente WHERE email = ?");
    if (!$stmt) {
        error_log("Login - Query preparation failed: " . $conn->error);
        SessionManager::setFlashMessage('Errore interno. Riprova più tardi.', 'danger');
        $conn->close();
        header('Location: ' . BASE_URL . '/pages/auth/login.php');
        exit();
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // ✅ VERIFICA PASSWORD
        if (password_verify($password, $user['password'])) {
            error_log("Login successful for: " . $email . " (ID: " . $user['id_utente'] . ")");

            // ✅ IMPOSTA SESSIONE UTENTE
            SessionManager::set('user_logged_in', true);
            SessionManager::set('user_id', $user['id_utente']);
            SessionManager::set('user_nome', $user['nome']);
            SessionManager::set('user_cognome', $user['cognome']);
            SessionManager::set('user_email', $user['email']);

            // ✅ CONTROLLA SE È ADMIN (COMPATIBILE CON TUTTE LE VERSIONI PHP)
            $isAdmin = false;
            $adminLevel = null;

            // Usa substr invece di str_ends_with per compatibilità PHP
            if (strlen($email) >= 12 && substr(strtolower($email), -12) === '@boxomnia.it') {
                error_log("Admin email detected: " . $email);

                // ✅ VERIFICA NELLA TABELLA ADMIN
                $adminStmt = $conn->prepare("SELECT id_admin, livello_admin FROM admin WHERE fk_utente = ?");
                if ($adminStmt) {
                    $adminStmt->bind_param("i", $user['id_utente']);
                    $adminStmt->execute();
                    $adminResult = $adminStmt->get_result();

                    if ($adminResult->num_rows === 1) {
                        $adminData = $adminResult->fetch_assoc();
                        $isAdmin = true;
                        $adminLevel = $adminData['livello_admin'];
                        error_log("User confirmed as admin with level: " . $adminLevel);
                    } else {
                        error_log("Email @boxomnia.it but not found in admin table for user: " . $user['id_utente']);
                    }
                    $adminStmt->close();
                } else {
                    error_log("Failed to prepare admin check query: " . $conn->error);
                }
            }

            // ✅ IMPOSTA STATO ADMIN
            SessionManager::set('user_is_admin', $isAdmin);
            if ($adminLevel) {
                SessionManager::set('admin_level', $adminLevel);
            }

            // ✅ REGENERA ID SESSIONE PER SICUREZZA
            session_regenerate_id(true);

            $stmt->close();
            $conn->close();

            // ✅ REDIRECT BASATO SUL RUOLO E REDIRECT SALVATO
            $redirect_url = SessionManager::get('redirect_after_login');

            if ($redirect_url) {
                // Se c'era una pagina salvata, vai lì
                SessionManager::remove('redirect_after_login');
                $final_redirect = $redirect_url;
                error_log("Redirecting to saved page: " . $final_redirect);
            } elseif ($isAdmin) {
                // Se è admin, vai alla dashboard
                $final_redirect = BASE_URL . '/pages/dashboard/dashboard.php';
                error_log("Redirecting admin to dashboard");
            } else {
                // Utente normale, vai alla home
                $final_redirect = BASE_URL . '/pages/home_utente.php';
                error_log("Redirecting user to home");
            }

            // ✅ FLUSH BUFFER E REDIRECT
            ob_end_clean();
            header('Location: ' . $final_redirect);
            exit();

        } else {
            error_log("Login failed - wrong password for: " . $email);
        }
    } else {
        error_log("Login failed - user not found: " . $email);
    }

    // ✅ LOGIN FALLITO
    $stmt->close();
    $conn->close();

    SessionManager::setFlashMessage('Email o password non corretti', 'danger');
    SessionManager::set('login_form_data', ['email' => $email]);

    ob_end_clean();
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit();

} catch (Exception $e) {
    // ✅ GESTIONE ERRORI GLOBALE
    error_log("Login critical error: " . $e->getMessage());
    error_log("Login error trace: " . $e->getTraceAsString());

    // Pulizia risorse se necessario
    if (isset($stmt) && $stmt) $stmt->close();
    if (isset($conn) && $conn) $conn->close();

    SessionManager::setFlashMessage('Si è verificato un errore imprevisto. Riprova più tardi.', 'danger');

    ob_end_clean();
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit();

} catch (Error $e) {
    // ✅ GESTIONE ERRORI FATALI
    error_log("Login fatal error: " . $e->getMessage());
    error_log("Login fatal error trace: " . $e->getTraceAsString());

    // Pulizia risorse se necessario
    if (isset($stmt) && $stmt) $stmt->close();
    if (isset($conn) && $conn) $conn->close();

    SessionManager::setFlashMessage('Errore di sistema. Contatta l\'amministratore.', 'danger');

    ob_end_clean();
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit();
}