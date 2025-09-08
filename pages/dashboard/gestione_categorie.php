<?php
// pages/dashboard/gestione_categorie.php
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

$success_message = '';
$error_message = '';

// Gestione azioni
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'crea':
            $nome_categoria = trim($_POST['nome_categoria'] ?? '');
            $tipo_oggetto = trim($_POST['tipo_oggetto'] ?? '');

            if (empty($nome_categoria)) {
                $error_message = "Il nome della categoria è obbligatorio";
            } else {
                try {
                    // Controlla se esiste già
                    $check_stmt = $conn->prepare("SELECT id_categoria FROM categoria_oggetto WHERE nome_categoria = ?");
                    $check_stmt->bind_param("s", $nome_categoria);
                    $check_stmt->execute();
                    $exists = $check_stmt->get_result()->num_rows > 0;
                    $check_stmt->close();

                    if ($exists) {
                        $error_message = "Una categoria con questo nome esiste già";
                    } else {
                        $stmt = $conn->prepare("INSERT INTO categoria_oggetto (nome_categoria, tipo_oggetto) VALUES (?, ?)");
                        $stmt->bind_param("ss", $nome_categoria, $tipo_oggetto);

                        if ($stmt->execute()) {
                            $success_message = "Categoria creata con successo!";
                        } else {
                            $error_message = "Errore durante la creazione della categoria";
                        }
                        $stmt->close();
                    }
                } catch (Exception $e) {
                    $error_message = "Errore: " . $e->getMessage();
                }
            }
            break;

        case 'modifica':
            $id = (int)($_POST['id'] ?? 0);
            $nome_categoria = trim($_POST['nome_categoria'] ?? '');
            $tipo_oggetto = trim($_POST['tipo_oggetto'] ?? '');

            if (empty($nome_categoria) || $id <= 0) {
                $error_message = "Dati non validi per la modifica";
            } else {
                try {
                    $stmt = $conn->prepare("UPDATE categoria_oggetto SET nome_categoria = ?, tipo_oggetto = ? WHERE id_categoria = ?");
                    $stmt->bind_param("ssi", $nome_categoria, $tipo_oggetto, $id);

                    if ($stmt->execute()) {
                        $success_message = "Categoria aggiornata con successo!";
                    } else {
                        $error_message = "Errore durante l'aggiornamento della categoria";
                    }
                    $stmt->close();
                } catch (Exception $e) {
                    $error_message = "Errore: " . $e->getMessage();
                }
            }
            break;

        case 'elimina':
            $id = (int)($_POST['id'] ?? 0);

            if ($id <= 0) {
                $error_message = "ID categoria non valido";
            } else {
                try {
                    // Controlla se ci sono prodotti associati
                    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM oggetto WHERE fk_categoria = ?");
                    $check_stmt->bind_param("i", $id);
                    $check_stmt->execute();
                    $result = $check_stmt->get_result();
                    $count = $result->fetch_assoc()['count'];
                    $check_stmt->close();

                    if ($count > 0) {
                        $error_message = "Impossibile eliminare la categoria: contiene $count prodotti";
                    } else {
                        $stmt = $conn->prepare("DELETE FROM categoria_oggetto WHERE id_categoria = ?");
                        $stmt->bind_param("i", $id);

                        if ($stmt->execute()) {
                            $success_message = "Categoria eliminata con successo!";
                        } else {
                            $error_message = "Errore durante l'eliminazione della categoria";
                        }
                        $stmt->close();
                    }
                } catch (Exception $e) {
                    $error_message = "Errore: " . $e->getMessage();
                }
            }
            break;
    }
}

// Recupera tutte le categorie con statistiche
$categorie = [];
$sql = "
    SELECT 
        c.*,
        COUNT(o.id_oggetto) as prodotti_count
    FROM categoria_oggetto c
    LEFT JOIN oggetto o ON c.id_categoria = o.fk_categoria_oggetto
    GROUP BY c.id_categoria, c.nome_categoria, c.tipo_oggetto
    ORDER BY c.nome_categoria ASC
