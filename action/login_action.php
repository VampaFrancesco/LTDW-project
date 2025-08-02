<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

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
        $stmt = $conn->prepare("SELECT id_utente, password FROM utente WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows === 1) {
                $stmt->bind_result($id_utente, $hashed_password);
                $stmt->fetch();

                if (password_verify($password, $hashed_password)) {
                    header('Location: ../pages/home_utente.php');
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
    header('Location: ../pages/auth/login.php?error=' . urlencode($error_message));
    exit();
}

header('Location: ../pages/auth/login.php?error=' . urlencode('Si è verificato un errore imprevisto'));
exit();