<?php
// include/config.inc.php

$config['dbms']['localhost']['user'] = "root";
$config['dbms']['localhost']['passwd'] = "root";
$config['dbms']['localhost']['host'] = "localhost";
$config['dbms']['localhost']['dbname'] = "boxomnia";

// Definizione della BASE_URL (modifica se necessario)
if (!defined('BASE_URL')) {
    define('BASE_URL', '/LTDW-project'); // oppure 'http://localhost/LTDW-project' se preferisci assoluta
}

return [
    'user' => 'root',
    'passwd' => 'root',
    'host' => 'localhost',
    'dbname' => 'boxomnia',
];
