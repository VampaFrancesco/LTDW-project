<?php
// include/config.inc.php

$config['dbms']['localhost']['user'] = "admin";
$config['dbms']['localhost']['passwd'] = "admin";
$config['dbms']['localhost']['host'] = "localhost";
$config['dbms']['localhost']['dbname'] = "boxomnia";

// Definizione della BASE_URL (modifica se necessario)
if (!defined('BASE_URL')) {
    define('BASE_URL', '/LTDW-project'); // oppure 'http://localhost/LTDW-project' se preferisci assoluta
}

return [
    'user' => 'admin',
    'passwd' => 'admin',
    'host' => 'localhost',
    'dbname' => 'boxomnia',
];
