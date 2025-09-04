<?php
/**
 * action/process_payment_action.php - VERSIONE FINALE
 * Supporta sia Mystery Box che oggetti singoli
 * Richiede la modifica della tabella info_ordine
 */

ob_start();

require_once __DIR__.'/../include/session_manager.php';
require_once __DIR__.'/../include/config.inc.php';

SessionManager::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/pages/cart.php');
    exit();
}

$user_id = SessionManager::get('user_id');
$indirizzo_id = intval($_POST['indirizzo_id'] ?? 0);
$payment_method = $_POST['payment_method'] ?? 'carta_credito';
$note_ordine = trim($_POST['note_ordine'] ?? '');

$checkout_data = SessionManager::get('checkout_data');

// Validazioni
if (!$checkout_data || !isset($checkout_data['items']) || $indirizzo_id <= 0) {
    SessionManager::setFlashMessage('Dati di pagamento non validi.', 'danger');
    header('Location: ' . BASE_URL . '/pages/cart.php');
    exit();
}

// Verifica scadenza sessione (60 minuti)
if (isset($checkout_data['timestamp']) && time() - $checkout_data['timestamp'] > 3600) {
    SessionManager::remove('checkout_data');
    SessionManager::setFlashMessage('Sessione scaduta.', 'warning');
    header('Location: ' . BASE_URL . '/pages/cart.php');
    exit();
}

// Verifica che l'utente corrente sia lo stesso del checkout
if (isset($checkout_data['user_id']) && $checkout_data['user_id'] != $user_id) {
    SessionManager::remove('checkout_data');
    SessionManager::setFlashMessage('Sessione non valida.', 'danger');
    header('Location: ' . BASE_URL . '/pages/cart.php');
    exit();
}

// Database
$db_config = $config['dbms']['localhost'];
$conn = new mysqli(
    $db_config['host'],
    $db_config['user'],
    $db_config['passwd'],
    $db_config['dbname']
);

if ($conn->connect_error) {
    SessionManager::setFlashMessage('Errore di connessione al database', 'danger');
    header('Location: ' . BASE_URL . '/pages/pagamento.php');
    exit();
}

$conn->begin_transaction();

