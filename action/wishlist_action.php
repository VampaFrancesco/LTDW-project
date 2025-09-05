<?php
// action/wishlist_action.php
require_once __DIR__ . '/../include/session_manager.php';
require_once __DIR__ . '/../include/config.inc.php';

// Verifica che l'utente sia loggato
SessionManager::requireLogin();
$user_id = SessionManager::getUserId();

// Connessione database
$db = $config['dbms']['localhost'];
$conn = new mysqli($db['host'], $db['user'], $db['passwd'], $db['dbname']);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Errore di connessione']));
}

// Imposta header JSON
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'add':
        $item_id = intval($_POST['item_id'] ?? 0);
        $item_type = $_POST['item_type'] ?? '';

        if ($item_id <= 0 || !in_array($item_type, ['oggetto', 'box'])) {
            echo json_encode(['success' => false, 'message' => 'Parametri non validi']);
            exit;
        }

        $fk_oggetto = ($item_type === 'oggetto') ? $item_id : null;
        $fk_box = ($item_type === 'box') ? $item_id : null;

        // Verifica se l'item esiste già
        $check_query = "SELECT id_wishlist FROM wishlist WHERE fk_utente = ? AND " . (($item_type === 'oggetto') ? "fk_oggetto = ?" : "fk_box = ?");
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("ii", $user_id, $item_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Prodotto già presente nella wishlist']);
            $check_stmt->close();
            exit;
        }
        $check_stmt->close();

        // Inserisci
        $insert_query = "INSERT INTO wishlist (fk_utente, fk_oggetto, fk_box) VALUES (?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("iii", $user_id, $fk_oggetto, $fk_box);

        if ($insert_stmt->execute()) {
            // ✅ FIX: Recupera l'ID dell'elemento appena inserito
            $new_wishlist_id = $conn->insert_id;

            // Conta gli elementi
            $count_query = "SELECT COUNT(*) as count FROM wishlist WHERE fk_utente = ?";
            $count_stmt = $conn->prepare($count_query);
            $count_stmt->bind_param("i", $user_id);
            $count_stmt->execute();
            $wishlist_count = $count_stmt->get_result()->fetch_assoc()['count'];
            $count_stmt->close();

            echo json_encode([
                'success' => true,
                'message' => 'Prodotto aggiunto alla wishlist',
                'wishlist_count' => $wishlist_count,
                'wishlist_id' => $new_wishlist_id // ✅ FIX: Invia l'ID al JavaScript
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Errore nell\'aggiunta alla wishlist']);
        }
        $insert_stmt->close();
        break;

    case 'remove':
        $wishlist_id = intval($_POST['wishlist_id'] ?? 0);

        if ($wishlist_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID non valido']);
            exit;
        }

        $delete_query = "DELETE FROM wishlist WHERE id_wishlist = ? AND fk_utente = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param("ii", $wishlist_id, $user_id);

        if ($delete_stmt->execute() && $delete_stmt->affected_rows > 0) {
            $count_query = "SELECT COUNT(*) as count FROM wishlist WHERE fk_utente = ?";
            $count_stmt = $conn->prepare($count_query);
            $count_stmt->bind_param("i", $user_id);
            $count_stmt->execute();
            $wishlist_count = $count_stmt->get_result()->fetch_assoc()['count'];
            $count_stmt->close();

            echo json_encode([
                'success' => true,
                'message' => 'Prodotto rimosso dalla wishlist',
                'wishlist_count' => $wishlist_count
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Errore nella rimozione o prodotto non trovato']);
        }
        $delete_stmt->close();
        break;

    // ✅ FIX: Aggiunta l'azione richiesta da `loadWishlistStates()` in JS
    case 'get_user_wishlist':
        $query = "
            SELECT 
                id_wishlist,
                COALESCE(fk_oggetto, fk_box) as item_id,
                CASE 
                    WHEN fk_oggetto IS NOT NULL THEN 'oggetto'
                    ELSE 'box'
                END as item_type
            FROM wishlist 
            WHERE fk_utente = ?
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $wishlist_items = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        echo json_encode(['success' => true, 'wishlist' => $wishlist_items]);
        break;

    case 'get_wishlist_id':
        $item_id = intval($_GET['item_id'] ?? 0);
        $item_type = $_GET['item_type'] ?? '';

        if ($item_id <= 0 || !in_array($item_type, ['oggetto', 'box'])) {
            echo json_encode(['success' => false, 'wishlist_id' => null]);
            exit;
        }

        $query = "SELECT id_wishlist FROM wishlist WHERE fk_utente = ? AND " . (($item_type === 'oggetto') ? "fk_oggetto = ?" : "fk_box = ?");
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $user_id, $item_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            echo json_encode(['success' => true, 'wishlist_id' => $result->fetch_assoc()['id_wishlist']]);
        } else {
            echo json_encode(['success' => false, 'wishlist_id' => null]);
        }
        $stmt->close();
        break;

    // ✅ FIX: Rimosso il 'case' duplicato per 'check'
    case 'check':
        $item_id = intval($_GET['item_id'] ?? 0);
        $item_type = $_GET['item_type'] ?? '';

        if ($item_id <= 0 || !in_array($item_type, ['oggetto', 'box'])) {
            echo json_encode(['success' => false, 'in_wishlist' => false]);
            exit;
        }

        $check_query = "SELECT id_wishlist FROM wishlist WHERE fk_utente = ? AND " . (($item_type === 'oggetto') ? "fk_oggetto = ?" : "fk_box = ?");
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("ii", $user_id, $item_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $row = $check_result->fetch_assoc();
            echo json_encode([
                'success' => true,
                'in_wishlist' => true,
                'wishlist_id' => $row['id_wishlist']
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'in_wishlist' => false,
                'wishlist_id' => null
            ]);
        }
        $check_stmt->close();
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Azione non valida']);
}

$conn->close();