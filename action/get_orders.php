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

// Query complessa per recuperare tutti gli ordini dell'utente
$query = "
SELECT 
    o.id_ordine,
    o.data_ordine,
    o.tracking,
    o.stato_ordine,
    CONCAT('Via ', ind.via, ' ', ind.civico, ', ', ind.cap, ' ', ind.citta, ' (', ind.provincia, ')') as indirizzo_completo,
    c.totale as totale_ordine,
    c.quantita as quantita_totale,
    mb.nome_box,
    mb.desc_box,
    ogg.nome_oggetto,
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
        'raw_status' => $row['stato_ordine']
    ];
}

$stmt->close();
$conn->close();

// Restituisce gli ordini per l'inclusione in altre pagine
return $orders;