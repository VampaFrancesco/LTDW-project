<?php
// Evita output prima degli header
ob_start();

require_once __DIR__.'/../include/session_manager.php';
require_once __DIR__.'/../include/config.inc.php';

// Inizializza sessione
SessionManager::startSecureSession();

// Verifica metodo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/pages/auth/register.php');
    exit();
}

// Recupera dati form
$nome = trim($_POST['nome'] ?? '');
$cognome = trim($_POST['cognome'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Validazione base
if (empty($nome) || empty($cognome) || empty($email) || empty($password)) {
    SessionManager::setFlashMessage('Tutti i campi sono obbligatori', 'danger');
    SessionManager::set('register_form_data', [
        'nome' => $nome,
        'cognome' => $cognome,
        'email' => $email
    ]);
    header('Location: ' . BASE_URL . '/pages/auth/register.php');
    exit();
}

// Blocca registrazioni con email @boxomnia.it
if (strpos(strtolower($email), '@boxomnia.it') !== false) {
    SessionManager::setFlashMessage('La registrazione con email @boxomnia.it non è consentita. Gli account amministratore devono essere creati dal sistema.', 'danger');
    SessionManager::set('register_form_data', [
        'nome' => $nome,
        'cognome' => $cognome,
        'email' => ''  // Rimuovi email non valida
    ]);
    header('Location: ' . BASE_URL . '/pages/auth/register.php');
    exit();
}

// Validazione email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    SessionManager::setFlashMessage('Formato email non valido', 'danger');
    SessionManager::set('register_form_data', [
        'nome' => $nome,
        'cognome' => $cognome,
        'email' => $email
    ]);
    header('Location: ' . BASE_URL . '/pages/auth/register.php');
    exit();
}

// Validazione password
if (strlen($password) < 6) {
    SessionManager::setFlashMessage('La password deve essere lunga almeno 6 caratteri', 'danger');
    SessionManager::set('register_form_data', [
        'nome' => $nome,
        'cognome' => $cognome,
        'email' => $email
    ]);
    header('Location: ' . BASE_URL . '/pages/auth/register.php');
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
    header('Location: ' . BASE_URL . '/pages/auth/register.php');
    exit();
}

// Verifica se email già esistente
$checkStmt = $conn->prepare("SELECT id_utente FROM utente WHERE email = ?");
if (!$checkStmt) {
    SessionManager::setFlashMessage('Errore interno del server', 'danger');
    header('Location: ' . BASE_URL . '/pages/auth/register.php');
    exit();
}

$checkStmt->bind_param("s", $email);
$checkStmt->execute();
$result = $checkStmt->get_result();

if ($result->num_rows > 0) {
    SessionManager::setFlashMessage('Email già registrata', 'danger');
    SessionManager::set('register_form_data', [
        'nome' => $nome,
        'cognome' => $cognome,
        'email' => ''
    ]);
    $checkStmt->close();
    $conn->close();
    header('Location: ' . BASE_URL . '/pages/auth/register.php');
    exit();
}

$checkStmt->close();

// Hash password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Inserisci nuovo utente
$stmt = $conn->prepare("INSERT INTO utente (nome, cognome, email, password) VALUES (?, ?, ?, ?)");
if (!$stmt) {
    SessionManager::setFlashMessage('Errore interno del server', 'danger');
    $conn->close();
    header('Location: ' . BASE_URL . '/pages/auth/register.php');
    exit();
}

$stmt->bind_param("ssss", $nome, $cognome, $email, $hashedPassword);

if ($stmt->execute()) {
    // Registrazione riuscita
    SessionManager::setFlashMessage('Registrazione completata con successo! Ora puoi accedere.', 'success');
    $stmt->close();
    $conn->close();
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit();
} else {
    // Errore nella registrazione
    SessionManager::setFlashMessage('Errore durante la registrazione', 'danger');
    SessionManager::set('register_form_data', [
        'nome' => $nome,
        'cognome' => $cognome,
        'email' => $email
    ]);
    $stmt->close();
    $conn->close();
    header('Location: ' . BASE_URL . '/pages/auth/register.php');
    exit();
}