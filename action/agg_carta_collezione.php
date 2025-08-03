<?php
// C:\xampp\htdocs\LTDW-project\actions\agg_carta_collezione.php

// Includi il file di configurazione
$configPath = __DIR__ . '/../include/config.inc.php';
if (!file_exists($configPath)) {
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/pages/collezione.php?add_status=error&add_message=' . urlencode('Errore interno del server: file di configurazione non trovato.'));
    exit();
}
require_once $configPath; 

// Assicurati che l'utente sia loggato
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/pages/auth/login.php');
    exit();
}

// Solo richieste POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/pages/collezione.php?add_status=error&add_message=' . urlencode('Metodo non consentito.'));
    exit();
}

$user_id = $_SESSION['user_id'];
$card_name = trim($_POST['card_name'] ?? '');
$card_quantity = (int)($_POST['card_quantity'] ?? 0);

// Validazione minima
if (empty($card_name) || $card_quantity < 0) {
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/pages/collezione.php?add_status=error&add_message=' . urlencode('Nome carta o quantità non validi.'));
    exit();
}

// Accedi alle credenziali dal global $config array
if (!isset($config['dbms']['localhost']['host'], $config['dbms']['localhost']['user'], $config['dbms']['localhost']['passwd'], $config['dbms']['localhost']['dbname'])) {
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/pages/collezione.php?add_status=error&add_message=' . urlencode('Errore: Credenziali database incomplete.'));
    exit();
}

$db_host = $config['dbms']['localhost']['host'];
$db_user = $config['dbms']['localhost']['user'];
$db_passwd = $config['dbms']['localhost']['passwd'];
$db_name = $config['dbms']['localhost']['dbname'];

// Connessione DB
$conn = new mysqli(
    $db_host,
    $db_user,
    $db_passwd,
    $db_name
);

if ($conn->connect_error) {
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/pages/collezione.php?add_status=error&add_message=' . urlencode('Errore di connessione al database.'));
    exit();
}

// Abilita la reportistica degli errori MySQLi
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // 1. Trova l'id_oggetto della carta tramite il nome E il tipo_oggetto 'Carta Singola'
    // Questo script aggiunge specificamente "Carte Singole".
    $sql_find_card = "SELECT id_oggetto FROM oggetto WHERE nome_oggetto = ? AND tipo_oggetto = 'Carta Singola'";
    $stmt_find_card = $conn->prepare($sql_find_card);
    if (!$stmt_find_card) {
        throw new Exception("Errore nella preparazione della query di ricerca carta: " . $conn->error);
    }
    $stmt_find_card->bind_param("s", $card_name);
    $stmt_find_card->execute();
    $result_find_card = $stmt_find_card->get_result();

    $oggetto_id = null;
    if ($result_find_card->num_rows > 0) {
        $oggetto_id = $result_find_card->fetch_assoc()['id_oggetto'];
    }
    $stmt_find_card->close();

    if (!$oggetto_id) {
        // Carta non trovata nel database degli oggetti disponibili come 'Carta Singola'
        header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/pages/collezione.php?add_status=error&add_message=' . urlencode('La carta "' . $card_name . '" non è stata trovata come "Carta Singola" nel catalogo. Assicurati il nome sia corretto.'));
        exit();
    }

    // 2. Controlla se l'utente ha già questa carta nella sua collezione
    $sql_check_user_card = "SELECT quantita_ogg FROM oggetto_utente WHERE fk_utente = ? AND fk_oggetto = ?";
    $stmt_check_user_card = $conn->prepare($sql_check_user_card);
    if (!$stmt_check_user_card) {
        throw new Exception("Errore nella preparazione della query di controllo collezione: " . $conn->error);
    }
    $stmt_check_user_card->bind_param("ii", $user_id, $oggetto_id);
    $stmt_check_user_card->execute();
    $result_check_user_card = $stmt_check_user_card->get_result();

    if ($result_check_user_card->num_rows > 0) {
        // La carta esiste già per l'utente, aggiorna la quantità
        if ($card_quantity === 0) {
             $sql_update_card = "DELETE FROM oggetto_utente WHERE fk_utente = ? AND fk_oggetto = ?";
             $message = 'Carta rimossa dalla collezione.';
        } else {
             $sql_update_card = "UPDATE oggetto_utente SET quantita_ogg = ? WHERE fk_utente = ? AND fk_oggetto = ?";
             $message = 'Quantità della carta aggiornata con successo!';
        }
        
        $stmt_update_card = $conn->prepare($sql_update_card);
        if (!$stmt_update_card) {
            throw new Exception("Errore nella preparazione della query di update/delete: " . $conn->error);
        }
        if ($card_quantity === 0) {
            $stmt_update_card->bind_param("ii", $user_id, $oggetto_id);
        } else {
            $stmt_update_card->bind_param("iii", $card_quantity, $user_id, $oggetto_id);
        }
        $stmt_update_card->execute();
        $stmt_update_card->close();
    } else {
        // La carta non esiste per l'utente, inserisci una nuova riga (solo se quantità > 0)
        if ($card_quantity > 0) {
            $sql_insert_card = "INSERT INTO oggetto_utente (fk_utente, fk_oggetto, quantita_ogg) VALUES (?, ?, ?)";
            $stmt_insert_card = $conn->prepare($sql_insert_card);
            if (!$stmt_insert_card) {
                throw new Exception("Errore nella preparazione della query di insert collezione: " . $conn->error);
            }
            $stmt_insert_card->bind_param("iii", $user_id, $oggetto_id, $card_quantity);
            $stmt_insert_card->execute();
            $stmt_insert_card->close();
            $message = 'Carta aggiunta alla collezione con successo!';
        } else {
            $message = 'Quantità specificata è zero, nessuna azione necessaria.';
        }
    }

    // Reindirizza con successo
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/pages/collezione.php?add_status=success&add_message=' . urlencode($message));
    exit();

} catch (mysqli_sql_exception $e) {
    error_log("Errore aggiunta/aggiornamento collezione: " . $e->getMessage());
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/pages/collezione.php?add_status=error&add_message=' . urlencode('Errore durante l\'operazione: ' . $e->getMessage()));
    exit();
} finally {
    // Assicurati che la connessione venga chiusa
    if ($conn && !$conn->connect_error) {
        $conn->close();
    }
}
?>