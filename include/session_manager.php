<?php

/**
 * Gestione centralizzata e sicura delle sessioni (Versione Unificata).
 *
 * Questa classe unifica tutte le configurazioni (cookie, timeout, ini_set)
 * e include tutti i metodi helper dell'applicazione (Carrello, Checkout, Admin).
 * Sostituisce session_manager.php, session_config.php, e session_constants.php.
 *
 * IMPORTANTE: Assicurati che non ci sia NESSUN output (spazi, HTML, echo)
 * prima della chiamata a startSecureSession() in qualsiasi pagina.
 */
class SessionManager
{
    // --- 1. CONFIGURAZIONE CENTRALIZZATA (UNICA FONTE DI VERITÀ) ---
    // Modifica i valori qui per cambiare il comportamento della sessione.

    /** @var int Timeout per INATTIVITÀ in secondi (es. 30 minuti). */
    private const SESSION_INACTIVITY_TIMEOUT = 1800; // 30 minuti (30 * 60)

    /** @var int Durata MASSIMA della sessione in secondi (es. 2 ore), indipendentemente dall'attività. */
    private const SESSION_MAX_LIFETIME = 7200; // 2 ore (60 * 60 * 2)

    /** @var int Frequenza di rigenerazione dell'ID sessione (es. 5 minuti). */
    private const SESSION_REGENERATION_INTERVAL = 300; // 5 minuti

    /** @var int Timeout specifico per i dati di checkout (es. 5 minuti). Logica di business. */
    private const CHECKOUT_EXPIRATION_SECONDS = 300; // 5 minuti

    /** @var string Nome base per il cookie di sessione. */
    private const SESSION_NAME = 'BOXOMNIA_SESS';

    /** @var string Path del cookie. Deve corrispondere alla root del progetto. */
    private const COOKIE_PATH = '/LTDW-project/';

    /** @var bool Cookie sicuro (solo HTTPS). Impostare a 'true' in produzione. */
    private const COOKIE_SECURE = false; // CAMBIARE A TRUE in produzione (con HTTPS attivo)

    /** @var bool Impedisce accesso al cookie tramite JavaScript (previene XSS). */
    private const COOKIE_HTTPONLY = true;

    /** @var string Policy SameSite (previene CSRF). 'Strict' è il più sicuro. */
    private const COOKIE_SAMESITE = 'Strict';

    /**
     * @var bool Flag per assicurare che l'inizializzazione avvenga una sola volta.
     */
    private static $initialized = false;

    // --- 2. METODI PRINCIPALI DI GESTIONE SESSIONE ---

    /**
     * Avvia e configura una sessione sicura.
     * Applica tutte le impostazioni ini_set e cookie dalla configurazione centralizzata.
     */
    public static function startSecureSession(): void
    {
        if (self::$initialized || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Impostazioni di sicurezza PHP (ini_set)
        ini_set('session.use_strict_mode', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_httponly', self::COOKIE_HTTPONLY ? 1 : 0);
        ini_set('session.cookie_samesite', self::COOKIE_SAMESITE);
        ini_set('session.cookie_secure', self::COOKIE_SECURE ? 1 : 0);
        ini_set('session.gc_maxlifetime', self::SESSION_MAX_LIFETIME);
        ini_set('session.gc_probability', 1);
        ini_set('session.gc_divisor', 100); // Imposta gc_divisor a 100 per coerenza
        ini_set('session.auto_start', 0);
        ini_set('session.use_trans_sid', 0);

        // Imposta i parametri del cookie di sessione
        session_set_cookie_params([
            'lifetime' => 0, // 0 = Scade alla chiusura del browser
            'path' => self::COOKIE_PATH,
            'domain' => '', // Dominio corrente
            'secure' => self::COOKIE_SECURE,
            'httponly' => self::COOKIE_HTTPONLY,
            'samesite' => self::COOKIE_SAMESITE
        ]);

        // Nome univoco per la sessione
        session_name(self::SESSION_NAME . '_' . md5($_SERVER['HTTP_HOST'] . self::COOKIE_PATH));

        // Avvia la sessione
        if (session_status() === PHP_SESSION_NONE) {
            if (!session_start()) {
                error_log("SessionManager: Impossibile avviare la sessione.");
                // In un ambiente di produzione, potresti voler mostrare una pagina di errore generica
                // die("Errore critico: impossibile inizializzare la sessione.");
            }
        }

        // Inizializza i timestamp se è una nuova sessione
        if (!isset($_SESSION['created'])) {
            self::resetSessionTimestamps();
        }

        // Rigenera l'ID sessione a intervalli regolari per prevenire session fixation
        if (time() - ($_SESSION['last_regeneration'] ?? 0) > self::SESSION_REGENERATION_INTERVAL) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }

        self::$initialized = true;
    }

    /**
     * Valida la sessione corrente controllando timeout e durata massima.
     * Questa è la funzione che determina se una sessione è scaduta.
     *
     * @return bool True se la sessione è valida, altrimenti false (e la sessione viene distrutta).
     */
    public static function validateSession(): bool
    {
        self::startSecureSession();

        // Se non c'è user_id, la sessione non è valida (non loggata)
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        // 1. Controllo timeout per INATTIVITÀ (es. 30 minuti)
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > self::SESSION_INACTIVITY_TIMEOUT)) {
            error_log("Sessione scaduta per inattività.");
            self::destroy();
            return false;
        }

