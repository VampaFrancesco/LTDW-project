<?php
/**
 * process_payment_action.php - SISTEMA ORDINI UNIVOCI
 * Ogni checkout = un ordine separato e distinto
 */

ob_start();

require_once __DIR__.'/../include/session_manager.php';
require_once __DIR__.'/../include/config.inc.php';

SessionManager::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/pages/cart.php');
    exit();
}

$indirizzo_id = intval($_POST['indirizzo_id'] ?? 0);
$payment_method = $_POST['payment_method'] ?? 'carta_credito';
$note_ordine = trim($_POST['note_ordine'] ?? '');

$checkout_data = SessionManager::get('checkout_data');
$user_id = SessionManager::get('user_id');

if (!$checkout_data || !isset($checkout_data['items']) || $indirizzo_id <= 0) {
    SessionManager::setFlashMessage('Dati di pagamento non validi. Riprova.', 'danger');
    header('Location: ' . BASE_URL . '/pages/cart.php');
    exit();
}

$db_config = $config['dbms']['localhost'];
$conn = new mysqli(
    $db_config['host'],
    $db_config['user'],
    $db_config['passwd'],
    $db_config['dbname']
);

if ($conn->connect_error) {
    SessionManager::setFlashMessage('Errore di connessione al database', 'danger');
    header('Location: ' . BASE_URL . '/pages/cart.php');
    exit();
}

$conn->begin_transaction();

try {
    // 1. Verifica indirizzo
    $stmt = $conn->prepare("SELECT id_indirizzo FROM indirizzo_spedizione WHERE id_indirizzo = ? AND fk_utente = ?");
    $stmt->bind_param("ii", $indirizzo_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Indirizzo di spedizione non valido");
    }
    $stmt->close();

    // 2. NUOVO APPROCCIO: Recupera SOLO gli elementi attuali del carrello
    // basandosi sui dati del checkout_data, non su query temporali
    $carrello_items_query = [];
    $totale_ordine = 0;
    $quantita_totale = 0;

    // Trova gli ID carrello corrispondenti agli items nel checkout
    foreach ($checkout_data['items'] as $checkout_item) {
        $search_query = "
            SELECT id_carrello, totale, quantita, fk_mystery_box, fk_oggetto 
            FROM carrello 
            WHERE fk_utente = ? 
            AND stato = 'attivo'
        ";

        $search_params = [$user_id];
        $search_types = "i";

        // Aggiungi condizione specifica per il prodotto
        if (isset($checkout_item['fk_mystery_box']) && $checkout_item['fk_mystery_box']) {
            $search_query .= " AND fk_mystery_box = ? AND fk_oggetto IS NULL";
            $search_params[] = $checkout_item['fk_mystery_box'];
            $search_types .= "i";
        } elseif (isset($checkout_item['fk_oggetto']) && $checkout_item['fk_oggetto']) {
            $search_query .= " AND fk_oggetto = ? AND fk_mystery_box IS NULL";
            $search_params[] = $checkout_item['fk_oggetto'];
            $search_types .= "i";
        } else {
            continue; // Skip invalid items
        }

        $search_query .= " AND quantita = ? LIMIT 1";
        $search_params[] = $checkout_item['quantita'];
        $search_types .= "i";

        $stmt = $conn->prepare($search_query);
        $stmt->bind_param($search_types, ...$search_params);
        $stmt->execute();
        $item_result = $stmt->get_result();

        if ($item_result->num_rows > 0) {
            $item = $item_result->fetch_assoc();
            $carrello_items_query[] = $item;
            $totale_ordine += floatval($item['totale']);
            $quantita_totale += intval($item['quantita']);
        }
        $stmt->close();
    }

    if (empty($carrello_items_query)) {
        throw new Exception("Nessun elemento valido trovato nel carrello per questo ordine");
    }

    // 3. Crea ordine con ID carrello rappresentativo (primo elemento)
    $primo_carrello_id = $carrello_items_query[0]['id_carrello'];

    $stmt = $conn->prepare("
        INSERT INTO ordine (data_ordine, tracking, stato_ordine, fk_utente, fk_indirizzo, fk_carrello) 
        VALUES (NOW(), NULL, 0, ?, ?, ?)
    ");
    $stmt->bind_param("iii", $user_id, $indirizzo_id, $primo_carrello_id);

    if (!$stmt->execute()) {
        throw new Exception("Errore nella creazione dell'ordine: " . $stmt->error);
    }

    $ordine_id = $conn->insert_id;
    $stmt->close();

    // 4. Processa Mystery Box per info_ordine
    foreach ($carrello_items_query as $item) {
        if (!empty($item['fk_mystery_box'])) {
            $stmt = $conn->prepare("SELECT id_box FROM mystery_box WHERE id_box = ?");
            $stmt->bind_param("i", $item['fk_mystery_box']);
            $stmt->execute();
            $result_check = $stmt->get_result();

            if ($result_check->num_rows > 0) {
                $stmt->close();

                $stmt = $conn->prepare("
                    INSERT INTO info_ordine (fk_ordine, fk_box, quantita_ordine, totale_ordine) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->bind_param("iiid",
                    $ordine_id,
                    $item['fk_mystery_box'],
                    $item['quantita'],
                    $item['totale']
                );

                if (!$stmt->execute()) {
                    error_log("Errore info_ordine per Mystery Box {$item['fk_mystery_box']}: " . $stmt->error);
                }
                $stmt->close();
            } else {
                $stmt->close();
            }
        }
    }

    // 5. Aggiorna SOLO gli elementi specifici di QUESTO ordine
    $carrello_ids = array_column($carrello_items_query, 'id_carrello');
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
        throw new Exception("Errore nell'aggiornamento del carrello");
    }
    $stmt->close();

    // 6. Aggiorna contatore carrello
    SessionManager::set('cart_items_count', 0);

    // 7. Log ordine con UUID per unicità
    $order_uuid = uniqid('ORD-', true);
    $stmt = $conn->prepare("
        INSERT INTO ordine_log (fk_ordine, stato_precedente, stato_nuovo, note, data_modifica) 
        VALUES (?, NULL, 0, ?, NOW())
    ");

    $note_log = "Ordine {$order_uuid} creato con {$quantita_totale} articoli (" . count($carrello_items_query) . " prodotti distinti) per €" . number_format($totale_ordine, 2);
    $stmt->bind_param("is", $ordine_id, $note_log);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    // 8. Cleanup sessione
    SessionManager::remove('checkout_data');
    SessionManager::set('cart_items_count', 0);

    // 9. Salva dati ordine per conferma
    SessionManager::set('ultimo_ordine', [
        'id' => $ordine_id,
        'uuid' => $order_uuid,
        'totale' => $totale_ordine,
        'quantita_articoli' => $quantita_totale,
        'numero_prodotti' => count($carrello_items_query),
        'metodo_pagamento' => $payment_method,
        'timestamp' => time(),
        'items' => $carrello_items_query
    ]);

    SessionManager::setFlashMessage(
        "Ordine #{$ordine_id} completato con successo! {$quantita_totale} articoli per €" . number_format($totale_ordine, 2),
        'success'
    );

    header('Location: ' . BASE_URL . '/pages/conferma_ordine.php?order_id=' . $ordine_id);
    exit();

} catch (Exception $e) {
    $conn->rollback();
    error_log("Errore processo pagamento: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    SessionManager::setFlashMessage(
        'Errore nell\'elaborazione del pagamento: ' . $e->getMessage(),
        'danger'
    );
    header('Location: ' . BASE_URL . '/pages/checkout.php');
    exit();

} finally {
    $conn->close();
}

ob_end_flush();
