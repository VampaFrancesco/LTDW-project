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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
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
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (empty($hideNav)): ?>
    <header>
        <div class="header-top py-2 background-header">
            <div class="container d-flex align-items-center">

                <!-- 1) Logo a sinistra -->
                <a href="<?php echo(defined('BASE_URL') ? BASE_URL : ''); ?>/pages/index.php" class="logo-link mr-3">
                    <img id="logo_header" src="<?php echo(defined('BASE_URL') ? BASE_URL : ''); ?>/images/boxomnia.png"
                         alt="logo">
                </a>

                <!-- 2) Search bar -->
                <div class="flex-fill px-3">
                    <form class="form-inline w-100" method="get"
                          action="<?php echo(defined('BASE_URL') ? BASE_URL : ''); ?>/action/search.php">
                        <input class="form-control mr-2 flex-grow-1" type="search" name="q" placeholder="Cerca..."
                               aria-label="Cerca">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                    </form>
                </div>

                <!-- 3) Top-links a destra -->
                <div class="top-links d-flex align-items-center ml-3">
                    <i class="bi bi-person-fill"></i>

                    <?php if (SessionManager::isLoggedIn()): ?>
                        <!-- Utente loggato -->
                        <div class="dropdown">
                            <a href="#" class="mx-2 dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php
                                $nome = SessionManager::get('user_nome', 'Utente');
                                $isAdmin = SessionManager::get('user_is_admin', false); // FIX: user_is_admin
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
                                           href="<?php echo(defined('BASE_URL') ? BASE_URL : ''); ?>/pages/home_utente.php">
                                            <i class="bi bi-house"></i> Area Utente
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <!-- Menu UTENTE NORMALE: Profilo invece di Dashboard -->
                                    <li>
                                        <a class="dropdown-item"
                                           href="<?php echo(defined('BASE_URL') ? BASE_URL : ''); ?>/pages/home_utente.php">
                                            <i class="bi bi-house"></i> <?php echo SessionManager::isLoggedIn() ? 'Home Page' : 'Dashboard'; ?>
                                        </a>
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
                                        <a class="dropdown-item"
                                           href="<?php echo(defined('BASE_URL') ? BASE_URL : ''); ?>/pages/ordini.php">
                                            <i class="bi bi-bag-check"></i> I miei Ordini
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
                        <i class="bi bi-heart-fill"></i>
                    </a>
                    <a href="<?php echo(defined('BASE_URL') ? BASE_URL : ''); ?>/pages/cart.php" class="mx-2"
                       title="Carrello">
                        <i class="bi bi-cart-fill"></i>
                        <?php
                        // Mostra numero items nel carrello se presente
                        $cart_items = SessionManager::get('cart_items_count', 0);
                        if ($cart_items > 0):
                            ?>
                            <span class="badge bg-danger"><?php echo $cart_items; ?></span>
                        <?php endif; ?>
                    </a>
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