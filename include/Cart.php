<?php
/**
 * include/Cart.php - Sistema di gestione carrello robusto
 */

class Cart {
    private $conn;
    private $user_id;

    /**
     * Costruttore
     */
    public function __construct($db_config = null) {
        // Inizializza session manager
        if (!class_exists('SessionManager')) {
            require_once __DIR__ . '/session_manager.php';
        }

        // Avvia sessione sicura
        SessionManager::startSecureSession();

        // Connessione database se config fornita
        if ($db_config) {
            $this->initDatabase($db_config);
        }

        // Ottieni user_id se loggato
        $this->user_id = SessionManager::isLoggedIn() ? SessionManager::get('user_id') : null;
    }

    /**
     * Inizializza connessione database
     */
    private function initDatabase($db_config) {
        try {
            $this->conn = new mysqli(
                $db_config['host'],
                $db_config['user'],
                $db_config['passwd'],
                $db_config['dbname']
            );

            if ($this->conn->connect_error) {
                error_log("Errore connessione DB in Cart: " . $this->conn->connect_error);
                $this->conn = null;
                return false;
            }

            $this->conn->set_charset("utf8mb4");
            return true;
        } catch (Exception $e) {
            error_log("Eccezione connessione DB in Cart: " . $e->getMessage());
            $this->conn = null;
            return false;
        }
    }

