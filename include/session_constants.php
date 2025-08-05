<?php

// Costanti per la gestione delle sessioni
if (!defined('SESSION_TIMEOUT')) {
    define('SESSION_TIMEOUT', 1800); // 30 minuti di inattività
}

if (!defined('SESSION_MAX_LIFETIME')) {
    define('SESSION_MAX_LIFETIME', 7200); // 2 ore massimo
}

if (!defined('SESSION_REGENERATION_INTERVAL')) {
    define('SESSION_REGENERATION_INTERVAL', 300); // Rigenera ID ogni 5 minuti
}

// Configurazione ambiente
if (!defined('IS_DEVELOPMENT')) {
    define('IS_DEVELOPMENT', ($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false));
}

// Configurazione sicurezza
if (!defined('ENABLE_IP_CHECK')) {
    define('ENABLE_IP_CHECK', !IS_DEVELOPMENT); // Controllo IP solo in produzione
}

if (!defined('ENABLE_SESSION_DEBUG')) {
    define('ENABLE_SESSION_DEBUG', IS_DEVELOPMENT); // Debug solo in sviluppo
}