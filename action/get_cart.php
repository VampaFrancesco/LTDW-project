<?php
require_once __DIR__ . '/../include/session_manager.php';
require_once __DIR__ . '/../include/config.inc.php';

SessionManager::startSecureSession();

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

$cart_items = [];
$total_items = 0;
$subtotal = 0;

if (SessionManager::isLoggedIn()) {
    // Recupera carrello dal database
    $id_utente = SessionManager::get('user_id');

    $query = "
SELECT 
    c.id_carrello,
    c.quantita,
    c.totale,
    CASE 
        WHEN c.fk_mystery_box IS NOT NULL THEN mb.nome_box
        WHEN c.fk_oggetto IS NOT NULL THEN ogg.nome_oggetto
        ELSE 'Prodotto sconosciuto'
    END as nome,
    CASE 
        WHEN c.fk_mystery_box IS NOT NULL THEN mb.prezzo_box
        WHEN c.fk_oggetto IS NOT NULL THEN ogg.prezzo_oggetto
        ELSE 0
    END as prezzo,
    CASE 
        WHEN c.fk_mystery_box IS NOT NULL THEN 'mystery_box'
        WHEN c.fk_oggetto IS NOT NULL THEN 'oggetto'
        ELSE 'unknown'
    END as tipo,
    COALESCE(c.fk_mystery_box, c.fk_oggetto) as prodotto_id,
    CASE 
        WHEN c.fk_mystery_box IS NOT NULL THEN 
            CONCAT('" . BASE_URL . "/images/', LOWER(REPLACE(REPLACE(mb.nome_box, ' ', '_'), '-', '_')), '.png')
        WHEN c.fk_oggetto IS NOT NULL THEN 
            COALESCE(CONCAT('" . BASE_URL . "/images/', img.nome_img), '" . BASE_URL . "/images/default_product.png')
        ELSE '" . BASE_URL . "/images/default_product.png'
    END as image
FROM carrello c
LEFT JOIN mystery_box mb ON c.fk_mystery_box = mb.id_box
LEFT JOIN oggetto ogg ON c.fk_oggetto = ogg.id_oggetto
LEFT JOIN immagine img ON ogg.id_oggetto = img.fk_oggetto
WHERE c.fk_utente = ?
ORDER BY c.data_creazione DESC
";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id_utente);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $cart_key = $row['tipo'] . '_' . $row['prodotto_id'];

        $cart_items[] = [
            'cart_key' => $cart_key,
            'id_carrello' => $row['id_carrello'],
            'nome' => $row['nome'],
            'prezzo' => $row['prezzo'],
            'quantita' => $row['quantita'],
            'tipo' => $row['tipo'],
            'prodotto_id' => $row['prodotto_id'],
            'image' => $row['image']
        ];

        $total_items += $row['quantita'];
        $subtotal += $row['totale'];
    }

    $stmt->close();

} else {
    // Recupera carrello dalla sessione
    $session_cart = SessionManager::get('cart', []);

    foreach ($session_cart as $key => $item) {
        $cart_items[] = [
            'cart_key' => $key,
            'id_carrello' => null,
            'nome' => $item['nome'],
            'prezzo' => $item['prezzo'],
            'quantita' => $item['quantita'],
            'tipo' => $item['tipo'],
            'prodotto_id' => $item['id'],
            'image' => BASE_URL . '/images/default_product.png' // Immagine di default per sessione
        ];

        $total_items += $item['quantita'];
        $subtotal += ($item['prezzo'] * $item['quantita']);
    }
}

$conn->close();

// Calcola totale con spedizione
$shipping_cost = $subtotal >= 50 ? 0 : 5.00;
$total = $subtotal + $shipping_cost;

// Aggiorna il contatore nel header
SessionManager::set('cart_items_count', $total_items);

return [
    'items' => $cart_items,
    'total_items' => $total_items,
    'subtotal' => $subtotal,
    'shipping_cost' => $shipping_cost,
    'total' => $total
];