<?php
require_once __DIR__ . '/../include/session_manager.php';
require_once __DIR__ . '/../include/config.inc.php';

// Richiedi login
SessionManager::requireLogin();

// Connessione database
$db_config = $config['dbms']['localhost'];
$conn = new mysqli(
    $db_config['host'],
    $db_config['user'],
    $db_config['passwd'],
    $db_config['dbname']
);

if ($conn->connect_error) {
    die("Errore di connessione: " . $conn->connect_error);
}

$id_utente = SessionManager::get('user_id');

// Query complessa per recuperare tutti gli ordini dell'utente con informazioni per "Compra di nuovo"
$query = "
SELECT 
    o.id_ordine,
    o.data_ordine,
    o.tracking,
    o.stato_ordine,
    CONCAT('Via ', ind.via, ' ', ind.civico, ', ', ind.cap, ' ', ind.citta, ' (', ind.provincia, ')') as indirizzo_completo,
    c.totale as totale_ordine,
    c.quantita as quantita_totale,
    c.fk_mystery_box,
    c.fk_oggetto,
    mb.nome_box,
    mb.desc_box,
    mb.quantita_box as mb_quantita_disponibile,
    ogg.nome_oggetto,
    ogg.quant_oggetto as ogg_quantita_disponibile,
    co.tipo_oggetto,
    co.nome_categoria,
    COALESCE(
        CONCAT('" . BASE_URL . "/images/', mb.nome_box, '.png'),
        CONCAT('" . BASE_URL . "/images/', img.nome_img),
        '" . BASE_URL . "/images/default_order.png'
    ) as immagine_ordine
FROM ordine o
LEFT JOIN indirizzo_spedizione ind ON o.fk_indirizzo = ind.id_indirizzo
LEFT JOIN carrello c ON o.fk_carrello = c.id_carrello
LEFT JOIN mystery_box mb ON c.fk_mystery_box = mb.id_box
LEFT JOIN oggetto ogg ON c.fk_oggetto = ogg.id_oggetto
LEFT JOIN categoria_oggetto co ON (mb.fk_categoria_oggetto = co.id_categoria OR ogg.fk_categoria_oggetto = co.id_categoria)
LEFT JOIN immagine img ON ogg.id_oggetto = img.fk_oggetto
WHERE o.fk_utente = ?
ORDER BY o.data_ordine DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id_utente);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
    // Converti stato_ordine in testo leggibile
    $status_map = [
        0 => 'In Elaborazione',
        1 => 'Completato',
        2 => 'Spedito',
        3 => 'Annullato',
        4 => 'Rimborsato'
    ];

    // Determina se il prodotto è ancora disponibile
    $is_available = false;
    $product_url = '';
    $product_id = '';
    $product_type = '';

    if (!empty($row['fk_mystery_box'])) {
        // È una Mystery Box
        $is_available = ($row['mb_quantita_disponibile'] > 0);
        $product_id = $row['fk_mystery_box'];
        $product_type = 'mystery_box';
        
        // Determina la pagina in base alla categoria
        if (stripos($row['nome_categoria'], 'yu-gi-oh') !== false) {
            $product_url = 'yugioh_mystery_boxes.php';
        } else if (stripos($row['nome_categoria'], 'pokémon') !== false || stripos($row['nome_categoria'], 'pokemon') !== false) {
            $product_url = 'pokemon_mystery_boxes.php';
        } else {
            $product_url = 'accessori.php';
        }
    } else if (!empty($row['fk_oggetto'])) {
        // È un oggetto
        $is_available = ($row['ogg_quantita_disponibile'] > 0);
        $product_id = $row['fk_oggetto'];
        $product_type = 'oggetto';
        
        // Determina la pagina in base al tipo di oggetto
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

    $orders[] = [
        'id' => 'ORD' . str_pad($row['id_ordine'], 3, '0', STR_PAD_LEFT),
        'date' => date('d/m/Y', strtotime($row['data_ordine'])),
        'status' => $status_map[$row['stato_ordine']] ?? 'Sconosciuto',
        'address' => $row['indirizzo_completo'],
        'quantity' => (int)($row['quantita_totale'] ?? 0),
        'total' => number_format((float)($row['totale_ordine'] ?? 0), 2),
        'image' => $row['immagine_ordine'],
        'tracking' => $row['tracking'],
        'product_name' => $row['nome_box'] ?? $row['nome_oggetto'] ?? 'Prodotto',
        'raw_status' => $row['stato_ordine'],
        // Nuovi campi per "Compra di nuovo"
        'is_available' => $is_available,
        'product_url' => $product_url,
        'product_id' => $product_id,
        'product_type' => $product_type,
        'tipo_oggetto' => $row['tipo_oggetto'] ?? '',
        'nome_categoria' => $row['nome_categoria'] ?? ''
    ];
}

$stmt->close();
$conn->close();

// Restituisce gli ordini per l'inclusione in altre pagine
return $orders;