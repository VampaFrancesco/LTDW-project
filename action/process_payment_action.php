<?php
// process_payment_action.php - Versione che NON usa metodo_pagamento
// (compatibile con la struttura database attuale)

ob_start();

require_once __DIR__.'/../include/session_manager.php';
require_once __DIR__.'/../include/config.inc.php';

// Richiedi autenticazione
SessionManager::requireLogin();

// Verifica metodo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/pages/cart.php');
    exit();
}

// Recupera i dati dal form
$indirizzo_id = intval($_POST['indirizzo_id'] ?? 0);
$payment_method = $_POST['payment_method'] ?? 'carta_credito'; // Lo salviamo per uso futuro
$note_ordine = trim($_POST['note_ordine'] ?? '');

// Recupera i dati del checkout dalla sessione
$checkout_data = SessionManager::get('checkout_data');
$user_id = SessionManager::get('user_id');

// Validazione
if (!$checkout_data || !isset($checkout_data['items']) || $indirizzo_id <= 0) {
    SessionManager::setFlashMessage('Dati di pagamento non validi. Riprova.', 'danger');
    header('Location: ' . BASE_URL . '/pages/cart.php');
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
    header('Location: ' . BASE_URL . '/pages/cart.php');
    exit();
}

// Inizia transazione
$conn->begin_transaction();

try {
    // 1. Verifica che l'indirizzo appartenga all'utente
    $stmt = $conn->prepare("SELECT id_indirizzo FROM indirizzo_spedizione WHERE id_indirizzo = ? AND fk_utente = ?");
    $stmt->bind_param("ii", $indirizzo_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Indirizzo di spedizione non valido");
    }
    $stmt->close();

    // 2. Recupera gli ID del carrello per questo utente
    $stmt = $conn->prepare("
        SELECT id_carrello 
        FROM carrello 
        WHERE fk_utente = ? 
        AND stato = 'attivo'
        ORDER BY id_carrello DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Nessun carrello attivo trovato");
    }

    $carrello_row = $result->fetch_assoc();
    $carrello_id = $carrello_row['id_carrello'];
    $stmt->close();

    // 3. Crea l'ordine principale (SENZA metodo_pagamento che non esiste nella tabella)
    $stmt = $conn->prepare("
        INSERT INTO ordine (data_ordine, tracking, stato_ordine, fk_utente, fk_indirizzo, fk_carrello) 
        VALUES (NOW(), NULL, 0, ?, ?, ?)
    ");

    $stmt->bind_param("iii", $user_id, $indirizzo_id, $carrello_id);

    if (!$stmt->execute()) {
        throw new Exception("Errore nella creazione dell'ordine: " . $stmt->error);
    }

    $ordine_id = $conn->insert_id;
    $stmt->close();

    // 4. Se hai mystery box nel carrello, crea record in info_ordine
    foreach ($checkout_data['items'] as $item) {
        if (isset($item['fk_mystery_box']) && $item['fk_mystery_box']) {
            // Verifica se la mystery box esiste
            $stmt = $conn->prepare("SELECT id_box FROM mystery_box WHERE id_box = ?");
            $stmt->bind_param("i", $item['fk_mystery_box']);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
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
                    // Log error ma continua
                    error_log("Errore info_ordine: " . $stmt->error);
                }
                $stmt->close();
            }
        }
    }

    // 5. Aggiorna lo stato del carrello a 'completato'
    $stmt = $conn->prepare("
    UPDATE carrello 
    SET stato = 'completato',
        data_ultima_modifica = NOW()
    WHERE id_carrello = ? 
    AND fk_utente = ?
");
    $stmt->bind_param("ii", $carrello_id, $user_id);

    if (!$stmt->execute()) {
        throw new Exception("Errore nell'aggiornamento del carrello");
    }
    $stmt->close();

// 5b. IMPORTANTE: Aggiorna contatore carrello nella sessione
    SessionManager::set('cart_items_count', 0);

    // 6. Crea log dell'ordine
    $stmt = $conn->prepare("
        INSERT INTO ordine_log (fk_ordine, stato_precedente, stato_nuovo, note, data_modifica) 
        VALUES (?, NULL, 0, 'Ordine creato', NOW())
    ");
    $stmt->bind_param("i", $ordine_id);
    $stmt->execute();
    $stmt->close();

    // 7. Se tutto è andato bene, conferma la transazione
    $conn->commit();

    // 8. Rimuovi i dati di checkout dalla sessione
    SessionManager::remove('checkout_data');
    SessionManager::set('cart_items_count', 0);

    // 9. Salva i dati dell'ordine per la pagina di conferma
    SessionManager::set('ultimo_ordine', [
        'id' => $ordine_id,
        'totale' => $checkout_data['totale'] ?? 0,
        'metodo_pagamento' => $payment_method, // Lo salviamo in sessione anche se non nel DB
        'timestamp' => time()
    ]);

    // 10. Redirect alla pagina di conferma
    SessionManager::setFlashMessage('Ordine completato con successo!', 'success');
    header('Location: ' . BASE_URL . '/pages/conferma_ordine.php?order_id=' . $ordine_id);
    exit();

} catch (Exception $e) {
    // Rollback in caso di errore
    $conn->rollback();

    error_log("Errore processo pagamento: " . $e->getMessage());

    SessionManager::setFlashMessage('Errore nell\'elaborazione del pagamento: ' . $e->getMessage(), 'danger');
    header('Location: ' . BASE_URL . '/pages/checkout.php');
    exit();
} finally {
    $conn->close();
}

ob_end_flush();
?>