<?php
/**
 * action/update_cart.php - VERSIONE FINALE FUNZIONANTE
 * Gestisce aggiornamenti del carrello via AJAX
 */

header('Content-Type: application/json');
ob_start();

require_once __DIR__ . '/../include/session_manager.php';
require_once __DIR__ . '/../include/config.inc.php';

// Verifica metodo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
    exit();
}

// Recupera parametri
$action = $_POST['action'] ?? '';
$item_key = $_POST['item_key'] ?? '';
$quantity = intval($_POST['quantity'] ?? 0);

// Validazione input
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

try {
    $message = '';

    if (SessionManager::isLoggedIn()) {
        // UTENTE LOGGATO - Aggiorna database
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
            throw new Exception('Errore di connessione al database');
        }

        // Parsing item_key per determinare tipo e ID prodotto
        if (strpos($item_key, 'mystery_box_') === 0) {
            $product_id = intval(str_replace('mystery_box_', '', $item_key));
            $where_clause = "fk_utente = ? AND fk_mystery_box = ? AND fk_oggetto IS NULL AND stato = 'attivo'";
            $is_mystery_box = true;
        } elseif (strpos($item_key, 'oggetto_') === 0) {
            $product_id = intval(str_replace('oggetto_', '', $item_key));
            $where_clause = "fk_utente = ? AND fk_oggetto = ? AND fk_mystery_box IS NULL AND stato = 'attivo'";
            $is_mystery_box = false;
        } else {
            throw new Exception('Formato item_key non valido');
        }

        if ($product_id <= 0) {
            throw new Exception('ID prodotto non valido');
        }

        if ($action === 'remove') {
            // Rimuovi prodotto dal carrello
            $stmt = $conn->prepare("DELETE FROM carrello WHERE $where_clause");
            $stmt->bind_param("ii", $user_id, $product_id);

            if (!$stmt->execute()) {
                throw new Exception('Errore nella rimozione');
            }

            if ($stmt->affected_rows === 0) {
                throw new Exception('Prodotto non trovato nel carrello');
            }

            $stmt->close();
            $message = 'Prodotto rimosso dal carrello';

        } else { // update
            // Prima recupera il prezzo del prodotto per ricalcolare il totale
            if ($is_mystery_box) {
                $price_stmt = $conn->prepare("SELECT prezzo_box FROM mystery_box WHERE id_box = ?");
            } else {
                $price_stmt = $conn->prepare("SELECT prezzo_oggetto FROM oggetto WHERE id_oggetto = ?");
            }

            $price_stmt->bind_param("i", $product_id);
            $price_stmt->execute();
            $price_result = $price_stmt->get_result();

            if ($price_result->num_rows === 0) {
                throw new Exception('Prodotto non trovato nel database');
            }

            $price_row = $price_result->fetch_assoc();
            $prezzo = $is_mystery_box ? $price_row['prezzo_box'] : $price_row['prezzo_oggetto'];
            $price_stmt->close();

            $nuovo_totale = $quantity * floatval($prezzo);

            // Aggiorna quantità
            $update_stmt = $conn->prepare("
                UPDATE carrello 
                SET quantita = ?, totale = ?, data_ultima_modifica = NOW() 
                WHERE $where_clause
            ");
            $update_stmt->bind_param("idii", $quantity, $nuovo_totale, $user_id, $product_id);

            if (!$update_stmt->execute()) {
                throw new Exception('Errore nell\'aggiornamento');
            }

            if ($update_stmt->affected_rows === 0) {
                throw new Exception('Prodotto non trovato nel carrello');
            }

            $update_stmt->close();
            $message = 'Quantità aggiornata';
        }

        // Aggiorna contatore in sessione
        $count_stmt = $conn->prepare("
            SELECT SUM(quantita) as total 
            FROM carrello 
            WHERE fk_utente = ? AND stato = 'attivo'
        ");
        $count_stmt->bind_param("i", $user_id);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $count_row = $count_result->fetch_assoc();
        $count_stmt->close();

        SessionManager::set('cart_items_count', $count_row['total'] ?? 0);
        $conn->close();

    } else {
        // UTENTE NON LOGGATO - Gestisci sessione
        $cart = SessionManager::get('cart', []);

        if (!isset($cart[$item_key])) {
            throw new Exception('Prodotto non trovato nel carrello');
        }

        if ($action === 'remove') {
            unset($cart[$item_key]);
            $message = 'Prodotto rimosso dal carrello';
        } else { // update
            $cart[$item_key]['quantita'] = $quantity;
            $cart[$item_key]['totale'] = $quantity * floatval($cart[$item_key]['prezzo']);
            $message = 'Quantità aggiornata';
        }

        SessionManager::set('cart', $cart);

        // Aggiorna contatore
        $total_items = 0;
        foreach ($cart as $item) {
            if (is_array($item) && isset($item['quantita'])) {
                $total_items += intval($item['quantita']);
            }
        }
        SessionManager::set('cart_items_count', $total_items);
    }

    // Risposta di successo
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);

} catch (Exception $e) {
    error_log("Errore in update_cart.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Pulisci buffer
ob_end_clean();
?>