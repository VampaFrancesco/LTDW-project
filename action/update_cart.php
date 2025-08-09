<?php
/**
 * update_cart.php
 * Gestisce aggiornamenti del carrello via AJAX
 * Compatibile con SessionManager e get_cart.php esistenti
 */

header('Content-Type: application/json');
ob_start();

require_once __DIR__ . '/../include/session_manager.php';
require_once __DIR__ . '/../include/config.inc.php';

// Verifica metodo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
    exit();
}

// Recupera parametri
$action = $_POST['action'] ?? '';
$item_key = $_POST['item_key'] ?? '';
$quantity = intval($_POST['quantity'] ?? 0);

// Validazione base
if (empty($action) || empty($item_key)) {
    echo json_encode(['success' => false, 'message' => 'Parametri mancanti']);
    exit();
}

if (!in_array($action, ['update', 'remove'])) {
    echo json_encode(['success' => false, 'message' => 'Azione non valida']);
    exit();
}

if ($action === 'update' && ($quantity < 1 || $quantity > 99)) {
    echo json_encode(['success' => false, 'message' => 'Quantità non valida (1-99)']);
    exit();
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
    echo json_encode(['success' => false, 'message' => 'Errore di connessione']);
    exit();
}

try {
    if (SessionManager::isLoggedIn()) {
        // Utente loggato - aggiorna database
        $user_id = SessionManager::get('user_id');

        // Parsing item_key per determinare tipo e ID
        if (strpos($item_key, 'mystery_box_') === 0) {
            $product_id = intval(str_replace('mystery_box_', '', $item_key));
            $where_clause = "fk_utente = ? AND fk_mystery_box = ? AND fk_oggetto IS NULL";
        } elseif (strpos($item_key, 'oggetto_') === 0) {
            $product_id = intval(str_replace('oggetto_', '', $item_key));
            $where_clause = "fk_utente = ? AND fk_oggetto = ? AND fk_mystery_box IS NULL";
        } else {
            throw new Exception('Formato item_key non valido');
        }

        if ($action === 'remove') {
            // Rimuovi item dal carrello
            $stmt = $conn->prepare("DELETE FROM carrello WHERE $where_clause");
            $stmt->bind_param("ii", $user_id, $product_id);

            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $message = 'Prodotto rimosso dal carrello';
                } else {
                    throw new Exception('Prodotto non trovato nel carrello');
                }
            } else {
                throw new Exception('Errore nella rimozione');
            }
            $stmt->close();

        } else { // update
            // Recupera prezzo corrente
            if (strpos($item_key, 'mystery_box_') === 0) {
                $stmt = $conn->prepare("SELECT prezzo_box FROM mystery_box WHERE id_box = ?");
            } else {
                $stmt = $conn->prepare("SELECT prezzo_oggetto FROM oggetto WHERE id_oggetto = ?");
            }
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $price_row = $result->fetch_assoc();
            $stmt->close();

            if (!$price_row) {
                throw new Exception('Prodotto non trovato');
            }

            $price = strpos($item_key, 'mystery_box_') === 0 ? $price_row['prezzo_box'] : $price_row['prezzo_oggetto'];
            $new_total = $price * $quantity;

            // Aggiorna quantità e totale
            $stmt = $conn->prepare("UPDATE carrello SET quantita = ?, totale = ? WHERE $where_clause");
            $stmt->bind_param("idii", $quantity, $new_total, $user_id, $product_id);

            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $message = 'Quantità aggiornata';
                } else {
                    throw new Exception('Prodotto non trovato nel carrello');
                }
            } else {
                throw new Exception('Errore nell\'aggiornamento');
            }
            $stmt->close();
        }

        // Aggiorna contatore carrello
        $stmt = $conn->prepare("SELECT SUM(quantita) as total FROM carrello WHERE fk_utente = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        SessionManager::set('cart_items_count', $row['total'] ?? 0);

    } else {
        // Utente non loggato - usa sessione
        if ($action === 'remove') {
            if (SessionManager::removeFromCart($item_key)) {
                $message = 'Prodotto rimosso dal carrello';
            } else {
                throw new Exception('Prodotto non trovato nel carrello');
            }
        } else { // update
            if (SessionManager::updateCartQuantity($item_key, $quantity)) {
                $message = 'Quantità aggiornata';
            } else {
                throw new Exception('Prodotto non trovato nel carrello');
            }
        }
    }

    // Recupera dati carrello aggiornati usando il TUO get_cart.php
    $cart_data = include __DIR__ . '/get_cart.php';

    echo json_encode([
        'success' => true,
        'message' => $message,
        'cart_total_items' => $cart_data['total_items'],
        'cart_subtotal' => number_format($cart_data['subtotal'], 2, ',', '.'),
        'cart_total' => number_format($cart_data['total'], 2, ',', '.'),
        'shipping_cost' => number_format($cart_data['shipping_cost'], 2, ',', '.'),
        'is_shipping_free' => $cart_data['shipping_cost'] == 0
    ]);

} catch (Exception $e) {
    error_log("Errore in update_cart.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
ob_end_flush();
?>