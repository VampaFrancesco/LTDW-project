<?php
require_once __DIR__ . '/../include/config.inc.php';
require_once __DIR__ . '/../include/session_manager.php';
SessionManager::startSecureSession();
?>
<!DOCTYPE html>
<html lang="it" class="html bebas-neue-regular">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Box Omnia</title>

    <!-- CSS -->
    <link rel="stylesheet" href="/LTDW-project/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="icon" href="../images/favicon.ico" type="image/gif"/>
    <link rel="stylesheet" href="/LTDW-project/css/style.css">
</head>
<?php if (empty($hideNav)): ?>
    <header>
        <div class="header-top bg-light py-2 background-custom ">
            <div class="container d-flex align-items-center">

                <!-- 1) Logo a sinistra -->
                <a href="/LTDW-project/pages/index.php" class="logo-link mr-3">
                    <img id="logo_header" src="/LTDW-project/images/boxomnia.png" alt="logo">
                </a>

                <!-- 2) Search bar -->
                <div class="flex-fill px-3">
                    <form class="form-inline w-100" method="get" action="search.php">
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
                    <a href="<?php echo SessionManager::get('user_logged_in') ? '/LTDW-project/pages/home_utente.php' : '/LTDW-project/pages/auth/login.php'; ?>" class="mx-2">ACCOUNT</a>
                    <a href="#" class="mx-2"><i class="bi bi-gift-fill"></i></a>
                    <a href="#" class="mx-2"><i class="bi bi-heart-fill"></i></a>
                    <a href="cart.php" class="mx-2"><i class="bi bi-cart-fill"></i></a>
                </div>

            </div>
        </div>
        <?php include __DIR__ . '/sections/navbar.php' ?>
    </header>
<?php endif; ?>
