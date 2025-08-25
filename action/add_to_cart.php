<?php
/**
 * action/add_to_cart.php - Sistema robusto per aggiungere al carrello
 */

require_once __DIR__ . '/../include/session_manager.php';
require_once __DIR__ . '/../include/config.inc.php';
require_once __DIR__ . '/../include/Cart.php';

// Determina se richiesta AJAX
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Funzione per risposta
function respond($success, $message, $redirect = null, $is_ajax = false) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $message
        ]);
        exit();
    } else {
        SessionManager::setFlashMessage($message, $success ? 'success' : 'danger');
        header('Location: ' . ($redirect ?? BASE_URL));
        exit();
    }
}

// Verifica metodo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Metodo non valido', BASE_URL, $is_ajax);
}

// Recupera e valida dati
$id_prodotto = intval($_POST['id_prodotto'] ?? 0);
$nome_prodotto = trim($_POST['nome_prodotto'] ?? '');
$prezzo = floatval($_POST['prezzo'] ?? 0);
$quantita = intval($_POST['quantita'] ?? 1);

// Gestione tipo prodotto - supporta varie nomenclature
$tipo = $_POST['tipo'] ?? $_POST['tipo_prodotto'] ?? '';

// Normalizza tipo: funko_pop e accessorio sono oggetti nel DB
if (in_array($tipo, ['funko_pop', 'accessorio'])) {
    $tipo = 'oggetto';
}

// URL di redirect (default: home utente)
$redirect_url = $_POST['redirect_url'] ?? BASE_URL . '/pages/home_utente.php';

// Validazione base
if ($id_prodotto <= 0) {
    respond(false, 'ID prodotto non valido', $redirect_url, $is_ajax);
}

if ($quantita < 1 || $quantita > 99) {
    respond(false, 'Quantità deve essere tra 1 e 99', $redirect_url, $is_ajax);
}

if (empty($nome_prodotto)) {
    respond(false, 'Nome prodotto mancante', $redirect_url, $is_ajax);
}

if (!in_array($tipo, ['mystery_box', 'oggetto'])) {
    respond(false, "Tipo prodotto non valido: $tipo", $redirect_url, $is_ajax);
}

try {
    // Inizializza carrello con gestione errori
    $cart = new Cart($config['dbms']['localhost']);

    // Aggiungi al carrello
    $result = $cart->addItem($id_prodotto, $tipo, $nome_prodotto, $prezzo, $quantita);

    // Successo
    respond(
        $result['success'],
        $result['message'] ?? 'Prodotto aggiunto al carrello',
        $redirect_url,
        $is_ajax
    );

} catch (Exception $e) {
    error_log("Errore in add_to_cart.php: " . $e->getMessage());
    respond(false, $e->getMessage(), $redirect_url, $is_ajax);
}