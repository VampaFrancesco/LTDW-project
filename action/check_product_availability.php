<?php
require_once __DIR__ . '/../include/session_manager.php';
require_once __DIR__ . '/../include/config.inc.php';

// Richiedi login
SessionManager::requireLogin();

// Verifica che sia una richiesta POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
    exit;
}

// Recupera i dati POST
$input = json_decode(file_get_contents('php://input'), true);
$product_type = $input['product_type'] ?? '';
$product_id = $input['product_id'] ?? '';

if (empty($product_type) || empty($product_id)) {
    echo json_encode(['error' => 'Parametri mancanti']);
    exit;
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
    echo json_encode(['error' => 'Errore di connessione al database']);
    exit;
}

$is_available = false;
$product_url = '';

if ($product_type === 'mystery_box') {
    // Controlla disponibilità Mystery Box
    $query = "
        SELECT mb.quantita_box, co.tipo_oggetto, co.nome_categoria
        FROM mystery_box mb
        LEFT JOIN categoria_oggetto co ON mb.fk_categoria_oggetto = co.id_categoria
        WHERE mb.id_box = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $is_available = ($row['quantita_box'] > 0);
        
        // Determina la pagina di destinazione
        if (stripos($row['nome_categoria'], 'yu-gi-oh') !== false) {
            $product_url = 'yugioh_mystery_boxes.php';
        } else if (stripos($row['nome_categoria'], 'pokémon') !== false || stripos($row['nome_categoria'], 'pokemon') !== false) {
            $product_url = 'pokemon_mystery_boxes.php';
        } else {
            $product_url = 'accessori.php';
        }
    }
    
} else if ($product_type === 'oggetto') {
    // Controlla disponibilità oggetto
    $query = "
        SELECT ogg.quant_oggetto, co.tipo_oggetto, co.nome_categoria
        FROM oggetto ogg
        LEFT JOIN categoria_oggetto co ON ogg.fk_categoria_oggetto = co.id_categoria
        WHERE ogg.id_oggetto = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $is_available = ($row['quant_oggetto'] > 0);
        
        // Determina la pagina di destinazione
        if (stripos($row['tipo_oggetto'], 'funko pop') !== false) {
            if (stripos($row['nome_categoria'], 'yu-gi-oh') !== false) {
                $product_url = 'yugioh_funko_pops.php';
            } else if (stripos($row['nome_categoria'], 'pokémon') !== false || stripos($row['nome_categoria'], 'pokemon') !== false) {
                $product_url = 'pokemon_funko_pops.php';
            } else {
                $product_url = 'accessori.php';
            }
        } else {
            // Proteggicarte, Plance di gioco, Scatole porta carte, Porta mazzi
            $product_url = 'accessori.php';
        }
    }
}

$stmt->close();
$conn->close();

// Restituisce la risposta JSON
echo json_encode([
    'is_available' => $is_available,
    'product_url' => $product_url,
    'product_id' => $product_id
]);