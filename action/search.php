<?php
/**
 * action/search.php - Gestione ricerca prodotti
 */

require_once __DIR__ . '/../include/session_manager.php';
require_once __DIR__ . '/../include/config.inc.php';

// Determina se è una richiesta AJAX
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Ottieni parametri di ricerca
$query = trim($_GET['q'] ?? $_POST['q'] ?? '');
$category = $_GET['category'] ?? $_POST['category'] ?? '';
$type = $_GET['type'] ?? $_POST['type'] ?? '';
$min_price = floatval($_GET['min_price'] ?? $_POST['min_price'] ?? 0);
$max_price = floatval($_GET['max_price'] ?? $_POST['max_price'] ?? 0);
$order_by = $_GET['order'] ?? $_POST['order'] ?? 'relevance';
$page = intval($_GET['page'] ?? $_POST['page'] ?? 1);
$per_page = intval($_GET['per_page'] ?? $_POST['per_page'] ?? 12);

// Validazione
if (strlen($query) < 2 && empty($category) && empty($type)) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Inserisci almeno 2 caratteri per la ricerca',
            'results' => []
        ]);
        exit;
    } else {
        SessionManager::setFlashMessage('Inserisci almeno 2 caratteri per la ricerca', 'warning');
        header('Location: ' . BASE_URL . '/pages/shop.php');
        exit;
    }
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
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Errore di connessione al database',
            'results' => []
        ]);
        exit;
    } else {
        SessionManager::setFlashMessage('Errore di connessione al database', 'danger');
        header('Location: ' . BASE_URL);
        exit;
    }
}

$conn->set_charset("utf8mb4");

// Array per risultati
$results = [];
$total_results = 0;

// Offset per paginazione
$offset = ($page - 1) * $per_page;

// Costruisci query base
$search_params = [];
$param_types = "";
$where_conditions = [];

// Ricerca nei Mystery Box
$sql_mystery = "
    SELECT 
        mb.id_box as id,
        mb.nome_box as nome,
        mb.desc_box as descrizione,
        mb.prezzo_box as prezzo,
        mb.quantita_box as stock,
        'mystery_box' as tipo,
        r.nome_rarita as rarita,
        r.colore as colore_rarita,
        c.nome_categoria as categoria,
        COALESCE(i.nome_img, 'default_box.png') as immagine,
        MATCH(mb.nome_box, mb.desc_box) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
    FROM mystery_box mb
    LEFT JOIN rarita r ON mb.fk_rarita = r.id_rarita
    LEFT JOIN categoria_oggetto c ON mb.fk_categoria_oggetto = c.id_categoria
    LEFT JOIN immagine i ON mb.id_box = i.fk_mystery_box
    WHERE 1=1
";

// Ricerca negli Oggetti
$sql_oggetti = "
    SELECT 
        o.id_oggetto as id,
        o.nome_oggetto as nome,
        o.desc_oggetto as descrizione,
        o.prezzo_oggetto as prezzo,
        o.quant_oggetto as stock,
        CASE 
            WHEN c.tipo_oggetto = 'Funko Pop' THEN 'funko_pop'
            WHEN c.tipo_oggetto IN ('Proteggicarte', 'Plance di gioco', 'Scatole porta carte', 'Porta mazzi') THEN 'accessorio'
            ELSE 'oggetto'
        END as tipo,
        r.nome_rarita as rarita,
        r.colore as colore_rarita,
        c.nome_categoria as categoria,
        COALESCE(i.nome_img, 'default_product.png') as immagine,
        MATCH(o.nome_oggetto, o.desc_oggetto) AGAINST(? IN NATURAL LANGUAGE MODE) as relevance
    FROM oggetto o
    LEFT JOIN rarita r ON o.fk_rarita = r.id_rarita
    LEFT JOIN categoria_oggetto c ON o.fk_categoria_oggetto = c.id_categoria
    LEFT JOIN immagine i ON o.id_oggetto = i.fk_oggetto
    WHERE o.prezzo_oggetto IS NOT NULL
";

// Aggiungi condizioni WHERE
if (!empty($query)) {
    $sql_mystery .= " AND (mb.nome_box LIKE ? OR mb.desc_box LIKE ?)";
    $sql_oggetti .= " AND (o.nome_oggetto LIKE ? OR o.desc_oggetto LIKE ?)";
    $like_query = '%' . $query . '%';
}

if (!empty($category)) {
    $sql_mystery .= " AND c.nome_categoria = ?";
    $sql_oggetti .= " AND c.nome_categoria = ?";
}

if ($min_price > 0) {
    $sql_mystery .= " AND mb.prezzo_box >= ?";
    $sql_oggetti .= " AND o.prezzo_oggetto >= ?";
}

if ($max_price > 0) {
    $sql_mystery .= " AND mb.prezzo_box <= ?";
    $sql_oggetti .= " AND o.prezzo_oggetto <= ?";
}

// Filtra per tipo se specificato
if ($type === 'mystery_box') {
    $sql_oggetti = ""; // Cerca solo mystery box
} elseif ($type === 'funko_pop') {
    $sql_mystery = ""; // Cerca solo funko pop
    $sql_oggetti .= " AND c.tipo_oggetto = 'Funko Pop'";
} elseif ($type === 'accessorio') {
    $sql_mystery = ""; // Cerca solo accessori
    $sql_oggetti .= " AND c.tipo_oggetto IN ('Proteggicarte', 'Plance di gioco', 'Scatole porta carte', 'Porta mazzi')";
}

// Combina le query con UNION se necessario
$sql = "";
if (!empty($sql_mystery) && !empty($sql_oggetti)) {
    $sql = "($sql_mystery) UNION ALL ($sql_oggetti)";
} elseif (!empty($sql_mystery)) {
    $sql = $sql_mystery;
} else {
    $sql = $sql