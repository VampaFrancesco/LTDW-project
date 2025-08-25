<?php
/**
 * action/cart_ajax.php - Gestore AJAX per operazioni carrello
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../include/session_manager.php';
require_once __DIR__ . '/../include/config.inc.php';
require_once __DIR__ . '/../include/Cart.php';

// Funzione per risposta JSON
function jsonResponse($success, $message, $data = []) {
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $data));
    exit();
}

// Verifica metodo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Metodo non consentito');
}

// Recupera azione
$action = $_POST['action'] ?? '';

if (empty($action)) {
    jsonResponse(false, 'Azione non specificata');
}

try {
    // Inizializza carrello
    $cart = new Cart($config['dbms']['localhost']);

    switch ($action) {
        case 'add':
            // Aggiungi prodotto
            $id_prodotto = intval($_POST['id_prodotto'] ?? 0);
            $tipo = $_POST['tipo'] ?? '';
            $nome = trim($_POST['nome_prodotto'] ?? '');
            $prezzo = floatval($_POST['prezzo'] ?? 0);
            $quantita = intval($_POST['quantita'] ?? 1);

            // Normalizza tipo (funko_pop e accessorio diventano oggetto)
            if (in_array($tipo, ['funko_pop', 'accessorio'])) {
                $tipo = 'oggetto';
            }

            $result = $cart->addItem($id_prodotto, $tipo, $nome, $prezzo, $quantita);
            $totals = $cart->getTotals();

            jsonResponse(
                $result['success'],
                $result['message'],
                ['totals' => $totals]
            );
            break;

        case 'update':
            // Aggiorna quantità
            $item_key = $_POST['item_key'] ?? '';
            $quantity = intval($_POST['quantity'] ?? 0);

            if (empty($item_key)) {
                jsonResponse(false, 'Item key mancante');
            }

            $result = $cart->updateQuantity($item_key, $quantity);
            $totals = $cart->getTotals();

            jsonResponse(
                $result['success'],
                $result['message'],
                ['totals' => $totals]
            );
            break;

        case 'remove':
            // Rimuovi prodotto
            $item_key = $_POST['item_key'] ?? '';

            if (empty($item_key)) {
                jsonResponse(false, 'Item key mancante');
            }

            $result = $cart->removeItem($item_key);
            $totals = $cart->getTotals();

            jsonResponse(
                $result['success'],
                $result['message'],
                ['totals' => $totals]
            );
            break;

        case 'get':
            // Ottieni stato carrello
            $totals = $cart->getTotals();
            jsonResponse(true, 'Carrello caricato', ['totals' => $totals]);
            break;

        case 'clear':
            // Svuota carrello
            $result = $cart->clear();
            jsonResponse($result['success'], $result['message']);
            break;

        default:
            jsonResponse(false, 'Azione non valida: ' . $action);
    }

} catch (Exception $e) {
    error_log("Errore in cart_ajax.php: " . $e->getMessage());
    jsonResponse(false, $e->getMessage());
}