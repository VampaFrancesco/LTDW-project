<?php

use JetBrains\PhpStorm\NoReturn;

class SessionManager {
    public static function startSecureSession($idUtente = null): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            require_once __DIR__.'/session_config.php';
            session_start();

            if (!isset($_SESSION['created'])) {
                $_SESSION['created'] = time();
                if ($idUtente !== null) {
                    $_SESSION['user_id'] = $idUtente;
                }
            } elseif (time() - $_SESSION['created'] > 1800) {
                session_regenerate_id(true);
                $_SESSION['created'] = time();
                if ($idUtente !== null) {
                    $_SESSION['user_id'] = $idUtente;
                }
            }
        }
    }

    public static function set($key, $value): void
    {
        self::startSecureSession();
        $_SESSION[$key] = $value;
    }

    public static function destroy(): void
    {
        self::startSecureSession();
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        header('Location: /LTDW-project/pages/auth/login.php');
        exit();

    }

    public static function get($key, $default = null) {
        self::startSecureSession();
        return $_SESSION[$key] ?? $default;
    }

    public static function checkAuth():bool
    {
        self::startSecureSession();

        if (!isset($_SESSION['user_id'])) {
            // Memorizza messaggio flash
            $_SESSION['flash_message'] = [
                'type' => 'warning',
                'content' => 'Per favore accedi per continuare'
            ];

            header('Location: /LTDW-project/pages/auth/login.php');
            exit();
        }

        // Verifica scadenza sessione
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
            self::destroy();
            $_SESSION['flash_message'] = [
                'type' => 'error',
                'content' => 'Sessione scaduta, per favore accedi di nuovo'
            ];
            header('Location: /LTDW-project/pages/auth/login.php');
            exit();
        }

        $_SESSION['last_activity'] = time();
        return true;
    }
}
