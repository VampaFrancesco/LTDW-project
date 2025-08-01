<?php
// include/config.inc.php

// Definizione della configurazione del database all'interno dell'array $config
$config['dbms']['localhost']['user'] = "admin";
$config['dbms']['localhost']['passwd'] = "admin";
$config['dbms']['localhost']['host'] = "localhost";
$config['dbms']['localhost']['dbname'] = "boxomnia";

// Definizione della BASE_URL (modifica se necessario)
if (!defined('BASE_URL')) {
    define('BASE_URL', '/LTDW-project'); // oppure 'http://localhost/LTDW-project' se preferisci assoluta
}

// *** ATTENZIONE: HO RIMOSSO LA RIGA 'return [...]' CHE C'ERA QUI SOTTO ***
// Se la lasci, $db_config in login.php prenderà quel valore e non la variabile $config.
?>