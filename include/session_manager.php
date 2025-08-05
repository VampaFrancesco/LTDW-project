<?php

class SessionManager {
    private static $initialized = false;

    public static function startSecureSession(): void
    {
        if (self::$initialized) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            // Carica la configurazione
            require_once __DIR__.'/session_config.php';

            // Avvia la sessione
            session_start();

            // Inizializza i valori base se nuova sessione
            if (!isset($_SESSION['created'])) {
                $_SESSION['created'] = time();
                $_SESSION['last_regeneration'] = time();
                $_SESSION['last_activity'] = time();
            }

            // Rigenera ID ogni 5 minuti per sicurezza
            if (time() - $_SESSION['last_regeneration'] > 300) {
                session_regenerate_id(true);
                $_SESSION['last_regeneration'] = time();
            }

            self::$initialized = true;
        }
    }

    public static function validateSession(): bool
    {
        self::startSecureSession();

        // Controlla timeout inattività (5 minuti)
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 300)) {
            self::destroy();
            return false;
        }

        // Controlla durata massima sessione (30 minuti)
        if (isset($_SESSION['created']) && (time() - $_SESSION['created'] > 1800)) {
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
}