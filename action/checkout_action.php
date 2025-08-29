<?php
// action/checkout_action.php - VERSIONE CORRETTA
ob_start();

require_once __DIR__.'/../include/session_manager.php';
require_once __DIR__.'/../include/config.inc.php';

// Richiedi autenticazione
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

// ✅ RECUPERA CARRELLO ATTIVO (CORRETTO)
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
        END as prezzo_unitario,
        CASE 
            WHEN c.fk_mystery_box IS NOT NULL THEN mb.desc_box
            WHEN c.fk_oggetto IS NOT NULL THEN o.desc_oggetto
        END as descrizione_prodotto
    FROM carrello c
    LEFT JOIN mystery_box mb ON c.fk_mystery_box = mb.id_box
    LEFT JOIN oggetto o ON c.fk_oggetto = o.id_oggetto
    WHERE c.fk_utente = ? 
    AND c.stato = 'attivo'
    AND c.id_carrello NOT IN (
        SELECT COALESCE(fk_carrello, 0) FROM ordine WHERE fk_utente = ?
    )
");

$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Verifica carrello vuoto
if ($result->num_rows === 0) {
    SessionManager::setFlashMessage('Il carrello è vuoto. Aggiungi dei prodotti prima di procedere al pagamento.', 'warning');
    $stmt->close();
    $conn->close();
    header('Location: ' . BASE_URL . '/pages/cart.php');
    exit();
}

// Memorizza elementi carrello
$carrello_items = [];
$totale_carrello = 0;

while ($row = $result->fetch_assoc()) {
    $carrello_items[] = $row;
    $totale_carrello += $row['totale'];
}
$stmt->close();

// Verifica indirizzi
$stmt = $conn->prepare("
    SELECT id_indirizzo, via, civico, cap, citta, provincia, nazione 
    FROM indirizzo_spedizione 
    WHERE fk_utente = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$indirizzi_result = $stmt->get_result();

if ($indirizzi_result->num_rows === 0) {
    SessionManager::setFlashMessage('Devi aggiungere un indirizzo di spedizione prima di procedere al pagamento.', 'warning');
    SessionManager::set('redirect_after_address', BASE_URL . '/pages/pagamento.php');
    $stmt->close();
    $conn->close();
    header('Location: ' . BASE_URL . '/pages/aggiungi_indirizzo.php');
    exit();
}

$stmt->close();
$conn->close();

// ✅ SALVA DATI CHECKOUT NELLA SESSIONE (CORRETTO)
SessionManager::set('checkout_data', [
    'items' => $carrello_items,
    'totale' => $totale_carrello,
    'timestamp' => time(),
    'user_id' => $user_id
]);

// Reindirizza a pagamento.php
header('Location: ' . BASE_URL . '/pages/pagamento.php');
exit();

ob_end_flush();
?>