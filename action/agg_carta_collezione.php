<?php
// C:\xampp\htdocs\LTDW-project\actions\add_card_to_collection.php


// Assicurati che l'utente sia loggato
if (!isset($_SESSION['user_id'])) {
    // Reindirizza al login se non autenticato, usando BASE_URL
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
// La checkbox 'card_obtained' non è strettamente necessaria per la logica di aggiunta/quantità,
// ma se la vuoi usare per altri scopi, la variabile è impostata correttamente.
// $card_obtained = isset($_POST['card_obtained']) ? 1 : 0; 

// Validazione minima
if (empty($card_name) || $card_quantity < 0) {
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/pages/collezione.php?add_status=error&add_message=' . urlencode('Nome carta o quantità non validi.'));
    exit();
}

// Carica configurazione DB. Il percorso da 'actions' a 'include' è '../include/config.inc.php'
$db_config = require_once __DIR__ . '/../include/config.inc.php';

// Connessione DB
$conn = new mysqli(
    $db_config['host'],
    $db_config['user'],
    $db_config['passwd'],
    $db_config['dbname']
);

if ($conn->connect_error) {
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/pages/collezione.php?add_status=error&add_message=' . urlencode('Errore di connessione al database.'));
    exit();
}

try {
    // 1. Trova l'id_oggetto della carta tramite il nome
    $sql_find_card = "SELECT id_oggetto FROM oggetto WHERE nome_oggetto = ?";
    $stmt_find_card = $conn->prepare($sql_find_card);
    $stmt_find_card->bind_param("s", $card_name);
    $stmt_find_card->execute();
    $result_find_card = $stmt_find_card->get_result();

    if ($result_find_card->num_rows === 0) {
        // Carta non trovata nel database degli oggetti disponibili
        header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/pages/collezione.php?add_status=error&add_message=' . urlencode('Carta non trovata nel catalogo. Assicurati il nome sia corretto.'));
        exit();
    }

    $oggetto_id = $result_find_card->fetch_assoc()['id_oggetto'];
    $stmt_find_card->close();

    // 2. Controlla se l'utente ha già questa carta nella sua collezione
    $sql_check_user_card = "SELECT quantita_ogg FROM oggetto_utente WHERE fk_utente = ? AND fk_oggetto = ?";
    $stmt_check_user_card = $conn->prepare($sql_check_user_card);
    $stmt_check_user_card->bind_param("ii", $user_id, $oggetto_id);
    $stmt_check_user_card->execute();
    $result_check_user_card = $stmt_check_user_card->get_result();

    if ($result_check_user_card->num_rows > 0) {
        // La carta esiste già per l'utente, aggiorna la quantità
        // Se la quantità è 0, puoi scegliere di eliminarla dalla collezione
        if ($card_quantity === 0) {
             $sql_update_card = "DELETE FROM oggetto_utente WHERE fk_utente = ? AND fk_oggetto = ?";
             $message = 'Carta rimossa dalla collezione.';
        } else {
             $sql_update_card = "UPDATE oggetto_utente SET quantita_ogg = ? WHERE fk_utente = ? AND fk_oggetto = ?";
             $message = 'Quantità della carta aggiornata con successo!';
        }
       
        $stmt_update_card = $conn->prepare($sql_update_card);
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
    $conn->close();
}
?>