<?php
// pages/dashboard/dashboard.php
require_once __DIR__ . '/admin_check.php';
require_once __DIR__ . '/../../include/config.inc.php';

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

// ✅ STATISTICHE CORRETTE E SICURE
try {
    // 1. Numero utenti registrati
    $result = $conn->query("SELECT COUNT(*) as total FROM utente");
    $stats['utenti_totali'] = $result ? $result->fetch_assoc()['total'] : 0;

    // 2. Ordini di oggi
    $today = date('Y-m-d');
    $result = $conn->query("SELECT COUNT(*) as total FROM ordine WHERE DATE(data_ordine) = '$today'");
    $stats['ordini_oggi'] = $result ? $result->fetch_assoc()['total'] : 0;

    // 3. Fatturato mensile (convertito da centesimi)
    $currentMonth = date('Y-m');
    $result = $conn->query("SELECT COALESCE(SUM(totale_fattura), 0) as total FROM fattura WHERE DATE_FORMAT(data_emissione, '%Y-%m') = '$currentMonth'");
    $fatturato_centesimi = $result ? $result->fetch_assoc()['total'] : 0;
    $stats['fatturato_mensile'] = $fatturato_centesimi / 100; // Converti da centesimi a euro

    // 4. Prodotti attivi
    $result1 = $conn->query("SELECT COUNT(*) as total FROM oggetto WHERE quant_oggetto IS NULL OR quant_oggetto > 0");
    $oggetti_attivi = $result1 ? $result1->fetch_assoc()['total'] : 0;

    $result2 = $conn->query("SELECT COUNT(*) as total FROM mystery_box WHERE quantita_box > 0");
    $box_attive = $result2 ? $result2->fetch_assoc()['total'] : 0;

    $stats['prodotti_attivi'] = $oggetti_attivi + $box_attive;

    // 5. Amministratori attivi
    $result = $conn->query("SELECT COUNT(*) as total FROM admin");
    $stats['admin_attivi'] = $result ? $result->fetch_assoc()['total'] : 0;

    // 6. Ultimi ordini (massimo 5)
    $ordini_recenti = [];
    $result = $conn->query("
        SELECT 
            o.id_ordine,
            o.data_ordine,
            o.stato_ordine,
            CONCAT(u.nome, ' ', u.cognome) as cliente_nome,
            c.totale,
            CASE 
                WHEN o.stato_ordine = 0 THEN 'In Elaborazione'
                WHEN o.stato_ordine = 1 THEN 'Completato'
                WHEN o.stato_ordine = 2 THEN 'Spedito'
                WHEN o.stato_ordine = 3 THEN 'Annullato'
                ELSE 'Sconosciuto'
            END as stato_nome
        FROM ordine o
        JOIN utente u ON o.fk_utente = u.id_utente
        JOIN carrello c ON o.fk_carrello = c.id_carrello
        ORDER BY o.data_ordine DESC
        LIMIT 5
    ");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $ordini_recenti[] = $row;
        }
    }

} catch (Exception $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
    // Valori di default in caso di errore
    $stats = [
            'utenti_totali' => 0,
            'ordini_oggi' => 0,
            'fatturato_mensile' => 0,
            'prodotti_attivi' => 0,
            'admin_attivi' => 0
    ];
    $ordini_recenti = [];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - Box Omnia Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        .sidebar {
            background: linear-gradient(180deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: calc(100vh - 56px);
        }

        .stats-card {
            transition: transform 0.2s;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }

        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }

        .action-card {
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .recent-orders {
            max-height: 400px;
            overflow-y: auto;
        }

        .badge-status {
            font-size: 0.8rem;
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">

<!-- Header -->
<header class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <i class="bi bi-speedometer2"></i> Dashboard Admin
        </a>
        <div class="navbar-nav ms-auto">
            <span class="nav-link">
                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($adminName); ?>
            </span>
            <a class="nav-link" href="<?php echo BASE_URL; ?>/pages/auth/logout.php">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>
</header>

<div class="container-fluid flex-grow-1">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-12 col-md-3 col-lg-2 d-md-block sidebar">
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
                            <i class="bi bi-bag-check"></i> Gestione Ordini
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="crea_admin.php">
                            <i class="bi bi-shield-plus"></i> Crea Admin
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Benvenuto, <?php echo htmlspecialchars($adminName); ?>!</h1>
                <small class="text-muted">Ultimo accesso: <?php echo date('d/m/Y H:i'); ?></small>
            </div>

            <!-- ✅ STATISTICHE PRINCIPALI -->
            <div class="row g-4 mb-4">
                <div class="col-sm-6 col-lg-3">
                    <div class="card stats-card text-center">
                        <div class="card-body">
                            <div class="stats-icon bg-primary bg-opacity-10">
                                <i class="bi bi-people-fill text-primary fs-2"></i>
                            </div>
                            <h3 class="fw-bold text-primary"><?php echo number_format($stats['utenti_totali']); ?></h3>
                            <p class="text-muted mb-0">Utenti Registrati</p>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <div class="card stats-card text-center">
                        <div class="card-body">
                            <div class="stats-icon bg-success bg-opacity-10">
                                <i class="bi bi-basket-fill text-success fs-2"></i>
                            </div>
                            <h3 class="fw-bold text-success"><?php echo number_format($stats['ordini_oggi']); ?></h3>
                            <p class="text-muted mb-0">Ordini Oggi</p>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <div class="card stats-card text-center">
                        <div class="card-body">
                            <div class="stats-icon bg-warning bg-opacity-10">
                                <i class="bi bi-currency-euro text-warning fs-2"></i>
                            </div>
                            <h3 class="fw-bold text-warning">€<?php echo number_format($stats['fatturato_mensile'], 2); ?></h3>
                            <p class="text-muted mb-0">Fatturato Mensile</p>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <div class="card stats-card text-center">
                        <div class="card-body">
                            <div class="stats-icon bg-info bg-opacity-10">
                                <i class="bi bi-box-seam text-info fs-2"></i>
                            </div>
                            <h3 class="fw-bold text-info"><?php echo number_format($stats['prodotti_attivi']); ?></h3>
                            <p class="text-muted mb-0">Prodotti Attivi</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ✅ AZIONI RAPIDE -->
            <h2 class="h4 mb-3">Azioni Rapide</h2>
            <div class="row g-3 mb-4">
                <div class="col-md-6 col-lg-3">
                    <div class="card action-card h-100">
                        <div class="card-body text-center">
                            <i class="bi bi-plus-circle text-success fs-1"></i>
                            <h5 class="card-title mt-2">Aggiungi Prodotto</h5>
                            <p class="card-text">Crea nuovi oggetti o mystery box</p>
                            <a href="gestione_prodotti.php" class="btn btn-success">Vai</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card action-card h-100">
                        <div class="card-body text-center">
                            <i class="bi bi-people text-primary fs-1"></i>
                            <h5 class="card-title mt-2">Gestisci Utenti</h5>
                            <p class="card-text">Visualizza e modifica utenti</p>
                            <a href="gestione_utenti.php" class="btn btn-primary">Vai</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card action-card h-100">
                        <div class="card-body text-center">
                            <i class="bi bi-bag-check text-info fs-1"></i>
                            <h5 class="card-title mt-2">Ordini</h5>
                            <p class="card-text">Gestisci ordini e spedizioni</p>
                            <a href="gestione_ordini.php" class="btn btn-info">Vai</a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card action-card h-100">
                        <div class="card-body text-center">
                            <i class="bi bi-shield-plus text-warning fs-1"></i>
                            <h5 class="card-title mt-2">Nuovo Admin</h5>
                            <p class="card-text">Crea nuovi amministratori</p>
                            <a href="crea_admin.php" class="btn btn-warning">Vai</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ✅ SEZIONE ORDINI RECENTI E INFO SISTEMA -->
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-clock-history"></i> Ultimi Ordini</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($ordini_recenti)): ?>
                                <div class="recent-orders">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Cliente</th>
                                                <th>Data</th>
                                                <th>Totale</th>
                                                <th>Stato</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($ordini_recenti as $ordine): ?>
                                                <tr>
                                                    <td>#<?php echo str_pad($ordine['id_ordine'], 3, '0', STR_PAD_LEFT); ?></td>
                                                    <td><?php echo htmlspecialchars($ordine['cliente_nome']); ?></td>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($ordine['data_ordine'])); ?></td>
                                                    <td>€<?php echo number_format($ordine['totale'], 2); ?></td>
                                                    <td>
                                                        <?php
                                                        $badge_class = match($ordine['stato_ordine']) {
                                                            0 => 'bg-warning',
                                                            1 => 'bg-success',
                                                            2 => 'bg-info',
                                                            3 => 'bg-danger',
                                                            default => 'bg-secondary'
                                                        };
                                                        ?>
                                                        <span class="badge <?php echo $badge_class; ?> badge-status">
                                                                <?php echo htmlspecialchars($ordine['stato_nome']); ?>
                                                            </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-inbox text-muted fs-1"></i>
                                    <p class="text-muted mt-2">Nessun ordine recente</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-info-circle"></i> Info Sistema</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span>Admin Attivi:</span>
                                <span class="badge bg-primary"><?php echo $stats['admin_attivi']; ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span>Server:</span>
                                <span class="badge bg-success">Online</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span>Database:</span>
                                <span class="badge bg-success">Connesso</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-0">
                                <span>Versione:</span>
                                <span class="text-muted">v1.0.0</span>
                            </div>
                        </div>
                    </div>

                    <!-- Sistema Rapido -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-lightning"></i> Accesso Rapido</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="<?php echo BASE_URL; ?>/pages/home_utente.php" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-house"></i> Vai al Sito
                                </a>
                                <a href="gestione_ordini.php" class="btn btn-outline-info btn-sm">
                                    <i class="bi bi-bag"></i> Tutti gli Ordini
                                </a>
                                <a href="<?php echo BASE_URL; ?>/pages/auth/logout.php" class="btn btn-outline-danger btn-sm">
                                    <i class="bi bi-box-arrow-right"></i> Logout
                                </a>
                            </div>
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

<!-- Dashboard interattività -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Animazione cards statistiche
        const statsCards = document.querySelectorAll('.stats-card');
        statsCards.forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
            card.classList.add('animate__animated', 'animate__fadeInUp');
        });

        // Aggiorna ora ogni minuto
        setInterval(function() {
            const now = new Date();
            const timeString = now.toLocaleString('it-IT');
            document.querySelector('small.text-muted').textContent = `Ultimo accesso: ${timeString}`;
        }, 60000);
    });
</script>

</body>
</html>