try {
    // 1. Verifica che l'indirizzo appartenga all'utente
    $stmt = $conn->prepare("
        SELECT id_indirizzo 
        FROM indirizzo_spedizione 
        WHERE id_indirizzo = ? AND fk_utente = ?
    ");
    $stmt->bind_param("ii", $indirizzo_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Indirizzo di spedizione non valido");
    }
    $stmt->close();

    // 2. Calcola totali
    $subtotale = $checkout_data['totale'];
    $spedizione = $subtotale >= 50 ? 0 : 5.00;
    $totale_finale = $subtotale + $spedizione;
    $quantita_totale = 0;

    // Calcola quantità totale
    foreach ($checkout_data['items'] as $item) {
        $quantita_totale += $item['quantita'];
    }

    // 3. Crea ordine
    $stmt = $conn->prepare("
        INSERT INTO ordine (stato_ordine, fk_utente, fk_indirizzo) 
        VALUES (0, ?, ?)
    ");
    $stmt->bind_param("ii", $user_id, $indirizzo_id);

    if (!$stmt->execute()) {
        throw new Exception("Errore nella creazione dell'ordine: " . $stmt->error);
    }

    $ordine_id = $conn->insert_id;
    $stmt->close();

    // 4. ✅ INSERISCI DETTAGLI ORDINE - SUPPORTA SIA MYSTERY BOX CHE OGGETTI
    $prodotti_inseriti = 0;
    $mystery_boxes_inserite = 0;
    $oggetti_inseriti = 0;
    $errori_inserimento = [];

    foreach ($checkout_data['items'] as $item) {

        // ✅ MYSTERY BOX
        if (isset($item['fk_mystery_box']) && $item['fk_mystery_box'] && $item['fk_mystery_box'] > 0) {
            // Verifica che la mystery box esista
            $stmt = $conn->prepare("SELECT id_box, nome_box FROM mystery_box WHERE id_box = ?");
            $stmt->bind_param("i", $item['fk_mystery_box']);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $stmt->close();

                // Inserisci nella tabella info_ordine (solo fk_box)
                $stmt = $conn->prepare("
                    INSERT INTO info_ordine (fk_ordine, fk_box, fk_oggetto, quantita_ordine, totale_ordine) 
                    VALUES (?, ?, NULL, ?, ?)
                ");
                $stmt->bind_param("iiid", $ordine_id, $item['fk_mystery_box'], $item['quantita'], $item['totale']);

                if ($stmt->execute()) {
                    $prodotti_inseriti++;
                    $mystery_boxes_inserite++;
                } else {
                    $errore = "Errore inserimento Mystery Box {$item['fk_mystery_box']}: " . $stmt->error;
                    error_log($errore);
                    $errori_inserimento[] = $errore;
                }
                $stmt->close();
            } else {
                $stmt->close();
                $errore = "Mystery Box {$item['fk_mystery_box']} non trovata nel database";
                error_log($errore);
                $errori_inserimento[] = $errore;
            }
        }

        // ✅ OGGETTO SINGOLO
        elseif (isset($item['fk_oggetto']) && $item['fk_oggetto'] && $item['fk_oggetto'] > 0) {
            // Verifica che l'oggetto esista
            $stmt = $conn->prepare("SELECT id_oggetto, nome_oggetto FROM oggetto WHERE id_oggetto = ?");
            $stmt->bind_param("i", $item['fk_oggetto']);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $stmt->close();

                // ✅ Inserisci nella tabella info_ordine (solo fk_oggetto)
                $stmt = $conn->prepare("
                    INSERT INTO info_ordine (fk_ordine, fk_box, fk_oggetto, quantita_ordine, totale_ordine) 
                    VALUES (?, NULL, ?, ?, ?)
                ");
                $stmt->bind_param("iiid", $ordine_id, $item['fk_oggetto'], $item['quantita'], $item['totale']);

                if ($stmt->execute()) {
                    $prodotti_inseriti++;
                    $oggetti_inseriti++;
                } else {
                    $errore = "Errore inserimento Oggetto {$item['fk_oggetto']}: " . $stmt->error;
                    error_log($errore);
                    $errori_inserimento[] = $errore;
                }
                $stmt->close();
            } else {
                $stmt->close();
                $errore = "Oggetto {$item['fk_oggetto']} non trovato nel database";
                error_log($errore);
                $errori_inserimento[] = $errore;
            }
        }

        // ITEM NON RICONOSCIUTO
        else {
            $errore = "Item non riconosciuto: né Mystery Box né Oggetto valido";
            error_log($errore . " - " . print_r($item, true));
            $errori_inserimento[] = $errore;
        }
    }

    // Verifica che almeno un prodotto sia stato inserito
    if ($prodotti_inseriti === 0) {
        $dettagli_errore = "Dettagli: " . count($checkout_data['items']) . " items nel carrello, " .
            count($errori_inserimento) . " errori. " .
            "Primi errori: " . implode('; ', array_slice($errori_inserimento, 0, 3));
        throw new Exception("Nessun prodotto valido nell'ordine. " . $dettagli_errore);
    }

    // 5. Aggiorna stato carrello
    $carrello_ids = array_column($checkout_data['items'], 'id_carrello');

    if (!empty($carrello_ids)) {
        $ids_placeholder = str_repeat('?,', count($carrello_ids) - 1) . '?';

        $stmt = $conn->prepare("
            UPDATE carrello 
            SET stato = 'completato',
                data_ultima_modifica = NOW()
            WHERE id_carrello IN ($ids_placeholder)
            AND fk_utente = ?
            AND stato = 'attivo'
        ");

        $types = str_repeat('i', count($carrello_ids)) . 'i';
        $params = array_merge($carrello_ids, [$user_id]);
        $stmt->bind_param($types, ...$params);

        if (!$stmt->execute()) {
            throw new Exception("Errore nell'aggiornamento del carrello: " . $stmt->error);
        }

        $carrelli_aggiornati = $stmt->affected_rows;
        $stmt->close();
    }

    // 6. Log ordine
    $order_uuid = uniqid('ORD-', true);
    $stmt = $conn->prepare("
        INSERT INTO ordine_log (fk_ordine, stato_precedente, stato_nuovo, note, data_modifica) 
        VALUES (?, NULL, 0, ?, NOW())
    ");

    $note_log = "Ordine {$order_uuid} creato - {$quantita_totale} articoli " .
        "({$mystery_boxes_inserite} Mystery Box, {$oggetti_inseriti} oggetti) - " .
        "Totale: €" . number_format($totale_finale, 2) .
        " - Pagamento: {$payment_method}";

    if (!empty($note_ordine)) {
        $note_log .= " - Note: " . $note_ordine;
    }
    if (!empty($errori_inserimento)) {
        $note_log .= " - Avvisi: " . count($errori_inserimento) . " items non processati";
    }

    $stmt->bind_param("is", $ordine_id, $note_log);

    if (!$stmt->execute()) {
        error_log("Errore log ordine: " . $stmt->error);
    }
    $stmt->close();

    // Commit transazione
    $conn->commit();

    // 7. Cleanup sessione
    SessionManager::remove('checkout_data');
    SessionManager::set('cart_items_count', 0);

    // 8. Salva dati per pagina di conferma
    SessionManager::set('ultimo_ordine', [
        'id' => $ordine_id,
        'uuid' => $order_uuid,
        'totale' => $totale_finale,
        'subtotale' => $subtotale,
        'spedizione' => $spedizione,
        'quantita' => $quantita_totale,
        'prodotti_inseriti' => $prodotti_inseriti,
        'mystery_boxes' => $mystery_boxes_inserite,
        'oggetti' => $oggetti_inseriti,
        'errori' => count($errori_inserimento),
        'metodo_pagamento' => $payment_method,
        'note' => $note_ordine,
        'timestamp' => time(),
        'carrelli_aggiornati' => $carrelli_aggiornati ?? 0
    ]);

    // Messaggio di successo
    $messaggio_successo = "🎉 Ordine #{$ordine_id} completato con successo! ";
    $messaggio_successo .= "{$prodotti_inseriti} prodotti elaborati ";
    if ($mystery_boxes_inserite > 0) $messaggio_successo .= "({$mystery_boxes_inserite} Mystery Box";
    if ($oggetti_inseriti > 0) $messaggio_successo .= ($mystery_boxes_inserite > 0 ? ", " : "(") . "{$oggetti_inseriti} oggetti";
    if ($prodotti_inseriti > 0) $messaggio_successo .= ") ";
    $messaggio_successo .= "- Totale: €" . number_format($totale_finale, 2);

    if (count($errori_inserimento) > 0) {
        $messaggio_successo .= " ⚠️ " . count($errori_inserimento) . " items non processati";
    }

    SessionManager::setFlashMessage($messaggio_successo, 'success');

    // Redirect alla pagina di conferma
    header('Location: ' . BASE_URL . '/pages/conferma_ordine.php');
    exit();

} catch (Exception $e) {
    // Rollback in caso di errore
    $conn->rollback();

    $error_message = $e->getMessage();
    error_log("Errore processo pagamento (Utente: {$user_id}): " . $error_message);

    // Se l'errore contiene "fk_oggetto", suggerisci l'aggiornamento DB
    if (strpos($error_message, 'fk_oggetto') !== false || strpos($error_message, "doesn't have a default value") !== false) {
        $error_message .= " ⚠️ SUGGERIMENTO: Potrebbe essere necessario aggiornare la struttura della tabella info_ordine per supportare oggetti singoli.";
    }

    SessionManager::setFlashMessage(
        'Errore nel completamento dell\'ordine: ' . $error_message . ' Riprova o contatta l\'assistenza.',
        'danger'
    );

    header('Location: ' . BASE_URL . '/pages/pagamento.php');
    exit();

} finally {
    if (isset($stmt) && $stmt) {
        $stmt->close();
    }
    $conn->close();
}

ob_end_flush();
?>