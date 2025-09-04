<?php

class SessionManager
{
    private static $initialized = false;

    // ✅ TIMEOUT UNIFORMATO A 5 MINUTI
    const SESSION_TIMEOUT_SECONDS = 300; // 5 minuti
    const SESSION_MAX_LIFETIME = 300;    // 5 minuti massimo
    const SESSION_REGENERATION_INTERVAL = 120; // Rigenera ogni 2 minuti

    public static function startSecureSession(): void
    {
        if (self::$initialized) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            // Carica la configurazione
            require_once __DIR__ . '/session_config.php';

            // Avvia la sessione
            session_start();

            // Inizializza i valori base se nuova sessione
            if (!isset($_SESSION['created'])) {
                $_SESSION['created'] = time();
                $_SESSION['last_regeneration'] = time();
                $_SESSION['last_activity'] = time();
            }

            // Rigenera ID ogni 2 minuti per sicurezza
            if (time() - ($_SESSION['last_regeneration'] ?? 0) > self::SESSION_REGENERATION_INTERVAL) {
                session_regenerate_id(true);
                $_SESSION['last_regeneration'] = time();
            }

            self::$initialized = true;
        }
    }

    public static function validateSession(): bool
    {
        self::startSecureSession();

        // ✅ CONTROLLA TIMEOUT INATTIVITÀ (5 MINUTI)
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > self::SESSION_TIMEOUT_SECONDS)) {
            error_log("Sessione scaduta per inattività: " . (time() - $_SESSION['last_activity']) . " secondi");
            self::destroy();
            return false;
        }

        // ✅ CONTROLLA DURATA MASSIMA SESSIONE (5 MINUTI)
        if (isset($_SESSION['created']) && (time() - $_SESSION['created'] > self::SESSION_MAX_LIFETIME)) {
            error_log("Sessione scaduta per durata massima: " . (time() - $_SESSION['created']) . " secondi");
            self::destroy();
            return false;
        }

        // Aggiorna timestamp attività
        $_SESSION['last_activity'] = time();

        return true;
    }

    public static function login($userId, $userData = []): void
    {
        self::startSecureSession();

        // Rigenera ID per sicurezza
        session_regenerate_id(true);

        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_id'] = $userId;
        $_SESSION['last_activity'] = time();
        $_SESSION['created'] = time();
        $_SESSION['last_regeneration'] = time();

        // Salva dati utente
        foreach ($userData as $key => $value) {
            $_SESSION['user_' . $key] = $value;
        }
    }

    public static function logout()
    {
        // Pulisce tutte le variabili di sessione
        $_SESSION = array();

        // Distrugge il cookie di sessione se esiste
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        // Distrugge la sessione
        session_destroy();
    }

    public static function isLoggedIn(): bool
    {
        self::startSecureSession();

        if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
            return false;
        }

        // Valida la sessione
        return self::validateSession();
    }

    public static function requireLogin($redirect = true): void
    {
        if (!self::isLoggedIn()) {
            if ($redirect) {
                // Salva la pagina richiesta per redirect dopo login
                $current_page = $_SERVER['REQUEST_URI'];
                self::set('redirect_after_login', $current_page);

                // Redirect al login
                $login_url = BASE_URL . '/pages/auth/login.php';
                header('Location: ' . $login_url);
                exit();
            }
        }
    }

    public static function set($key, $value): void
    {
        self::startSecureSession();
        $_SESSION[$key] = $value;
    }

    public static function get($key, $default = null)
    {
        self::startSecureSession();
        return $_SESSION[$key] ?? $default;
    }

    public static function remove($key): void
    {
        self::startSecureSession();
        unset($_SESSION[$key]);
    }

    public static function getUserId(): ?int
    {
        return self::isLoggedIn() ? (int)$_SESSION['user_id'] : null;
    }

    public static function destroy(): void
    {
        self::startSecureSession();

        // Distruggi tutti i dati della sessione
        $_SESSION = [];

        // Elimina il cookie di sessione
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        // Distruggi la sessione
        session_destroy();

        // Reset del flag
        self::$initialized = false;
    }

    public static function getFlashMessage($key = 'flash_message')
    {
        $message = self::get($key);
        if ($message) {
            self::remove($key);
        }
        return $message;
    }

    public static function setFlashMessage($content, $type = 'info', $key = 'flash_message'): void
    {
        self::set($key, [
            'content' => $content,
            'type' => $type
        ]);
    }

    /**
     * ✅ CREA SESSIONE CHECKOUT CON TIMESTAMP CORRETTO
     */
    public static function createCheckoutSession($items, $total, $user_id): bool
    {
        if (empty($items) || $total <= 0) {
            return false;
        }

        $checkout_data = [
            'items' => $items,
            'totale' => $total,
            'user_id' => $user_id,
            'timestamp' => time(), // ✅ Timestamp attuale
            'expires_at' => time() + self::SESSION_TIMEOUT_SECONDS // ✅ Scadenza esplicita
        ];

        self::set('checkout_data', $checkout_data);

        error_log("Checkout session creata: timestamp=" . $checkout_data['timestamp'] . ", expires_at=" . $checkout_data['expires_at']);

        return true;
    }

    /**
     * ✅ VALIDA SESSIONE CHECKOUT
     */
    public static function validateCheckoutSession(): array|false
    {
        $checkout_data = self::get('checkout_data');

        if (!$checkout_data || !is_array($checkout_data)) {
            error_log("Checkout session mancante o non valida");
            return false;
        }

        // Verifica scadenza
        $current_time = time();
        if (isset($checkout_data['expires_at']) && $current_time > $checkout_data['expires_at']) {
            error_log("Checkout session scaduta: current_time={$current_time}, expires_at={$checkout_data['expires_at']}");
            self::remove('checkout_data');
            return false;
        }

        // Verifica user_id
        $current_user = self::get('user_id');
        if (isset($checkout_data['user_id']) && $checkout_data['user_id'] != $current_user) {
            error_log("Checkout session user_id mismatch: stored={$checkout_data['user_id']}, current={$current_user}");
            self::remove('checkout_data');
            return false;
        }

        // Verifica items
        if (!isset($checkout_data['items']) || !is_array($checkout_data['items']) || empty($checkout_data['items'])) {
            error_log("Checkout session items non validi");
            self::remove('checkout_data');
            return false;
        }

        return $checkout_data;
    }

    /**
     * Aggiorna il contatore del carrello
     */
    public static function updateCartCount() {
        $cart = self::get('cart', []);
        $total_items = 0;

        foreach ($cart as $item) {
            $total_items += $item['quantita'] ?? 0;
        }

        self::set('cart_items_count', $total_items);
    }

    /**
     * Aggiungi prodotto al carrello
     */
    public static function addToCart($product_id, $product_data) {
        $cart = self::get('cart', []);
        $item_key = $product_data['tipo'] . '_' . $product_id;

        if (isset($cart[$item_key])) {
            $cart[$item_key]['quantita'] += $product_data['quantita'];
        } else {
            $cart[$item_key] = $product_data;
        }

        self::set('cart', $cart);
        self::updateCartCount();
    }

    /**
     * Rimuovi prodotto dal carrello
     */
    public static function removeFromCart($item_key) {
        $cart = self::get('cart', []);

        if (isset($cart[$item_key])) {
            unset($cart[$item_key]);
            self::set('cart', $cart);
            self::updateCartCount();
            return true;
        }

        return false;
    }

    /**
     * Aggiorna quantità nel carrello
     */
    public static function updateCartQuantity($item_key, $quantity) {
        $cart = self::get('cart', []);

        if (isset($cart[$item_key])) {
            if ($quantity <= 0) {
                unset($cart[$item_key]);
            } else {
                $cart[$item_key]['quantita'] = $quantity;
            }

            self::set('cart', $cart);
            self::updateCartCount();
            return true;
        }

        return false;
    }

    /**
     * Svuota il carrello
     */
    public static function clearCart() {
        self::set('cart', []);
        self::set('cart_items_count', 0);
    }

    /**
     * Verifica se l'utente è admin
     */
    public static function isAdmin() {
        self::startSecureSession();
        return self::get('user_is_admin', false) === true;
    }

    /**
     * Richiede privilegi admin
     */
    public static function requireAdmin() {
        self::requireLogin();

        if (!self::isAdmin()) {
            self::setFlashMessage('Accesso negato. Area riservata agli amministratori.', 'danger');
            $redirect_url = (defined('BASE_URL') ? BASE_URL : '') . '/pages/home_utente.php';
            header('Location: ' . $redirect_url);
            exit();
        }
    }

    /**
     * ✅ DEBUG INFO PER TIMEOUT
     */
    public static function getSessionDebugInfo(): array
    {
        self::startSecureSession();

        return [
            'current_time' => time(),
            'created' => $_SESSION['created'] ?? 'N/A',
            'last_activity' => $_SESSION['last_activity'] ?? 'N/A',
            'last_regeneration' => $_SESSION['last_regeneration'] ?? 'N/A',
            'age_seconds' => isset($_SESSION['created']) ? (time() - $_SESSION['created']) : 'N/A',
            'inactive_seconds' => isset($_SESSION['last_activity']) ? (time() - $_SESSION['last_activity']) : 'N/A',
            'timeout_limit' => self::SESSION_TIMEOUT_SECONDS,
            'max_lifetime' => self::SESSION_MAX_LIFETIME,
            'is_valid' => self::validateSession(),
            'checkout_data_exists' => (bool)self::get('checkout_data'),
            'checkout_timestamp' => self::get('checkout_data')['timestamp'] ?? 'N/A',
            'checkout_expires_at' => self::get('checkout_data')['expires_at'] ?? 'N/A'
        ];
    }
}