        // 2. Controllo DURATA MASSIMA della sessione (es. 2 ore)
        if (isset($_SESSION['created']) && (time() - $_SESSION['created'] > self::SESSION_MAX_LIFETIME)) {
            error_log("Sessione scaduta per durata massima.");
            self::destroy();
            return false;
        }

        // Sessione valida, aggiorna il timestamp di attività
        $_SESSION['last_activity'] = time();

        return true;
    }

    /**
     * Esegue il login di un utente, rigenerando l'ID e resettando i timestamp.
     */
    public static function login($userId, $userData = []): void
    {
        self::startSecureSession();
        // Rigenera l'ID per prevenire session fixation
        session_regenerate_id(true);

        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_id'] = $userId;

        // Resetta i timestamp della sessione al momento del login
        self::resetSessionTimestamps();

        // Salva dati utente aggiuntivi (es. username, is_admin)
        foreach ($userData as $key => $value) {
            $_SESSION['user_' . $key] = $value;
        }
    }

    /**
     * Esegue il logout: pulisce i dati di sessione, distrugge il cookie e la sessione.
     */
    public static function logout(): void
    {
        self::startSecureSession();

        // Pulisce tutte le variabili di sessione
        $_SESSION = [];

        // Distrugge il cookie di sessione se esiste
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        // Distrugge la sessione sul server
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        self::$initialized = false;
    }

    /**
     * Alias di logout() per coerenza semantica.
     */
    public static function destroy(): void
    {
        self::logout();
    }

    /**
     * Verifica se l'utente è attualmente loggato e la sessione è valida.
     * @return bool
     */
    public static function isLoggedIn(): bool
    {
        self::startSecureSession();

        if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
            return false;
        }

        // Controlla che la sessione non sia scaduta per inattività o vita massima
        return self::validateSession();
    }

    /**
     * Richiede che l'utente sia loggato. Se non lo è, reindirizza alla pagina di login.
     */
    public static function requireLogin($redirect = true): void
    {
        if (!self::isLoggedIn()) {
            if ($redirect) {
                // Salva la pagina richiesta per redirect dopo login
                $current_page = $_SERVER['REQUEST_URI'];
                self::set('redirect_after_login', $current_page);

                // Determina la BASE_URL se non definita globalmente
                $base_url = (defined('BASE_URL') ? BASE_URL : self::COOKIE_PATH);
                $login_url = rtrim($base_url, '/') . '/pages/auth/login.php';

                header('Location: ' . $login_url);
                exit();
            }
        }
    }

    /**
     * Resetta i timestamp della sessione (usato alla creazione e al login).
     */
    private static function resetSessionTimestamps(): void
    {
        $_SESSION['created'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['last_regeneration'] = time();
    }

    // --- 3. METODI HELPER (GET/SET E FLASH) ---

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

    // --- 4. METODI SPECIFICI DELL'APPLICAZIONE (UTENTE E ADMIN) ---

    public static function getUserId(): ?int
    {
        // isLoggedIn() include già la validazione della sessione
        return self::isLoggedIn() ? (int)$_SESSION['user_id'] : null;
    }

    public static function isAdmin(): bool
    {
        self::startSecureSession();
        // Usiamo get() per sicurezza, che gestisce la sessione non avviata
        return self::get('user_is_admin', false) === true;
    }

    /**
     * Richiede privilegi admin, altrimenti reindirizza.
     */
    public static function requireAdmin(): void
    {
        self::requireLogin(); // Prima controlla se è loggato

        if (!self::isAdmin()) {
            self::setFlashMessage('Accesso negato. Area riservata agli amministratori.', 'danger');
            $base_url = (defined('BASE_URL') ? BASE_URL : self::COOKIE_PATH);
            $redirect_url = rtrim($base_url, '/') . '/pages/home_utente.php';
            header('Location: ' . $redirect_url);
            exit();
        }
    }

    // --- 5. METODI SPECIFICI DELL'APPLICAZIONE (CHECKOUT) ---

    /**
     * Crea una sessione di checkout temporanea.
     * Utilizza il timeout specifico per il checkout (CHECKOUT_EXPIRATION_SECONDS).
     */
    public static function createCheckoutSession($items, $total, $user_id): bool
    {
        if (empty($items) || $total <= 0) {
            return false;
        }

        // Usa la costante specifica per il checkout (es. 5 min)
        $expires_at = time() + self::CHECKOUT_EXPIRATION_SECONDS;

        $checkout_data = [
            'items' => $items,
            'totale' => $total,
            'user_id' => $user_id,
            'timestamp' => time(),
            'expires_at' => $expires_at
        ];

        self::set('checkout_data', $checkout_data);
        error_log("Checkout session creata: scade alle " . $expires_at);
        return true;
    }

    /**
     * Valida i dati della sessione di checkout (scadenza e utente).
     */
    public static function validateCheckoutSession(): array|false
    {
        $checkout_data = self::get('checkout_data');

        if (!$checkout_data || !is_array($checkout_data)) {
            error_log("Checkout session mancante o non valida");
            return false;
        }

        // Verifica scadenza specifica del checkout
        $current_time = time();
        if (isset($checkout_data['expires_at']) && $current_time > $checkout_data['expires_at']) {
            error_log("Checkout session scaduta.");
            self::remove('checkout_data');
            return false;
        }

        // Verifica corrispondenza user_id
        $current_user = self::get('user_id'); // Usa get() per sicurezza
        if (isset($checkout_data['user_id']) && $checkout_data['user_id'] != $current_user) {
            error_log("Checkout session user_id mismatch.");
            self::remove('checkout_data');
            return false;
        }

        // Verifica items
        if (!isset($checkout_data['items']) || !is_array($checkout_data['items']) || empty($checkout_data['items'])) {
            error_log("Checkout session items non validi.");
            self::remove('checkout_data');
            return false;
        }

        return $checkout_data;
    }

    // --- 6. METODI SPECIFICI DELL'APPLICAZIONE (CARRELLO) ---
    // (Questi metodi operano sul carrello salvato in sessione)

    public static function updateCartCount(): void
    {
        $cart = self::get('cart', []);
        $total_items = 0;

        foreach ($cart as $item) {
            $total_items += $item['quantita'] ?? 0;
        }

        self::set('cart_items_count', $total_items);
    }

    public static function addToCart($product_id, $product_data): void
    {
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

    public static function removeFromCart($item_key): bool
    {
        $cart = self::get('cart', []);

        if (isset($cart[$item_key])) {
            unset($cart[$item_key]);
            self::set('cart', $cart);
            self::updateCartCount();
            return true;
        }
        return false;
    }

    public static function updateCartQuantity($item_key, $quantity): bool
    {
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

    public static function clearCart(): void
    {
        self::set('cart', []);
        self::set('cart_items_count', 0);
    }

    // --- 7. METODI DI DEBUG ---

    /**
     * Restituisce il timeout di INATTIVITÀ per API esterne (es. JavaScript).
     */
    public static function getSessionInactivityTimeout(): int
    {
        return self::SESSION_INACTIVITY_TIMEOUT;
    }

    /**
     * Ritorna un array di informazioni di debug sulla sessione corrente.
     */
    public static function getSessionDebugInfo(): array
    {
        self::startSecureSession();

        $checkout_data = self::get('checkout_data', []);
        $created_time = self::get('created');
        $activity_time = self::get('last_activity');

        return [
            'current_time_ts' => time(),
            'created_ts' => $created_time ?? 'N/A',
            'last_activity_ts' => $activity_time ?? 'N/A',
            'last_regeneration_ts' => self::get('last_regeneration', 'N/A'),
            'age_seconds' => $created_time ? (time() - $created_time) : 'N/A',
            'inactive_seconds' => $activity_time ? (time() - $activity_time) : 'N/A',
            'INACTIVITY_TIMEOUT_LIMIT' => self::SESSION_INACTIVITY_TIMEOUT,
            'MAX_LIFETIME_LIMIT' => self::SESSION_MAX_LIFETIME,
            'is_valid' => self::validateSession(), // Attenzione: chiamare questo resetta il timer di inattività
            'checkout_data_exists' => !empty($checkout_data),
            'checkout_expires_at' => $checkout_data['expires_at'] ?? 'N/A',
            'checkout_seconds_remaining' => isset($checkout_data['expires_at']) ? ($checkout_data['expires_at'] - time()) : 'N/A',
            'CHECKOUT_TIMEOUT_LIMIT' => self::CHECKOUT_EXPIRATION_SECONDS,
            'session_cookie_params' => session_get_cookie_params(),
            'session_name' => session_name(),
            'session_id' => session_id()
        ];
    }
}