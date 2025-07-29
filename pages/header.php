<?php
session_start();
require_once __DIR__ . '/../include/config.inc.php';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Box Omnia</title>
    <!-- CSS -->
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <link rel="icon" href="../images/favicon.ico" type="image/gif"/>
</head>
<body>

<?php if (empty($hideNav)): ?>
    <header>
        <div class="header-top bg-light py-2">
            <div class="container d-flex align-items-center">

                <!-- 1) Logo a sinistra -->
                <a href="index.php" class="logo-link mr-3">
                    <img id="logo_header" src="../images/boxomnia.png" alt="logo">
                </a>
                <div class="flex-fill px-3">
                    <form class="form-inline w-100" method="get" action="search.php">
                        <input class="form-control mr-2 flex-grow-1" type="search" name="q" placeholder="Cerca..." aria-label="Cerca">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fa fa-search"></i>
                        </button>
                    </form>
                </div>
                <!-- 3) Top-links a destra -->
                <div class="top-links d-flex align-items-center ml-3">
                    <i class="bi bi-person-fill"></i>
                    <a href="#" class="mx-2">ACCOUNT</a>
                    <a href="#" class="mx-2"><i class="bi bi-gift-fill"></i></a>
                    <a href="#" class="mx-2"><i class="bi bi-heart-fill"></i></a>
                    <a href="cart.php" class="mx-2"><i class="bi bi-cart-fill"></i></a>
                </div>

            </div>
        </div>
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-top border-bottom">
            <div class="container">
                <a class="navbar-brand" href="../index.php">
                    <img src="../images/logo.png" alt="Box Omnia">
                </a>
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#mainNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="mainNav">
                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item"><a class="nav-link" href="../index.php">Homepage</a></li>
                        <li class="nav-item"><a class="nav-link" href="../novita.php">Novità</a></li>
                        <li class="nav-item"><a class="nav-link" href="../prevendita.php">Prevendita</a></li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="merchDropdown" data-toggle="dropdown">Merch</a>
                            <div class="dropdown-menu" aria-labelledby="merchDropdown">
                                <a class="dropdown-item" href="../merch/spille.php">Spille e Gadget</a>
                                <a class="dropdown-item" href="../merch/peluche.php">Peluche</a>
                                <!-- aggiungi gli altri link -->
                            </div>
                        </li>
                        <!-- replica dropdown per Games, Pokemon, Accessori, ecc. -->
                    </ul>
                </div>
            </div>
        </nav>
    </header>
<?php endif; ?>
