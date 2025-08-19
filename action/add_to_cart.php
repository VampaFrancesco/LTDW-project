<?php
/**
 * action/add_to_cart.php - VERSIONE MIGLIORATA COMPLETA
 * Gestisce l'aggiunta di prodotti al carrello con controlli di disponibilità avanzati
 */

// Evita output prima degli header
ob_start();

require_once __DIR__ . '/../include/session_manager.php';
require_once __DIR__ . '/../include/config.inc.php';

// Verifica metodo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    SessionManager::setFlashMessage('Metodo non valido', 'danger');
    header('Location: ' . BASE_URL);
    exit();
}

// Recupera e valida dati
$id_prodotto = intval($_POST['id_prodotto'] ?? 0);
$nome_prodotto = trim($_POST['nome_prodotto'] ?? '');
$prezzo = floatval($_POST['prezzo'] ?? 0);
$quantita = intval($_POST['quantita'] ?? 1);

// ⚠️ CORREZIONE: Supporta sia 'tipo' che 'tipo_prodotto'
$tipo = $_POST['tipo'] ?? $_POST['tipo_prodotto'] ?? '';

// Se il tipo è 'funko_pop' o 'accessorio', convertilo in 'oggetto' (sono oggetti nel DB)
if (in_array($tipo, ['funko_pop', 'accessorio'])) {
    $tipo = 'oggetto';
}

$redirect_url = $_POST['redirect_url'] ?? BASE_URL . '/pages/home_utente.php';

// ✅ VALIDAZIONE INPUT MIGLIORATA
$errors = [];
if ($id_prodotto <= 0) $errors[] = "ID prodotto non valido";
if ($quantita <= 0) $errors[] = "Quantità non valida";
if ($quantita > 99) $errors[] = "Quantità massima: 99";
if (empty($nome_prodotto)) $errors[] = "Nome prodotto mancante";
if ($prezzo < 0) $errors[] = "Prezzo non valido";
if (!in_array($tipo, ['mystery_box', 'oggetto'])) {
    $errors[] = "Tipo prodotto non valido (ricevuto: '$tipo')";
}

if (!empty($errors)) {
    SessionManager::setFlashMessage(implode(', ', $errors), 'danger');
    header('Location: ' . $redirect_url);
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
    SessionManager::setFlashMessage('Errore di connessione al database', 'danger');
    header('Location: ' . $redirect_url);
    exit();
}

