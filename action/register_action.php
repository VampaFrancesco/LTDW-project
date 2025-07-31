<?php
// register_action.php

// 1) Carico configurazione
$config = include __DIR__ . '/../include/config.inc.php';

// 2) Connessione MySQLi (host, user, passwd, dbname)
$conn = new mysqli(
    $config['host'],
    $config['user'],
    $config['passwd'],
    $config['dbname']
);

// Verifica connessione
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'errors'  => ['Connessione al database fallita.']
    ]);
    exit;
}

// Forzo mysqli a lanciare eccezioni (opzionale ma utile)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 3) Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    exit;
}

// 4) Recupero e validazione dei campi
$nome     = trim($_POST['nome']     ?? '');
$cognome  = trim($_POST['cognome']  ?? '');
$email    = trim($_POST['email']    ?? '');
$password = $_POST['password']      ?? '';

$errors = [];
if ($nome === '')     $errors[] = 'Nome obbligatorio.';
if ($cognome === '')  $errors[] = 'Cognome obbligatorio.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email non valida.';
}
if (strlen($password) < 6) {
    $errors[] = 'Password troppo corta (minimo 6 caratteri).';
}

if (!empty($errors)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// 5) Hash della password
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// 6) Prepared statement e debug
try {
    $stmt = $conn->prepare(
        "INSERT INTO utente (nome, cognome, email, password)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param('ssss', $nome, $cognome, $email, $passwordHash);

    if ($stmt->execute()) {
        // Controllo quante righe sono state influenzate
        $n = $stmt->affected_rows;
        header('Content-Type: application/json');
        echo json_encode([
            'success'       => true,
            'affected_rows' => $n
        ]);
    } else {
        // Se execute() ritorna false, mostro l’errore
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error'   => $stmt->error
        ]);
    }

} catch (mysqli_sql_exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'exception' => $e->getMessage(),
        'code'      => $e->getCode()
    ]);
}

$stmt->close();
$conn->close();
exit;