";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categorie[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestione Categorie - Box Omnia Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        .sidebar {
            background: linear-gradient(180deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: calc(100vh - 56px);
        }
        .category-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        .category-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            color: white;
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

        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="bi bi-tags me-2"></i>
                    Gestione Categorie
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuovaCategoriaModal">
                        <i class="bi bi-plus-circle"></i> Nuova Categoria
                    </button>
                </div>
            </div>

            <!-- Messaggi di feedback -->
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Lista categorie -->
            <div class="row g-4">
                <?php if (empty($categorie)): ?>
                    <div class="col-12">
                        <div class="text-center py-5">
                            <i class="bi bi-tags display-1 text-muted"></i>
                            <h3 class="mt-3 text-muted">Nessuna categoria creata</h3>
                            <p class="text-muted">Crea la prima categoria per organizzare i tuoi prodotti.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nuovaCategoriaModal">
                                <i class="bi bi-plus-circle"></i> Crea Prima Categoria
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($categorie as $categoria): ?>
                        <div class="col-sm-6 col-lg-4">
                            <div class="card category-card h-100">
                                <div class="card-body text-center">
                                    <div class="category-icon" style="background-color: #6c757d;">
                                        <i class="bi bi-tags"></i>
                                    </div>
                                    <h5 class="card-title"><?php echo htmlspecialchars($categoria['nome_categoria']); ?></h5>
                                    <p class="card-text text-muted">
                                        Tipo: <?php echo $categoria['tipo_oggetto'] ? htmlspecialchars($categoria['tipo_oggetto']) : 'Non specificato'; ?>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <small class="text-muted">
                                            <i class="bi bi-box"></i> <?php echo $categoria['prodotti_count']; ?> prodotti
                                        </small>
                                        <small class="text-muted">ID: <?php echo $categoria['id_categoria']; ?></small>
                                    </div>
                                    <div class="btn-group w-100" role="group">
                                        <button type="button" class="btn btn-outline-primary btn-sm"
                                                onclick="editCategoria(<?php echo $categoria['id_categoria']; ?>, '<?php echo htmlspecialchars(addslashes($categoria['nome_categoria'])); ?>', '<?php echo htmlspecialchars(addslashes($categoria['tipo_oggetto'])); ?>')">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php if ($categoria['prodotti_count'] == 0): ?>
                                            <button type="button" class="btn btn-outline-danger btn-sm"
                                                    onclick="deleteCategoria(<?php echo $categoria['id_categoria']; ?>, '<?php echo htmlspecialchars(addslashes($categoria['nome_categoria'])); ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-outline-secondary btn-sm" disabled
                                                    title="Non puoi eliminare una categoria con prodotti associati">
                                                <i class="bi bi-lock"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<!-- Modal Nuova Categoria -->
<div class="modal fade" id="nuovaCategoriaModal" tabindex="-1" aria-labelledby="nuovaCategoriaModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="crea">
                <div class="modal-header">
                    <h5 class="modal-title" id="nuovaCategoriaModalLabel">
                        <i class="bi bi-plus-circle me-2"></i>Nuova Categoria
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="nome_categoria" class="form-label">Nome Categoria *</label>
                        <input type="text" class="form-control" id="nome_categoria" name="nome_categoria" required maxlength="50"
                               placeholder="es. Elettronica, Abbigliamento...">
                    </div>
                    <div class="mb-3">
                        <label for="tipo_oggetto" class="form-label">Tipo Oggetto</label>
                        <input type="text" class="form-control" id="tipo_oggetto" name="tipo_oggetto" maxlength="100"
                               placeholder="es. Dispositivi, Accessori, Gadget...">
                    </div>
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i>
                            La categoria sarà utilizzata per organizzare i prodotti nel sistema.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Crea Categoria
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modifica Categoria -->
<div class="modal fade" id="modificaCategoriaModal" tabindex="-1" aria-labelledby="modificaCategoriaModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="modificaCategoriaForm">
                <input type="hidden" name="action" value="modifica">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="modificaCategoriaModalLabel">
                        <i class="bi bi-pencil me-2"></i>Modifica Categoria
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_nome_categoria" class="form-label">Nome Categoria *</label>
                        <input type="text" class="form-control" id="edit_nome_categoria" name="nome_categoria" required maxlength="50">
                    </div>
                    <div class="mb-3">
                        <label for="edit_tipo_oggetto" class="form-label">Tipo Oggetto</label>
                        <input type="text" class="form-control" id="edit_tipo_oggetto" name="tipo_oggetto" maxlength="100">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Salva Modifiche
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Form nascosto per eliminazione -->
<form method="POST" id="deleteCategoriaForm" style="display: none;">
    <input type="hidden" name="action" value="elimina">
    <input type="hidden" name="id" id="delete_id">
</form>

<!-- Footer -->
<footer class="footer mt-auto py-3 bg-light">
    <div class="container-fluid">
        <span class="text-muted">&copy; <?php echo date('Y'); ?> BOX OMNIA - Dashboard Amministrativa</span>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function editCategoria(id, nome_categoria, tipo_oggetto) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_nome_categoria').value = nome_categoria;
        document.getElementById('edit_tipo_oggetto').value = tipo_oggetto;

        const modal = new bootstrap.Modal(document.getElementById('modificaCategoriaModal'));
        modal.show();
    }

    function deleteCategoria(id, nome) {
        if (confirm(`Sei sicuro di voler eliminare la categoria "${nome}"?\n\nQuesta azione non può essere annullata.`)) {
            document.getElementById('delete_id').value = id;
            document.getElementById('deleteCategoriaForm').submit();
        }
    }

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