<?php
// gestione_ordini.php - Sistema corretto per gestione ordini admin con styling uniforme
require_once __DIR__ . '/admin_check.php';
require_once __DIR__ . '/../../include/config.inc.php';

$adminName = SessionManager::get('user_nome', 'Admin');
$adminId = SessionManager::get('admin_id');

// Connessione database
$db_config = $config['dbms']['localhost'];
$conn = new mysqli($db_config['host'], $db_config['user'], $db_config['passwd'], $db_config['dbname']);

if ($conn->connect_error) {
    die("Errore di connessione: " . $conn->connect_error);
}

// Gestione azioni
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
$orderId = intval($_POST['order_id'] ?? $_GET['id'] ?? 0);

// Recupera messaggi flash
$flash_message = SessionManager::getFlashMessage();

// Gestione carrelli abbandonati (cleanup automatico)
if ($action === 'cleanup_carts') {
    $days = intval($_GET['days'] ?? 30);

    // Controlla se esiste il campo data_ultima_modifica
    $result = $conn->query("SHOW COLUMNS FROM carrello LIKE 'data_ultima_modifica'");
    if ($result->num_rows > 0) {
        $stmt = $conn->prepare("
            UPDATE carrello 
            SET stato = 'abbandonato' 
            WHERE stato = 'attivo' 
            AND data_ultima_modifica < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
    } else {
        // Fallback usando data_creazione se data_ultima_modifica non esiste
        $stmt = $conn->prepare("
            UPDATE carrello 
            SET stato = 'abbandonato' 
            WHERE stato = 'attivo' 
            AND data_creazione < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
    }

    $stmt->bind_param("i", $days);
    if ($stmt->execute()) {
        $affected = $stmt->affected_rows;
        SessionManager::setFlashMessage("$affected carrelli marcati come abbandonati", 'info');
    }
    $stmt->close();
    header('Location: gestione_ordini.php');
    exit();
}

// Azioni POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_order') {
    $newStatus = intval($_POST['new_status'] ?? -1);
    $tracking = trim($_POST['tracking'] ?? '');
    $note = trim($_POST['note'] ?? '');

    if ($orderId > 0 && $newStatus >= 0) {
        $conn->begin_transaction();

        try {
            // Recupera stato precedente e dati ordine
            $stmt = $conn->prepare("
                SELECT o.stato_ordine, o.fk_utente, o.fk_carrello, o.data_ordine,
                       CONCAT(u.nome, ' ', u.cognome) as cliente_nome,
                       u.email,
                       CONCAT(i.via, ' ', i.civico) as via,
                       i.cap, i.citta, i.provincia, i.nazione
                FROM ordine o 
                JOIN utente u ON o.fk_utente = u.id_utente
                JOIN indirizzo_spedizione i ON o.fk_indirizzo = i.id_indirizzo
                WHERE o.id_ordine = ?
            ");
            $stmt->bind_param("i", $orderId);
            $stmt->execute();
            $result = $stmt->get_result();
            $order_data = $result->fetch_assoc();
            $old_status = $order_data['stato_ordine'] ?? null;
            $stmt->close();

            // Aggiorna ordine
            $stmt = $conn->prepare("UPDATE ordine SET stato_ordine = ?, tracking = ? WHERE id_ordine = ?");
            $stmt->bind_param("isi", $newStatus, $tracking, $orderId);

            if (!$stmt->execute()) {
                throw new Exception("Errore aggiornamento ordine");
            }
            $stmt->close();

            // Log modifica (se tabella esiste)
            $stmt = $conn->prepare("
                INSERT INTO ordine_log (fk_ordine, stato_precedente, stato_nuovo, note, modificato_da, data_modifica)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            if ($stmt) {
                $stmt->bind_param("iiisi", $orderId, $old_status, $newStatus, $note, $adminId);
                $stmt->execute();
                $stmt->close();
            }

            // Se ordine spedito, invia email
            if ($newStatus == 1 && !empty($tracking)) {
                $subject = "Il tuo ordine #" . str_pad($orderId, 6, '0', STR_PAD_LEFT) . " è stato spedito!";
                $message = "Ciao " . $order_data['cliente_nome'] . ",\n\n";
                $message .= "Il tuo ordine è stato spedito!\n\n";
                $message .= "Numero tracking: " . $tracking . "\n\n";
                $message .= "Puoi tracciare il tuo pacco sul sito del corriere.\n\n";
                $message .= "Grazie per il tuo acquisto!\n";
                $message .= "Il team di Box Omnia";

                @mail($order_data['email'], $subject, $message, "From: noreply@boxomnia.com");
            }

            // Se ordine passa a "consegnato", crea fattura
            if ($newStatus == 2 && $old_status != 2) {
                // Controlla se esiste già una fattura per questo ordine
                $stmt = $conn->prepare("SELECT id_fattura FROM fattura WHERE fk_ordine = ?");
                $stmt->bind_param("i", $orderId);
                $stmt->execute();
                $result = $stmt->get_result();
                $fattura_esistente = $result->fetch_assoc();
                $stmt->close();

                if (!$fattura_esistente) {
                    // Genera numero fattura
                    $anno_corrente = date('Y');
                    $stmt = $conn->prepare("
                        SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(numero_fattura, '/', 1) AS UNSIGNED)), 0) + 1 as prossimo
                        FROM fattura 
                        WHERE YEAR(data_emissione) = ?
                    ");
                    $stmt->bind_param("i", $anno_corrente);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $prossimo_numero = $result->fetch_assoc()['prossimo'];
                    $numero_fattura = str_pad($prossimo_numero, 4, '0', STR_PAD_LEFT) . '/' . $anno_corrente;
                    $stmt->close();

                    // Calcola totale carrello
                    $totale_ordine = 0;
                    $carrello_id = $order_data['fk_carrello'];

                    if ($carrello_id) {
                        $stmt = $conn->prepare("SELECT COALESCE(totale, 0) as totale FROM carrello WHERE id_carrello = ?");
                        $stmt->bind_param("i", $carrello_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $carrello = $result->fetch_assoc();
                        $totale_ordine = $carrello['totale'] ?? 0;
                        $stmt->close();
                    }

                    SessionManager::setFlashMessage(
                            "Ordine consegnato e fattura #{$numero_fattura} creata automaticamente!",
                            'success'
                    );
                } else {
                    SessionManager::setFlashMessage('Ordine aggiornato (fattura già esistente)', 'info');
                }
            } else {
                SessionManager::setFlashMessage('Ordine aggiornato con successo!', 'success');
            }

            $conn->commit();

        } catch (Exception $e) {
            $conn->rollback();
            SessionManager::setFlashMessage('Errore: ' . $e->getMessage(), 'danger');
        }
    }

    header('Location: gestione_ordini.php');
    exit();
}

// Filtri
$filter_status = $_GET['status'] ?? '';
$filter_search = $_GET['search'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

// Query recupero ordini con filtri
$where_conditions = [];
$params = [];
$types = "";

if ($filter_status !== '') {
    $where_conditions[] = "o.stato_ordine = ?";
    $params[] = $filter_status;
    $types .= "i";
}

if (!empty($filter_search)) {
    $where_conditions[] = "(u.nome LIKE ? OR u.cognome LIKE ? OR u.email LIKE ? OR o.tracking LIKE ?)";
    $search_param = "%$filter_search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

if (!empty($filter_date_from)) {
    $where_conditions[] = "DATE(o.data_ordine) >= ?";
    $params[] = $filter_date_from;
    $types .= "s";
}

if (!empty($filter_date_to)) {
    $where_conditions[] = "DATE(o.data_ordine) <= ?";
    $params[] = $filter_date_to;
    $types .= "s";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Query principale ordini
$query = "
    SELECT 
        o.id_ordine,
        o.data_ordine,
        o.tracking,
        o.stato_ordine,
        CONCAT(u.nome, ' ', u.cognome) as cliente_nome,
        u.email as cliente_email,
        u.telefono as cliente_telefono,
        COALESCE(
            c_diretto.totale,
            (SELECT SUM(c2.totale) 
             FROM carrello c2 
             WHERE c2.fk_utente = o.fk_utente 
             AND c2.stato IN ('checkout', 'completato')
             AND c2.data_creazione <= o.data_ordine
             ORDER BY c2.id_carrello DESC 
             LIMIT 1
            ),
            0
        ) as totale,
        COALESCE(
            c_diretto.quantita,
            (SELECT SUM(c2.quantita) 
             FROM carrello c2 
             WHERE c2.fk_utente = o.fk_utente 
             AND c2.stato IN ('checkout', 'completato')
             AND c2.data_creazione <= o.data_ordine
            ),
            1
        ) as quantita_articoli,
        CONCAT('Via ', i.via, ' ', i.civico, ', ', i.cap, ' ', i.citta, ' (', i.provincia, ')') as indirizzo,
        CASE 
            WHEN o.stato_ordine = 0 THEN 'In elaborazione'
            WHEN o.stato_ordine = 1 THEN 'Spedito'
            WHEN o.stato_ordine = 2 THEN 'Consegnato'
            WHEN o.stato_ordine = 3 THEN 'Annullato'
            WHEN o.stato_ordine = 4 THEN 'Rimborsato'
            ELSE 'Sconosciuto'
        END as stato_nome
    FROM ordine o
    JOIN utente u ON o.fk_utente = u.id_utente
    LEFT JOIN carrello c_diretto ON o.fk_carrello = c_diretto.id_carrello
    JOIN indirizzo_spedizione i ON o.fk_indirizzo = i.id_indirizzo
    $where_clause
    ORDER BY o.data_ordine DESC
";

$ordini = [];
if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

while ($row = $result->fetch_assoc()) {
    $ordini[] = $row;
}

// Statistiche
$stats = [];

// Ordini in elaborazione
$result = $conn->query("SELECT COUNT(*) as total FROM ordine WHERE stato_ordine = 0");
$stats['in_elaborazione'] = $result->fetch_assoc()['total'];

// Ordini di oggi
$result = $conn->query("SELECT COUNT(*) as total FROM ordine WHERE DATE(data_ordine) = CURDATE()");
$stats['ordini_oggi'] = $result->fetch_assoc()['total'];

// Fatturato mensile
$currentMonth = date('Y-m');
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(user_totals.total_user), 0) as total
    FROM ordine,(
        SELECT 
            o.fk_utente,
            SUM(c.totale) as total_user
        FROM ordine o
        JOIN carrello c ON c.fk_utente = o.fk_utente
        WHERE o.stato_ordine = 2
        AND DATE_FORMAT(o.data_ordine, '%Y-%m') = ?
        AND c.stato IN ('completato', 'checkout')
        AND c.data_creazione <= DATE_ADD(o.data_ordine, INTERVAL 1 DAY)
        GROUP BY o.fk_utente, DATE(o.data_ordine)
    ) user_totals
");
$stmt->bind_param("s", $currentMonth);
$stmt->execute();
$result = $stmt->get_result();
$stats['fatturato_mese'] = $result->fetch_assoc()['total'];
$stmt->close();
// Statistiche carrelli per stato
$result = $conn->query("
    SELECT 
        stato,
        COUNT(*) as count,
        COALESCE(SUM(totale), 0) as totale
    FROM carrello 
    WHERE stato IS NOT NULL
    GROUP BY stato
");
$stats['carrelli_per_stato'] = [];
while ($row = $result->fetch_assoc()) {
    $stats['carrelli_per_stato'][$row['stato']] = [
            'count' => $row['count'],
            'totale' => $row['totale']
    ];
}

// Statistiche aggiuntive
$result = $conn->query("
    SELECT 
        COUNT(DISTINCT o.id_ordine) as ordini_totali,
        COALESCE(AVG(c.totale), 0) as valore_medio,
        MAX(c.totale) as ordine_massimo,
        COUNT(DISTINCT o.fk_utente) as clienti_unici
    FROM ordine o
    LEFT JOIN carrello c ON o.fk_carrello = c.id_carrello
    WHERE DATE_FORMAT(o.data_ordine, '%Y-%m') = '$currentMonth'
    AND o.stato_ordine NOT IN (3, 4)
");
$extra_stats = $result->fetch_assoc();
$stats = array_merge($stats, $extra_stats);

// Dettaglio ordine se richiesto
$dettaglio_ordine = null;
$prodotti_ordine = [];
$log_ordine = [];

if ($action === 'detail' && $orderId > 0) {
    // Info ordine
    $stmt = $conn->prepare("
        SELECT 
            o.*,
            CONCAT(u.nome, ' ', u.cognome) as cliente_nome,
            u.email as cliente_email,
            u.telefono as cliente_telefono,
            u.id_utente,
            COALESCE(c.totale, 0) as totale,
            COALESCE(c.quantita, 0) as quantita_totale,
            CONCAT('Via ', i.via, ' ', i.civico, ', ', i.cap, ' ', i.citta, ' (', i.provincia, ') - ', i.nazione) as indirizzo_completo
        FROM ordine o
        JOIN utente u ON o.fk_utente = u.id_utente
        LEFT JOIN carrello c ON o.fk_carrello = c.id_carrello
        JOIN indirizzo_spedizione i ON o.fk_indirizzo = i.id_indirizzo
        WHERE o.id_ordine = ?
    ");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    $dettaglio_ordine = $result->fetch_assoc();
    $stmt->close();

    // Prodotti nell'ordine
    if ($dettaglio_ordine && $dettaglio_ordine['fk_carrello']) {
        $stmt = $conn->prepare("
            SELECT 
                c.*,
                COALESCE(mb.nome_box, og.nome_oggetto) as nome_prodotto,
                COALESCE(mb.desc_box, og.desc_oggetto) as desc_prodotto,
                COALESCE(mb.prezzo_box, og.prezzo_oggetto) as prezzo_unitario,
                c.stato as stato_carrello
            FROM carrello c
            LEFT JOIN mystery_box mb ON c.fk_mystery_box = mb.id_box
            LEFT JOIN oggetto og ON c.fk_oggetto = og.id_oggetto
            WHERE c.id_carrello = ?
        ");
        $stmt->bind_param("i", $dettaglio_ordine['fk_carrello']);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $prodotti_ordine[] = $row;
        }
        $stmt->close();
    }

    // Log modifiche ordine
    $stmt = $conn->prepare("
        SELECT 
            ol.*,
            u.nome as admin_nome
        FROM ordine_log ol
        LEFT JOIN admin a ON ol.modificato_da = a.id_admin
        LEFT JOIN utente u ON a.fk_utente = u.id_utente
        WHERE ol.fk_ordine = ?
        ORDER BY ol.data_modifica DESC
    ");
    if ($stmt) {
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $log_ordine[] = $row;
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Ordini - Admin Box Omnia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .sidebar {
            background: linear-gradient(180deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: calc(100vh - 56px);
        }
        .status-badge {
            padding: 0.35em 0.65em;
            font-size: 0.875em;
            font-weight: 500;
            border-radius: 0.375rem;
        }
        .status-0 { background-color: #ffc107; color: #000; }
        .status-1 { background-color: #17a2b8; color: #fff; }
        .status-2 { background-color: #28a745; color: #fff; }
        .status-3 { background-color: #dc3545; color: #fff; }
        .status-4 { background-color: #6c757d; color: #fff; }

        .stats-card-enhanced {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .stats-card-enhanced:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .order-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">

<!-- Header -->
<header class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">
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
                        <a class="nav-link" href="dashboard.php">
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
                        <a class="nav-link" href="gestione_categorie.php">
                            <i class="bi bi-tags"></i> Gestione Categorie
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="#">
                            <i class="bi bi-bag-check"></i> Gestione Ordini
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="gestione_supporto.php">
                            <i class="bi bi-headset"></i> Supporto Clienti
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
                </ul>
            </div>
        </nav>

        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="bi bi-cart-check me-2"></i> Gestione Ordini
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <?php if (($stats['carrelli_per_stato']['attivo']['count'] ?? 0) > 0): ?>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-gear"></i> Azioni
                            </button>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item" href="?action=cleanup_carts&days=30"
                                       onclick="return confirm('Marcare come abbandonati i carrelli inattivi da più di 30 giorni?')">
                                        <i class="bi bi-trash"></i> Pulisci carrelli vecchi (30gg)
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="?action=cleanup_carts&days=7"
                                       onclick="return confirm('Marcare come abbandonati i carrelli inattivi da più di 7 giorni?')">
                                        <i class="bi bi-trash"></i> Pulisci carrelli vecchi (7gg)
                                    </a>
                                </li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Messaggi Flash -->
            <?php if ($flash_message): ?>
                <div class="alert alert-<?php echo $flash_message['type']; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($flash_message['content'] ?? ''); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistiche -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stats-card-enhanced text-center">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-center mb-2">
                                <i class="bi bi-hourglass-split text-warning fs-2 me-2"></i>
                                <div>
                                    <h4 class="fw-bold mb-0"><?php echo $stats['in_elaborazione']; ?></h4>
                                    <small class="text-muted">In Elaborazione</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card-enhanced text-center">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-center mb-2">
                                <i class="bi bi-calendar-check text-success fs-2 me-2"></i>
                                <div>
                                    <h4 class="fw-bold mb-0"><?php echo $stats['ordini_oggi']; ?></h4>
                                    <small class="text-muted">Ordini Oggi</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card-enhanced text-center">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-center mb-2">
                                <i class="bi bi-currency-euro text-primary fs-2 me-2"></i>
                                <div>
                                    <h4 class="fw-bold mb-0">€<?php echo number_format($stats['fatturato_mese'], 2); ?></h4>
                                    <small class="text-muted">Fatturato <?php echo date('M Y'); ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card-enhanced text-center">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-center mb-2">
                                <i class="bi bi-people text-info fs-2 me-2"></i>
                                <div>
                                    <h4 class="fw-bold mb-0"><?php echo $stats['clienti_unici'] ?? 0; ?></h4>
                                    <small class="text-muted">Clienti Unici</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistiche aggiuntive -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card order-card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="bi bi-graph-up me-2"></i>
                                Riepilogo Mensile - <?php echo date('F Y'); ?>
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-3">
                                    <div class="border-end">
                                        <h5 class="text-primary mb-1"><?php echo $stats['ordini_totali'] ?? 0; ?></h5>
                                        <small class="text-muted">Ordini Totali</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border-end">
                                        <h5 class="text-success mb-1">€<?php echo number_format($stats['valore_medio'] ?? 0, 2); ?></h5>
                                        <small class="text-muted">Valore Medio Ordine</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border-end">
                                        <h5 class="text-warning mb-1">€<?php echo number_format($stats['ordine_massimo'] ?? 0, 2); ?></h5>
                                        <small class="text-muted">Ordine Massimo</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <h5 class="text-info mb-1"><?php echo ($stats['carrelli_per_stato']['attivo']['count'] ?? 0); ?></h5>
                                    <small class="text-muted">Carrelli Attivi</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistiche Carrelli Dettagliate -->
            <?php if (!empty($stats['carrelli_per_stato'])): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card order-card">
                            <div class="card-header">
                                <h6 class="mb-0">Stato Carrelli</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($stats['carrelli_per_stato'] as $stato => $dati): ?>
                                        <div class="col-md-3 mb-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span>
                                                    <?php
                                                    $badge_class = '';
                                                    switch($stato) {
                                                        case 'attivo': $badge_class = 'bg-success'; break;
                                                        case 'checkout': $badge_class = 'bg-warning'; break;
                                                        case 'completato': $badge_class = 'bg-primary'; break;
                                                        case 'abbandonato': $badge_class = 'bg-danger'; break;
                                                        default: $badge_class = 'bg-secondary';
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $badge_class; ?>">
                                                        <?php echo ucfirst($stato); ?>
                                                    </span>
                                                </span>
                                                <span>
                                                    <strong><?php echo $dati['count']; ?></strong> carrelli
                                                    <br>
                                                    <small>€<?php echo number_format($dati['totale'], 2); ?></small>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($action === 'detail' && $dettaglio_ordine): ?>
                <!-- Dettaglio Ordine -->
                <div class="card order-card mb-4">
                    <div class="card-header">
                        <h4>Ordine #<?php echo str_pad($dettaglio_ordine['id_ordine'], 6, '0', STR_PAD_LEFT); ?></h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Informazioni Cliente</h5>
                                <p>
                                    <strong>Nome:</strong> <?php echo htmlspecialchars($dettaglio_ordine['cliente_nome']); ?><br>
                                    <strong>Email:</strong> <?php echo htmlspecialchars($dettaglio_ordine['cliente_email']); ?><br>
                                    <strong>Telefono:</strong> <?php echo htmlspecialchars($dettaglio_ordine['cliente_telefono'] ?? 'N/D'); ?>
                                </p>
                                <h5>Indirizzo Spedizione</h5>
                                <p><?php echo htmlspecialchars($dettaglio_ordine['indirizzo_completo']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <h5>Informazioni Ordine</h5>
                                <p>
                                    <strong>Data:</strong> <?php echo date('d/m/Y H:i', strtotime($dettaglio_ordine['data_ordine'])); ?><br>
                                    <strong>Totale:</strong> €<?php echo number_format($dettaglio_ordine['totale'], 2); ?><br>
                                    <strong>Articoli:</strong> <?php echo $dettaglio_ordine['quantita_totale']; ?><br>
                                    <strong>Tracking:</strong> <?php echo htmlspecialchars($dettaglio_ordine['tracking'] ?? 'N/D'); ?>
                                </p>

                                <!-- Form aggiornamento stato -->
                                <form method="POST" action="gestione_ordini.php">
                                    <input type="hidden" name="action" value="update_order">
                                    <input type="hidden" name="order_id" value="<?php echo $dettaglio_ordine['id_ordine']; ?>">

                                    <div class="mb-3">
                                        <label class="form-label">Stato Ordine</label>
                                        <select name="new_status" class="form-select" required>
                                            <option value="0" <?php echo $dettaglio_ordine['stato_ordine'] == 0 ? 'selected' : ''; ?>>In elaborazione</option>
                                            <option value="1" <?php echo $dettaglio_ordine['stato_ordine'] == 1 ? 'selected' : ''; ?>>Spedito</option>
                                            <option value="2" <?php echo $dettaglio_ordine['stato_ordine'] == 2 ? 'selected' : ''; ?>>Consegnato</option>
                                            <option value="3" <?php echo $dettaglio_ordine['stato_ordine'] == 3 ? 'selected' : ''; ?>>Annullato</option>
                                            <option value="4" <?php echo $dettaglio_ordine['stato_ordine'] == 4 ? 'selected' : ''; ?>>Rimborsato</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Codice Tracking</label>
                                        <input type="text" name="tracking" class="form-control"
                                               value="<?php echo htmlspecialchars($dettaglio_ordine['tracking'] ?? ''); ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Note (opzionale)</label>
                                        <textarea name="note" class="form-control" rows="2"></textarea>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Aggiorna Ordine
                                    </button>
                                    <a href="gestione_ordini.php" class="btn btn-secondary">
                                        <i class="bi bi-arrow-left"></i> Torna alla lista
                                    </a>
                                </form>
                            </div>
                        </div>

                        <!-- Prodotti Ordine -->
                        <?php if (!empty($prodotti_ordine)): ?>
                            <h5 class="mt-4">Prodotti nell'ordine</h5>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                    <tr>
                                        <th>Prodotto</th>
                                        <th>Tipo</th>
                                        <th>Quantità</th>
                                        <th>Prezzo Unit.</th>
                                        <th>Totale</th>
                                        <th>Stato Carrello</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($prodotti_ordine as $prodotto): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($prodotto['nome_prodotto']); ?></td>
                                            <td>
                                                <?php if ($prodotto['fk_mystery_box']): ?>
                                                    <span class="badge bg-primary">Mystery Box</span>
                                                <?php else: ?>
                                                    <span class="badge bg-info">Oggetto</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $prodotto['quantita']; ?></td>
                                            <td>€<?php echo number_format($prodotto['prezzo_unitario'], 2); ?></td>
                                            <td>€<?php echo number_format($prodotto['totale'], 2); ?></td>
                                            <td>
                                                <?php
                                                $stato_carrello = $prodotto['stato_carrello'] ?? 'sconosciuto';
                                                $badge_class = '';
                                                switch($stato_carrello) {
                                                    case 'attivo': $badge_class = 'bg-success'; break;
                                                    case 'checkout': $badge_class = 'bg-warning'; break;
                                                    case 'completato': $badge_class = 'bg-primary'; break;
                                                    case 'abbandonato': $badge_class = 'bg-danger'; break;
                                                    default: $badge_class = 'bg-secondary';
                                                }
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>">
                                                    <?php echo ucfirst($stato_carrello); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <!-- Log Modifiche -->
                        <?php if (!empty($log_ordine)): ?>
                            <h5 class="mt-4">Cronologia Modifiche</h5>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Admin</th>
                                        <th>Modifica</th>
                                        <th>Note</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($log_ordine as $log): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y H:i', strtotime($log['data_modifica'])); ?></td>
                                            <td><?php echo htmlspecialchars($log['admin_nome'] ?? 'Sistema'); ?></td>
                                            <td>
                                                <?php
                                                $stati = ['In elaborazione', 'Spedito', 'Consegnato', 'Annullato', 'Rimborsato'];
                                                echo $stati[$log['stato_precedente']] ?? 'N/D';
                                                echo ' → ';
                                                echo $stati[$log['stato_nuovo']] ?? 'N/D';
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($log['note'] ?? ''); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Filtri -->
                <div class="card order-card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Stato</label>
                                <select name="status" class="form-select">
                                    <option value="">Tutti</option>
                                    <option value="0" <?php echo $filter_status === '0' ? 'selected' : ''; ?>>In elaborazione</option>
                                    <option value="1" <?php echo $filter_status === '1' ? 'selected' : ''; ?>>Spedito</option>
                                    <option value="2" <?php echo $filter_status === '2' ? 'selected' : ''; ?>>Consegnato</option>
                                    <option value="3" <?php echo $filter_status === '3' ? 'selected' : ''; ?>>Annullato</option>
                                    <option value="4" <?php echo $filter_status === '4' ? 'selected' : ''; ?>>Rimborsato</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Cerca</label>
                                <input type="text" name="search" class="form-control"
                                       placeholder="Nome, email, tracking..."
                                       value="<?php echo htmlspecialchars($filter_search); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Dal</label>
                                <input type="date" name="date_from" class="form-control"
                                       value="<?php echo $filter_date_from; ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Al</label>
                                <input type="date" name="date_to" class="form-control"
                                       value="<?php echo $filter_date_to; ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Filtra
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tabella Ordini -->
                <div class="card order-card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                <tr>
                                    <th>#Ordine</th>
                                    <th>Cliente</th>
                                    <th>Data</th>
                                    <th>Articoli</th>
                                    <th>Totale</th>
                                    <th>Stato</th>
                                    <th>Tracking</th>
                                    <th>Azioni</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($ordini)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <i class="bi bi-inbox text-muted" style="font-size: 2rem;"></i>
                                            <p class="text-muted mt-2">Nessun ordine trovato</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($ordini as $ordine): ?>
                                        <tr>
                                            <td>
                                                <strong>#<?php echo str_pad($ordine['id_ordine'], 6, '0', STR_PAD_LEFT); ?></strong>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($ordine['cliente_nome']); ?><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($ordine['cliente_email']); ?></small>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($ordine['data_ordine'])); ?></td>
                                            <td><?php echo $ordine['quantita_articoli']; ?></td>
                                            <td>€<?php echo number_format($ordine['totale'], 2); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $ordine['stato_ordine']; ?>">
                                                    <?php echo $ordine['stato_nome']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($ordine['tracking']): ?>
                                                    <code><?php echo htmlspecialchars($ordine['tracking']); ?></code>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="?action=detail&id=<?php echo $ordine['id_ordine']; ?>"
                                                   class="btn btn-sm btn-outline-primary" title="Dettagli">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Footer -->
<footer class="footer mt-auto py-3 bg-light">
    <div class="container-fluid">
        <span class="text-muted">&copy; <?php echo date('Y'); ?> BOX OMNIA - Dashboard Amministrativa</span>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto-dismiss alerts
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    });
</script>
</body>
</html>