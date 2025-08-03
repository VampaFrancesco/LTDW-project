<?php

// Impostazioni fondamentali per i cookie di sessione
$cookieParams = [
    'lifetime' => 3600, // 1 ora
    'path' => '/LTDW-project/' ?? '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'httponly' => true,
    'samesite' => 'Strict'
];

session_set_cookie_params($cookieParams);
session_name('SESSID_'.md5($_SERVER['HTTP_HOST'].$cookieParams['path']));

// Altre impostazioni di sicurezza
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_lifetime', $cookieParams['lifetime']);
