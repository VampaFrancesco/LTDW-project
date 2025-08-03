<?php

// Definizione della configurazione del database all'interno dell'array $config
$config['dbms']['localhost']['user'] = "admin";
$config['dbms']['localhost']['passwd'] = "admin";
$config['dbms']['localhost']['host'] = "localhost";
$config['dbms']['localhost']['dbname'] = "boxomnia";

// Definizione della BASE_URL
if (!defined('BASE_URL')) {
    define('BASE_URL', '/LTDW-project');
}

return $config;
