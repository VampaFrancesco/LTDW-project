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
    // 1. Numero utenti registrati (escludendo admin interni)
    $result = $conn->query("
        SELECT COUNT(*) as total 
        FROM utente 
        WHERE email NOT LIKE '%@boxomnia.%'
    ");
    $stats['utenti_totali'] = $result ? $result->fetch_assoc()['total'] : 0;

    // 2. Ordini di oggi
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM ordine WHERE DATE(data_ordine) = ?");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['ordini_oggi'] = $result->fetch_assoc()['total'];
    $stmt->close();

    // 3. Fatturato mensile REALE (da carrelli completati)
    $currentMonth = date('Y-m');
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(c.totale), 0) as total 
        FROM carrello c
        JOIN ordine o ON c.id_carrello = o.fk_carrello
        WHERE c.stato IN ('completato', 'checkout')
        AND DATE_FORMAT(o.data_ordine, '%Y-%m') = ?
        AND o.stato_ordine NOT IN (3, 4)
    ");
    $stmt->bind_param("s", $currentMonth);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['fatturato_mensile'] = $result->fetch_assoc()['total'];
    $stmt->close();

    // Fallback: se non ci sono carrelli collegati, calcola da fatture
    if ($stats['fatturato_mensile'] <= 0) {
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(totale_fattura), 0) as total 
            FROM fattura 
            WHERE DATE_FORMAT(data_emissione, '%Y-%m') = ?
        ");
        $stmt->bind_param("s", $currentMonth);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['fatturato_mensile'] = $result->fetch_assoc()['total'];
        $stmt->close();
    }

    // 4. Prodotti effettivamente disponibili
    $result1 = $conn->query("
        SELECT COUNT(*) as total 
        FROM oggetto 
        WHERE (quant_oggetto IS NULL OR quant_oggetto > 0)
        AND prezzo_oggetto IS NOT NULL 
        AND prezzo_oggetto > 0
    ");
    $oggetti_attivi = $result1 ? $result1->fetch_assoc()['total'] : 0;

    $result2 = $conn->query("
        SELECT COUNT(*) as total 
        FROM mystery_box 
        WHERE quantita_box > 0
    ");
    $box_attive = $result2 ? $result2->fetch_assoc()['total'] : 0;

    $stats['prodotti_attivi'] = $oggetti_attivi + $box_attive;

    // 5. Amministratori attivi
    $result = $conn->query("SELECT COUNT(*) as total FROM admin");
    $stats['admin_attivi'] = $result ? $result->fetch_assoc()['total'] : 0;

    // 6. Ultimi ordini (massimo 5) con totale corretto
    $ordini_recenti = [];
    $result = $conn->query("
        SELECT 
            o.id_ordine,
            o.data_ordine,
            o.stato_ordine,
            CONCAT(u.nome, ' ', u.cognome) as cliente_nome,
            COALESCE(c.totale, 0) as totale,
            CASE 
                WHEN o.stato_ordine = 0 THEN 'In Elaborazione'
                WHEN o.stato_ordine = 1 THEN 'Spedito'
                WHEN o.stato_ordine = 2 THEN 'Consegnato'
                WHEN o.stato_ordine = 3 THEN 'Annullato'
                WHEN o.stato_ordine = 4 THEN 'Rimborsato'
                ELSE 'Sconosciuto'
            END as stato_nome
        FROM ordine o
        JOIN utente u ON o.fk_utente = u.id_utente
        LEFT JOIN carrello c ON o.fk_carrello = c.id_carrello
        ORDER BY o.data_ordine DESC
        LIMIT 5
    ");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $ordini_recenti[] = $row;
        }
    }

    // 7. Statistiche aggiuntive per insight migliori

    // Valore medio ordine
    $result = $conn->query("
        SELECT COALESCE(AVG(c.totale), 0) as media
        FROM carrello c
        JOIN ordine o ON c.id_carrello = o.fk_carrello
        WHERE c.stato IN ('completato', 'checkout')
        AND o.stato_ordine NOT IN (3, 4)
        AND DATE_FORMAT(o.data_ordine, '%Y-%m') = '$currentMonth'
    ");
    $stats['valore_medio_ordine'] = $result ? $result->fetch_assoc()['media'] : 0;

    // Prodotti più venduti (top 3)
    $prodotti_top = [];
    $result = $conn->query("
        SELECT 
            COALESCE(mb.nome_box, og.nome_oggetto) as nome_prodotto,
            COUNT(*) as vendite,
            SUM(c.totale) as ricavo_totale,
            CASE WHEN mb.id_box IS NOT NULL THEN 'Mystery Box' ELSE 'Oggetto' END as tipo
        FROM carrello c
        LEFT JOIN mystery_box mb ON c.fk_mystery_box = mb.id_box
        LEFT JOIN oggetto og ON c.fk_oggetto = og.id_oggetto
        JOIN ordine o ON c.id_carrello = o.fk_carrello
        WHERE c.stato IN ('completato', 'checkout')
        AND o.stato_ordine NOT IN (3, 4)
        AND DATE_FORMAT(o.data_ordine, '%Y-%m') = '$currentMonth'
        GROUP BY COALESCE(mb.id_box, og.id_oggetto), nome_prodotto, tipo
        ORDER BY vendite DESC
        LIMIT 3
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $prodotti_top[] = $row;
        }
    }

    // Trend vendite ultimi 7 giorni
    $trend_vendite = [];
    for ($i = 6; $i >= 0; $i--) {
        $data = date('Y-m-d', strtotime("-$i days"));
        $result = $conn->query("
            SELECT 
                COUNT(DISTINCT o.id_ordine) as ordini,
                COALESCE(SUM(c.totale), 0) as fatturato
            FROM ordine o
            LEFT JOIN carrello c ON o.fk_carrello = c.id_carrello
            WHERE DATE(o.data_ordine) = '$data'
            AND o.stato_ordine NOT IN (3, 4)
        ");
        $trend_data = $result ? $result->fetch_assoc() : ['ordini' => 0, 'fatturato' => 0];
        $trend_vendite[date('d/m', strtotime($data))] = $trend_data;
    }

} catch (Exception $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
    // Valori di default in caso di errore
    $stats = [
            'utenti_totali' => 0,
            'ordini_oggi' => 0,
            'fatturato_mensile' => 0,
            'prodotti_attivi' => 0,
            'admin_attivi' => 0,
            'valore_medio_ordine' => 0
    ];
    $ordini_recenti = [];
    $prodotti_top = [];
    $trend_vendite = [];
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

        .trend-chart {
            height: 150px;
        }

        .insight-card {
            border-left: 4px solid var(--bs-primary);
        }

        .metric-change {
            font-size: 0.875rem;
        }

        .metric-up {
            color: var(--bs-success);
        }

        .metric-down {
            color: var(--bs-danger);
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
                    <li class="nav-item">
                        <a class="nav-link" href="gestione_contenuti.php">
                            <i class="bi bi-pencil-fill"></i> Gestisci contenuti
                        </a>
                    </li>
                    <!-- AGGIUNGI questa voce nella sidebar del tuo dashboard.php -->

<li class="nav-item">
    <a class="nav-link" href="gestione_supporto.php">
        <i class="bi bi-headset"></i> Supporto Clienti
        <?php
        // Mostra il numero di richieste aperte - con connessione separata
        try {
            $db_config = $config['dbms']['localhost'];
            $conn_sidebar = new mysqli(
                $db_config['host'],
                $db_config['user'],
                $db_config['passwd'],
                $db_config['dbname']
            );
            
            if (!$conn_sidebar->connect_error) {
                $stmt = $conn_sidebar->prepare("SELECT COUNT(*) as count FROM richieste_supporto WHERE stato IN ('aperta', 'in_corso')");
                $stmt->execute();
                $result = $stmt->get_result();
                $open_requests = $result->fetch_assoc()['count'];
                $stmt->close();
                $conn_sidebar->close();
                
                if ($open_requests > 0) {
                    echo '<span class="badge bg-warning text-dark ms-2">' . $open_requests . '</span>';
                }
            }
        } catch (Exception $e) {
            // Ignora errori per non bloccare la dashboard
            error_log("Errore conteggio richieste supporto: " . $e->getMessage());
        }
        ?>
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

            <!-- ✅ STATISTICHE PRINCIPALI CORRETTE -->
            <div class="row g-4 mb-4">
                <div class="col-sm-6 col-lg-3">
                    <div class="card stats-card text-center">
                        <div class="card-body">
                            <div class="stats-icon bg-primary bg-opacity-10">
                                <i class="bi bi-people-fill text-primary fs-2"></i>
                            </div>
                            <h3 class="fw-bold text-primary"><?php echo number_format($stats['utenti_totali']); ?></h3>
                            <p class="text-muted mb-0">Clienti Registrati</p>
                            <small class="text-muted">Escl. staff interno</small>
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
                            <!-- Assicuriamo che ci sia sempre un piccolo testo -->
                            <small class="text-muted">
                                <?php if ($stats['valore_medio_ordine'] > 0): ?>
                                    Media: €<?php echo number_format($stats['valore_medio_ordine'], 2); ?>
                                <?php else: ?>
                                    Nessuna media disponibile
                                <?php endif; ?>
                            </small>
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
                            <p class="text-muted mb-0">Fatturato <?php echo date('M Y'); ?></p>
                            <small class="text-muted">Solo ordini completati</small>
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
                            <small class="text-muted">Disponibili e prezzati</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Trend Vendite degli ultimi 7 giorni -->
            <?php if (!empty($trend_vendite)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card insight-card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-graph-up me-2"></i>
                                    Trend Vendite Ultimi 7 Giorni
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <canvas id="trendChart" class="trend-chart"></canvas>
                                    </div>
                                    <div class="col-md-4">
                                        <h6>Riepilogo 7 giorni:</h6>
                                        <?php
                                        $totale_ordini_7g = array_sum(array_column($trend_vendite, 'ordini'));
                                        $totale_fatturato_7g = array_sum(array_column($trend_vendite, 'fatturato'));
                                        ?>
                                        <p class="mb-1"><strong><?php echo $totale_ordini_7g; ?></strong> ordini totali</p>
                                        <p class="mb-1"><strong>€<?php echo number_format($totale_fatturato_7g, 2); ?></strong> fatturato</p>
                                        <p class="mb-0 text-muted">Media: €<?php echo $totale_ordini_7g > 0 ? number_format($totale_fatturato_7g / $totale_ordini_7g, 2) : '0.00'; ?>/ordine</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

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
                            <i class="bi bi-pencil text-warning fs-1"></i>
                            <h5 class="card-title mt-2">Contenuti</h5>
                            <p class="card-text">Modifica testi del sito</p>
                            <a href="gestione_contenuti.php" class="btn btn-warning">Vai</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ✅ SEZIONE ORDINI RECENTI E PRODOTTI TOP -->
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
                                                    <td>€<?php echo number_format((float)($ordine['totale'] ?? 0), 2, ',', '.'); ?></td>
                                                    <td>
                                                        <?php
                                                        $badge_class = match($ordine['stato_ordine']) {
                                                            0 => 'bg-warning',
                                                            1 => 'bg-info',
                                                            2 => 'bg-success',
                                                            3 => 'bg-danger',
                                                            4 => 'bg-secondary',
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
                    <!-- Prodotti più venduti -->
                    <?php if (!empty($prodotti_top)): ?>
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-trophy"></i> Top Prodotti del Mese</h6>
                            </div>
                            <div class="card-body">
                                <?php foreach ($prodotti_top as $index => $prodotto): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <div class="fw-bold"><?php echo ($index + 1); ?>. <?php echo htmlspecialchars($prodotto['nome_prodotto']); ?></div>
                                            <small class="text-muted"><?php echo $prodotto['tipo']; ?></small>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold"><?php echo $prodotto['vendite']; ?> vendite</div>
                                            <small class="text-success">€<?php echo number_format($prodotto['ricavo_totale'], 2); ?></small>
                                        </div>
                                    </div>
                                    <?php if ($index < count($prodotti_top) - 1): ?>
                                        <hr class="my-2">
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Info Sistema -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-info-circle"></i> Info Sistema</h6>
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

<!-- Chart.js per il grafico del trend -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Dashboard interattività -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Animazione cards statistiche
        const statsCards = document.querySelectorAll('.stats-card');
        statsCards.forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';

            setTimeout(() => {
                card.style.transition = 'all 0.5s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });

        // Grafico trend vendite se i dati sono disponibili
        <?php if (!empty($trend_vendite)): ?>
        const ctx = document.getElementById('trendChart');
        if (ctx) {
            const trendData = <?php echo json_encode($trend_vendite); ?>;
            const labels = Object.keys(trendData);
            const ordiniData = labels.map(label => trendData[label].ordini);
            const fatturatoData = labels.map(label => trendData[label].fatturato);

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Ordini',
                            data: ordiniData,
                            borderColor: 'rgb(75, 192, 192)',
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            tension: 0.4,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Fatturato (€)',
                            data: fatturatoData,
                            borderColor: 'rgb(255, 99, 132)',
                            backgroundColor: 'rgba(255, 99, 132, 0.2)',
                            tension: 0.4,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: false
                        },
                        legend: {
                            position: 'bottom'
                        }
                    },
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Data'
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Ordini',
                                color: 'rgb(75, 192, 192)'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Fatturato (€)',
                                color: 'rgb(255, 99, 132)'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                        }
                    }
                }
            });
        }
        <?php endif; ?>

        // Aggiorna ora ogni minuto
        setInterval(function() {
            const now = new Date();
            const timeString = now.toLocaleString('it-IT');
            const timeElement = document.querySelector('small.text-muted');
            if (timeElement) {
                timeElement.textContent = `Ultimo accesso: ${timeString}`;
            }
        }, 60000);

        // Refresh automatico delle statistiche ogni 5 minuti
        setInterval(function() {
            // Aggiorna solo se la pagina è visibile
            if (!document.hidden) {
                window.location.reload();
            }
        }, 300000); // 5 minuti
    });
</script>

</body>
</html>