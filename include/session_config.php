<?php

// Verifica che la configurazione non sia già stata caricata
if (defined('SESSION_CONFIG_LOADED')) {
    return;
}
define('SESSION_CONFIG_LOADED', true);

// Impostazioni fondamentali per i cookie di sessione
$cookieParams = [
    'lifetime' => 0, // 0 = sessione (chiude quando chiudi il browser)
    'path' => (defined('BASE_URL') ? BASE_URL : '/LTDW-project/') . '/',
    'domain' => '', // Lascia vuoto per auto-detect sicuro
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // Solo HTTPS se disponibile
    'httponly' => true,
    'samesite' => 'Strict'
];

// Applica i parametri dei cookie SOLO se la sessione non è già avviata
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params($cookieParams);

    // Nome sessione univoco basato sul progetto
    session_name('BOXOMNIA_SESS_' . md5($_SERVER['HTTP_HOST'] . $cookieParams['path']));

    // Altre impostazioni di sicurezza
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_lifetime', $cookieParams['lifetime']);

    // Impostazioni aggiuntive di sicurezza - TIMEOUT 5 MINUTI
    ini_set('session.cookie_secure', $cookieParams['secure'] ? 1 : 0);
    ini_set('session.gc_maxlifetime', 300); // 5 minuti (300 secondi)
    ini_set('session.gc_probability', 1);
    ini_set('session.gc_divisor', 100);

    // Rigenerazione automatica dell'ID sessione
    ini_set('session.auto_start', 0);
    ini_set('session.use_trans_sid', 0);
}

// Costanti per il timeout della sessione
define('SESSION_TIMEOUT_SECONDS', 300); // 5 minuti

// Funzione helper per debug (solo in sviluppo)
if (!function_exists('getSessionInfo')) {
    function getSessionInfo(): array {
        return [
            'session_id' => session_id(),
            'session_name' => session_name(),
            'session_status' => session_status(),
            'cookie_params' => session_get_cookie_params(),
            'session_data_size' => strlen(serialize($_SESSION ?? [])),
            'gc_maxlifetime' => ini_get('session.gc_maxlifetime'),
            'timeout_seconds' => SESSION_TIMEOUT_SECONDS
        ];
    }
}