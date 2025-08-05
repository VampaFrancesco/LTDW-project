<?php
// dashboard.php
// Pagina standalone per la Dashboard
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Dashboard</title>

    <!-- Bootstrap CSS (da CDN) -->
    <!-- Bootstrap CSS -->
    <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
            rel="stylesheet"
            integrity="sha384-..." crossorigin="anonymous">

    <!-- Bootstrap Icons -->
    <link
            href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css"
            rel="stylesheet">

    <!-- CSS personalizzato -->
    <link rel="stylesheet" href="../../css/dashboard.css">

</head>
<body class="d-flex flex-column min-vh-100">

<!-- Header / Navbar -->
<header class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">La Mia Dashboard</a>
        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#navbarNav"
            aria-controls="navbarNav"
            aria-expanded="false"
            aria-label="Toggle navigation"
        >
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link active" href="#">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="#">Profilo</a></li>
                <li class="nav-item"><a class="nav-link" href="#">Logout</a></li>
            </ul>
        </div>
    </div>
</header>

<!-- Contenuto Principale -->
<div class="container-fluid flex-grow-1">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-12 col-md-3 col-lg-2 d-md-block bg-light sidebar collapse" id="sidebarMenu">
            <div class="position-sticky pt-3">
                <ul class="nav flex-column">
                    <li class="nav-item"><a class="nav-link active" href="#">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Report</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Utenti</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Impostazioni</a></li>
                </ul>
            </div>
        </nav>

        <!-- Main -->
        <main class="col-12 col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <h1 class="h2 mb-4">Benvenuto nella Dashboard</h1>

            <div class="row g-4">
                <div class="col-sm-6 col-lg-3">
                    <div class="dashboard-card text-center p-4">
                        <i class="bi bi-people-fill icon-lg mb-2"></i>
                        <h3 class="mb-1">1234</h3>
                        <p class="text-muted mb-0">Utenti Registrati</p>
                    </div>

                    <!-- Azioni rapide CRUD -->
                    <section id="crud-operations" class="mt-5">
                        <h2 class="h3 mb-4">Azioni rapide</h2>
                        <div class="row g-4 row-cols-1 row-cols-md-2">
                            <!-- Create -->
                            <div class="col">
                                <div class="dashboard-card crud-card text-center p-4">
                                    <i class="bi bi-plus-circle icon-lg mb-2 text-success"></i>
                                    <h5 class="mb-1">Crea</h5>
                                    <a href="create.php" class="btn btn-outline-success btn-sm mt-2">Vai</a>
                                </div>
                            </div>
                            <!-- Read -->
                            <div class="col">
                                <div class="dashboard-card crud-card text-center p-4">
                                    <i class="bi bi-list-ul icon-lg mb-2 text-primary"></i>
                                    <h5 class="mb-1">Visualizza</h5>
                                    <a href="read.php" class="btn btn-outline-primary btn-sm mt-2">Vai</a>
                                </div>
                            </div>
                            <!-- Update -->
                            <div class="col">
                                <div class="dashboard-card crud-card text-center p-4">
                                    <i class="bi bi-pencil-square icon-lg mb-2 text-warning"></i>
                                    <h5 class="mb-1">Aggiorna</h5>
                                    <a href="update.php" class="btn btn-outline-warning btn-sm mt-2">Vai</a>
                                </div>
                            </div>
                            <!-- Delete -->
                            <div class="col">
                                <div class="dashboard-card crud-card text-center p-4">
                                    <i class="bi bi-trash icon-lg mb-2 text-danger"></i>
                                    <h5 class="mb-1">Elimina</h5>
                                    <a href="delete.php" class="btn btn-outline-danger btn-sm mt-2">Vai</a>
                                </div>
                            </div>
                        </div>
                    </section>

                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="dashboard-card text-center p-4">
                        <i class="bi bi-basket-fill icon-lg mb-2"></i>
                        <h3 class="mb-1">56</h3>
                        <p class="text-muted mb-0">Vendite Oggi</p>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="dashboard-card text-center p-4">
                        <i class="bi bi-currency-euro icon-lg mb-2"></i>
                        <h3 class="mb-1">€ 7.890</h3>
                        <p class="text-muted mb-0">Fatturato</p>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="dashboard-card text-center p-4">
                        <i class="bi bi-chat-dots-fill icon-lg mb-2"></i>
                        <h3 class="mb-1">12</h3>
                        <p class="text-muted mb-0">Nuovi Messaggi</p>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Footer -->
<footer class="footer mt-auto py-3 bg-light">
    <div class="container">
        <span class="text-muted">&copy; <?php echo date('Y'); ?> BOX OMNIA - DASHBOARD</span>
    </div>
</footer>

<!-- Bootstrap JS Bundle (Popper incluso) -->
<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-ENjdO4Dr2bkBIFxQpeoMZ1NibQw1Q92W9E2+zp0Flp/Q0h+I1N0AhTe/n6g7Sk"
    crossorigin="anonymous"
></script>
</body>
</html>