// ✅ VERIFICA PRODOTTO ESISTENTE E DISPONIBILITÀ MIGLIORATA
try {
    if ($tipo === 'mystery_box') {
        // Verifica esistenza Mystery Box
        $stmt = $conn->prepare("
            SELECT mb.*, c.nome_categoria 
            FROM mystery_box mb
            LEFT JOIN categoria_oggetto c ON mb.fk_categoria_oggetto = c.id_categoria
            WHERE mb.id_box = ?
        ");
        $stmt->bind_param("i", $id_prodotto);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$product) {
            throw new Exception("Mystery Box non trovata");
        }

        // ✅ CONTROLLO STOCK DISPONIBILE VS CARRELLO ESISTENTE
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(quantita), 0) as qty_in_cart 
            FROM carrello 
            WHERE fk_mystery_box = ? AND stato = 'attivo'
        ");
        $stmt->bind_param("i", $id_prodotto);
        $stmt->execute();
        $cart_qty = $stmt->get_result()->fetch_assoc()['qty_in_cart'];
        $stmt->close();

        $disponibile = $product['quantita_box'] - $cart_qty;

        if ($disponibile <= 0) {
            throw new Exception("Mystery Box esaurita");
        }

        if ($quantita > $disponibile) {
            throw new Exception("Quantità richiesta non disponibile. Disponibili: $disponibile");
        }

        // Verifica prezzo (con tolleranza per arrotondamenti)
        if (abs($prezzo - $product['prezzo_box']) > 0.01) {
            // Usa il prezzo del DB per sicurezza
            $prezzo = $product['prezzo_box'];
        }

    } else { // oggetto
        // Verifica esistenza Oggetto
        $stmt = $conn->prepare("
            SELECT o.*, c.nome_categoria 
            FROM oggetto o
            LEFT JOIN categoria_oggetto c ON o.fk_categoria_oggetto = c.id_categoria
            WHERE o.id_oggetto = ?
        ");
        $stmt->bind_param("i", $id_prodotto);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$product) {
            throw new Exception("Oggetto non trovato");
        }

        // ✅ CONTROLLO STOCK OGGETTI (SE LIMITATO)
        if ($product['quant_oggetto'] !== null) {
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(quantita), 0) as qty_in_cart 
                FROM carrello 
                WHERE fk_oggetto = ? AND stato = 'attivo'
            ");
            $stmt->bind_param("i", $id_prodotto);
            $stmt->execute();
            $cart_qty = $stmt->get_result()->fetch_assoc()['qty_in_cart'];
            $stmt->close();

            $disponibile = $product['quant_oggetto'] - $cart_qty;

            if ($disponibile <= 0) {
                throw new Exception("Prodotto esaurito");
            }

            if ($quantita > $disponibile) {
                throw new Exception("Quantità richiesta non disponibile. Disponibili: $disponibile");
            }
        }

        // Verifica prezzo - solo se l'oggetto ha un prezzo
        if ($product['prezzo_oggetto'] !== null) {
            if (abs($prezzo - $product['prezzo_oggetto']) > 0.01) {
                // Usa il prezzo del DB per sicurezza
                $prezzo = $product['prezzo_oggetto'];
            }
        } else {
            // Se l'oggetto non ha prezzo nel DB, usa 0
            $prezzo = 0;
        }
    }

    // ✅ GESTIONE CARRELLO MIGLIORATA
    if (SessionManager::isLoggedIn()) {
        $user_id = SessionManager::get('user_id');

        // Verifica se l'item è già nel carrello attivo
        if ($tipo === 'mystery_box') {
            $check_stmt = $conn->prepare("
                SELECT id_carrello, quantita 
                FROM carrello 
                WHERE fk_utente = ? 
                AND fk_mystery_box = ? 
                AND fk_oggetto IS NULL
                AND stato = 'attivo'
            ");
            $check_stmt->bind_param("ii", $user_id, $id_prodotto);
        } else {
            $check_stmt = $conn->prepare("
                SELECT id_carrello, quantita 
                FROM carrello 
                WHERE fk_utente = ? 
                AND fk_oggetto = ? 
                AND fk_mystery_box IS NULL
                AND stato = 'attivo'
            ");
            $check_stmt->bind_param("ii", $user_id, $id_prodotto);
        }

        $check_stmt->execute();
        $existing = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();

        if ($existing) {
            // Aggiorna quantità esistente
            $nuova_quantita = $existing['quantita'] + $quantita;

            // ✅ NUOVA VERIFICA: Controlla che la quantità totale non superi lo stock
            if ($tipo === 'mystery_box') {
                if ($nuova_quantita > $product['quantita_box']) {
                    $max_aggiungibile = $product['quantita_box'] - $existing['quantita'];
                    throw new Exception(
                        "Non puoi aggiungere {$quantita} pezzi. " .
                        "Hai già {$existing['quantita']} nel carrello. " .
                        "Puoi aggiungerne al massimo {$max_aggiungibile}. " .
                        "Stock totale: {$product['quantita_box']}"
                    );
                }
            } else {
                if ($product['quant_oggetto'] !== null && $nuova_quantita > $product['quant_oggetto']) {
                    $max_aggiungibile = $product['quant_oggetto'] - $existing['quantita'];
                    throw new Exception(
                        "Non puoi aggiungere {$quantita} pezzi. " .
                        "Hai già {$existing['quantita']} nel carrello. " .
                        "Puoi aggiungerne al massimo {$max_aggiungibile}. " .
                        "Stock totale: {$product['quant_oggetto']}"
                    );
                }
            }

            $nuovo_totale = $nuova_quantita * $prezzo;

            $update_stmt = $conn->prepare("
                UPDATE carrello 
                SET quantita = ?, 
                    totale = ?, 
                    data_ultima_modifica = NOW() 
                WHERE id_carrello = ?
            ");
            $update_stmt->bind_param("idi", $nuova_quantita, $nuovo_totale, $existing['id_carrello']);

            if (!$update_stmt->execute()) {
                throw new Exception("Errore nell'aggiornamento del carrello");
            }
            $update_stmt->close();

            $message = "Quantità aggiornata nel carrello (Totale: {$nuova_quantita})";
        } else {
            // Inserisci nuovo item
            $totale = $quantita * $prezzo;

            if ($tipo === 'mystery_box') {
                $insert_stmt = $conn->prepare("
                    INSERT INTO carrello 
                    (fk_utente, fk_mystery_box, fk_oggetto, quantita, totale, stato, data_creazione, data_ultima_modifica) 
                    VALUES (?, ?, NULL, ?, ?, 'attivo', NOW(), NOW())
                ");
                $insert_stmt->bind_param("iiid", $user_id, $id_prodotto, $quantita, $totale);
            } else {
                $insert_stmt = $conn->prepare("
                    INSERT INTO carrello 
                    (fk_utente, fk_mystery_box, fk_oggetto, quantita, totale, stato, data_creazione, data_ultima_modifica) 
                    VALUES (?, NULL, ?, ?, ?, 'attivo', NOW(), NOW())
                ");
                $insert_stmt->bind_param("iiid", $user_id, $id_prodotto, $quantita, $totale);
            }

            if (!$insert_stmt->execute()) {
                throw new Exception("Errore nell'inserimento nel carrello: " . $insert_stmt->error);
            }
            $insert_stmt->close();

            $message = "Prodotto aggiunto al carrello";
        }

        // ✅ AGGIORNA CONTATORE CARRELLO IN SESSIONE
        $count_stmt = $conn->prepare("
            SELECT SUM(quantita) as total 
            FROM carrello 
            WHERE fk_utente = ? AND stato = 'attivo'
        ");
        $count_stmt->bind_param("i", $user_id);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result()->fetch_assoc();
        $count_stmt->close();

        SessionManager::set('cart_items_count', $count_result['total'] ?? 0);

    } else {
        // ✅ UTENTE NON LOGGATO - GESTIONE SESSIONE MIGLIORATA
        $cart = SessionManager::get('cart', []);
        $item_key = $tipo . '_' . $id_prodotto;

        if (isset($cart[$item_key])) {
            $nuova_quantita = $cart[$item_key]['quantita'] + $quantita;

            // Controllo disponibilità per sessione
            if ($tipo === 'mystery_box') {
                $disponibile_totale = $product['quantita_box'];
            } else {
                $disponibile_totale = $product['quant_oggetto'];
            }

            if ($disponibile_totale !== null && $nuova_quantita > $disponibile_totale) {
                throw new Exception("Impossibile aggiungere {$quantita} articoli. Disponibili: " . ($disponibile_totale - $cart[$item_key]['quantita']));
            }

            $cart[$item_key]['quantita'] = $nuova_quantita;
            $cart[$item_key]['totale'] = $nuova_quantita * $prezzo;
            $message = "Quantità aggiornata nel carrello";
        } else {
            $cart[$item_key] = [
                'id_prodotto' => $id_prodotto,
                'nome_prodotto' => $nome_prodotto,
                'prezzo' => $prezzo,
                'quantita' => $quantita,
                'totale' => $quantita * $prezzo,
                'tipo' => $tipo
            ];
            $message = "Prodotto aggiunto al carrello";
        }

        SessionManager::set('cart', $cart);

        // Aggiorna contatore
        $total_items = array_sum(array_column($cart, 'quantita'));
        SessionManager::set('cart_items_count', $total_items);
    }

    // ✅ SUCCESSO
    SessionManager::setFlashMessage($message, 'success');

} catch (Exception $e) {
    error_log("Errore add_to_cart.php: " . $e->getMessage());
    SessionManager::setFlashMessage($e->getMessage(), 'danger');
} finally {
    $conn->close();
}

// Reindirizza alla pagina di origine
header('Location: ' . $redirect_url);
exit();

ob_end_flush();
?>