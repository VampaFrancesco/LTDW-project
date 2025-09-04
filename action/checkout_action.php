<?php
// action/checkout_action.php - VERSIONE CORRETTA
ob_start();

require_once __DIR__.'/../include/session_manager.php';
require_once __DIR__.'/../include/config.inc.php';

SessionManager::requireLogin();
$user_id = SessionManager::get('user_id');

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
    header('Location: ' . BASE_URL . '/pages/cart.php');
    exit();
}

// ✅ RECUPERA CARRELLO ATTIVO
$stmt = $conn->prepare("
    SELECT 
        c.id_carrello,
        c.totale,
        c.quantita,
        c.fk_mystery_box,
        c.fk_oggetto,
        CASE 
            WHEN c.fk_mystery_box IS NOT NULL THEN mb.nome_box
            WHEN c.fk_oggetto IS NOT NULL THEN o.nome_oggetto
        END as nome_prodotto,
        CASE 
            WHEN c.fk_mystery_box IS NOT NULL THEN mb.prezzo_box
            WHEN c.fk_oggetto IS NOT NULL THEN o.prezzo_oggetto
        END as prezzo_unitario
    FROM carrello c
    LEFT JOIN mystery_box mb ON c.fk_mystery_box = mb.id_box
    LEFT JOIN oggetto o ON c.fk_oggetto = o.id_oggetto
    WHERE c.fk_utente = ? 
    AND c.stato = 'attivo'
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Verifica carrello vuoto
if ($result->num_rows === 0) {
    SessionManager::setFlashMessage('Il carrello è vuoto.', 'warning');
    header('Location: ' . BASE_URL . '/pages/cart.php');
    exit();
}

// Salva dati carrello
$carrello_items = [];
$totale_carrello = 0;

while ($row = $result->fetch_assoc()) {
    $carrello_items[] = $row;
    $totale_carrello += $row['totale'];
}

// Verifica indirizzi di spedizione
$stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM indirizzo_spedizione WHERE fk_utente = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$address_count = $result->fetch_assoc()['count'];

if ($address_count === 0) {
    SessionManager::setFlashMessage('Aggiungi un indirizzo prima di procedere.', 'warning');
    SessionManager::set('redirect_after_address', BASE_URL . '/pages/pagamento.php');
    header('Location: ' . BASE_URL . '/pages/aggiungi_indirizzo.php');
    exit();
}

$stmt->close();
$conn->close();

// ✅ SALVA DATI CHECKOUT NELLA SESSIONE
SessionManager::set('checkout_data', [
    'items' => $carrello_items,
    'totale' => $totale_carrello,
    'timestamp' => time(),
    'user_id' => $user_id
]);

// ✅ REINDIRIZZA A PAGAMENTO.PHP
header('Location: ' . BASE_URL . '/pages/pagamento.php');
exit();
?>