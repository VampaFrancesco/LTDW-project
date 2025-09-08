<?php
// gestione_utenti.php
require_once __DIR__ . '/admin_check.php';
require_once __DIR__ . '/../../include/config.inc.php';

$adminName = SessionManager::get('user_nome', 'Admin');
$currentUserId = SessionManager::get('user_id');
$currentUserLevel = SessionManager::get('user_livello_admin', 'admin');

// Connessione database
$db_config = $config['dbms']['localhost'];
$conn = new mysqli($db_config['host'], $db_config['user'], $db_config['passwd'], $db_config['dbname']);

if ($conn->connect_error) {
    die("Errore di connessione: " . $conn->connect_error);
}

// Gestione azioni
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
$userId = $_POST['user_id'] ?? $_GET['id'] ?? null;

// Recupera messaggi flash
$flash_message = SessionManager::getFlashMessage();

// CRUD Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'toggle_admin':
            // Solo super admin può modificare ruoli admin
            if ($currentUserLevel === 'super_admin' && $userId && $userId != $currentUserId) {
                // Verifica se l'utente è già admin
                $checkStmt = $conn->prepare("SELECT id_admin FROM admin WHERE fk_utente = ?");
                $checkStmt->bind_param("i", $userId);
                $checkStmt->execute();
                $isAdmin = $checkStmt->get_result()->num_rows > 0;
                $checkStmt->close();

                if ($isAdmin) {
                    // Rimuovi admin
                    $stmt = $conn->prepare("DELETE FROM admin WHERE fk_utente = ?");
                    $stmt->bind_param("i", $userId);
                    if ($stmt->execute()) {
                        SessionManager::setFlashMessage('Privilegi admin rimossi con successo!', 'success');
                    } else {
                        SessionManager::setFlashMessage('Errore nella rimozione dei privilegi admin', 'danger');
                    }
                } else {
                    // Aggiungi admin
                    $stmt = $conn->prepare("INSERT INTO admin (fk_utente, livello_admin, creato_da) VALUES (?, 'admin', ?)");
                    $stmt->bind_param("ii", $userId, $currentUserId);
                    if ($stmt->execute()) {
                        SessionManager::setFlashMessage('Privilegi admin assegnati con successo!', 'success');
                    } else {
                        SessionManager::setFlashMessage('Errore nell\'assegnazione dei privilegi admin', 'danger');
                    }
                }
                $stmt->close();
            }
            break;

        case 'delete_user':
            // Solo super admin può eliminare utenti (tranne se stesso)
            if ($currentUserLevel === 'super_admin' && $userId && $userId != $currentUserId) {
                $stmt = $conn->prepare("DELETE FROM utente WHERE id_utente = ?");
                $stmt->bind_param("i", $userId);

                if ($stmt->execute()) {
                    SessionManager::setFlashMessage('Utente eliminato con successo!', 'success');
                } else {
                    SessionManager::setFlashMessage('Errore nell\'eliminazione dell\'utente', 'danger');
                }
                $stmt->close();
            }
            break;

        case 'update_user':
            if ($userId) {
                $nome = $_POST['nome'] ?? '';
                $cognome = $_POST['cognome'] ?? '';
                $email = $_POST['email'] ?? '';

                if ($nome && $cognome && $email) {
                    $stmt = $conn->prepare("UPDATE utente SET nome = ?, cognome = ?, email = ? WHERE id_utente = ?");
                    $stmt->bind_param("sssi", $nome, $cognome, $email, $userId);

                    if ($stmt->execute()) {
                        SessionManager::setFlashMessage('Utente aggiornato con successo!', 'success');
                    } else {
                        SessionManager::setFlashMessage('Errore nell\'aggiornamento dell\'utente', 'danger');
                    }
                    $stmt->close();
                }
            }
            break;
    }

    header('Location: gestione_utenti.php');
    exit();
}

// Recupera tutti gli utenti con informazioni admin
$utenti = [];
$query = "
    SELECT u.*, 
           a.id_admin,
           a.livello_admin,
           a.data_creazione as admin_da,
           CASE WHEN a.id_admin IS NOT NULL THEN 'Admin' ELSE 'Utente' END as tipo_utente,
           creator.nome as creato_da_nome
    FROM utente u 
    LEFT JOIN admin a ON u.id_utente = a.fk_utente 
    LEFT JOIN utente creator ON a.creato_da = creator.id_utente
    ORDER BY a.id_admin DESC, u.id_utente DESC
";

$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $utenti[] = $row;
}

