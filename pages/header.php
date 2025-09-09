<?php
require_once __DIR__ . '/../include/config.inc.php';
require_once __DIR__ . '/../include/session_manager.php';

// IMPORTANTE: Fai tutti i controlli PRIMA di qualsiasi output HTML
SessionManager::startSecureSession();

// Recupera il messaggio flash PRIMA dell'output HTML
$flash_message = SessionManager::get('flash_message');
if ($flash_message) {
    SessionManager::set('flash_message', null); // Rimuovi subito per evitare duplicati
}

// Recupera il conteggio delle notifiche non lette (solo per utenti loggati non admin)
$notifications_count = 0;
if (SessionManager::isLoggedIn() && !SessionManager::get('user_is_admin', false)) {
    $user_id = SessionManager::getUserId();

    try {
        // Connessione database per le notifiche
        if (isset($config['dbms']['localhost'])) {
            $db_config = $config['dbms']['localhost'];
            $conn_notifications = new mysqli(
                    $db_config['host'],
                    $db_config['user'],
                    $db_config['passwd'],
                    $db_config['dbname']
            );

            if (!$conn_notifications->connect_error) {
                $stmt = $conn_notifications->prepare("
                    SELECT COUNT(*) as count 
                    FROM notifiche_utente 
                    WHERE fk_utente = ? AND letta = FALSE
                ");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($row = $result->fetch_assoc()) {
                    $notifications_count = (int)$row['count'];
                }

                $stmt->close();
                $conn_notifications->close();
            }
        }
    } catch (Exception $e) {
        // Log dell'errore, ma non interrompere il caricamento della pagina
        error_log("Errore recupero notifiche header: " . $e->getMessage());
    }
}

