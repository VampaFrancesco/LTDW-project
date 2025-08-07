<?php
// gestione_ordini.php
require_once __DIR__ . '/admin_check.php';
require_once __DIR__ . '/../../include/config.inc.php';

$adminName = SessionManager::get('user_nome', 'Admin');

// Connessione database
$db_config = $config['dbms']['localhost'];
$conn = new mysqli($db_config['host'], $db_config['user'], $db_config['passwd'], $db_config['dbname']);

if ($conn->connect_error) {
    die("Errore di connessione: " . $conn->connect_error);
}

// Gestione azioni
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
$orderId = $_POST['order_id'] ?? $_GET['id'] ?? null;

// Recupera messaggi flash
$flash_message = SessionManager::getFlashMessage();

// Azioni POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_order') {
    $newStatus = $_POST['new_status'] ?? '';
    $tracking = $_POST['tracking'] ?? '';

    if ($orderId && $newStatus !== '') {
        $stmt = $conn->prepare("UPDATE ordine SET stato_ordine = ?, tracking = ? WHERE id_ordine = ?");
        $stmt->bind_param("isi", $newStatus, $tracking, $orderId);

        if ($stmt->execute()) {
            SessionManager::setFlashMessage('Ordine aggiornato con successo!', 'success');
        } else {
            SessionManager::setFlashMessage('Errore nell\'aggiornamento dell\'ordine', 'danger');
        }
        $stmt->close();
    }

    header('Location: gestione_ordini.php');
    exit();
}

// Recupera tutti gli ordini
$ordini = [];
$query = "
    SELECT 
        o.id_ordine,
        o.data_ordine,
        o.tracking,
        o.stato_ordine,
        CONCAT(u.nome, ' ', u.cognome) as cliente_nome,
        u.email as cliente_email,
        c.totale,
        CONCAT(i.via, ' ', i.civico, ', ', i.citta) as indirizzo,
        CASE 
            WHEN o.stato_ordine = 0 THEN 'In elaborazione'
            WHEN o.stato_ordine = 1 THEN 'Spedito'
            WHEN o.stato_ordine = 2 THEN 'Consegnato'
            WHEN o.stato_ordine = 3 THEN 'Annullato'
            ELSE 'Sconosciuto'
        END as stato_nome
    FROM ordine o
    JOIN utente u ON o.fk_utente = u.id_utente
    JOIN carrello c ON o.fk_carrello = c.id_carrello
    JOIN indirizzo_spedizione i ON o.fk_indirizzo = i.id_indirizzo
    ORDER BY o.data_ordine DESC
";

$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $ordini[] = $row;
}

