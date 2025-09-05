<?php
// api/wishlist_api.php
require_once __DIR__ . '/../include/session_manager.php';
require_once __DIR__ . '/../include/config.inc.php';

header('Content-Type: application/json');

// Verifica login
if (!SessionManager::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Non autenticato']);
    exit;
}

$user_id = SessionManager::getUserId();

// Connessione database
$db = $config['dbms']['localhost'];
$conn = new mysqli($db['host'], $db['user'], $db['passwd'], $db['dbname']);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Errore database']);
    exit;
}

$action = $_GET['action'] ?? '';

switch($action) {
    case 'get_items':
        $page = intval($_GET['page'] ?? 1);
        $limit = intval($_GET['limit'] ?? 12);
        $offset = ($page - 1) * $limit;

        // Query per ottenere gli items con paginazione
        $query = "
    SELECT 
        w.id_wishlist,
        w.data_aggiunta,
        COALESCE(o.id_oggetto, mb.id_box) as item_id,
        COALESCE(o.nome_oggetto, mb.nome_box) as nome,
        COALESCE(o.prezzo_oggetto, mb.prezzo_box) as prezzo,
        COALESCE(o.desc_oggetto, mb.desc_box) as descrizione,
        COALESCE(o.quant_oggetto, mb.quantita_box) as quantita,
        CASE 
            WHEN o.id_oggetto IS NOT NULL THEN 'oggetto'
            ELSE 'box'
        END as tipo,
        COALESCE(img_o.nome_img, img_mb.nome_img) as immagine
    FROM wishlist w
    LEFT JOIN oggetto o ON w.fk_oggetto = o.id_oggetto
    LEFT JOIN mystery_box mb ON w.fk_box = mb.id_box
    LEFT JOIN immagine img_o ON o.id_oggetto = img_o.fk_oggetto
    LEFT JOIN immagine img_mb ON mb.id_box = img_mb.fk_box
    WHERE w.fk_utente = ?
    ORDER BY w.data_aggiunta DESC
    LIMIT ? OFFSET ?
";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("iii", $user_id, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Conta il totale per la paginazione
        $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM wishlist WHERE fk_utente = ?");
        $count_stmt->bind_param("i", $user_id);
        $count_stmt->execute();
        $total = $count_stmt->get_result()->fetch_assoc()['total'];
        $count_stmt->close();

        echo json_encode([
            'success' => true,
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'total_pages' => ceil($total / $limit)
        ]);
        break;

    case 'get_summary':
        // Ottieni un riepilogo della wishlist
        $query = "
            SELECT 
                COUNT(*) as total_items,
                SUM(COALESCE(o.prezzo_oggetto, mb.prezzo_box)) as total_value,
                COUNT(DISTINCT CASE WHEN o.id_oggetto IS NOT NULL THEN o.id_oggetto END) as total_objects,
                COUNT(DISTINCT CASE WHEN mb.id_box IS NOT NULL THEN mb.id_box END) as total_boxes
            FROM wishlist w
            LEFT JOIN oggetto o ON w.fk_oggetto = o.id_oggetto
            LEFT JOIN mystery_box mb ON w.fk_box = mb.id_box
            WHERE w.fk_utente = ?
        ";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $summary = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        echo json_encode([
            'success' => true,
            'summary' => $summary
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Azione non valida']);
}

$conn->close();
?>