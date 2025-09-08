<?php
// action/register_action.php
ob_start();

require_once __DIR__ . '/../include/session_manager.php';
require_once __DIR__ . '/../include/config.inc.php';

// ✅ DEFINISCI BASE_URL COME IN LOGIN_ACTION
if (!defined('BASE_URL')) {
    define('BASE_URL', '/LTDW-project');
}

// Inizializza sessione
SessionManager::startSecureSession();

// Verifica metodo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/pages/auth/register.php');
    exit();
}

// ✅ RECUPERA E SANITIZZA DATI FORM
$nome = trim($_POST['nome'] ?? '');
$cognome = trim($_POST['cognome'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// ✅ VALIDAZIONE COMPLETA LATO SERVER
$errors = [];

// Validazione campi obbligatori
if (empty($nome)) $errors[] = 'Il nome è obbligatorio';
if (empty($cognome)) $errors[] = 'Il cognome è obbligatorio';
if (empty($email)) $errors[] = 'L\'email è obbligatoria';
if (empty($password)) $errors[] = 'La password è obbligatoria';
if (empty($confirm_password)) $errors[] = 'La conferma password è obbligatoria';

// Validazione lunghezza campi
if (strlen($nome) < 2) $errors[] = 'Il nome deve essere lungo almeno 2 caratteri';
if (strlen($cognome) < 2) $errors[] = 'Il cognome deve essere lungo almeno 2 caratteri';

// ✅ VALIDAZIONE EMAIL E BLOCCO @boxomnia.it
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Formato email non valido';
} elseif (str_ends_with(strtolower($email), '@boxomnia.it')) {
    $errors[] = 'Le email con dominio @boxomnia.it sono riservate agli amministratori. Contattare l\'amministratore per l\'accesso.';
}

// Validazione password
if (strlen($password) < 6) {
    $errors[] = 'La password deve essere lunga almeno 6 caratteri';
}

// Validazione conferma password
if ($password !== $confirm_password) {
    $errors[] = 'Le password non corrispondono';
}

// ✅ SE CI SONO ERRORI, TORNA ALLA REGISTRAZIONE
if (!empty($errors)) {
    SessionManager::setFlashMessage(implode('<br>', $errors), 'danger');
    SessionManager::set('register_form_data', [
        'nome' => $nome,
        'cognome' => $cognome,
        'email' => str_ends_with(strtolower($email), '@boxomnia.it') ? '' : $email
    ]);
    ob_end_clean(); // ✅ AGGIUNTO
    header('Location: ' . BASE_URL . '/pages/auth/register.php');
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
    error_log("Register - Database connection error: " . $conn->connect_error);
    SessionManager::setFlashMessage('Errore di connessione al database. Riprova più tardi.', 'danger');
    ob_end_clean(); // ✅ AGGIUNTO
    header('Location: ' . BASE_URL . '/pages/auth/register.php');
    exit();
}

try {
    // ✅ VERIFICA SE EMAIL GIÀ ESISTENTE
    $checkStmt = $conn->prepare("SELECT id_utente FROM utente WHERE email = ?");
    if (!$checkStmt) {
        throw new Exception("Errore preparazione query controllo email: " . $conn->error);
    }

    $checkStmt->bind_param("s", $email);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows > 0) {
        SessionManager::setFlashMessage('Questa email è già registrata. <a href="' . BASE_URL . '/pages/auth/login.php">Accedi qui</a>', 'warning');
        SessionManager::set('register_form_data', [
            'nome' => $nome,
            'cognome' => $cognome,
            'email' => ''
        ]);
        $checkStmt->close();
        $conn->close();
        ob_end_clean(); // ✅ AGGIUNTO
        header('Location: ' . BASE_URL . '/pages/auth/register.php');
        exit();
    }

    $checkStmt->close();

    // ✅ HASH PASSWORD SICURO
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // ✅ INSERISCI NUOVO UTENTE
    $stmt = $conn->prepare("INSERT INTO utente (nome, cognome, email, password) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Errore preparazione query inserimento: " . $conn->error);
    }

    $stmt->bind_param("ssss", $nome, $cognome, $email, $hashedPassword);

    if ($stmt->execute()) {
        $new_user_id = $conn->insert_id;

        // ✅ REGISTRAZIONE RIUSCITA
        error_log("New user registered successfully: ID $new_user_id, Email: $email");

        // ✅ PULISCI COMPLETAMENTE LA SESSIONE DOPO REGISTRAZIONE
        SessionManager::remove('register_form_data');

        // ✅ RIGENERA ID SESSIONE PER SICUREZZA
        session_regenerate_id(true);

        SessionManager::setFlashMessage('🎉 Registrazione completata con successo! Ora puoi accedere con le tue credenziali.', 'success');

        $stmt->close();
        $conn->close();

        ob_end_clean(); // ✅ AGGIUNTO
        header('Location: ' . BASE_URL . '/pages/auth/login.php');
        exit();

    } else {
        throw new Exception("Errore durante l'inserimento: " . $stmt->error);
    }

} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());

    SessionManager::setFlashMessage('Errore durante la registrazione. Riprova più tardi.', 'danger');
    SessionManager::set('register_form_data', [
        'nome' => $nome,
        'cognome' => $cognome,
        'email' => $email
    ]);

    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();

    ob_end_clean(); // ✅ AGGIUNTO
    header('Location: ' . BASE_URL . '/pages/auth/register.php');
    exit();
}