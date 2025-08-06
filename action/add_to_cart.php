<?php
ob_start();

require_once __DIR__ . '/../include/session_manager.php';
require_once __DIR__ . '/../include/config.inc.php';

// Inizializza sessione
SessionManager::startSecureSession();

// Verifica metodo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    SessionManager::setFlashMessage('Metodo non valido', 'danger');
    header('Location: ' . BASE_URL);
    exit();
}

// Recupera dati
$id_prodotto = intval($_POST['id_prodotto'] ?? 0);
$nome_prodotto = trim($_POST['nome_prodotto'] ?? '');
$prezzo = floatval($_POST['prezzo'] ?? 0);
$quantita = intval($_POST['quantita'] ?? 1);
$tipo = trim($_POST['tipo'] ?? 'mystery_box'); // mystery_box o oggetto
$redirect_url = $_POST['redirect_url'] ?? BASE_URL . '/pages/pokémon.php';

// Validazione
if ($id_prodotto <= 0 || $quantita <= 0 || empty($nome_prodotto) || $prezzo <= 0) {
    SessionManager::setFlashMessage('Dati prodotto non validi', 'danger');
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

// Se utente è loggato, salva nel database
if (SessionManager::isLoggedIn()) {
    $id_utente = SessionManager::get('user_id');

    // Controlla se il prodotto è già nel carrello
    $stmt = $conn->prepare("SELECT id_carrello, quantita FROM carrello WHERE fk_utente = ? AND " .
        ($tipo === 'mystery_box' ? 'fk_mystery_box = ? AND fk_oggetto IS NULL' : 'fk_oggetto = ? AND fk_mystery_box IS NULL'));
    $stmt->bind_param("ii", $id_utente, $id_prodotto);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Aggiorna quantità esistente
        $row = $result->fetch_assoc();
        $new_quantity = $row['quantita'] + $quantita;
        $new_total = $new_quantity * $prezzo;

        $stmt->close();
        $stmt = $conn->prepare("UPDATE carrello SET quantita = ?, totale = ? WHERE id_carrello = ?");
        $stmt->bind_param("idi", $new_quantity, $new_total, $row['id_carrello']);

        if ($stmt->execute()) {
            SessionManager::setFlashMessage("Quantità aggiornata nel carrello!", 'success');
        } else {
            SessionManager::setFlashMessage('Errore nell\'aggiornare il carrello', 'danger');
        }

    } else {
        // Inserisci nuovo item nel carrello
        $stmt->close();
        $totale = $quantita * $prezzo;

        // Prima verifica che il prodotto esista effettivamente
        if ($tipo === 'mystery_box') {
            // Verifica che la mystery box esista
            $check = $conn->prepare("SELECT id_box FROM mystery_box WHERE id_box = ?");
            $check->bind_param("i", $id_prodotto);
            $check->execute();
            if ($check->get_result()->num_rows === 0) {
                SessionManager::setFlashMessage('Mystery Box non trovata', 'danger');
                header('Location: ' . $redirect_url);
                exit();
            }
            $check->close();

            $stmt = $conn->prepare("INSERT INTO carrello (fk_utente, fk_mystery_box, fk_oggetto, quantita, totale) VALUES (?, ?, NULL, ?, ?)");
            $stmt->bind_param("iiid", $id_utente, $id_prodotto, $quantita, $totale);
        } else {
            // Verifica che l'oggetto esista e abbia un prezzo
            $check = $conn->prepare("SELECT id_oggetto, prezzo_oggetto FROM oggetto WHERE id_oggetto = ?");
            $check->bind_param("i", $id_prodotto);
            $check->execute();
            $result = $check->get_result();

            if ($result->num_rows === 0) {
                SessionManager::setFlashMessage('Oggetto non trovato', 'danger');
                header('Location: ' . $redirect_url);
                exit();
            }

            $oggetto = $result->fetch_assoc();
            if ($oggetto['prezzo_oggetto'] === null) {
                SessionManager::setFlashMessage('Questo oggetto non può essere acquistato singolarmente', 'danger');
                header('Location: ' . $redirect_url);
                exit();
            }
            $check->close();

            $stmt = $conn->prepare("INSERT INTO carrello (fk_utente, fk_mystery_box, fk_oggetto, quantita, totale) VALUES (?, NULL, ?, ?, ?)");
            $stmt->bind_param("iiid", $id_utente, $id_prodotto, $quantita, $totale);
        }

        if ($stmt->execute()) {
            SessionManager::setFlashMessage("Prodotto aggiunto al carrello!", 'success');
        } else {
            SessionManager::setFlashMessage('Errore nell\'aggiungere il prodotto: ' . $stmt->error, 'danger');
        }
    }

    $stmt->close();
    updateCartCount($conn, $id_utente);

} else {
    // Utente non loggato - usa sessione
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
            'tipo' => $tipo
        ];
    }

    SessionManager::set('cart', $cart);

    // Aggiorna contatore
    $total_items = 0;
    foreach ($cart as $item) {
        $total_items += $item['quantita'];
    }
    SessionManager::set('cart_items_count', $total_items);

    SessionManager::setFlashMessage('Prodotto aggiunto al carrello!', 'success');
}

$conn->close();
header('Location: ' . $redirect_url);
exit();

// Funzione per aggiornare il contatore carrello
function updateCartCount($conn, $id_utente) {
    $stmt = $conn->prepare("SELECT SUM(quantita) as total FROM carrello WHERE fk_utente = ?");
    $stmt->bind_param("i", $id_utente);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    SessionManager::set('cart_items_count', $row['total'] ?? 0);
}