// Aggiorna la sessione con il conteggio
SessionManager::set('notifications_count', $notifications_count);
?>
<!DOCTYPE html>
<html lang="it" class="html bebas-neue-regular">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Box Omnia</title>

    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo(defined('BASE_URL') ? BASE_URL : ''); ?>/css/style.css">
    <link rel="stylesheet" href="<?php echo(defined('BASE_URL') ? BASE_URL : ''); ?>/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" href="<?php echo(defined('BASE_URL') ? BASE_URL : ''); ?>/images/favicon.ico" type="image/gif"/>

    <style>
        /* Header Design Ottimizzato */
        .header-top {
            min-height: 70px;
            display: flex;
            align-items: center;
            margin-bottom: 15px; /* Spazio tra header e navbar */
        }
        .search-input {
            flex: 1;
            border: none;
            padding: 12px 16px;
            font-size: 14px;
            outline: none;
            background: white;
        }

        .search-button {
            width: 50px;
            height: 42px;
            border: none;
            background: var(--accent-color, #007bff);
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s ease;
        }

        .search-button:hover {
            background: var(--primary-color, #0056b3);
        }

        .user-actions .action-item {
            display: flex;
            align-items: center;
            height: 40px;
            padding: 0 10px;
            text-decoration: none;
            color: inherit;
            border-radius: 8px;
            transition: background-color 0.3s ease;
            white-space: nowrap;
            position: relative;
        }

        .user-actions .action-item:hover {
            background: rgba(0, 0, 0, 0.05);
        }

        .user-actions .action-item img {
            width: 20px;
            height: 20px;
            margin-right: 5px;
        }

        /* Dropdown User */
        .user-dropdown {
            position: relative;
        }

        .user-dropdown .dropdown-toggle {
            display: flex;
            align-items: center;
            height: 40px;
            padding: 0 12px;
            background: rgba(0, 0, 0, 0.05);
            border-radius: 20px;
            text-decoration: none;
            color: inherit;
            border: none;
            font-size: 14px;
            font-weight: 500;
        }

        .user-dropdown .dropdown-toggle::after {
            margin-left: 8px;
        }

        .user-dropdown .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 5px;
            min-width: 220px;
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            padding: 8px 0;
            z-index: 1050;
        }

        .user-dropdown .dropdown-item {
            padding: 10px 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-dropdown .dropdown-item i {
            width: 16px;
            font-size: 14px;
        }

        /* Badge per notifiche */
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .admin-badge {
            background: #dc3545;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 5px;
            font-weight: bold;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .header-container {
                grid-template-columns: 180px 1fr 280px;
                gap: 15px;
            }

            .search-form {
                max-width: 400px;
            }
        }

        @media (max-width: 992px) {
            .header-container {
                grid-template-columns: 150px 1fr 250px;
                gap: 10px;
            }

            .search-form {
                max-width: 350px;
            }

            .user-actions {
                gap: 10px;
            }
        }

        @media (max-width: 768px) {
            .header-container {
                grid-template-columns: 120px 1fr 200px;
                gap: 10px;
                padding: 0 15px;
            }

            .logo-section img {
                height: 35px;
            }

            .search-form {
                height: 38px;
                max-width: 300px;
            }

            .search-input {
                font-size: 13px;
                padding: 0 15px;
            }

            .search-button {
                width: 45px;
                height: 38px;
            }

            .user-actions {
                gap: 8px;
            }

            .user-actions .action-item {
                padding: 0 8px;
            }

            .user-actions .action-item img {
                width: 18px;
                height: 18px;
            }
        }

        @media (max-width: 576px) {
            .header-container {
                grid-template-columns: 100px 1fr 150px;
                gap: 8px;
            }

            .search-form {
                max-width: 250px;
            }

            .user-actions .action-item span {
                display: none;
            }
        }
    </style>

    <script>
        window.BASE_URL = '<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>';
        window.isLoggedIn = <?php echo SessionManager::isLoggedIn() ? 'true' : 'false'; ?>;
    </script>

</head>
<body>
<!-- Gestione messaggi flash -->
<?php if ($flash_message): ?>
    <div class="alert alert-<?php echo htmlspecialchars($flash_message['type']); ?> alert-dismissible fade show"
         role="alert">
        <?php echo htmlspecialchars($flash_message['content']); ?>
    </div>
<?php endif; ?>

<?php if (empty($hideNav)): ?>
    <header>
        <div class="header-top background-header">
            <div class="container d-flex align-items-center">

                <!-- 1) Logo a sinistra -->
                <a href="<?php echo(defined('BASE_URL') ? BASE_URL : ''); ?>/pages/home_utente.php" class="logo-link">
                    <img id="logo_header" src="<?php echo(defined('BASE_URL') ? BASE_URL : ''); ?>/images/boxomnia.png" alt="logo">
                </a>

                <!-- 2) Search bar -->
                <div class="flex-fill px-3">
                    <form class="search-form w-100" method="get" action="...">
                        <input class="search-input flex-grow-1" type="search" name="q" placeholder="Cerca..." aria-label="Cerca">
                        <button class="search-button" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                    </form>
                </div>

                <!-- 3) Top-links a destra -->
                <div class="top-links d-flex align-items-center ml-3">
                    <img src="<?php echo BASE_URL; ?>/images/svg/account.svg" alt="account">

                    <?php if (SessionManager::isLoggedIn()): ?>
                        <!-- Utente loggato -->
                        <?php $isAdmin = SessionManager::get('user_is_admin', false); ?>
                        <div class="dropdown">
                            <a href="#" class="mx-2 dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php
                                $nome = SessionManager::get('user_nome', 'Utente');
                                echo htmlspecialchars($nome);
                                if ($isAdmin) {
                                    echo ' <span class="admin-badge">A.</span>';
                                }
                                ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <?php if ($isAdmin): ?>
                                    <!-- Menu ADMIN -->
                                    <li>
                                        <a class="dropdown-item" href="<?php echo BASE_URL; ?>/pages/dashboard/dashboard.php">
                                            <i class="bi bi-speedometer2"></i> Dashboard Admin
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo BASE_URL; ?>/pages/dashboard/gestione_supporto.php">
                                            <i class="bi bi-headset"></i> Supporto Clienti
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo BASE_URL; ?>/pages/home_utente.php">
                                            <i class="bi bi-house"></i> Area Utente
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <!-- Menu UTENTE NORMALE -->
                                    <li>
                                        <a class="dropdown-item" href="<?php echo BASE_URL; ?>/pages/home_utente.php">
                                            <i class="bi bi-house"></i> Homepage
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo BASE_URL; ?>/pages/profilo.php">
                                            <i class="bi bi-person-gear"></i> Profilo
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo BASE_URL; ?>/pages/ordini.php">
                                            <i class="bi bi-bag-check"></i> I miei Ordini
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item" href="<?php echo BASE_URL; ?>/pages/supporto_utente.php">
                                            <i class="bi bi-headset"></i> Supporto e Assistenza
                                            <?php if ($notifications_count > 0): ?>
                                                <span class="badge bg-warning text-dark ms-2"><?php echo $notifications_count; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>/pages/auth/logout.php">
                                        <i class="bi bi-box-arrow-right"></i> Logout
                                    </a>
                                </li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <!-- Utente non loggato -->
                        <a href="<?php echo BASE_URL; ?>/pages/auth/login.php" class="mx-2">ACCEDI</a>
                        <a href="<?php echo BASE_URL; ?>/pages/auth/register.php" class="mx-2">REGISTRATI</a>
                    <?php endif; ?>

                    <!-- Altri link sempre visibili -->
                    <a href="<?php echo BASE_URL; ?>/pages/wishlist.php" class="mx-2" title="Lista Desideri">
                        <img src="<?php echo BASE_URL; ?>/images/svg/cuore.svg" alt="wishlist">
                    </a>
                    <a href="<?php echo BASE_URL; ?>/pages/cart.php" class="mx-2" title="Carrello">
                        <img src="<?php echo BASE_URL; ?>/images/svg/carrello.svg" alt="carrello">
                    </a>

                    <?php if (SessionManager::isLoggedIn() && !SessionManager::get('user_is_admin', false)): ?>
                        <!-- Notifiche solo per utenti normali (non admin) -->
                        <a href="<?php echo BASE_URL; ?>/pages/supporto_utente.php" class="mx-2" title="Supporto e Notifiche" style="position: relative;">
                            <img src="<?php echo BASE_URL; ?>/images/svg/campanello.svg" alt="campanello">
                            <?php if ($notifications_count > 0): ?>
                                <span class="notification-badge"><?php echo $notifications_count; ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                </div>

            </div>
        </div>
        <?php include __DIR__ . '/sections/navbar.php' ?>
    </header>
<?php endif; ?>

<!-- Script per il dropdown (Bootstrap 5) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Auto-hide alerts dopo 5 secondi
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function (alert) {
            setTimeout(function () {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });
    });
</script>