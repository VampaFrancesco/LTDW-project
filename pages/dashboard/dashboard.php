<?php
// dashboard.php
// Pagina Dashboard per amministratori

// Controllo accesso admin
require_once __DIR__ . '/admin_check.php';
require_once __DIR__ . '/../../include/config.inc.php';

// Ora possiamo recuperare i dati dell'utente admin
$adminName = SessionManager::get('user_nome', 'Admin');
$adminEmail = SessionManager::get('user_email', '');

// Connessione database per recuperare statistiche
$db_config = $config['dbms']['localhost'];
$conn = new mysqli(
    $db_config['host'],
    $db_config['user'],
    $db_config['passwd'],
    $db_config['dbname']
);

if ($conn->connect_error) {
    die("Errore di connessione: " . $conn->connect_error);
}

// Recupera statistiche
$stats = [];

// 1. Numero utenti registrati
$result = $conn->query("SELECT COUNT(*) as total FROM utente");
$stats['utenti_totali'] = $result->fetch_assoc()['total'];

// 2. Ordini di oggi (assumendo che esista la tabella ordine)
$today = date('Y-m-d');
$result = $conn->query("SELECT COUNT(*) as total FROM ordine WHERE DATE(data_ordine) = '$today'");
$stats['ordini_oggi'] = $result->fetch_assoc()['total'];

// 3. Fatturato mensile (assumendo che esista la tabella fattura)
$currentMonth = date('Y-m');
$result = $conn->query("SELECT COALESCE(SUM(totale_fattura), 0) as total FROM fattura WHERE DATE_FORMAT(data_emissione, '%Y-%m') = '$currentMonth'");
$stats['fatturato_mensile'] = $result->fetch_assoc()['total'];

// 4. Prodotti attivi (oggetti + mystery box)
$result1 = $conn->query("SELECT COUNT(*) as total FROM oggetto WHERE quant_oggetto IS NULL OR quant_oggetto > 0");
$oggetti_attivi = $result1->fetch_assoc()['total'];
$result2 = $conn->query("SELECT COUNT(*) as total FROM mystery_box WHERE quantita_box > 0");
$box_attive = $result2->fetch_assoc()['total'];
$stats['prodotti_attivi'] = $oggetti_attivi + $box_attive;