// Dettaglio ordine se richiesto
$dettaglio_ordine = null;
if ($action === 'detail' && $orderId) {
    $stmt = $conn->prepare("
        SELECT 
            o.*,
            CONCAT(u.nome, ' ', u.cognome) as cliente_nome,
            u.email as cliente_email,
            u.id_utente,
            c.totale,
            CONCAT(i.via, ' ', i.civico, ', ', i.cap, ' ', i.citta, ' (', i.provincia, ')') as indirizzo_completo
        FROM ordine o
        JOIN utente u ON o.fk_utente = u.id_utente
        JOIN carrello c ON o.fk_carrello = c.id_carrello
        JOIN indirizzo_spedizione i ON o.fk_indirizzo = i.id_indirizzo
        WHERE o.id_ordine = ?
    ");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $dettaglio_ordine = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestione Ordini - Box Omnia Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">

<!-- Header -->
<header class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">
            <i class="bi bi-speedometer2"></i> Dashboard Admin
        </a>
        <div class="navbar-nav ms-auto">
            <span class="nav-link"><i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($adminName); ?></span>
            <a class="nav-link" href="<?php echo BASE_URL; ?>/pages/auth/logout.php">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>
</header>

<div class="container-fluid flex-grow-1">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-12 col-md-3 col-lg-2 d-md-block bg-light sidebar">
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
                        <a class="nav-link active" href="#">
                            <i class="bi bi-cart"></i> Gestione Ordini
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="col-12 col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="bi bi-cart"></i> Gestione Ordini
                    <?php if ($action === 'detail'): ?>
                        - Dettaglio #<?php echo str_pad($orderId, 6, '0', STR_PAD_LEFT); ?>
                    <?php endif; ?>
                </h1>
                <?php if ($action === 'detail'): ?>
                    <a href="gestione_ordini.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Torna alla Lista
                    </a>
                <?php endif; ?>
            </div>

            <!-- Messaggi flash -->
            <?php if ($flash_message): ?>
                <div class="alert alert-<?php echo htmlspecialchars($flash_message['type']); ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($flash_message['content']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($action === 'detail' && $dettaglio_ordine): ?>
                <!-- Dettaglio Ordine -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Dettagli Ordine #<?php echo str_pad($dettaglio_ordine['id_ordine'], 6, '0', STR_PAD_LEFT); ?></h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Informazioni Cliente</h6>
                                        <p><strong>Nome:</strong> <?php echo htmlspecialchars($dettaglio_ordine['cliente_nome']); ?></p>
                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($dettaglio_ordine['cliente_email']); ?></p>
                                        <p><strong>Indirizzo:</strong><br><?php echo htmlspecialchars($dettaglio_ordine['indirizzo_completo']); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Dettagli Ordine</h6>
                                        <p><strong>Data:</strong> <?php echo date('d/m/Y H:i', strtotime($dettaglio_ordine['data_ordine'])); ?></p>
                                        <p><strong>Totale:</strong> €<?php echo number_format($dettaglio_ordine['totale'], 2); ?></p>
                                        <p><strong>Stato:</strong>
                                            <?php
                                            $badge_class = 'bg-secondary';
                                            switch ($dettaglio_ordine['stato_ordine']) {
                                                case 0: $badge_class = 'bg-warning'; break;
                                                case 1: $badge_class = 'bg-primary'; break;
                                                case 2: $badge_class = 'bg-success'; break;
                                                case 3: $badge_class = 'bg-danger'; break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>">
                                                <?php
                                                switch ($dettaglio_ordine['stato_ordine']) {
                                                    case 0: echo 'In elaborazione'; break;
                                                    case 1: echo 'Spedito'; break;
                                                    case 2: echo 'Consegnato'; break;
                                                    case 3: echo 'Annullato'; break;
                                                    default: echo 'Sconosciuto';
                                                }
                                                ?>
                                            </span>
                                        </p>
                                        <p><strong>Tracking:</strong>
                                            <?php echo $dettaglio_ordine['tracking'] ? htmlspecialchars($dettaglio_ordine['tracking']) : '<span class="text-muted">Non assegnato</span>'; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <!-- Form Aggiorna Ordine -->
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Aggiorna Ordine</h6>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_order">
                                    <input type="hidden" name="order_id" value="<?php echo $dettaglio_ordine['id_ordine']; ?>">

                                    <div class="mb-3">
                                        <label for="new_status" class="form-label">Stato Ordine</label>
                                        <select class="form-select" id="new_status" name="new_status" required>
                                            <option value="0" <?php echo $dettaglio_ordine['stato_ordine'] == 0 ? 'selected' : ''; ?>>In elaborazione</option>
                                            <option value="1" <?php echo $dettaglio_ordine['stato_ordine'] == 1 ? 'selected' : ''; ?>>Spedito</option>
                                            <option value="2" <?php echo $dettaglio_ordine['stato_ordine'] == 2 ? 'selected' : ''; ?>>Consegnato</option>
                                            <option value="3" <?php echo $dettaglio_ordine['stato_ordine'] == 3 ? 'selected' : ''; ?>>Annullato</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="tracking" class="form-label">Codice Tracking</label>
                                        <input type="text"
                                               class="form-control"
                                               id="tracking"
                                               name="tracking"
                                               value="<?php echo htmlspecialchars($dettaglio_ordine['tracking'] ?? ''); ?>"
                                               placeholder="Es: IT123456789">
                                    </div>

                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-check-circle"></i> Aggiorna Ordine
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- Lista Ordini -->
                <div class="row mb-3">
                    <div class="col">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Statistiche Ordini</h5>
                                <div class="row text-center">
                                    <div class="col-md-3">
                                        <h3 class="text-warning"><?php echo count(array_filter($ordini, fn($o) => $o['stato_ordine'] == 0)); ?></h3>
                                        <p class="mb-0">In Elaborazione</p>
                                    </div>
                                    <div class="col-md-3">
                                        <h3 class="text-primary"><?php echo count(array_filter($ordini, fn($o) => $o['stato_ordine'] == 1)); ?></h3>
                                        <p class="mb-0">Spediti</p>
                                    </div>
                                    <div class="col-md-3">
                                        <h3 class="text-success"><?php echo count(array_filter($ordini, fn($o) => $o['stato_ordine'] == 2)); ?></h3>
                                        <p class="mb-0">Consegnati</p>
                                    </div>
                                    <div class="col-md-3">
                                        <h3 class="text-danger"><?php echo count(array_filter($ordini, fn($o) => $o['stato_ordine'] == 3)); ?></h3>
                                        <p class="mb-0">Annullati</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Lista Ordini (<?php echo count($ordini); ?> totali)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                <tr>
                                    <th>#Ordine</th>
                                    <th>Cliente</th>
                                    <th>Data</th>
                                    <th>Totale</th>
                                    <th>Stato</th>
                                    <th>Tracking</th>
                                    <th>Azioni</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($ordini)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">Nessun ordine trovato</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($ordini as $ordine): ?>
                                        <tr>
                                            <td>#<?php echo str_pad($ordine['id_ordine'], 6, '0', STR_PAD_LEFT); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($ordine['cliente_nome']); ?><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($ordine['cliente_email']); ?></small>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($ordine['data_ordine'])); ?></td>
                                            <td>€<?php echo number_format($ordine['totale'], 2); ?></td>
                                            <td>
                                                <?php
                                                $badge_class = 'bg-secondary';
                                                switch ($ordine['stato_ordine']) {
                                                    case 0: $badge_class = 'bg-warning'; break;
                                                    case 1: $badge_class = 'bg-primary'; break;
                                                    case 2: $badge_class = 'bg-success'; break;
                                                    case 3: $badge_class = 'bg-danger'; break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?>">
                                                    <?php echo htmlspecialchars($ordine['stato_nome']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($ordine['tracking']): ?>
                                                    <code><?php echo htmlspecialchars($ordine['tracking']); ?></code>
                                                <?php else: ?>
                                                    <span class="text-muted">Non assegnato</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="gestione_ordini.php?action=detail&id=<?php echo $ordine['id_ordine']; ?>"
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i> Dettagli
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>