    /**
     * Ottieni elementi del carrello
     * @return array Sempre un array con struttura consistente
     */
    public function getItems() {
        try {
            if ($this->user_id && $this->conn) {
                return $this->getItemsFromDatabase();
            } else {
                return $this->getItemsFromSession();
            }
        } catch (Exception $e) {
            error_log("Errore in Cart::getItems: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Ottieni elementi dal database per utente loggato
     */
    private function getItemsFromDatabase() {
        $items = [];

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
                END as prezzo_prodotto,
                CASE 
                    WHEN c.fk_mystery_box IS NOT NULL THEN mb.quantita_box
                    WHEN c.fk_oggetto IS NOT NULL THEN o.quant_oggetto
                    ELSE NULL
                END as stock_disponibile
            FROM carrello c
            LEFT JOIN mystery_box mb ON c.fk_mystery_box = mb.id_box
            LEFT JOIN oggetto o ON c.fk_oggetto = o.id_oggetto
            WHERE c.fk_utente = ? 
            AND c.stato = 'attivo'
            ORDER BY c.data_creazione DESC
        ";

        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Errore preparazione query: " . $this->conn->error);
        }

        $stmt->bind_param("i", $this->user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $tipo = $row['fk_mystery_box'] != null ? 'mystery_box' : 'oggetto';
            $prodotto_id = $row['fk_mystery_box'] ?? $row['fk_oggetto'];

            $items[] = [
                'cart_key' => $tipo . '_' . $prodotto_id,
                'id_carrello' => $row['id_carrello'],
                'prodotto_id' => $prodotto_id,
                'tipo' => $tipo,
                'nome' => $row['nome_prodotto'] ?? 'Prodotto Sconosciuto',
                'prezzo' => floatval($row['prezzo_prodotto'] ?? 0),
                'quantita' => intval($row['quantita'] ?? 1),
                'totale' => floatval($row['totale'] ?? 0),
                'stock_disponibile' => $row['stock_disponibile'],
                'image' => BASE_URL . '/images/default_product.png'
            ];
        }

        $stmt->close();
        return $items;
    }

    /**
     * Ottieni elementi dalla sessione per utente non loggato
     */
    private function getItemsFromSession() {
        $items = [];
        $session_cart = SessionManager::get('cart', []);

        if (!is_array($session_cart)) {
            return [];
        }

        foreach ($session_cart as $key => $item) {
            if (!is_array($item)) continue;

            $items[] = [
                'cart_key' => $key,
                'id_carrello' => null,
                'prodotto_id' => $item['id_prodotto'] ?? 0,
                'tipo' => $item['tipo'] ?? 'oggetto',
                'nome' => $item['nome_prodotto'] ?? 'Prodotto Sconosciuto',
                'prezzo' => floatval($item['prezzo'] ?? 0),
                'quantita' => intval($item['quantita'] ?? 1),
                'totale' => floatval($item['totale'] ?? 0),
                'stock_disponibile' => null,
                'image' => BASE_URL . '/images/default_product.png'
            ];
        }

        return $items;
    }

    /**
     * Ottieni disponibilità effettiva di un prodotto
     * @param int $id_prodotto ID del prodotto
     * @param string $tipo Tipo prodotto (mystery_box o oggetto)
     * @return array Informazioni sulla disponibilità
     */
    public function getProductAvailability($id_prodotto, $tipo) {
        if (!$this->conn) {
            return [
                'available' => true,
                'stock_total' => null,
                'stock_in_carts' => 0,
                'stock_available' => null,
                'unlimited' => true
            ];
        }

        try {
            $stock_totale = 0;
            $quantita_in_carrelli = 0;
            $unlimited = false;

            if ($tipo === 'mystery_box') {
                // Ottieni stock totale
                $stmt = $this->conn->prepare("SELECT quantita_box, nome_box FROM mystery_box WHERE id_box = ?");
                $stmt->bind_param("i", $id_prodotto);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $stmt->close();

                if (!$row) {
                    return [
                        'available' => false,
                        'error' => 'Prodotto non trovato'
                    ];
                }

                $stock_totale = intval($row['quantita_box']);
                $nome_prodotto = $row['nome_box'];

                // Ottieni quantità nei carrelli
                $stmt = $this->conn->prepare(
                    "SELECT COALESCE(SUM(quantita), 0) as qty_in_carts 
                     FROM carrello 
                     WHERE fk_mystery_box = ? AND stato = 'attivo'"
                );
                $stmt->bind_param("i", $id_prodotto);

            } else { // oggetto
                // Ottieni stock totale
                $stmt = $this->conn->prepare("SELECT quant_oggetto, nome_oggetto FROM oggetto WHERE id_oggetto = ?");
                $stmt->bind_param("i", $id_prodotto);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $stmt->close();

                if (!$row) {
                    return [
                        'available' => false,
                        'error' => 'Prodotto non trovato'
                    ];
                }

                if ($row['quant_oggetto'] === null) {
                    $unlimited = true;
                    $stock_totale = null;
                } else {
                    $stock_totale = intval($row['quant_oggetto']);
                }

                $nome_prodotto = $row['nome_oggetto'];

                // Ottieni quantità nei carrelli (solo se stock limitato)
                if (!$unlimited) {
                    $stmt = $this->conn->prepare(
                        "SELECT COALESCE(SUM(quantita), 0) as qty_in_carts 
                         FROM carrello 
                         WHERE fk_oggetto = ? AND stato = 'attivo'"
                    );
                    $stmt->bind_param("i", $id_prodotto);
                }
            }

            // Calcola disponibilità solo se stock limitato
            if (!$unlimited && isset($stmt)) {
                $stmt->execute();
                $result = $stmt->get_result();
                $quantita_in_carrelli = intval($result->fetch_assoc()['qty_in_carts']);
                $stmt->close();

                $stock_disponibile = $stock_totale - $quantita_in_carrelli;
            } else {
                $stock_disponibile = $unlimited ? null : $stock_totale;
            }

            // Quantità già nel carrello dell'utente corrente
            $qty_user_cart = 0;
            if ($this->user_id) {
                if ($tipo === 'mystery_box') {
                    $stmt = $this->conn->prepare(
                        "SELECT COALESCE(quantita, 0) as qty 
                         FROM carrello 
                         WHERE fk_utente = ? AND fk_mystery_box = ? AND stato = 'attivo'"
                    );
                } else {
                    $stmt = $this->conn->prepare(
                        "SELECT COALESCE(quantita, 0) as qty 
                         FROM carrello 
                         WHERE fk_utente = ? AND fk_oggetto = ? AND stato = 'attivo'"
                    );
                }
                $stmt->bind_param("ii", $this->user_id, $id_prodotto);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $qty_user_cart = intval($row['qty']);
                $stmt->close();
            }

            return [
                'available' => $unlimited || $stock_disponibile > 0,
                'stock_total' => $stock_totale,
                'stock_in_carts' => $quantita_in_carrelli,
                'stock_available' => $stock_disponibile,
                'unlimited' => $unlimited,
                'user_cart_qty' => $qty_user_cart,
                'max_purchasable' => $unlimited ? 99 : min(99, $stock_disponibile),
                'product_name' => $nome_prodotto
            ];

        } catch (Exception $e) {
            error_log("Errore in getProductAvailability: " . $e->getMessage());
            return [
                'available' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Aggiungi prodotto al carrello
     */
    public function addItem($id_prodotto, $tipo, $nome, $prezzo, $quantita = 1) {
        // Validazione input
        if ($id_prodotto <= 0 || $quantita <= 0 || $quantita > 99) {
            throw new Exception("Parametri non validi");
        }

        if (!in_array($tipo, ['mystery_box', 'oggetto'])) {
            throw new Exception("Tipo prodotto non valido");
        }

        // Verifica disponibilità
        $this->checkAvailability($id_prodotto, $tipo, $quantita);

        if ($this->user_id && $this->conn) {
            return $this->addItemToDatabase($id_prodotto, $tipo, $prezzo, $quantita);
        } else {
            return $this->addItemToSession($id_prodotto, $tipo, $nome, $prezzo, $quantita);
        }
    }

    /**
     * Verifica disponibilità prodotto considerando anche il carrello
     */
    private function checkAvailability($id_prodotto, $tipo, $quantita_richiesta, $exclude_cart_id = null) {
        if (!$this->conn) return true; // Skip check se no DB

        $stock_disponibile = 0;
        $quantita_in_carrelli = 0;

        if ($tipo === 'mystery_box') {
            // Ottieni stock totale
            $stmt = $this->conn->prepare("SELECT quantita_box FROM mystery_box WHERE id_box = ?");
            $stmt->bind_param("i", $id_prodotto);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            if (!$row) {
                throw new Exception("Mystery Box non trovata");
            }

            $stock_totale = intval($row['quantita_box']);

            // Ottieni quantità già nei carrelli attivi (escludendo eventualmente il carrello corrente)
            $query = "SELECT COALESCE(SUM(quantita), 0) as qty_in_carts 
                     FROM carrello 
                     WHERE fk_mystery_box = ? 
                     AND stato = 'attivo'";

            if ($exclude_cart_id) {
                $query .= " AND id_carrello != ?";
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param("ii", $id_prodotto, $exclude_cart_id);
            } else {
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param("i", $id_prodotto);
            }

            $stmt->execute();
            $result = $stmt->get_result();
            $quantita_in_carrelli = intval($result->fetch_assoc()['qty_in_carts']);
            $stmt->close();

        } else { // oggetto
            // Ottieni stock totale
            $stmt = $this->conn->prepare("SELECT quant_oggetto FROM oggetto WHERE id_oggetto = ?");
            $stmt->bind_param("i", $id_prodotto);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            if (!$row) {
                throw new Exception("Oggetto non trovato");
            }

            // Se stock illimitato (NULL), permetti
            if ($row['quant_oggetto'] === null) {
                return true;
            }

            $stock_totale = intval($row['quant_oggetto']);

            // Ottieni quantità già nei carrelli attivi
            $query = "SELECT COALESCE(SUM(quantita), 0) as qty_in_carts 
                     FROM carrello 
                     WHERE fk_oggetto = ? 
                     AND stato = 'attivo'";

            if ($exclude_cart_id) {
                $query .= " AND id_carrello != ?";
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param("ii", $id_prodotto, $exclude_cart_id);
            } else {
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param("i", $id_prodotto);
            }

            $stmt->execute();
            $result = $stmt->get_result();
            $quantita_in_carrelli = intval($result->fetch_assoc()['qty_in_carts']);
            $stmt->close();
        }

        // Calcola disponibilità effettiva
        $stock_disponibile = $stock_totale - $quantita_in_carrelli;

        if ($stock_disponibile <= 0) {
            throw new Exception("Prodotto esaurito (tutto lo stock è già nei carrelli)");
        }

        if ($quantita_richiesta > $stock_disponibile) {
            throw new Exception("Quantità non disponibile. Disponibili: $stock_disponibile unità");
        }

        return true;
    }

    /**
     * Aggiungi al database per utente loggato
     */
    private function addItemToDatabase($id_prodotto, $tipo, $prezzo, $quantita) {
        // Controlla se già esiste
        if ($tipo === 'mystery_box') {
            $check_sql = "SELECT id_carrello, quantita FROM carrello 
                         WHERE fk_utente = ? AND fk_mystery_box = ? 
                         AND fk_oggetto IS NULL AND stato = 'attivo'";
            $check_stmt = $this->conn->prepare($check_sql);
            $check_stmt->bind_param("ii", $this->user_id, $id_prodotto);
        } else {
            $check_sql = "SELECT id_carrello, quantita FROM carrello 
                         WHERE fk_utente = ? AND fk_oggetto = ? 
                         AND fk_mystery_box IS NULL AND stato = 'attivo'";
            $check_stmt = $this->conn->prepare($check_sql);
            $check_stmt->bind_param("ii", $this->user_id, $id_prodotto);
        }

        $check_stmt->execute();
        $existing = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();

        if ($existing) {
            // Aggiorna quantità
            $nuova_quantita = $existing['quantita'] + $quantita;

            // Verifica disponibilità per la nuova quantità totale
            // Passa l'id_carrello per escluderlo dal conteggio
            $this->checkAvailability($id_prodotto, $tipo, $nuova_quantita, $existing['id_carrello']);

            $nuovo_totale = $nuova_quantita * $prezzo;

            $update_stmt = $this->conn->prepare(
                "UPDATE carrello SET quantita = ?, totale = ?, data_ultima_modifica = NOW() 
                 WHERE id_carrello = ?"
            );
            $update_stmt->bind_param("idi", $nuova_quantita, $nuovo_totale, $existing['id_carrello']);
            $update_stmt->execute();
            $update_stmt->close();

            return ['success' => true, 'message' => 'Quantità aggiornata'];
        } else {
            // Verifica disponibilità per nuovo inserimento
            $this->checkAvailability($id_prodotto, $tipo, $quantita);

            // Inserisci nuovo
            $totale = $quantita * $prezzo;

            if ($tipo === 'mystery_box') {
                $insert_sql = "INSERT INTO carrello 
                              (fk_utente, fk_mystery_box, fk_oggetto, quantita, totale, stato) 
                              VALUES (?, ?, NULL, ?, ?, 'attivo')";
                $insert_stmt = $this->conn->prepare($insert_sql);
                $insert_stmt->bind_param("iiid", $this->user_id, $id_prodotto, $quantita, $totale);
            } else {
                $insert_sql = "INSERT INTO carrello 
                              (fk_utente, fk_mystery_box, fk_oggetto, quantita, totale, stato) 
                              VALUES (?, NULL, ?, ?, ?, 'attivo')";
                $insert_stmt = $this->conn->prepare($insert_sql);
                $insert_stmt->bind_param("iiid", $this->user_id, $id_prodotto, $quantita, $totale);
            }

            $insert_stmt->execute();
            $insert_stmt->close();

            return ['success' => true, 'message' => 'Prodotto aggiunto'];
        }
    }

    /**
     * Aggiungi alla sessione per utente non loggato
     */
    private function addItemToSession($id_prodotto, $tipo, $nome, $prezzo, $quantita) {
        $cart = SessionManager::get('cart', []);
        $item_key = $tipo . '_' . $id_prodotto;

        if (isset($cart[$item_key])) {
            $cart[$item_key]['quantita'] += $quantita;
            $cart[$item_key]['totale'] = $cart[$item_key]['quantita'] * $prezzo;
            $message = 'Quantità aggiornata';
        } else {
            $cart[$item_key] = [
                'id_prodotto' => $id_prodotto,
                'nome_prodotto' => $nome,
                'prezzo' => $prezzo,
                'quantita' => $quantita,
                'totale' => $quantita * $prezzo,
                'tipo' => $tipo
            ];
            $message = 'Prodotto aggiunto';
        }

        SessionManager::set('cart', $cart);
        $this->updateCartCount();

        return ['success' => true, 'message' => $message];
    }

    /**
     * Aggiorna quantità prodotto
     */
    public function updateQuantity($item_key, $new_quantity) {
        if ($new_quantity < 1 || $new_quantity > 99) {
            throw new Exception("Quantità non valida");
        }

        if ($this->user_id && $this->conn) {
            return $this->updateQuantityInDatabase($item_key, $new_quantity);
        } else {
            return $this->updateQuantityInSession($item_key, $new_quantity);
        }
    }

    /**
     * Aggiorna quantità nel database
     */
    private function updateQuantityInDatabase($item_key, $new_quantity) {
        // Parse item_key
        list($tipo, $product_id) = $this->parseItemKey($item_key);

        // Prima ottieni l'id_carrello per questo prodotto
        if ($tipo === 'mystery_box') {
            $cart_stmt = $this->conn->prepare(
                "SELECT id_carrello FROM carrello 
                 WHERE fk_utente = ? AND fk_mystery_box = ? AND stato = 'attivo'"
            );
        } else {
            $cart_stmt = $this->conn->prepare(
                "SELECT id_carrello FROM carrello 
                 WHERE fk_utente = ? AND fk_oggetto = ? AND stato = 'attivo'"
            );
        }

        $cart_stmt->bind_param("ii", $this->user_id, $product_id);
        $cart_stmt->execute();
        $cart_result = $cart_stmt->get_result();
        $cart_row = $cart_result->fetch_assoc();
        $cart_stmt->close();

        if (!$cart_row) {
            throw new Exception("Prodotto non trovato nel carrello");
        }

        // Verifica disponibilità per la nuova quantità
        // Passa l'id_carrello per escluderlo dal conteggio
        $this->checkAvailability($product_id, $tipo, $new_quantity, $cart_row['id_carrello']);

        // Ottieni prezzo per ricalcolare totale
        if ($tipo === 'mystery_box') {
            $price_stmt = $this->conn->prepare("SELECT prezzo_box FROM mystery_box WHERE id_box = ?");
        } else {
            $price_stmt = $this->conn->prepare("SELECT prezzo_oggetto FROM oggetto WHERE id_oggetto = ?");
        }

        $price_stmt->bind_param("i", $product_id);
        $price_stmt->execute();
        $price_result = $price_stmt->get_result();
        $price_row = $price_result->fetch_assoc();
        $price_stmt->close();

        if (!$price_row) {
            throw new Exception("Prodotto non trovato");
        }

        $prezzo = $tipo === 'mystery_box' ? $price_row['prezzo_box'] : $price_row['prezzo_oggetto'];
        $nuovo_totale = $new_quantity * floatval($prezzo);

        // Aggiorna carrello
        if ($tipo === 'mystery_box') {
            $update_sql = "UPDATE carrello SET quantita = ?, totale = ?, data_ultima_modifica = NOW() 
                          WHERE fk_utente = ? AND fk_mystery_box = ? AND stato = 'attivo'";
        } else {
            $update_sql = "UPDATE carrello SET quantita = ?, totale = ?, data_ultima_modifica = NOW() 
                          WHERE fk_utente = ? AND fk_oggetto = ? AND stato = 'attivo'";
        }

        $update_stmt = $this->conn->prepare($update_sql);
        $update_stmt->bind_param("idii", $new_quantity, $nuovo_totale, $this->user_id, $product_id);
        $update_stmt->execute();

        if ($update_stmt->affected_rows === 0) {
            throw new Exception("Errore nell'aggiornamento del carrello");
        }

        $update_stmt->close();
        $this->updateCartCount();

        return ['success' => true, 'message' => 'Quantità aggiornata'];
    }

    /**
     * Aggiorna quantità in sessione
     */
    private function updateQuantityInSession($item_key, $new_quantity) {
        $cart = SessionManager::get('cart', []);

        if (!isset($cart[$item_key])) {
            throw new Exception("Prodotto non trovato nel carrello");
        }

        $cart[$item_key]['quantita'] = $new_quantity;
        $cart[$item_key]['totale'] = $new_quantity * floatval($cart[$item_key]['prezzo']);

        SessionManager::set('cart', $cart);
        $this->updateCartCount();

        return ['success' => true, 'message' => 'Quantità aggiornata'];
    }

    /**
     * Rimuovi prodotto dal carrello
     */
    public function removeItem($item_key) {
        if ($this->user_id && $this->conn) {
            return $this->removeItemFromDatabase($item_key);
        } else {
            return $this->removeItemFromSession($item_key);
        }
    }

    /**
     * Rimuovi dal database
     */
    private function removeItemFromDatabase($item_key) {
        list($tipo, $product_id) = $this->parseItemKey($item_key);

        if ($tipo === 'mystery_box') {
            $delete_sql = "DELETE FROM carrello 
                          WHERE fk_utente = ? AND fk_mystery_box = ? AND stato = 'attivo'";
        } else {
            $delete_sql = "DELETE FROM carrello 
                          WHERE fk_utente = ? AND fk_oggetto = ? AND stato = 'attivo'";
        }

        $delete_stmt = $this->conn->prepare($delete_sql);
        $delete_stmt->bind_param("ii", $this->user_id, $product_id);
        $delete_stmt->execute();

        if ($delete_stmt->affected_rows === 0) {
            throw new Exception("Prodotto non trovato nel carrello");
        }

        $delete_stmt->close();
        $this->updateCartCount();

        return ['success' => true, 'message' => 'Prodotto rimosso'];
    }

    /**
     * Rimuovi dalla sessione
     */
    private function removeItemFromSession($item_key) {
        $cart = SessionManager::get('cart', []);

        if (!isset($cart[$item_key])) {
            throw new Exception("Prodotto non trovato nel carrello");
        }

        unset($cart[$item_key]);
        SessionManager::set('cart', $cart);
        $this->updateCartCount();

        return ['success' => true, 'message' => 'Prodotto rimosso'];
    }

    /**
     * Calcola totali del carrello
     */
    public function getTotals() {
        $items = $this->getItems();

        // Assicura che items sia sempre un array
        if (!is_array($items)) {
            $items = [];
        }

        $totals = [
            'items' => $items,
            'total_items' => 0,
            'subtotal' => 0,
            'shipping' => 5.00,
            'total' => 0
        ];

        foreach ($items as $item) {
            if (is_array($item)) {
                $totals['total_items'] += intval($item['quantita'] ?? 0);
                $totals['subtotal'] += floatval($item['totale'] ?? 0);
            }
        }

        // Spedizione gratuita sopra 50€
        if ($totals['subtotal'] >= 50) {
            $totals['shipping'] = 0;
        }

        $totals['total'] = $totals['subtotal'] + $totals['shipping'];

        return $totals;
    }

    /**
     * Svuota carrello
     */
    public function clear() {
        if ($this->user_id && $this->conn) {
            $stmt = $this->conn->prepare(
                "UPDATE carrello SET stato = 'abbandonato' 
                 WHERE fk_utente = ? AND stato = 'attivo'"
            );
            $stmt->bind_param("i", $this->user_id);
            $stmt->execute();
            $stmt->close();
        } else {
            SessionManager::set('cart', []);
        }

        $this->updateCartCount();
        return ['success' => true, 'message' => 'Carrello svuotato'];
    }

    /**
     * Aggiorna contatore carrello in sessione
     */
    private function updateCartCount() {
        $totals = $this->getTotals();
        SessionManager::set('cart_items_count', $totals['total_items']);
    }

    /**
     * Parse item key per ottenere tipo e ID
     */
    private function parseItemKey($item_key) {
        if (strpos($item_key, 'mystery_box_') === 0) {
            return ['mystery_box', intval(str_replace('mystery_box_', '', $item_key))];
        } elseif (strpos($item_key, 'oggetto_') === 0) {
            return ['oggetto', intval(str_replace('oggetto_', '', $item_key))];
        }

        throw new Exception("Formato item_key non valido: " . $item_key);
    }

    /**
     * Distruttore - chiude connessione DB
     */
    public function __destruct() {
        if ($this->conn && $this->conn instanceof mysqli) {
            $this->conn->close();
        }
    }
}