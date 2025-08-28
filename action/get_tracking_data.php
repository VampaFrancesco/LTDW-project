<?php
require_once __DIR__ . '/../include/session_manager.php';
require_once __DIR__ . '/../include/config.inc.php';

// Richiedi login
SessionManager::requireLogin();

// Connessione database
$db_config = $config['dbms']['localhost'];
$conn = new mysqli(
    $db_config['host'],
    $db_config['user'],
    $db_config['passwd'],
    $db_config['dbname']
);

if ($conn->connect_error) {
    die("Errore di connessione: " . $conn->connect_error);
}

$id_utente = SessionManager::get('user_id');


// Recupera l'ID dell'ordine dalla query string
$orderId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($orderId <= 0) {
    return null; // Ritorna null se l'ID non è valido
}

try {
    $pdo->beginTransaction();

    // 1. Recupera l'ordine e il tracking
    $sqlOrder = "SELECT
                     o.id_ordine,
                     o.data_ordine,
                     o.tracking,
                     o.stato_ordine,
                     u.nome,
                     u.email,
                     i.via,
                     i.cap,
                     i.citta,
                     i.stato
                 FROM ordine o
                 JOIN utente u ON o.fk_utente = u.id_utente
                 LEFT JOIN indirizzo i ON o.fk_indirizzo = i.id_indirizzo
                 WHERE o.id_ordine = :id_ordine AND o.fk_utente = :user_id";

    $stmtOrder = $pdo->prepare($sqlOrder);
    $stmtOrder->execute([
        ':id_ordine' => $orderId,
        ':user_id' => $_SESSION['user_id'] // Assicurati che l'utente stia visualizzando il proprio ordine
    ]);
    $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

    // Se l'ordine non esiste o non appartiene all'utente, termina
    if (!$order) {
        $pdo->rollBack();
        return null;
    }

    // 2. Recupera la cronologia dello stato dell'ordine
    $sqlHistory = "SELECT stato_nuovo, data_modifica
                   FROM ordine_log
                   WHERE fk_ordine = :id_ordine
                   ORDER BY data_modifica ASC";
    $stmtHistory = $pdo->prepare($sqlHistory);
    $stmtHistory->execute([':id_ordine' => $orderId]);
    $history = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

    $pdo->commit();

    return [
        'order' => $order,
        'history' => $history,
        'client' => ['nome' => $order['nome'], 'email' => $order['email']],
        'address' => ['via' => $order['via'], 'cap' => $order['cap'], 'citta' => $order['citta'], 'stato' => $order['stato']]
    ];
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Errore nel recupero dei dati di tracciamento: " . $e->getMessage());
    return null;
}