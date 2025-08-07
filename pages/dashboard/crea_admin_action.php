<?php
// crea_admin_action.php
// Logica per creare nuovi amministratori

ob_start();

require_once __DIR__ . '/admin_check.php';
require_once __DIR__ . '/../../include/config.inc.php';

// Verifica metodo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: crea_admin.php');
    exit();
}

// Recupera dati dal form
$nome = trim($_POST['nome'] ?? '');
$cognome = trim($_POST['cognome'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$conferma_password = $_POST['conferma_password'] ?? '';
$livello_admin = $_POST['livello_admin'] ?? 'admin';

// Recupera ID admin corrente
$creato_da = SessionManager::get('user_id');

// Validazione base
$errors = [];

if (empty($nome)) $errors[] = "Il nome è obbligatorio";
if (empty($cognome)) $errors[] = "Il cognome è obbligatorio";
if (empty($email)) $errors[] = "L'email è obbligatoria";
if (empty($password)) $errors[] = "La password è obbligatoria";
if (empty($conferma_password)) $errors[] = "La conferma password è obbligatoria";

// Validazione email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Formato email non valido";
}

// Verifica dominio @boxomnia.it
if (!str_ends_with(strtolower($email), '@boxomnia.it')) {
    $errors[] = "L'email deve terminare con @boxomnia.it";
}

// Validazione password
if (strlen($password) < 8) {
    $errors[] = "La password deve essere lunga almeno 8 caratteri";
}

if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $password)) {
    $errors[] = "La password deve contenere almeno una maiuscola, una minuscola e un numero";
}

// Verifica corrispondenza password
if ($password !== $conferma_password) {
    $errors[] = "Le password non corrispondono";
}

// Validazione livello admin
if (!in_array($livello_admin, ['admin', 'super_admin'])) {
    $errors[] = "Livello amministratore non valido";
}

// Se ci sono errori, torna indietro
if (!empty($errors)) {
    SessionManager::setFlashMessage(implode(', ', $errors), 'danger');
    SessionManager::set('admin_form_data', [
        'nome' => $nome,
        'cognome' => $cognome,
        'email' => $email,
        'livello_admin' => $livello_admin
    ]);
    header('Location: crea_admin.php');
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
    header('Location: crea_admin.php');
    exit();
}

// Avvia transazione
$conn->begin_transaction();

try {
    // 1. Verifica se email già esistente
    $checkStmt = $conn->prepare("SELECT id_utente FROM utente WHERE email = ?");
    $checkStmt->bind_param("s", $email);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows > 0) {
        throw new Exception("Email già registrata");
    }
    $checkStmt->close();

    // 2. Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // 3. Inserisci utente
    $userStmt = $conn->prepare("INSERT INTO utente (nome, cognome, email, password) VALUES (?, ?, ?, ?)");
    $userStmt->bind_param("ssss", $nome, $cognome, $email, $hashedPassword);

    if (!$userStmt->execute()) {
        throw new Exception("Errore durante la creazione dell'utente");
    }

    $new_user_id = $conn->insert_id;
    $userStmt->close();

    // 4. Inserisci admin
    $adminStmt = $conn->prepare("INSERT INTO admin (fk_utente, livello_admin, creato_da) VALUES (?, ?, ?)");
    $adminStmt->bind_param("isi", $new_user_id, $livello_admin, $creato_da);

    if (!$adminStmt->execute()) {
        throw new Exception("Errore durante la creazione dell'amministratore");
    }

    $adminStmt->close();

    // Commit transazione
    $conn->commit();
    $conn->close();

    // Successo
    SessionManager::setFlashMessage(
        "Amministratore {$nome} {$cognome} creato con successo!",
        'success'
    );

    // Log dell'operazione (opzionale)
    error_log("Admin creato: {$email} da admin ID: {$creato_da}");

    header('Location: dashboard.php');
    exit();

} catch (Exception $e) {
    // Rollback in caso di errore
    $conn->rollback();
    $conn->close();

    SessionManager::setFlashMessage(
        'Errore durante la creazione: ' . $e->getMessage(),
        'danger'
    );

    SessionManager::set('admin_form_data', [
        'nome' => $nome,
        'cognome' => $cognome,
        'email' => $email,
        'livello_admin' => $livello_admin
    ]);

    header('Location: crea_admin.php');
    exit();
}