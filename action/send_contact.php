<?php
// action/send_contact.php
require_once __DIR__ . '/../include/session_manager.php';
require_once __DIR__ . '/../include/config.inc.php';

SessionManager::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/pages/contatti.php');
    exit;
}

$user_id = SessionManager::getUserId();
$nome = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$messaggio = trim($_POST['message'] ?? '');

// Validazione base
$errors = [];

if (empty($nome)) {
    $errors[] = "Il nome è obbligatorio";
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Email non valida";
}

if (empty($messaggio)) {
    $errors[] = "Il messaggio è obbligatorio";
}

if (!empty($errors)) {
    SessionManager::set('flash_message', [
        'type' => 'danger',
        'content' => 'Errori nella compilazione: ' . implode(', ', $errors)
    ]);
    header('Location: ' . BASE_URL . '/pages/contatti.php');
    exit;
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
    SessionManager::set('flash_message', [
        'type' => 'danger',
        'content' => 'Errore di connessione al database'
    ]);
    header('Location: ' . BASE_URL . '/pages/contatti.php');
    exit;
}

try {
    // Determina l'oggetto della richiesta
    $oggetto = "Richiesta di supporto da " . $nome;
    $messaggio_completo = "Nome: " . $nome . "\n";
    $messaggio_completo .= "Email: " . $email . "\n\n";
    $messaggio_completo .= "Messaggio:\n" . $messaggio;
    
    // Determina la priorità basandosi su parole chiave
    $priorita = 'normale';
    $parole_urgenti = ['urgente', 'importante', 'problema grave', 'non funziona', 'errore', 'bug', 'rimborso'];
    
    foreach ($parole_urgenti as $parola) {
        if (stripos($messaggio, $parola) !== false) {
            $priorita = 'alta';
            break;
        }
    }
    
    // Inserisci la richiesta
    $stmt = $conn->prepare("
        INSERT INTO richieste_supporto (fk_utente, oggetto, messaggio, priorita) 
        VALUES (?, ?, ?, ?)
    ");
    
    $stmt->bind_param("isss", $user_id, $oggetto, $messaggio_completo, $priorita);
    
    if ($stmt->execute()) {
        SessionManager::set('flash_message', [
            'type' => 'success',
            'content' => 'La tua richiesta è stata inviata con successo! Ti risponderemo al più presto.'
        ]);
        
        // Redirect alla pagina di supporto per vedere la richiesta
        header('Location: ' . BASE_URL . '/pages/supporto_utente.php');
    } else {
        throw new Exception('Errore nell\'inserimento della richiesta');
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Errore invio richiesta supporto: " . $e->getMessage());
    SessionManager::set('flash_message', [
        'type' => 'danger',
        'content' => 'Errore nell\'invio della richiesta. Riprova più tardi.'
    ]);
    header('Location: ' . BASE_URL . '/pages/contatti.php');
} finally {
    $conn->close();
}
?>