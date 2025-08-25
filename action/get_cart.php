<?php
/**
 * action/get_cart.php - VERSIONE FINALE ANTI-ERRORE
 * IMPORTANTE: Questo file NON deve mai usare return statements!
 */

// ⚠️ IMPORTANTE: Non usare return con valori in questo file!
// Il return assegnerebbe quel valore alle variabili quando si fa include!

// Assicura che le variabili esistano e siano del tipo corretto
if (!isset($cart_items) || !is_array($cart_items)) {
    $cart_items = [];
}
if (!isset($total_items) || !is_numeric($total_items)) {
    $total_items = 0;
}
if (!isset($subtotal) || !is_numeric($subtotal)) {
    $subtotal = 0;
}
if (!isset($shipping_cost) || !is_numeric($shipping_cost)) {
    $shipping_cost = 5.00;
}
if (!isset($total) || !is_numeric($total)) {
    $total = 5.00;
}

// Reset per evitare accumuli - SEMPRE FORZARE ARRAY
$cart_items = [];
$total_items = 0;
$subtotal = 0;

// Include dipendenze se non già caricate
if (!class_exists('SessionManager')) {
    require_once __DIR__ . '/../include/session_manager.php';
}
if (!isset($config)) {
    require_once __DIR__ . '/../include/config.inc.php';
}

// Connessione database con gestione errore robusta
$db_config = $config['dbms']['localhost'] ?? null;
if (!$db_config) {
    error_log("Errore: configurazione DB mancante in get_cart.php");
    // Mantieni array vuoto ma valido
    $cart_items = [];
    $total_items = 0;
    $subtotal = 0;
    $shipping_cost = 5.00;
    $total = 5.00;
    // NON usare return qui!
    goto end_get_cart;
}

$conn = new mysqli(
    $db_config['host'],
    $db_config['user'],
    $db_config['passwd'],
    $db_config['dbname']
);

if ($conn->connect_error) {
    error_log("Errore connessione DB in get_cart.php: " . $conn->connect_error);
    // Mantieni array vuoto ma valido
    $cart_items = [];
    $total_items = 0;
    $subtotal = 0;
    $shipping_cost = 5.00;
    $total = 5.00;
    // NON usare return qui!
    goto end_get_cart;
}

try {
    if (SessionManager::isLoggedIn()) {
        // UTENTE LOGGATO - Recupera dal database
        $user_id = SessionManager::get('user_id');

        $query = "
            SELECT 
                c.id_carrello,
                c.quantita,
                c.totale,
                c.fk_mystery_box,
                c.fk_oggetto,
                CASE 
                    WHEN c.fk_mystery_box IS NOT NULL THEN mb.nome_box
                    WHEN c.fk_oggetto IS NOT NULL THEN o.nome_oggetto
                    ELSE 'Prodotto Sconosciuto'
                END as nome_prodotto,
                CASE 
                    WHEN c.fk_mystery_box IS NOT NULL THEN mb.prezzo_box
                    WHEN c.fk_oggetto IS NOT NULL THEN o.prezzo_oggetto
                    ELSE 0
                END as prezzo_prodotto
            FROM carrello c
            LEFT JOIN mystery_box mb ON c.fk_mystery_box = mb.id_box
            LEFT JOIN oggetto o ON c.fk_oggetto = o.id_oggetto
            WHERE c.fk_utente = ? 
            AND c.stato = 'attivo'
            ORDER BY c.data_creazione DESC
        ";

        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();

            // Forza sempre array inizializzazione
            $cart_items = [];
            $total_items = 0;
            $subtotal = 0;

            while ($row = $result->fetch_assoc()) {
                // Determina tipo prodotto e ID
                if ($row['fk_mystery_box'] != null) {
                    $tipo = 'mystery_box';
                    $prodotto_id = $row['fk_mystery_box'];
                } else {
                    $tipo = 'oggetto';
                    $prodotto_id = $row['fk_oggetto'];
                }

                $cart_key = $tipo . '_' . $prodotto_id;

                // Aggiungi all'array cart_items - FORZA SEMPRE ARRAY
                $cart_items[] = [
                    'cart_key' => $cart_key,
                    'id_carrello' => $row['id_carrello'],
                    'nome' => $row['nome_prodotto'] ?? 'Prodotto Sconosciuto',
                    'prezzo' => floatval($row['prezzo_prodotto'] ?? 0),
                    'quantita' => intval($row['quantita'] ?? 1),
                    'tipo' => $tipo,
                    'prodotto_id' => $prodotto_id,
                    'image' => BASE_URL . '/images/default_product.png'
                ];

                // Aggiorna totali
                $total_items += intval($row['quantita']);
                $subtotal += floatval($row['totale']);
            }

            $stmt->close();
        } else {
            error_log("Errore preparazione query carrello: " . $conn->error);
            // Mantieni array vuoto
            $cart_items = [];
        }

    } else {
        // UTENTE NON LOGGATO - Usa sessione
        $session_cart = SessionManager::get('cart', []);

        // Forza sempre array inizializzazione
        $cart_items = [];
        $total_items = 0;
        $subtotal = 0;

        if (is_array($session_cart) && !empty($session_cart)) {
            foreach ($session_cart as $key => $item) {
                if (!is_array($item)) {
                    continue; // Salta elementi non validi
                }

                $cart_items[] = [
                    'cart_key' => $key,
                    'id_carrello' => null, // Non applicabile per sessione
                    'nome' => $item['nome_prodotto'] ?? 'Prodotto Sconosciuto',
                    'prezzo' => floatval($item['prezzo'] ?? 0),
                    'quantita' => intval($item['quantita'] ?? 1),
                    'tipo' => $item['tipo'] ?? 'oggetto',
                    'prodotto_id' => $item['id_prodotto'] ?? 0,
                    'image' => BASE_URL . '/images/default_product.png'
                ];

                // Aggiorna totali
                $quantita = intval($item['quantita'] ?? 1);
                $prezzo = floatval($item['prezzo'] ?? 0);

                $total_items += $quantita;
                $subtotal += ($prezzo * $quantita);
            }
        }
    }

} catch (Exception $e) {
    error_log("Errore in get_cart.php: " . $e->getMessage());
    // In caso di errore, mantieni array vuoto ma valido
    $cart_items = [];
    $total_items = 0;
    $subtotal = 0;
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}

// Label per goto in caso di errori
end_get_cart:

// Calcola costi di spedizione
$shipping_cost = 5.00;
if ($subtotal >= 50) {
    $shipping_cost = 0;
}

// Calcola totale finale
$total = $subtotal + $shipping_cost;

// Aggiorna contatore in sessione
SessionManager::set('cart_items_count', $total_items);

// ✅ CONTROLLO FINALE CRITICO - FORZA SEMPRE ARRAY
if (!is_array($cart_items)) {
    error_log("CRITICO: cart_items non è array alla fine di get_cart.php! Tipo: " . gettype($cart_items) . " Valore: " . print_r($cart_items, true));
    $cart_items = [];
    $total_items = 0;
    $subtotal = 0;
    $total = 5.00;
}

// ⚠️ MAI USARE RETURN QUI! Causerebbe il problema dell'int!
?>