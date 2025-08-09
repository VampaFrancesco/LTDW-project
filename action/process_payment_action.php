<?php
// Evita output prima degli header
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
$payment_method = $_POST['payment_method'] ?? 'carta_credito';
$note_ordine = trim($_POST['note_ordine'] ?? '');

// Recupera i dati del checkout dalla sessione
$checkout_data = SessionManager::get('checkout_data');
$user_id = SessionManager::get('user_id');

// Validazione
if (!$checkout_data || !isset($checkout_data['items']) || $indirizzo_id <= 0) {
    SessionManager::setFlashMessage('Dati di pagamento non validi. Riprova.', 'danger');
    header('Location: ' . BASE_URL . '/pages/carrello.php');
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
    header('Location: ' . BASE_URL . '/pages/carrello.php');
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

    // 2. Crea l'ordine principale
    // Nota: assumiamo che ci sia solo un carrello attivo per utente
    // In un sistema reale, dovresti gestire meglio questa parte
    $carrello_id = $checkout_data['items'][0]['id_carrello']; // Prendi l'ID del primo item

    $stmt = $conn->prepare("
        INSERT INTO ordine (data_ordine, tracking, stato_ordine, fk_utente, fk_indirizzo, fk_carrello) 
        VALUES (NOW(), NULL, 0, ?, ?, ?)
    ");

    $stmt->bind_param("iii", $user_id, $indirizzo_id, $carrello_id);

    if (!$stmt->execute()) {
        throw new Exception("Errore nella creazione dell'ordine");
    }

    $ordine_id = $conn->insert_id;
    $stmt->close();

    // 3. Per ogni item nel carrello, crea un record in info_ordine (se hai mystery box)
    foreach ($checkout_data['items'] as $item) {
        if ($item['fk_mystery_box']) {
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
                throw new Exception("Errore nel salvataggio dei dettagli dell'ordine");
            }
            $stmt->close();
        }
    }

    // 4. Svuota il carrello dell'utente
    $stmt = $conn->prepare("DELETE FROM carrello WHERE fk_utente = ?");
    $stmt->bind_param("i", $user_id);

    if (!$stmt->execute()) {
        throw new Exception("Errore nello svuotamento del carrello");
    }
    $stmt->close();

    // 5. Se tutto è andato bene, conferma la transazione
    $conn->commit();

    // 6. Rimuovi i dati di checkout dalla sessione
    SessionManager::remove('checkout_data');

    // 7. Salva i dati dell'ordine per la pagina di conferma
    SessionManager::set('ultimo_ordine', [
        'id' => $ordine_id,
        'totale' => $checkout_data['totale'],
        'metodo_pagamento' => $payment_method,
        'timestamp' => time()
    ]);

    // 8. Redirect alla pagina di conferma
    header('Location: ' . BASE_URL . '/pages/conferma_ordine.php');
    exit();

} catch (Exception $e) {
    // Rollback in caso di errore
    $conn->rollback();

    error_log("Errore processo pagamento: " . $e->getMessage());

    SessionManager::setFlashMessage('Si è verificato un errore durante l\'elaborazione del pagamento. Riprova.', 'danger');
    header('Location: ' . BASE_URL . '/pages/pagamento.php');
    exit();
} finally {
    $conn->close();
}

ob_end_flush();