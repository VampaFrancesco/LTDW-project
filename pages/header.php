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
        <div class="header-top py-2 background-header">
            <div class="container d-flex align-items-center">

                <!-- 1) Logo a sinistra -->
                <a href="<?php echo(defined('BASE_URL') ? BASE_URL : ''); ?>/pages/home_utente.php" class="logo-link mr-3">
                    <img id="logo_header" src="<?php echo(defined('BASE_URL') ? BASE_URL : ''); ?>/images/boxomnia.png"
                         alt="logo">
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
                        <div class="dropdown">
                            <a href="#" class="mx-2 dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php
                                $nome = SessionManager::get('user_nome', 'Utente');
                                $isAdmin = SessionManager::get('user_is_admin', false);
                                echo htmlspecialchars($nome);
                                if ($isAdmin) {
                                    echo ' <span class="badge bg-danger">Admin</span>';
                                }
                                ?>
                            </a>
                            <ul class="dropdown-menu">
                                <?php if ($isAdmin): ?>
                                    <!-- Menu ADMIN: Dashboard invece di Profilo -->
                                    <li>
                                        <a class="dropdown-item"
                                           href="<?php echo(defined('BASE_URL') ? BASE_URL : ''); ?>/pages/dashboard/dashboard.php">
                                            <i class="bi bi-speedometer2"></i> Dashboard Admin
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item"
                                           href="<?php echo(defined('BASE_URL') ? BASE_URL : ''); ?>/pages/dashboard/gestione_supporto.php">
                                            <i class="bi bi-headset"></i> Supporto Clienti
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item"
                                           href="<?php echo(defined('BASE_URL') ? BASE_URL : ''); ?>/pages/home_utente.php">
                                            <i class="bi bi-house"></i> Area Utente
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <!-- Menu UTENTE NORMALE: Profilo invece di Dashboard -->
                                    <li>
                                        <a class="dropdown-item"
                                           href="<?php echo(defined('BASE_URL') ? BASE_URL : ''); ?>/pages/home_utente.php">
                                            <i class="bi bi-house"></i> Homepage
                                        </a>
                                    </li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li>
                                        <a class="dropdown-item"
                                           href="<?php echo(defined('BASE_URL') ? BASE_URL : ''); ?>/pages/profilo.php">
                                            <i class="bi bi-person-gear"></i> Profilo
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php if (!$isAdmin): ?>
                                    <!-- Ordini solo per utenti normali -->
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li>
                                        <a class="dropdown-item"
                                           href="<?php echo(defined('BASE_URL') ? BASE_URL : ''); ?>/pages/ordini.php">
                                            <i class="bi bi-bag-check"></i> I miei Ordini
                                        </a>
                                    </li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li>
                                        <a class="dropdown-item"
                                           href="<?php echo(defined('BASE_URL') ? BASE_URL : ''); ?>/pages/supporto_utente.php">
                                            <i class="bi bi-headset"></i> Supporto e Assistenza
                                            <?php if ($notifications_count > 0): ?>
                                                <span class="badge bg-warning text-dark ms-1"><?php echo $notifications_count; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li>
                                    <a class="dropdown-item"
                                       href="<?php echo(defined('BASE_URL') ? BASE_URL : ''); ?>/pages/auth/logout.php">
                                        <i class="bi bi-box-arrow-right"></i> Logout
                                    </a>
                                </li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <!-- Utente non loggato -->
                        <a href="<?php echo(defined('BASE_URL') ? BASE_URL : ''); ?>/pages/auth/login.php" class="mx-2">ACCEDI</a>
                        <a href="<?php echo(defined('BASE_URL') ? BASE_URL : ''); ?>/pages/auth/register.php"
                           class="mx-2">REGISTRATI</a>
                    <?php endif; ?>

                    <!-- Altri link sempre visibili -->
                    <a href="<?php echo(defined('BASE_URL') ? BASE_URL : ''); ?>/pages/wishlist.php" class="mx-2"
                       title="Lista Desideri">
                        <img src="<?php echo BASE_URL; ?>/images/svg/cuore.svg" alt="wishlist">
                    </a>
                    <a href="<?php echo(defined('BASE_URL') ? BASE_URL : ''); ?>/pages/cart.php" class="mx-2"
                       title="Carrello">
                        <img src="<?php echo BASE_URL; ?>/images/svg/carrello.svg" alt="carrello">
                    </a>
                    
                    <?php if (SessionManager::isLoggedIn() && !SessionManager::get('user_is_admin', false)): ?>
                        <!-- Notifiche solo per utenti normali (non admin) -->
                        <a href="<?php echo(defined('BASE_URL') ? BASE_URL : ''); ?>/pages/supporto_utente.php" class="mx-2"
                           title="Supporto e Notifiche">
                            <img src="<?php echo BASE_URL; ?>/images/svg/campanello.svg" alt="campanello">
                            <?php if ($notifications_count > 0): ?>
                                <span class="badge bg-warning text-dark"><?php echo $notifications_count; ?></span>
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