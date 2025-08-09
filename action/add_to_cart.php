<?php
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
$tipo = trim($_POST['tipo'] ?? 'mystery_box'); // mystery_box o oggetto
$redirect_url = $_POST['redirect_url'] ?? BASE_URL . '/pages/home_utente.php';

// Validazione input
$errors = [];
if ($id_prodotto <= 0) $errors[] = "ID prodotto non valido";
if ($quantita <= 0) $errors[] = "Quantità non valida";
if ($quantita > 99) $errors[] = "Quantità massima: 99";
if (empty($nome_prodotto)) $errors[] = "Nome prodotto mancante";
if ($prezzo <= 0) $errors[] = "Prezzo non valido";
if (!in_array($tipo, ['mystery_box', 'oggetto'])) $errors[] = "Tipo prodotto non valido";

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

        // Verifica prezzo
        if (abs($prezzo - $product['prezzo_box']) > 0.01) {
            throw new Exception("Prezzo non corrispondente");
        }

    } else { // oggetto
        $stmt = $conn->prepare("
            SELECT o.*, c.nome_categoria 
            FROM oggetto o
            LEFT JOIN categoria_oggetto c ON o.fk_categoria_oggetto = c.id_categoria
            WHERE o.id_oggetto = ? AND (o.quant_oggetto IS NULL OR o.quant_oggetto > 0)
            AND o.prezzo_oggetto IS NOT NULL
        ");
        $stmt->bind_param("i", $id_prodotto);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$product) {
            throw new Exception("Oggetto non trovato, non disponibile o non vendibile singolarmente");
        }

        if ($product['quant_oggetto'] !== null && $quantita > $product['quant_oggetto']) {
            throw new Exception("Quantità richiesta non disponibile (disponibili: {$product['quant_oggetto']})");
        }

        // Verifica prezzo
        if (abs($prezzo - $product['prezzo_oggetto']) > 0.01) {
            throw new Exception("Prezzo non corrispondente");
        }
    }

} catch (Exception $e) {
    SessionManager::setFlashMessage($e->getMessage(), 'danger');
    header('Location: ' . $redirect_url);
    exit();
}

// Se utente è loggato, salva nel database
if (SessionManager::isLoggedIn()) {
    $id_utente = SessionManager::get('user_id');

    try {
        // Controlla se il prodotto è già nel carrello
        $check_sql = "SELECT id_carrello, quantita FROM carrello WHERE fk_utente = ? AND ";
        if ($tipo === 'mystery_box') {
            $check_sql .= "fk_mystery_box = ? AND fk_oggetto IS NULL";
        } else {
            $check_sql .= "fk_oggetto = ? AND fk_mystery_box IS NULL";
        }

        $stmt = $conn->prepare($check_sql);
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
                throw new Exception('Errore nell\'aggiornare il carrello');
            }
            $stmt->close();

        } else {
            // Inserisci nuovo item nel carrello
            $stmt->close();
            $totale = $quantita * $prezzo;

            if ($tipo === 'mystery_box') {
                $stmt = $conn->prepare("
                    INSERT INTO carrello (fk_utente, fk_mystery_box, fk_oggetto, quantita, totale) 
                    VALUES (?, ?, NULL, ?, ?)
                ");
                $stmt->bind_param("iiid", $id_utente, $id_prodotto, $quantita, $totale);
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO carrello (fk_utente, fk_mystery_box, fk_oggetto, quantita, totale) 
                    VALUES (?, NULL, ?, ?, ?)
                ");
                $stmt->bind_param("iiid", $id_utente, $id_prodotto, $quantita, $totale);
            }

            if ($stmt->execute()) {
                SessionManager::setFlashMessage("Prodotto aggiunto al carrello!", 'success');
            } else {
                throw new Exception('Errore nell\'aggiungere il prodotto: ' . $stmt->error);
            }
            $stmt->close();
        }

        // Aggiorna contatore carrello nel database
        $stmt = $conn->prepare("SELECT SUM(quantita) as total FROM carrello WHERE fk_utente = ?");
        $stmt->bind_param("i", $id_utente);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        SessionManager::set('cart_items_count', $row['total'] ?? 0);

    } catch (Exception $e) {
        SessionManager::setFlashMessage($e->getMessage(), 'danger');
        header('Location: ' . $redirect_url);
        exit();
    }

} else {
    // Utente non loggato - usa sessione con il TUO SessionManager
    try {
        // Prepara dati prodotto per sessione
        $product_data = [
            'id' => $id_prodotto,
            'nome' => $nome_prodotto,
            'prezzo' => $prezzo,
            'quantita' => $quantita,
            'tipo' => $tipo
        ];

        // Usa i metodi del TUO SessionManager
        SessionManager::addToCart($id_prodotto, $product_data);
        SessionManager::setFlashMessage('Prodotto aggiunto al carrello!', 'success');

    } catch (Exception $e) {
        SessionManager::setFlashMessage('Errore nell\'aggiungere al carrello: ' . $e->getMessage(), 'danger');
    }
}

$conn->close();

// Redirect con messaggio di successo
header('Location: ' . $redirect_url);
exit();

ob_end_flush();
