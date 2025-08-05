<?php
// File: /action/logout.php

require_once __DIR__.'/../../include/session_manager.php';
require_once __DIR__.'/../../include/config.inc.php';

// Distruggi la sessione
SessionManager::destroy();

// Crea nuova sessione per il messaggio
session_start();
SessionManager::setFlashMessage('Logout effettuato con successo', 'success');

// Redirect al login
header('Location: ' . BASE_URL . '/pages/auth/login.php');
exit();