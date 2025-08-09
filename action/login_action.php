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

// Recupera e sanitizza dati form
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$captcha = isset($_POST['captcha']) ? intval($_POST['captcha']) : null;
$captcha_answer = isset($_POST['captcha_answer']) ? intval($_POST['captcha_answer']) : null;

// Recupera contatori di sicurezza
$failed_attempts = SessionManager::get('login_failed_attempts', 0);
$last_attempt_time = SessionManager::get('last_login_attempt', 0);
$lockout_until = SessionManager::get('lockout_until', 0);

// Funzione per registrare tentativo fallito
function recordFailedAttempt($email) {
    $attempts = SessionManager::get('login_failed_attempts', 0) + 1;
    SessionManager::set('login_failed_attempts', $attempts);
    SessionManager::set('last_login_attempt', time());

    // Log di sicurezza
    error_log("Failed login attempt #{$attempts} for email: " . $email . " from IP: " . $_SERVER['REMOTE_ADDR']);

    // Lockout progressivo
    if ($attempts >= 5) {
        $lockout_duration = min(300, $attempts * 60); // Max 5 minuti
        SessionManager::set('lockout_until', time() + $lockout_duration);
        error_log("Account locked for email: " . $email . " for {$lockout_duration} seconds");
    }

    return $attempts;
}

// Funzione per pulire contatori dopo login riuscito
function clearFailedAttempts() {
    SessionManager::remove('login_failed_attempts');
    SessionManager::remove('last_login_attempt');
    SessionManager::remove('lockout_until');
}

// Verifica lockout
if ($lockout_until > time()) {
    $remaining_time = ceil(($lockout_until - time()) / 60);
    SessionManager::setFlashMessage(
        "Account temporaneamente bloccato per sicurezza. Riprova tra {$remaining_time} minuti.",
        'danger'
    );
    SessionManager::set('login_form_data', ['email' => $email]);
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit();
}

// Validazione base migliorata
if (empty($email) || empty($password)) {
    SessionManager::setFlashMessage(
        'Inserisci email e password per accedere al tuo account.',
        'danger'
    );
    SessionManager::set('login_form_data', ['email' => $email]);
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit();
}

// Validazione formato email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    SessionManager::setFlashMessage(
        'Inserisci un indirizzo email valido. Controlla il formato (esempio@dominio.com).',
        'danger'
    );
    SessionManager::set('login_form_data', ['email' => $email]);
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit();
}

// Validazione lunghezza password
if (strlen($password) < 6) {
    SessionManager::setFlashMessage(
        'La password deve essere lunga almeno 6 caratteri.',
        'danger'
    );
    SessionManager::set('login_form_data', ['email' => $email]);
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit();
}

// Verifica captcha se richiesto (dopo 3 tentativi falliti)
if ($failed_attempts >= 3) {
    if ($captcha === null || $captcha_answer === null || $captcha !== $captcha_answer) {
        recordFailedAttempt($email);
        SessionManager::setFlashMessage(
            'Risolvi correttamente la verifica di sicurezza per procedere.',
            'danger'
        );
        SessionManager::set('login_form_data', ['email' => $email]);
        header('Location: ' . BASE_URL . '/pages/auth/login.php');
        exit();
    }
}

// Connessione database con gestione errori migliorata
$db_config = $config['dbms']['localhost'];
$conn = new mysqli(
    $db_config['host'],
    $db_config['user'],
    $db_config['passwd'],
    $db_config['dbname']
);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    SessionManager::setFlashMessage(
        'Servizio temporaneamente non disponibile. Riprova più tardi.',
        'danger'
    );
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit();
}

// Preparazione query con gestione errori
$stmt = $conn->prepare("SELECT id_utente, password, nome, cognome, email FROM utente WHERE email = ?");
if (!$stmt) {
    error_log("Failed to prepare statement: " . $conn->error);
    SessionManager::setFlashMessage(
        'Errore interno del server. Riprova più tardi.',
        'danger'
    );
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
        // LOGIN RIUSCITO
        error_log("Successful login for: " . $email);
        error_log("User ID: " . $user['id_utente']);

        // Pulisci contatori di tentativi falliti
        clearFailedAttempts();

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
                    error_log("Email @boxomnia.it but not in admin table - treating as regular user");
                    $isAdmin = false;
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
            'is_admin' => $isAdmin
        ]);

        // Debug: controlla se è stato salvato
        error_log("Session data after login: " . print_r($_SESSION, true));

        // Determina dove reindirizzare
        if ($isAdmin) {
            // Se è admin, vai alla dashboard
            $final_redirect = BASE_URL . '/pages/dashboard/dashboard.php';
            error_log("Redirecting admin to dashboard: " . $final_redirect);
            SessionManager::setFlashMessage(
                'Accesso amministratore eseguito con successo!',
                'success'
            );
        } else {
            // Per tutti gli utenti non-admin (inclusi @boxomnia.it non in tabella admin)
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
            SessionManager::setFlashMessage(
                'Accesso eseguito con successo! Benvenuto/a, ' . $user['nome'] . '!',
                'success'
            );
        }

        $stmt->close();
        $conn->close();

        header('Location: ' . $final_redirect);
        exit();

    } else {
        // PASSWORD ERRATA
        error_log("Password verification failed for: " . $email);
        $attempts = recordFailedAttempt($email);

        // Messaggi progressivamente più dettagliati
        if ($attempts == 1) {
            $message = 'Email o password non corretti. Controlla le tue credenziali e riprova.';
        } elseif ($attempts == 2) {
            $message = 'Email o password non corretti. Verifica di aver inserito la password corretta.';
        } elseif ($attempts == 3) {
            $message = 'Credenziali non valide. Per sicurezza, sarà richiesta una verifica aggiuntiva al prossimo tentativo.';
        } elseif ($attempts == 4) {
            $message = 'Accesso negato. Controlla attentamente email e password. Ultimo tentativo prima del blocco temporaneo.';
        } else {
            $message = 'Troppi tentativi falliti. Account bloccato temporaneamente per sicurezza.';
        }

        SessionManager::setFlashMessage($message, 'danger');
    }
} else {
    // UTENTE NON TROVATO
    error_log("User not found: " . $email);
    $attempts = recordFailedAttempt($email);

    // Messaggio generico per sicurezza (non rivelare se l'email esiste)
    if ($attempts <= 2) {
        $message = 'Email o password non corretti. Verifica le tue credenziali.';
    } else {
        $message = 'Credenziali non valide. Se hai dimenticato la password, contatta l\'assistenza.';
    }

    SessionManager::setFlashMessage($message, 'danger');
}

// Chiudi connessioni
$stmt->close();
$conn->close();

// Salva email per ripopolare il form (ma non la password per sicurezza)
SessionManager::set('login_form_data', ['email' => $email]);

// Reindirizza alla pagina di login con parametro di errore per JavaScript
$redirect_url = BASE_URL . '/pages/auth/login.php';
if ($failed_attempts >= 3) {
    $redirect_url .= '?error=invalid_credentials&attempts=' . $failed_attempts;
} else {
    $redirect_url .= '?error=invalid_credentials';
}

header('Location: ' . $redirect_url);
exit();

ob_end_flush();