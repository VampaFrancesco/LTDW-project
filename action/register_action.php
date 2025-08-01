<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// register_action.php

// 1) Carica configurazione
$configPath = __DIR__ . '/../include/config.inc.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    exit(json_encode([
        'success' => false,
        'errors'  => ['File di configurazione non trovato.']
    ]));
}

// *** MODIFICA QUI: Includi il file per rendere disponibile la variabile $config ***
require_once $configPath; // Usa require_once per includere la variabile $config

// *** E poi assegna la parte specifica del database di $config a $db_config ***
// Questo è cruciale per usare correttamente i valori di host, user, ecc.
// Se il tuo config.inc.php definisce $config['dbms']['localhost'] allora usa quello:
$db_config = $config['dbms']['localhost'];

// Se il tuo config.inc.php definisce $config direttamente con host, user, ecc., allora usa:
// $db_config = $config; // Scegli questa riga o quella sopra, a seconda del config.inc.php

// 2) Connessione MySQLi (HOST, USER, PASSWD, DBNAME)
$conn = new mysqli(
    $db_config['host'], // Ora usa $db_config
    $db_config['user'], // Ora usa $db_config
    $db_config['passwd'], // Ora usa $db_config
    $db_config['dbname']  // Ora usa $db_config
);
if ($conn->connect_error) {
    http_response_code(500);
    exit(json_encode([
        'success' => false,
        'errors'  => ['Connessione al database fallita: ' . $conn->connect_error]
    ]));
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 3) Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// 4) Recupera e valida
$nome    = trim($_POST['nome']    ?? '');
$cognome = trim($_POST['cognome'] ?? '');
$email   = trim($_POST['email']   ?? '');
$pass    = $_POST['password']     ?? '';

$errors = [];
if ($nome === '')     $errors[] = 'Nome obbligatorio.';
if ($cognome === '')  $errors[] = 'Cognome obbligatorio.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email non valida.';
}
if (strlen($pass) < 6) {
    $errors[] = 'Password troppo corta (min 6 caratteri).';
}
if (!empty($errors)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// 5) Hash
$passwordHash = password_hash($pass, PASSWORD_DEFAULT);

// 6) Insert con prepared statement
try {
    $stmt = $conn->prepare(
        "INSERT INTO utente (nome, cognome, email, password)
        VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param('ssss', $nome, $cognome, $email, $passwordHash);
    $stmt->execute();

    // Redirect al login su success
    header('Location: /LTDW-project/pages/auth/login.php?registered=1');
    exit;


} catch (mysqli_sql_exception $e) {
    // 1062 = duplicate entry
    $msg = $e->getCode() === 1062
        ? 'Email già registrata.'
        : 'Errore interno. Riprova più tardi.';

    header('Location: /register.php?error=' . urlencode($msg));
    exit;
}