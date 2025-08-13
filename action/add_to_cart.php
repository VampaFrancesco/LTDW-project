<?php
// action/add_to_cart.php - VERSIONE CORRETTA COMPLETA
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

// Se il tipo è 'funko_pop', convertilo in 'oggetto' (i Funko Pop sono oggetti nel DB)
if ($tipo === 'funko_pop') {
    $tipo = 'oggetto';
}

$redirect_url = $_POST['redirect_url'] ?? BASE_URL . '/pages/home_utente.php';

// Validazione input
$errors = [];
if ($id_prodotto <= 0) $errors[] = "ID prodotto non valido";
if ($quantita <= 0) $errors[] = "Quantità non valida";
if ($quantita > 99) $errors[] = "Quantità massima: 99";
if (empty($nome_prodotto)) $errors[] = "Nome prodotto mancante";
if ($prezzo < 0) $errors[] = "Prezzo non valido"; // Cambiato da <= a < per permettere prodotti gratuiti
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
    SessionManager::setFlashMessage('Errore di connessione', 'danger');
    header('Location: ' . $redirect_url);
    exit();
}

// Verifica che il prodotto esista e sia disponibile
try {
    if ($tipo === 'mystery_box') {
        $stmt = $conn->prepare("
            SELECT mb.*, c.nome_categoria 
            FROM mystery_box mb
            LEFT JOIN categoria_oggetto c ON mb.fk_categoria_oggetto = c.id_categoria
            WHERE mb.id_box = ? AND mb.quantita_box > 0
        ");
        $stmt->bind_param("i", $id_prodotto);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$product) {
            throw new Exception("Mystery Box non trovata o non disponibile");
        }

        if ($quantita > $product['quantita_box']) {
            throw new Exception("Quantità richiesta non disponibile (disponibili: {$product['quantita_box']})");
        }

        // Verifica prezzo (con tolleranza per arrotondamenti)
        if (abs($prezzo - $product['prezzo_box']) > 0.01) {
            // Non bloccare per piccole differenze di prezzo, usa il prezzo del DB
            $prezzo = $product['prezzo_box'];
        }

    } else { // oggetto
        $stmt = $conn->prepare("
            SELECT o.*, c.nome_categoria 
            FROM oggetto o
            LEFT JOIN categoria_oggetto c ON o.fk_categoria_oggetto = c.id_categoria
            WHERE o.id_oggetto = ? 
            AND (o.quant_oggetto IS NULL OR o.quant_oggetto >= ?)
        ");
        $stmt->bind_param("ii", $id_prodotto, $quantita);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$product) {
            throw new Exception("Oggetto non trovato o quantità non disponibile");
        }

        // Per oggetti con quantità NULL (illimitata), permettiamo l'acquisto
        if ($product['quant_oggetto'] !== null && $quantita > $product['quant_oggetto']) {
            throw new Exception("Quantità richiesta non disponibile (disponibili: {$product['quant_oggetto']})");
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

    // Se l'utente è loggato, salva nel database
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

            $message = "Quantità aggiornata nel carrello";
        } else {
            // Inserisci nuovo item
            $totale = $quantita * $prezzo;

            if ($tipo === 'mystery_box') {
                $insert_stmt = $conn->prepare("
                    INSERT INTO carrello 
                    (fk_utente, fk_mystery_box, fk_oggetto, quantita, totale, stato, data_creazione) 
                    VALUES (?, ?, NULL, ?, ?, 'attivo', NOW())
                ");
                $insert_stmt->bind_param("iiid", $user_id, $id_prodotto, $quantita, $totale);
            } else {
                $insert_stmt = $conn->prepare("
                    INSERT INTO carrello 
                    (fk_utente, fk_mystery_box, fk_oggetto, quantita, totale, stato, data_creazione) 
                    VALUES (?, NULL, ?, ?, ?, 'attivo', NOW())
                ");
                $insert_stmt->bind_param("iiid", $user_id, $id_prodotto, $quantita, $totale);
            }

            if (!$insert_stmt->execute()) {
                throw new Exception("Errore nell'inserimento nel carrello: " . $insert_stmt->error);
            }
            $insert_stmt->close();

            $message = "Prodotto aggiunto al carrello";
        }
    } else {
        // Utente non loggato - salva in sessione
        $cart = SessionManager::get('cart', []);
        $item_key = $tipo . '_' . $id_prodotto;

        if (isset($cart[$item_key])) {
            $cart[$item_key]['quantita'] += $quantita;
        } else {
            $cart[$item_key] = [
                'id' => $id_prodotto,
                'nome' => $nome_prodotto,
                'prezzo' => $prezzo,
                'quantita' => $quantita,
                'tipo' => $tipo,
                'fk_mystery_box' => ($tipo === 'mystery_box') ? $id_prodotto : null,
                'fk_oggetto' => ($tipo === 'oggetto') ? $id_prodotto : null
            ];
        }

        SessionManager::set('cart', $cart);
        $message = "Prodotto aggiunto al carrello (accedi per salvare)";
    }

    SessionManager::setFlashMessage($message, 'success');

} catch (Exception $e) {
    SessionManager::setFlashMessage($e->getMessage(), 'danger');
} finally {
    $conn->close();
}

header('Location: ' . $redirect_url);
exit();