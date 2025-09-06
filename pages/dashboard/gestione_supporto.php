<?php
// pages/dashboard/gestione_supporto.php
require_once __DIR__ . '/admin_check.php';
require_once __DIR__ . '/../../include/config.inc.php';

$adminName = SessionManager::get('user_nome', 'Admin');
$adminId = SessionManager::getUserId();

// Connessione database
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

// Gestione invio risposta
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'rispondi') {
    $richiesta_id = (int)$_POST['richiesta_id'];
    $messaggio = trim($_POST['messaggio']);
    
    if (!empty($messaggio) && $richiesta_id > 0) {
        try {
            $conn->begin_transaction();
            
            // Inserisci risposta
            $stmt = $conn->prepare("
                INSERT INTO risposte_supporto (fk_richiesta, fk_admin, messaggio) 
                VALUES (?, ?, ?)
            ");
            $stmt->bind_param("iis", $richiesta_id, $adminId, $messaggio);
            $stmt->execute();
            
            // Aggiorna stato richiesta
            $nuovo_stato = $_POST['nuovo_stato'] ?? 'in_corso';
            $stmt2 = $conn->prepare("
                UPDATE richieste_supporto 
                SET stato = ?, data_aggiornamento = NOW() 
                WHERE id_richiesta = ?
            ");
            $stmt2->bind_param("si", $nuovo_stato, $richiesta_id);
            $stmt2->execute();
            
            // Crea notifica per l'utente
            $stmt3 = $conn->prepare("
                INSERT INTO notifiche_utente (fk_utente, fk_richiesta, titolo, messaggio)
                SELECT rs.fk_utente, rs.id_richiesta, 
                       CONCAT('Risposta ricevuta: ', rs.oggetto),
                       CONCAT('Hai ricevuto una risposta alla tua richiesta di supporto da ', ?)
                FROM richieste_supporto rs 
                WHERE rs.id_richiesta = ?
            ");
            $stmt3->bind_param("si", $adminName, $richiesta_id);
            $stmt3->execute();
            
            $conn->commit();
            $success_message = "Risposta inviata con successo!";
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Errore nell'invio della risposta: " . $e->getMessage();
        }
    }
}

// Recupera tutte le richieste con dettagli utente
$richieste = [];
$sql = "
    SELECT 
        rs.id_richiesta,
        rs.oggetto,
        rs.messaggio,
        rs.stato,
        rs.priorita,
        rs.data_creazione,
        rs.data_aggiornamento,
        CONCAT(u.nome, ' ', u.cognome) as cliente_nome,
        u.email as cliente_email,
        u.telefono as cliente_telefono,
        COUNT(resp.id_risposta) as num_risposte,
        MAX(resp.data_risposta) as ultima_risposta
    FROM richieste_supporto rs
    JOIN utente u ON rs.fk_utente = u.id_utente
    LEFT JOIN risposte_supporto resp ON rs.id_richiesta = resp.fk_richiesta
    WHERE u.email NOT LIKE '%@boxomnia.%'
    GROUP BY rs.id_richiesta, rs.oggetto, rs.messaggio, rs.stato, rs.priorita, 
             rs.data_creazione, rs.data_aggiornamento, cliente_nome, cliente_email, cliente_telefono
    ORDER BY 
        CASE rs.stato 
            WHEN 'aperta' THEN 1 
            WHEN 'in_corso' THEN 2 
            WHEN 'chiusa' THEN 3 
        END,
        CASE rs.priorita 
            WHEN 'alta' THEN 1 
            WHEN 'normale' THEN 2 
            WHEN 'bassa' THEN 3 
        END,
        rs.data_creazione DESC
";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $richieste[] = $row;
    }
}

// Funzione per recuperare risposte di una richiesta specifica
function getRisposteRichiesta($conn, $richiesta_id) {
    $risposte = [];
    $stmt = $conn->prepare("
        SELECT 
            r.messaggio,
            r.data_risposta,
            CONCAT(u.nome, ' ', u.cognome) as admin_nome
        FROM risposte_supporto r
        JOIN utente u ON r.fk_admin = u.id_utente
        WHERE r.fk_richiesta = ?
        ORDER BY r.data_risposta ASC
    ");
    $stmt->bind_param("i", $richiesta_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $risposte[] = $row;
    }
    
    return $risposte;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestione Supporto - Box Omnia Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>/css/dashboard.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">

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
                        <a class="nav-link" href="gestione_ordini.php">
                            <i class="bi bi-bag-check"></i> Gestione Ordini
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="#">
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
                    <i class="bi bi-headset me-2"></i>
                    Gestione Supporto Clienti
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="filterByStatus('all')">
                            Tutte (<?php echo count($richieste); ?>)
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-warning" onclick="filterByStatus('aperta')">
                            Aperte (<?php echo count(array_filter($richieste, fn($r) => $r['stato'] === 'aperta')); ?>)
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-info" onclick="filterByStatus('in_corso')">
                            In Corso (<?php echo count(array_filter($richieste, fn($r) => $r['stato'] === 'in_corso')); ?>)
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="filterByStatus('chiusa')">
                            Chiuse (<?php echo count(array_filter($richieste, fn($r) => $r['stato'] === 'chiusa')); ?>)
                        </button>
                    </div>
                </div>
            </div>

            <!-- Messaggi di feedback -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Lista richieste -->
            <div class="support-requests-container">
                <?php if (empty($richieste)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox display-1 text-muted"></i>
                        <h3 class="mt-3 text-muted">Nessuna richiesta di supporto</h3>
                        <p class="text-muted">Quando gli utenti invieranno richieste, appariranno qui.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($richieste as $richiesta): ?>
                        <div class="support-request-card" data-status="<?php echo $richiesta['stato']; ?>">
                            <div class="request-header">
                                <div class="request-info">
                                    <h5 class="request-title">
                                        <?php echo htmlspecialchars($richiesta['oggetto']); ?>
                                        <?php
                                        $badge_class = match($richiesta['priorita']) {
                                            'alta' => 'bg-danger',
                                            'normale' => 'bg-secondary',
                                            'bassa' => 'bg-light text-dark',
                                        };
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?> ms-2">
                                            <?php echo ucfirst($richiesta['priorita']); ?>
                                        </span>
                                    </h5>
                                    <div class="client-info">
                                        <i class="bi bi-person-circle"></i>
                                        <strong><?php echo htmlspecialchars($richiesta['cliente_nome']); ?></strong>
                                        <span class="text-muted">
                                            (<?php echo htmlspecialchars($richiesta['cliente_email']); ?>)
                                        </span>
                                        <?php if ($richiesta['cliente_telefono']): ?>
                                            <span class="text-muted ms-2">
                                                <i class="bi bi-telephone"></i>
                                                <?php echo htmlspecialchars($richiesta['cliente_telefono']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="request-meta">
                                    <?php
                                    $status_class = match($richiesta['stato']) {
                                        'aperta' => 'bg-warning',
                                        'in_corso' => 'bg-info',
                                        'chiusa' => 'bg-success',
                                    };
                                    ?>
                                    <span class="badge <?php echo $status_class; ?> mb-2">
                                        <?php echo ucfirst(str_replace('_', ' ', $richiesta['stato'])); ?>
                                    </span>
                                    <div class="request-dates">
                                        <small class="text-muted d-block">
                                            <i class="bi bi-calendar"></i>
                                            Creata: <?php echo date('d/m/Y H:i', strtotime($richiesta['data_creazione'])); ?>
                                        </small>
                                        <?php if ($richiesta['ultima_risposta']): ?>
                                            <small class="text-muted d-block">
                                                <i class="bi bi-reply"></i>
                                                Ultima risposta: <?php echo date('d/m/Y H:i', strtotime($richiesta['ultima_risposta'])); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="request-content">
                                <div class="original-message">
                                    <h6><i class="bi bi-chat-dots"></i> Messaggio originale:</h6>
                                    <p><?php echo nl2br(htmlspecialchars($richiesta['messaggio'])); ?></p>
                                </div>

                                <?php
                                $risposte = getRisposteRichiesta($conn, $richiesta['id_richiesta']);
                                if (!empty($risposte)):
                                ?>
                                    <div class="responses-history">
                                        <h6><i class="bi bi-clock-history"></i> Cronologia risposte:</h6>
                                        <?php foreach ($risposte as $risposta): ?>
                                            <div class="admin-response">
                                                <div class="response-meta">
                                                    <strong><?php echo htmlspecialchars($risposta['admin_nome']); ?></strong>
                                                    <small class="text-muted">
                                                        - <?php echo date('d/m/Y H:i', strtotime($risposta['data_risposta'])); ?>
                                                    </small>
                                                </div>
                                                <p><?php echo nl2br(htmlspecialchars($risposta['messaggio'])); ?></p>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($richiesta['stato'] !== 'chiusa'): ?>
                                    <div class="response-form">
                                        <form method="POST" class="mt-3">
                                            <input type="hidden" name="action" value="rispondi">
                                            <input type="hidden" name="richiesta_id" value="<?php echo $richiesta['id_richiesta']; ?>">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">
                                                    <i class="bi bi-reply"></i> Scrivi risposta:
                                                </label>
                                                <textarea name="messaggio" class="form-control" rows="4" 
                                                        placeholder="Scrivi la tua risposta al cliente..." required></textarea>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label class="form-label">Cambia stato:</label>
                                                    <select name="nuovo_stato" class="form-select">
                                                        <option value="in_corso" <?php echo $richiesta['stato'] === 'in_corso' ? 'selected' : ''; ?>>
                                                            In Corso
                                                        </option>
                                                        <option value="chiusa">Chiudi richiesta</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6 d-flex align-items-end">
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="bi bi-send"></i> Invia Risposta
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-success mt-3">
                                        <i class="bi bi-check-circle"></i> Richiesta chiusa
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function filterByStatus(status) {
    const cards = document.querySelectorAll('.support-request-card');
    
    cards.forEach(card => {
        if (status === 'all' || card.dataset.status === status) {
            card.style.display = 'block';
            card.style.opacity = '1';
        } else {
            card.style.opacity = '0';
            setTimeout(() => {
                if (card.style.opacity === '0') {
                    card.style.display = 'none';
                }
            }, 300);
        }
    });
    
    // Aggiorna pulsanti attivi
    document.querySelectorAll('.btn-toolbar .btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
}

// Auto-refresh ogni 30 secondi per nuove richieste
setInterval(() => {
    if (!document.hidden) {
        window.location.reload();
    }
}, 30000);

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

<?php $conn->close(); ?>