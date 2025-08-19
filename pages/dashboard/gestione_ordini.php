<?php
// gestione_ordini_fixed.php - Sistema corretto per gestione ordini admin
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
            // Recupera stato precedente
            $stmt = $conn->prepare("SELECT stato_ordine FROM ordine WHERE id_ordine = ?");
            $stmt->bind_param("i", $orderId);
            $stmt->execute();
            $result = $stmt->get_result();
            $old_status = $result->fetch_assoc()['stato_ordine'] ?? null;
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
                $stmt = $conn->prepare("
                    SELECT u.email, u.nome 
                    FROM ordine o 
                    JOIN utente u ON o.fk_utente = u.id_utente 
                    WHERE o.id_ordine = ?
                ");
                $stmt->bind_param("i", $orderId);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                $stmt->close();

                if ($user) {
                    $subject = "Il tuo ordine #" . str_pad($orderId, 6, '0', STR_PAD_LEFT) . " è stato spedito!";
                    $message = "Ciao " . $user['nome'] . ",\n\n";
                    $message .= "Il tuo ordine è stato spedito!\n\n";
                    $message .= "Numero tracking: " . $tracking . "\n\n";
                    $message .= "Puoi tracciare il tuo pacco sul sito del corriere.\n\n";
                    $message .= "Grazie per il tuo acquisto!\n";
                    $message .= "Il team di Box Omnia";

                    @mail($user['email'], $subject, $message, "From: noreply@boxomnia.com");
                }
            }

            $conn->commit();
            SessionManager::setFlashMessage('Ordine aggiornato con successo!', 'success');

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

$query = "
    SELECT 
        o.id_ordine,
        o.data_ordine,
        o.tracking,
        o.stato_ordine,
        CONCAT(u.nome, ' ', u.cognome) as cliente_nome,
        u.email as cliente_email,
        u.telefono as cliente_telefono,
        IFNULL(c.totale, 0) as totale,
        IFNULL(c.quantita, 0) as quantita_articoli,
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
    LEFT JOIN carrello c ON o.fk_carrello = c.id_carrello
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
$result = $conn->query("SELECT COUNT(*) as total FROM ordine WHERE stato_ordine = 0");
$stats['in_elaborazione'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM ordine WHERE DATE(data_ordine) = CURDATE()");
$stats['ordini_oggi'] = $result->fetch_assoc()['total'];

$result = $conn->query("
    SELECT COALESCE(SUM(totale_fattura), 0) as total 
    FROM fattura 
    WHERE DATE_FORMAT(data_emissione, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
");
$fatturato_centesimi = $result->fetch_assoc()['total'];
$stats['fatturato_mese'] = $fatturato_centesimi;

// Statistiche carrelli per stato
$result = $conn->query("
    SELECT 
        stato,
        COUNT(*) as count,
        SUM(totale) as totale
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

// Dettaglio ordine se richiesto
$dettaglio_ordine = null;
$prodotti_ordine = [];

if ($action === 'detail' && $orderId > 0) {
    // Info ordine
    $stmt = $conn->prepare("
        SELECT 
            o.*,
            CONCAT(u.nome, ' ', u.cognome) as cliente_nome,
            u.email as cliente_email,
            u.telefono as cliente_telefono,
            u.id_utente,
            IFNULL(c.totale, 0) as totale,
            IFNULL(c.quantita, 0) as quantita_totale,
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
        // Prima metodo: cerca per ID carrello diretto
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

        // Se non trova nulla con l'ID diretto, cerca per stato e data
        if ($result->num_rows == 0) {
            $stmt->close();
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
                WHERE c.fk_utente = ? 
                AND c.stato IN ('checkout', 'completato')
                AND c.data_creazione <= ?
                ORDER BY c.id_carrello
            ");
            $stmt->bind_param("is", $dettaglio_ordine['id_utente'], $dettaglio_ordine['data_ordine']);
            $stmt->execute();
            $result = $stmt->get_result();
        }

        while ($row = $result->fetch_assoc()) {
            $prodotti_ordine[] = $row;
        }
        $stmt->close();
    }

    // Log modifiche ordine
    $log_ordine = [];
    $stmt = $conn->prepare("
        SELECT 
            ol.*,
            a.livello_admin,
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
    </style>
</head>
<body>
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h2">
                <i class="bi bi-cart-check"></i> Gestione Ordini
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Gestione Ordini</li>
                </ol>
            </nav>
        </div>
        <div class="col-md-4 text-end">
            <?php if ($stats['carrelli_per_stato']['attivo']['count'] ?? 0 > 0): ?>
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
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">In Elaborazione</h5>
                    <p class="card-text display-6"><?php echo $stats['in_elaborazione']; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Ordini Oggi</h5>
                    <p class="card-text display-6"><?php echo $stats['ordini_oggi']; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Fatturato Mese</h5>
                    <p class="card-text display-6">€<?php echo number_format($stats['fatturato_mese'], 2); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Carrelli Attivi</h5>
                    <p class="card-text display-6">
                        <?php echo $stats['carrelli_per_stato']['attivo']['count'] ?? 0; ?>
                    </p>
                    <small class="text-muted">
                        €<?php echo number_format($stats['carrelli_per_stato']['attivo']['totale'] ?? 0, 2); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistiche Carrelli Dettagliate -->
    <?php if (!empty($stats['carrelli_per_stato'])): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
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
        <div class="card mb-4">
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
                <?php endif; ?>

                <!-- Log Modifiche -->
                <?php if (!empty($log_ordine)): ?>
                    <h5 class="mt-4">Cronologia Modifiche</h5>
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
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <!-- Filtri -->
        <div class="card mb-4">
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
        <div class="card">
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
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>