// Dettaglio utente per edit
$edit_user = null;
if ($action === 'edit' && $userId) {
    $stmt = $conn->prepare("SELECT * FROM utente WHERE id_utente = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $edit_user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Statistiche
$stats = [
    'totale_utenti' => count($utenti),
    'admin_totali' => count(array_filter($utenti, fn($u) => $u['tipo_utente'] === 'Admin')),
    'utenti_normali' => count(array_filter($utenti, fn($u) => $u['tipo_utente'] === 'Utente')),
    'registrazioni_oggi' => count(array_filter($utenti, fn($u) => date('Y-m-d', strtotime($u['admin_da'] ?? $u['id_utente'])) === date('Y-m-d')))
];

$conn->close();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestione Utenti - Box Omnia Admin</title>
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
            <span class="nav-link">
                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($adminName); ?>
                <span class="badge bg-<?php echo $currentUserLevel === 'super_admin' ? 'danger' : 'warning'; ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $currentUserLevel)); ?>
                </span>
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
                        <a class="nav-link active" href="#">
                            <i class="bi bi-tags"></i> Gestione Categorie
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="gestione_ordini.php">
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


        <!-- Main Content -->
        <main class="col-12 col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="bi bi-people"></i> Gestione Utenti
                    <?php if ($action === 'edit'): ?>
                        - Modifica Utente
                    <?php endif; ?>
                </h1>
                <?php if ($action === 'edit'): ?>
                    <a href="gestione_utenti.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Torna alla Lista
                    </a>
                <?php else: ?>
                    <a href="crea_admin.php" class="btn btn-success">
                        <i class="bi bi-person-plus"></i> Crea Admin
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

            <?php if ($action === 'edit' && $edit_user): ?>
                <!-- Form Modifica Utente -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Modifica Utente: <?php echo htmlspecialchars($edit_user['nome'] . ' ' . $edit_user['cognome']); ?></h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_user">
                                    <input type="hidden" name="user_id" value="<?php echo $edit_user['id_utente']; ?>">

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="nome" class="form-label">Nome *</label>
                                            <input type="text" class="form-control" id="nome" name="nome"
                                                   value="<?php echo htmlspecialchars($edit_user['nome']); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="cognome" class="form-label">Cognome *</label>
                                            <input type="text" class="form-control" id="cognome" name="cognome"
                                                   value="<?php echo htmlspecialchars($edit_user['cognome']); ?>" required>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email *</label>
                                        <input type="email" class="form-control" id="email" name="email"
                                               value="<?php echo htmlspecialchars($edit_user['email']); ?>" required>
                                    </div>

                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle"></i>
                                        <strong>Nota:</strong> La password non può essere modificata da qui. L'utente deve utilizzare il reset password se necessario.
                                    </div>

                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-check-circle"></i> Aggiorna Utente
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Informazioni Utente</h6>
                            </div>
                            <div class="card-body">
                                <p><strong>ID Utente:</strong> <?php echo $edit_user['id_utente']; ?></p>
                                <p><strong>Data Registrazione:</strong> N/A</p>
                                <p><strong>Tipo Account:</strong>
                                    <?php if (str_ends_with($edit_user['email'], '@boxomnia.it')): ?>
                                        <span class="badge bg-danger">Admin Domain</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary">Utente Normale</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- Lista Utenti -->
                <div class="row mb-3">
                    <div class="col">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Statistiche Utenti</h5>
                                <div class="row text-center">
                                    <div class="col-md-3">
                                        <h3 class="text-primary"><?php echo $stats['totale_utenti']; ?></h3>
                                        <p class="mb-0">Totale Utenti</p>
                                    </div>
                                    <div class="col-md-3">
                                        <h3 class="text-danger"><?php echo $stats['admin_totali']; ?></h3>
                                        <p class="mb-0">Amministratori</p>
                                    </div>
                                    <div class="col-md-3">
                                        <h3 class="text-success"><?php echo $stats['utenti_normali']; ?></h3>
                                        <p class="mb-0">Utenti Normali</p>
                                    </div>
                                    <div class="col-md-3">
                                        <h3 class="text-warning"><?php echo $stats['registrazioni_oggi']; ?></h3>
                                        <p class="mb-0">Oggi</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Lista Utenti (<?php echo count($utenti); ?> totali)</h5>
                        <div class="d-flex gap-2">
                            <!-- Filtri -->
                            <select class="form-select form-select-sm" id="filterType" onchange="filterUsers()">
                                <option value="">Tutti gli utenti</option>
                                <option value="Admin">Solo Admin</option>
                                <option value="Utente">Solo Utenti</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="usersTable">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome Completo</th>
                                    <th>Email</th>
                                    <th>Tipo</th>
                                    <th>Livello Admin</th>
                                    <th>Azioni</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($utenti as $utente): ?>
                                    <tr data-user-type="<?php echo $utente['tipo_utente']; ?>">
                                        <td><?php echo $utente['id_utente']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']); ?></strong>
                                            <?php if ($utente['id_utente'] == $currentUserId): ?>
                                                <span class="badge bg-info">Tu</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($utente['email']); ?>
                                            <?php if (str_ends_with($utente['email'], '@boxomnia.it')): ?>
                                                <i class="bi bi-shield-check text-warning" title="Dominio admin"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($utente['tipo_utente'] === 'Admin'): ?>
                                                <span class="badge bg-danger">Admin</span>
                                            <?php else: ?>
                                                <span class="badge bg-primary">Utente</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($utente['livello_admin']): ?>
                                                <span class="badge bg-<?php echo $utente['livello_admin'] === 'super_admin' ? 'danger' : 'warning'; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $utente['livello_admin'])); ?>
                                                </span>
                                                <?php if ($utente['admin_da']): ?>
                                                    <br><small class="text-muted">
                                                        Da: <?php echo date('d/m/Y', strtotime($utente['admin_da'])); ?>
                                                    </small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="gestione_utenti.php?action=edit&id=<?php echo $utente['id_utente']; ?>"
                                                   class="btn btn-outline-primary" title="Modifica">
                                                    <i class="bi bi-pencil"></i>
                                                </a>

                                                <?php if ($currentUserLevel === 'super_admin' && $utente['id_utente'] != $currentUserId): ?>
                                                    <!-- Toggle Admin -->
                                                    <button type="button" class="btn btn-outline-<?php echo $utente['tipo_utente'] === 'Admin' ? 'warning' : 'success'; ?>"
                                                            title="<?php echo $utente['tipo_utente'] === 'Admin' ? 'Rimuovi Admin' : 'Rendi Admin'; ?>"
                                                            onclick="confirmToggleAdmin(<?php echo $utente['id_utente']; ?>, '<?php echo $utente['tipo_utente']; ?>', '<?php echo htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']); ?>')">
                                                        <i class="bi bi-<?php echo $utente['tipo_utente'] === 'Admin' ? 'person-dash' : 'person-plus'; ?>"></i>
                                                    </button>

                                                    <!-- Delete User -->
                                                    <button type="button" class="btn btn-outline-danger" title="Elimina Utente"
                                                            onclick="confirmDeleteUser(<?php echo $utente['id_utente']; ?>, '<?php echo htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']); ?>')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Modal Toggle Admin -->
<div class="modal fade" id="toggleAdminModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="toggleAdminTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="toggleAdminMessage"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="toggle_admin">
                    <input type="hidden" name="user_id" id="toggleAdminUserId">
                    <button type="submit" class="btn btn-primary" id="toggleAdminBtn"></button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Delete User -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Elimina Utente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Sei sicuro di voler eliminare <strong id="deleteUserName"></strong>?</p>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Attenzione!</strong> Questa azione eliminerà definitivamente l'utente e tutti i suoi dati associati.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    <button type="submit" class="btn btn-danger">Elimina Definitivamente</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function confirmToggleAdmin(userId, currentType, userName) {
        document.getElementById('toggleAdminUserId').value = userId;

        if (currentType === 'Admin') {
            document.getElementById('toggleAdminTitle').textContent = 'Rimuovi Privilegi Admin';
            document.getElementById('toggleAdminMessage').innerHTML = `Vuoi rimuovere i privilegi di amministratore da <strong>${userName}</strong>?`;
            document.getElementById('toggleAdminBtn').textContent = 'Rimuovi Admin';
            document.getElementById('toggleAdminBtn').className = 'btn btn-warning';
        } else {
            document.getElementById('toggleAdminTitle').textContent = 'Assegna Privilegi Admin';
            document.getElementById('toggleAdminMessage').innerHTML = `Vuoi rendere <strong>${userName}</strong> un amministratore?`;
            document.getElementById('toggleAdminBtn').textContent = 'Rendi Admin';
            document.getElementById('toggleAdminBtn').className = 'btn btn-success';
        }

        const toggleModal = new bootstrap.Modal(document.getElementById('toggleAdminModal'));
        toggleModal.show();
    }

    function confirmDeleteUser(userId, userName) {
        document.getElementById('deleteUserId').value = userId;
        document.getElementById('deleteUserName').textContent = userName;

        const deleteModal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
        deleteModal.show();
    }

    function filterUsers() {
        const filterType = document.getElementById('filterType').value;
        const rows = document.querySelectorAll('#usersTable tbody tr');

        rows.forEach(row => {
            const userType = row.getAttribute('data-user-type');
            if (filterType === '' || userType === filterType) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
</script>
</body>
</html>