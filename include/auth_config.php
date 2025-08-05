<?php
// Configurazione per l'autenticazione

define('LOGIN_URL', (defined('BASE_URL') ? BASE_URL : '') . '/pages/auth/login.php');
define('HOME_URL', (defined('BASE_URL') ? BASE_URL : '') . '/pages/home_utente.php');
define('LOGOUT_URL', (defined('BASE_URL') ? BASE_URL : '') . '/pages/auth/logout.php');

// Pagine che richiedono autenticazione
$protected_pages = [
    '/pages/collezione.php',
    '/pages/home_utente.php',
    '/pages/profilo.php',
    // aggiungi altre pagine protette
];

// Funzione helper per controllare se una pagina è protetta
function isProtectedPage($currentPage) {
    global $protected_pages;
    foreach ($protected_pages as $protected) {
        if (strpos($currentPage, $protected) !== false) {
            return true;
        }
    }
    return false;
}
?>