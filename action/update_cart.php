<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../include/session_manager.php';
require_once __DIR__ . '/../include/config.inc.php';

SessionManager::startSecureSession();

// Verifica metodo
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metodo non valido']);
    exit();
}

// Parametri di base
$action   = $_POST['action']   ?? '';
$item_key = $_POST['item_key'] ?? '';

if (empty($action) || empty($item_key)) {
    echo json_encode(['success' => false, 'message' => 'Parametri mancanti']);
    exit();
}

try {
    if (SessionManager::isLoggedIn()) {
        // Utente autenticato: operazioni su database
        $id_utente = SessionManager::get('user_id');

        // --- Estrai tipo e ID dal cart_key sull'ultimo underscore ---
        $pos = strrpos($item_key, '_');
        if ($pos === false) {
            throw new Exception('Formato item_key non valido');
        }
        $tipo        = substr($item_key, 0, $pos);
        $prodotto_id = intval(substr($item_key, $pos + 1));
        // ------------------------------------------------------------

        // Connessione database
        $db  = $config['dbms']['localhost'];
        $conn = new mysqli($db['host'], $db['user'], $db['passwd'], $db['dbname']);
        if ($conn->connect_error) {
            throw new Exception('Errore di connessione al database');
        }

        if ($action === 'update') {
            $quantity = intval($_POST['quantity'] ?? 1);
            $quantity = max(1, min(99, $quantity));

            if ($tipo === 'mystery_box') {
                // Prendi prezzo
                $stmt = $conn->prepare("
                    SELECT prezzo_box 
                      FROM mystery_box 
                     WHERE id_box = ?
                ");
                $stmt->bind_param("i", $prodotto_id);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res->num_rows === 0) {
                    throw new Exception('Mystery Box non trovata');
                }
                $prezzo = $res->fetch_assoc()['prezzo_box'];
                $stmt->close();

                $totale = $quantity * $prezzo;
                $stmt = $conn->prepare("
                    UPDATE carrello 
                       SET quantita = ?, totale = ?
                     WHERE fk_utente = ? 
                       AND fk_mystery_box = ? 
                       AND fk_oggetto IS NULL
                ");
                $stmt->bind_param("idii", $quantity, $totale, $id_utente, $prodotto_id);

            } elseif ($tipo === 'oggetto') {
                // Prendi prezzo
                $stmt = $conn->prepare("
                    SELECT prezzo_oggetto 
                      FROM oggetto 
                     WHERE id_oggetto = ?
                ");
                $stmt->bind_param("i", $prodotto_id);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res->num_rows === 0) {
                    throw new Exception('Oggetto non trovato');
                }
                $row = $res->fetch_assoc();
                if ($row['prezzo_oggetto'] === null) {
                    throw new Exception('Oggetto senza prezzo definito');
                }
                $prezzo = $row['prezzo_oggetto'];
                $stmt->close();

                $totale = $quantity * $prezzo;
                $stmt = $conn->prepare("
                    UPDATE carrello 
                       SET quantita = ?, totale = ?
                     WHERE fk_utente = ? 
                       AND fk_oggetto = ? 
                       AND fk_mystery_box IS NULL
                ");
                $stmt->bind_param("idii", $quantity, $totale, $id_utente, $prodotto_id);

            } else {
                throw new Exception('Tipo prodotto non valido');
            }

            if (!$stmt->execute()) {
                throw new Exception('Errore nell\'aggiornamento: ' . $stmt->error);
            }
            $stmt->close();

        } elseif ($action === 'remove') {

            if ($tipo === 'mystery_box') {
                $stmt = $conn->prepare("
                    DELETE FROM carrello 
                     WHERE fk_utente = ? 
                       AND fk_mystery_box = ? 
                       AND fk_oggetto IS NULL
                ");
            } elseif ($tipo === 'oggetto') {
                $stmt = $conn->prepare("
                    DELETE FROM carrello 
                     WHERE fk_utente = ? 
                       AND fk_oggetto = ? 
                       AND fk_mystery_box IS NULL
                ");
            } else {
                throw new Exception('Tipo prodotto non valido');
            }

            $stmt->bind_param("ii", $id_utente, $prodotto_id);
            if (!$stmt->execute()) {
                throw new Exception('Errore nella rimozione: ' . $stmt->error);
            }
            if ($stmt->affected_rows === 0) {
                throw new Exception('Nessun elemento trovato da rimuovere');
            }
            $stmt->close();

        } else {
            throw new Exception('Azione non valida');
        }

        // Aggiorna contatore carrello
        $stmt = $conn->prepare("
            SELECT SUM(quantita) AS total 
              FROM carrello 
             WHERE fk_utente = ?
        ");
        $stmt->bind_param("i", $id_utente);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
        $stmt->close();
        $conn->close();

        SessionManager::set('cart_items_count', intval($total));

    } else {
        // Utente NON autenticato: carrello in sessione
        $cart = SessionManager::get('cart', []);

        if ($action === 'update') {
            $quantity = intval($_POST['quantity'] ?? 1);
            $quantity = max(1, min(99, $quantity));
            if (!isset($cart[$item_key])) {
                throw new Exception('Prodotto non trovato nel carrello');
            }
            $cart[$item_key]['quantita'] = $quantity;

        } elseif ($action === 'remove') {
            if (!isset($cart[$item_key])) {
                throw new Exception('Prodotto non trovato nel carrello');
            }
            unset($cart[$item_key]);

        } else {
            throw new Exception('Azione non valida');
        }

        SessionManager::set('cart', $cart);

        // Riconta
        $count = 0;
        foreach ($cart as $it) {
            $count += $it['quantita'];
        }
        SessionManager::set('cart_items_count', $count);
    }

    // Tutto OK
    echo json_encode(['success' => true, 'message' => 'Operazione completata']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();
}