// 5. Ultimi ordini
$ultimi_ordini = [];
$result = $conn->query("
    SELECT 
        o.id_ordine,
        CONCAT(u.nome, ' ', u.cognome) as cliente_nome,
        o.data_ordine,
        c.totale,
        CASE 
            WHEN o.stato_ordine = 0 THEN 'In elaborazione'
            WHEN o.stato_ordine = 1 THEN 'Spedito'
            WHEN o.stato_ordine = 2 THEN 'Consegnato'
            ELSE 'Sconosciuto'
        END as stato_nome,
        o.stato_ordine
    FROM ordine o
    JOIN utente u ON o.fk_utente = u.id_utente
    JOIN carrello c ON o.fk_carrello = c.id_carrello
    ORDER BY o.data_ordine DESC
    LIMIT 10
");

while ($row = $result->fetch_assoc()) {
    $ultimi_ordini[] = $row;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Dashboard Admin - Box Omnia</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- CSS personalizzato -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/dashboard.css">
</head>
<body class="d-flex flex-column min-vh-100">

<!-- Header / Navbar -->
<header class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <i class="bi bi-speedometer2"></i> Dashboard Admin Box Omnia
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>/pages/index.php">
                        <i class="bi bi-house"></i> Sito Principale
                    </a>
                </li>
                <li class="nav-item">
                    <span class="nav-link">
                        <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($adminName); ?>
                    </span>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>/pages/auth/logout.php">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</header>

<!-- Contenuto Principale -->
<div class="container-fluid flex-grow-1">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-12 col-md-3 col-lg-2 d-md-block bg-light sidebar">
            <div class="position-sticky pt-3">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="#">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="gestione_utenti.php">
                            <i class="bi bi-people"></i> Gestione Utenti
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="gestione_prodotti.php">
                            <i class="bi bi-box"></i> Gestione Prodotti
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="gestione_ordini.php">
                            <i class="bi bi-cart"></i> Gestione Ordini
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="report.php">
                            <i class="bi bi-graph-up"></i> Report
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="impostazioni.php">
                            <i class="bi bi-gear"></i> Impostazioni
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main -->
        <main class="col-12 col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
                <h1 class="h2">Benvenuto, <?php echo htmlspecialchars($adminName); ?>!</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary">Condividi</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary">Esporta</button>
                    </div>
                </div>
            </div>

            <!-- Cards statistiche -->
            <div class="row g-4 mb-4">
                <div class="col-sm-6 col-lg-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="bi bi-people-fill text-primary" style="font-size: 2rem;"></i>
                            <h3 class="mb-1"><?php echo number_format($stats['utenti_totali']); ?></h3>
                            <p class="text-muted mb-0">Utenti Registrati</p>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="bi bi-basket-fill text-success" style="font-size: 2rem;"></i>
                            <h3 class="mb-1"><?php echo $stats['ordini_oggi']; ?></h3>
                            <p class="text-muted mb-0">Ordini Oggi</p>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="bi bi-currency-euro text-warning" style="font-size: 2rem;"></i>
                            <h3 class="mb-1">€<?php echo number_format($stats['fatturato_mensile'], 2); ?></h3>
                            <p class="text-muted mb-0">Fatturato Mensile</p>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="bi bi-box-seam text-info" style="font-size: 2rem;"></i>
                            <h3 class="mb-1"><?php echo $stats['prodotti_attivi']; ?></h3>
                            <p class="text-muted mb-0">Prodotti Attivi</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sezione azioni rapide -->
            <h2 class="h4 mb-3">Azioni Rapide</h2>
            <div class="row g-3 mb-4">
                <div class="col-md-6 col-lg-3">
                    <a href="create.php" class="btn btn-success w-100">
                        <i class="bi bi-plus-circle"></i> Crea Nuovo
                    </a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <a href="read.php" class="btn btn-primary w-100">
                        <i class="bi bi-eye"></i> Visualizza Dati
                    </a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <a href="update.php" class="btn btn-warning w-100">
                        <i class="bi bi-pencil"></i> Modifica Dati
                    </a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <a href="delete.php" class="btn btn-danger w-100">
                        <i class="bi bi-trash"></i> Elimina Dati
                    </a>
                </div>
            </div>

            <!-- Sezione gestione specifica -->
            <h2 class="h4 mb-3">Gestione Sistema</h2>
            <div class="row g-3 mb-4">
                <div class="col-md-6 col-lg-3">
                    <a href="gestione_utenti.php" class="btn btn-outline-primary w-100">
                        <i class="bi bi-people"></i> Gestisci Utenti
                    </a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <a href="gestione_prodotti.php" class="btn btn-outline-success w-100">
                        <i class="bi bi-box"></i> Gestisci Prodotti
                    </a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <a href="gestione_ordini.php" class="btn btn-outline-info w-100">
                        <i class="bi bi-cart"></i> Gestisci Ordini
                    </a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <a href="crea_admin.php" class="btn btn-outline-danger w-100">
                        <i class="bi bi-shield-plus"></i> Crea Admin
                    </a>
                </div>
            </div>

            <!-- Tabella ultimi ordini -->
            <h2 class="h4 mb-3">Ultimi Ordini</h2>
            <div class="table-responsive">
                <table class="table table-striped table-sm">
                    <thead>
                    <tr>
                        <th>#Ordine</th>
                        <th>Cliente</th>
                        <th>Data</th>
                        <th>Totale</th>
                        <th>Stato</th>
                        <th>Azioni</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($ultimi_ordini)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">Nessun ordine trovato</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ultimi_ordini as $ordine): ?>
                            <tr>
                                <td>#<?php echo str_pad($ordine['id_ordine'], 6, '0', STR_PAD_LEFT); ?></td>
                                <td><?php echo htmlspecialchars($ordine['cliente_nome']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($ordine['data_ordine'])); ?></td>
                                <td>€<?php echo number_format($ordine['totale'], 2); ?></td>
                                <td>
                                    <?php
                                    $badge_class = 'bg-secondary';
                                    switch ($ordine['stato_ordine']) {
                                        case 0: $badge_class = 'bg-warning'; break;
                                        case 1: $badge_class = 'bg-primary'; break;
                                        case 2: $badge_class = 'bg-success'; break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($ordine['stato_nome']); ?></span>
                                </td>
                                <td>
                                    <a href="dettaglio_ordine.php?id=<?php echo $ordine['id_ordine']; ?>" class="btn btn-sm btn-outline-primary">Dettagli</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Statistiche aggiuntive -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-graph-up"></i> Statistiche Veloci</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6">
                                    <h6>Mystery Box</h6>
                                    <p class="h5 text-primary"><?php
                                        $conn = new mysqli($db_config['host'], $db_config['user'], $db_config['passwd'], $db_config['dbname']);
                                        $result = $conn->query("SELECT COUNT(*) as total FROM mystery_box");
                                        echo $result->fetch_assoc()['total'];
                                        $conn->close();
                                        ?></p>
                                </div>
                                <div class="col-6">
                                    <h6>Oggetti</h6>
                                    <p class="h5 text-success"><?php
                                        $conn = new mysqli($db_config['host'], $db_config['user'], $db_config['passwd'], $db_config['dbname']);
                                        $result = $conn->query("SELECT COUNT(*) as total FROM oggetto");
                                        echo $result->fetch_assoc()['total'];
                                        $conn->close();
                                        ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-info-circle"></i> Sistema</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Ultimo accesso:</strong> <?php echo date('d/m/Y H:i'); ?></p>
                            <p><strong>Admin attivi:</strong> <?php
                                $conn = new mysqli($db_config['host'], $db_config['user'], $db_config['passwd'], $db_config['dbname']);
                                $result = $conn->query("SELECT COUNT(*) as total FROM admin");
                                echo $result->fetch_assoc()['total'];
                                $conn->close();
                                ?></p>
                            <p class="mb-0"><strong>Versione:</strong> 1.0.0</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Footer -->
<footer class="footer mt-auto py-3 bg-light">
    <div class="container-fluid">
        <span class="text-muted">&copy; <?php echo date('Y'); ?> BOX OMNIA - Dashboard Amministrativa</span>
    </div>
</footer>

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>6 col-lg-3">
<div class="card text-center">
    <div class="card-body">
        <i class="bi bi-people-fill text-primary" style="font-size: 2rem;"></i>
        <h3 class="mb-1"><?php echo number_format($stats['utenti_totali']); ?></h3>
        <p class="text-muted mb-0">Utenti Registrati</p>
    </div>
</div>
</div>
<div class="col-sm-6 col-lg-3">
    <div class="card text-center">
        <div class="card-body">
            <i class="bi bi-basket-fill text-success" style="font-size: 2rem;"></i>
            <h3 class="mb-1"><?php echo $stats['ordini_oggi']; ?></h3>
            <p class="text-muted mb-0">Ordini Oggi</p>
        </div>
    </div>
</div>
<div class="col-sm-