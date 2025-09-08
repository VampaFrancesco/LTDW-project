<?php
// gestione_prodotti.php - CRUD completo per prodotti
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
$productId = $_POST['product_id'] ?? $_GET['id'] ?? null;
$productType = $_POST['product_type'] ?? $_GET['type'] ?? 'oggetto';

// Recupera messaggi flash
$flash_message = SessionManager::getFlashMessage();

// CRUD Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'create_oggetto':
            $nome = $_POST['nome_oggetto'] ?? '';
            $desc = $_POST['desc_oggetto'] ?? '';
            $prezzo = $_POST['prezzo_oggetto'] ?? null;
            $quantita = $_POST['quant_oggetto'] ?? null;
            $categoria = $_POST['fk_categoria_oggetto'] ?? '';
            $rarita = $_POST['fk_rarita'] ?? null;

            if ($nome && $desc && $categoria) {
                $stmt = $conn->prepare("INSERT INTO oggetto (nome_oggetto, desc_oggetto, prezzo_oggetto, quant_oggetto, fk_categoria_oggetto, fk_rarita) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssdiis", $nome, $desc, $prezzo, $quantita, $categoria, $rarita);

                if ($stmt->execute()) {
                    SessionManager::setFlashMessage('Oggetto creato con successo!', 'success');
                } else {
                    SessionManager::setFlashMessage('Errore nella creazione dell\'oggetto', 'danger');
                }
                $stmt->close();
            }
            break;

        case 'create_mystery_box':
            $nome = $_POST['nome_box'] ?? '';
            $desc = $_POST['desc_box'] ?? '';
            $prezzo = $_POST['prezzo_box'] ?? '';
            $quantita = $_POST['quantita_box'] ?? '';
            $categoria = $_POST['fk_categoria_oggetto'] ?? '';
            $rarita = $_POST['fk_rarita'] ?? '';

            if ($nome && $desc && $prezzo && $quantita && $categoria && $rarita) {
                $stmt = $conn->prepare("INSERT INTO mystery_box (nome_box, desc_box, prezzo_box, quantita_box, fk_categoria_oggetto, fk_rarita) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssdiii", $nome, $desc, $prezzo, $quantita, $categoria, $rarita);

                if ($stmt->execute()) {
                    SessionManager::setFlashMessage('Mystery Box creata con successo!', 'success');
                } else {
                    SessionManager::setFlashMessage('Errore nella creazione della Mystery Box', 'danger');
                }
                $stmt->close();
            }
            break;

        case 'update_oggetto':
            if ($productId) {
                $nome = $_POST['nome_oggetto'] ?? '';
                $desc = $_POST['desc_oggetto'] ?? '';
                $prezzo = $_POST['prezzo_oggetto'] ?? null;
                $quantita = $_POST['quant_oggetto'] ?? null;
                $categoria = $_POST['fk_categoria_oggetto'] ?? '';
                $rarita = $_POST['fk_rarita'] ?? null;

                $stmt = $conn->prepare("UPDATE oggetto SET nome_oggetto = ?, desc_oggetto = ?, prezzo_oggetto = ?, quant_oggetto = ?, fk_categoria_oggetto = ?, fk_rarita = ? WHERE id_oggetto = ?");
                $stmt->bind_param("ssdiisi", $nome, $desc, $prezzo, $quantita, $categoria, $rarita, $productId);

                if ($stmt->execute()) {
                    SessionManager::setFlashMessage('Oggetto aggiornato con successo!', 'success');
                } else {
                    SessionManager::setFlashMessage('Errore nell\'aggiornamento dell\'oggetto', 'danger');
                }
                $stmt->close();
            }
            break;

        case 'delete_product':
            if ($productId && $productType) {
                if ($productType === 'oggetto') {
                    $stmt = $conn->prepare("DELETE FROM oggetto WHERE id_oggetto = ?");
                } else {
                    $stmt = $conn->prepare("DELETE FROM mystery_box WHERE id_box = ?");
                }
                $stmt->bind_param("i", $productId);

                if ($stmt->execute()) {
                    SessionManager::setFlashMessage('Prodotto eliminato con successo!', 'success');
                } else {
                    SessionManager::setFlashMessage('Errore nell\'eliminazione del prodotto', 'danger');
                }
                $stmt->close();
            }
            break;
        case 'update_quantity':
            $productType = $_POST['product_type'] ?? '';
            $productId = $_POST['product_id'] ?? '';
            $newQuantity = intval($_POST['new_quantity'] ?? 0);
            $operation = $_POST['operation'] ?? 'set'; // 'set', 'add', 'subtract'

            if ($productId && $productType && $newQuantity >= 0) {
                $conn->begin_transaction();
                try {
                    if ($productType === 'oggetto') {
                        if ($operation === 'set') {
                            $stmt = $conn->prepare("UPDATE oggetto SET quant_oggetto = ? WHERE id_oggetto = ?");
                            $stmt->bind_param("ii", $newQuantity, $productId);
                        } elseif ($operation === 'add') {
                            $stmt = $conn->prepare("UPDATE oggetto SET quant_oggetto = COALESCE(quant_oggetto, 0) + ? WHERE id_oggetto = ?");
                            $stmt->bind_param("ii", $newQuantity, $productId);
                        } elseif ($operation === 'subtract') {
                            $stmt = $conn->prepare("UPDATE oggetto SET quant_oggetto = GREATEST(0, COALESCE(quant_oggetto, 0) - ?) WHERE id_oggetto = ?");
                            $stmt->bind_param("ii", $newQuantity, $productId);
                        }
                    } else { // mystery_box
                        if ($operation === 'set') {
                            $stmt = $conn->prepare("UPDATE mystery_box SET quantita_box = ? WHERE id_box = ?");
                            $stmt->bind_param("ii", $newQuantity, $productId);
                        } elseif ($operation === 'add') {
                            $stmt = $conn->prepare("UPDATE mystery_box SET quantita_box = quantita_box + ? WHERE id_box = ?");
                            $stmt->bind_param("ii", $newQuantity, $productId);
                        } elseif ($operation === 'subtract') {
                            $stmt = $conn->prepare("UPDATE mystery_box SET quantita_box = GREATEST(0, quantita_box - ?) WHERE id_box = ?");
                            $stmt->bind_param("ii", $newQuantity, $productId);
                        }
                    }

                    if ($stmt->execute()) {
                        // Log dell'operazione (opzionale)
                        $logStmt = $conn->prepare("
                            INSERT INTO inventory_log (product_type, product_id, operation_type, quantity_change, admin_id, timestamp) 
                            VALUES (?, ?, ?, ?, ?, NOW())
                        ");
                        if ($logStmt) {
                            $adminId = SessionManager::get('admin_id');
                            $logStmt->bind_param("sisii", $productType, $productId, $operation, $newQuantity, $adminId);
                            $logStmt->execute();
                            $logStmt->close();
                        }

                        $conn->commit();
                        SessionManager::setFlashMessage("Quantità aggiornata con successo!", 'success');
                    } else {
                        throw new Exception("Errore nell'aggiornamento della quantità");
                    }
                    $stmt->close();
                } catch (Exception $e) {
                    $conn->rollback();
                    SessionManager::setFlashMessage('Errore: ' . $e->getMessage(), 'danger');
                }
            }
            break;
    }

    header('Location: gestione_prodotti.php');
    exit();
}

// Recupera dati per i form
$categorie = [];
$result = $conn->query("SELECT * FROM categoria_oggetto ORDER BY nome_categoria");
while ($row = $result->fetch_assoc()) {
    $categorie[] = $row;
}

$rarita = [];
$result = $conn->query("SELECT * FROM rarita ORDER BY ordine");
while ($row = $result->fetch_assoc()) {
    $rarita[] = $row;
}

// Recupera prodotti
$oggetti = [];
$mystery_boxes = [];

$result = $conn->query("
    SELECT o.*, c.nome_categoria, c.tipo_oggetto, r.nome_rarita 
    FROM oggetto o 
    LEFT JOIN categoria_oggetto c ON o.fk_categoria_oggetto = c.id_categoria
    LEFT JOIN rarita r ON o.fk_rarita = r.id_rarita
    ORDER BY o.id_oggetto DESC
");
while ($row = $result->fetch_assoc()) {
    $oggetti[] = $row;
}

$result = $conn->query("
    SELECT mb.*, c.nome_categoria, c.tipo_oggetto, r.nome_rarita 
    FROM mystery_box mb 
    LEFT JOIN categoria_oggetto c ON mb.fk_categoria_oggetto = c.id_categoria
    LEFT JOIN rarita r ON mb.fk_rarita = r.id_rarita
    ORDER BY mb.id_box DESC
");
while ($row = $result->fetch_assoc()) {
    $mystery_boxes[] = $row;
}

// Recupera prodotto per edit
$edit_product = null;
if ($action === 'edit' && $productId && $productType) {
    if ($productType === 'oggetto') {
        $stmt = $conn->prepare("SELECT * FROM oggetto WHERE id_oggetto = ?");
    } else {
        $stmt = $conn->prepare("SELECT * FROM mystery_box WHERE id_box = ?");
    }
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $edit_product = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestione Prodotti - Box Omnia Admin</title>
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
                    <i class="bi bi-box"></i> Gestione Prodotti
                    <?php if ($action === 'create'): ?>
                        - Crea Nuovo
                    <?php elseif ($action === 'edit'): ?>
                        - Modifica
                    <?php endif; ?>
                </h1>
                <?php if ($action !== 'list'): ?>
                    <a href="gestione_prodotti.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Torna alla Lista
                    </a>
                <?php else: ?>
                    <div>
                        <a href="gestione_prodotti.php?action=create&type=oggetto" class="btn btn-success me-2">
                            <i class="bi bi-plus-circle"></i> Nuovo Oggetto
                        </a>
                        <a href="gestione_prodotti.php?action=create&type=mystery_box" class="btn btn-primary">
                            <i class="bi bi-box"></i> Nuova Mystery Box
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Messaggi flash -->
            <?php if ($flash_message): ?>
                <div class="alert alert-<?php echo htmlspecialchars($flash_message['type']); ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($flash_message['content']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($action === 'create'): ?>
                <!-- Form Creazione -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <?php echo $productType === 'oggetto' ? 'Crea Nuovo Oggetto' : 'Crea Nuova Mystery Box'; ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="create_<?php echo $productType; ?>">

                                    <?php if ($productType === 'oggetto'): ?>
                                        <div class="mb-3">
                                            <label for="nome_oggetto" class="form-label">Nome Oggetto *</label>
                                            <input type="text" class="form-control" id="nome_oggetto" name="nome_oggetto" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="desc_oggetto" class="form-label">Descrizione *</label>
                                            <textarea class="form-control" id="desc_oggetto" name="desc_oggetto" rows="3" required></textarea>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="prezzo_oggetto" class="form-label">Prezzo (€)</label>
                                                <input type="number" step="0.01" class="form-control" id="prezzo_oggetto" name="prezzo_oggetto">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="quant_oggetto" class="form-label">Quantità</label>
                                                <input type="number" class="form-control" id="quant_oggetto" name="quant_oggetto">
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="mb-3">
                                            <label for="nome_box" class="form-label">Nome Mystery Box *</label>
                                            <input type="text" class="form-control" id="nome_box" name="nome_box" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="desc_box" class="form-label">Descrizione *</label>
                                            <textarea class="form-control" id="desc_box" name="desc_box" rows="3" required></textarea>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="prezzo_box" class="form-label">Prezzo (€) *</label>
                                                <input type="number" step="0.01" class="form-control" id="prezzo_box" name="prezzo_box" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="quantita_box" class="form-label">Quantità *</label>
                                                <input type="number" class="form-control" id="quantita_box" name="quantita_box" required>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="fk_categoria_oggetto" class="form-label">Categoria *</label>
                                            <select class="form-select" id="fk_categoria_oggetto" name="fk_categoria_oggetto" required>
                                                <option value="">Seleziona categoria</option>
                                                <?php foreach ($categorie as $cat): ?>
                                                    <option value="<?php echo $cat['id_categoria']; ?>">
                                                        <?php echo htmlspecialchars($cat['nome_categoria'] . ' - ' . $cat['tipo_oggetto']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="fk_rarita" class="form-label">Rarità <?php echo $productType === 'mystery_box' ? '*' : ''; ?></label>
                                            <select class="form-select" id="fk_rarita" name="fk_rarita" <?php echo $productType === 'mystery_box' ? 'required' : ''; ?>>
                                                <option value="">Seleziona rarità</option>
                                                <?php foreach ($rarita as $rar): ?>
                                                    <option value="<?php echo $rar['id_rarita']; ?>">
                                                        <?php echo htmlspecialchars($rar['nome_rarita']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="reset" class="btn btn-outline-secondary me-md-2">
                                            <i class="bi bi-arrow-clockwise"></i> Reset
                                        </button>
                                        <button type="submit" class="btn btn-success">
                                            <i class="bi bi-check-circle"></i> Crea Prodotto
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Informazioni</h6>
                            </div>
                            <div class="card-body">
                                <p class="small">
                                    <strong>Campi obbligatori sono contrassegnati con *</strong>
                                </p>

                                <?php if ($productType === 'oggetto'): ?>
                                    <h6>Oggetto</h6>
                                    <ul class="small">
                                        <li>Il prezzo e la quantità sono opzionali</li>
                                        <li>La rarità è opzionale</li>
                                        <li>Utilizzato per carte singole e accessori</li>
                                    </ul>
                                <?php else: ?>
                                    <h6>Mystery Box</h6>
                                    <ul class="small">
                                        <li>Prezzo e quantità sono obbligatori</li>
                                        <li>La rarità determina il tipo di contenuto</li>
                                        <li>Utilizzata per pacchetti sorpresa</li>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($action === 'edit' && $edit_product): ?>
                <!-- Form Modifica -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Modifica <?php echo $productType === 'oggetto' ? 'Oggetto' : 'Mystery Box'; ?></h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_<?php echo $productType; ?>">
                                    <input type="hidden" name="product_id" value="<?php echo $productId; ?>">

                                    <?php if ($productType === 'oggetto'): ?>
                                        <div class="mb-3">
                                            <label for="nome_oggetto" class="form-label">Nome Oggetto *</label>
                                            <input type="text" class="form-control" id="nome_oggetto" name="nome_oggetto"
                                                   value="<?php echo htmlspecialchars($edit_product['nome_oggetto']); ?>" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="desc_oggetto" class="form-label">Descrizione *</label>
                                            <textarea class="form-control" id="desc_oggetto" name="desc_oggetto" rows="3" required><?php echo htmlspecialchars($edit_product['desc_oggetto']); ?></textarea>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="prezzo_oggetto" class="form-label">Prezzo (€)</label>
                                                <input type="number" step="0.01" class="form-control" id="prezzo_oggetto" name="prezzo_oggetto"
                                                       value="<?php echo $edit_product['prezzo_oggetto']; ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="quant_oggetto" class="form-label">Quantità</label>
                                                <input type="number" class="form-control" id="quant_oggetto" name="quant_oggetto"
                                                       value="<?php echo $edit_product['quant_oggetto']; ?>">
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="mb-3">
                                            <label for="nome_box" class="form-label">Nome Mystery Box *</label>
                                            <input type="text" class="form-control" id="nome_box" name="nome_box"
                                                   value="<?php echo htmlspecialchars($edit_product['nome_box']); ?>" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="desc_box" class="form-label">Descrizione *</label>
                                            <textarea class="form-control" id="desc_box" name="desc_box" rows="3" required><?php echo htmlspecialchars($edit_product['desc_box']); ?></textarea>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="prezzo_box" class="form-label">Prezzo (€) *</label>
                                                <input type="number" step="0.01" class="form-control" id="prezzo_box" name="prezzo_box"
                                                       value="<?php echo $edit_product['prezzo_box']; ?>" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="quantita_box" class="form-label">Quantità *</label>
                                                <input type="number" class="form-control" id="quantita_box" name="quantita_box"
                                                       value="<?php echo $edit_product['quantita_box']; ?>" required>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="fk_categoria_oggetto" class="form-label">Categoria *</label>
                                            <select class="form-select" id="fk_categoria_oggetto" name="fk_categoria_oggetto" required>
                                                <?php foreach ($categorie as $cat): ?>
                                                    <option value="<?php echo $cat['id_categoria']; ?>"
                                                        <?php echo $edit_product['fk_categoria_oggetto'] == $cat['id_categoria'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($cat['nome_categoria'] . ' - ' . $cat['tipo_oggetto']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="fk_rarita" class="form-label">Rarità</label>
                                            <select class="form-select" id="fk_rarita" name="fk_rarita">
                                                <option value="">Seleziona rarità</option>
                                                <?php foreach ($rarita as $rar): ?>
                                                    <option value="<?php echo $rar['id_rarita']; ?>"
                                                        <?php echo $edit_product['fk_rarita'] == $rar['id_rarita'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($rar['nome_rarita']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-check-circle"></i> Aggiorna Prodotto
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- Lista Prodotti -->
                <div class="row mb-3">
                    <div class="col">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Statistiche Prodotti</h5>
                                <div class="row text-center">
                                    <div class="col-md-3">
                                        <h3 class="text-primary"><?php echo count($oggetti); ?></h3>
                                        <p class="mb-0">Oggetti</p>
                                    </div>
                                    <div class="col-md-3">
                                        <h3 class="text-success"><?php echo count($mystery_boxes); ?></h3>
                                        <p class="mb-0">Mystery Box</p>
                                    </div>
                                    <div class="col-md-3">
                                        <h3 class="text-warning"><?php echo count($categorie); ?></h3>
                                        <p class="mb-0">Categorie</p>
                                    </div>
                                    <div class="col-md-3">
                                        <h3 class="text-info"><?php echo count($rarita); ?></h3>
                                        <p class="mb-0">Rarità</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs per oggetti e mystery box -->
                <ul class="nav nav-tabs" id="productTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="oggetti-tab" data-bs-toggle="tab" data-bs-target="#oggetti"
                                type="button" role="tab">
                            <i class="bi bi-card-text"></i> Oggetti (<?php echo count($oggetti); ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="mystery-box-tab" data-bs-toggle="tab" data-bs-target="#mystery-box"
                                type="button" role="tab">
                            <i class="bi bi-box"></i> Mystery Box (<?php echo count($mystery_boxes); ?>)
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="productTabsContent">
                    <!-- Tab Oggetti -->
                    <div class="tab-pane fade show active" id="oggetti" role="tabpanel">
                        <div class="card border-top-0">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nome</th>
                                            <th>Categoria</th>
                                            <th>Prezzo</th>
                                            <th>Quantità</th>
                                            <th>Rarità</th>
                                            <th>Azioni</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (empty($oggetti)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center text-muted">Nessun oggetto trovato</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($oggetti as $oggetto): ?>
                                                <tr>
                                                    <td><?php echo $oggetto['id_oggetto']; ?></td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($oggetto['nome_oggetto']); ?></strong><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars(substr($oggetto['desc_oggetto'], 0, 50)) . '...'; ?></small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($oggetto['nome_categoria'] ?? 'N/A'); ?></td>
                                                    <td><?php echo $oggetto['prezzo_oggetto'] ? '€' . number_format($oggetto['prezzo_oggetto'], 2) : 'N/A'; ?></td>
                                                    <td><?php echo $oggetto['quant_oggetto'] ?? 'N/A'; ?></td>
                                                    <td>
                                                        <?php if ($oggetto['nome_rarita']): ?>
                                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($oggetto['nome_rarita']); ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">N/A</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="gestione_prodotti.php?action=edit&type=oggetto&id=<?php echo $oggetto['id_oggetto']; ?>"
                                                               class="btn btn-outline-primary">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                            <button type="button" class="btn btn-outline-danger"
                                                                    onclick="confirmDelete(<?php echo $oggetto['id_oggetto']; ?>, 'oggetto', '<?php echo htmlspecialchars($oggetto['nome_oggetto']); ?>')">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Mystery Box -->
                    <div class="tab-pane fade" id="mystery-box" role="tabpanel">
                        <div class="card border-top-0">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nome</th>
                                            <th>Categoria</th>
                                            <th>Prezzo</th>
                                            <th>Quantità</th>
                                            <th>Rarità</th>
                                            <th>Azioni</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (empty($mystery_boxes)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center text-muted">Nessuna Mystery Box trovata</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($mystery_boxes as $box): ?>
                                                <tr>
                                                    <td><?php echo $box['id_box']; ?></td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($box['nome_box']); ?></strong><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars(substr($box['desc_box'], 0, 50)) . '...'; ?></small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($box['nome_categoria'] ?? 'N/A'); ?></td>
                                                    <td>€<?php echo number_format($box['prezzo_box'], 2); ?></td>
                                                    <td><?php echo $box['quantita_box']; ?></td>
                                                    <td>
                                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($box['nome_rarita']); ?></span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="gestione_prodotti.php?action=edit&type=mystery_box&id=<?php echo $box['id_box']; ?>"
                                                               class="btn btn-outline-primary">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                            <button type="button" class="btn btn-outline-danger"
                                                                    onclick="confirmDelete(<?php echo $box['id_box']; ?>, 'mystery_box', '<?php echo htmlspecialchars($box['nome_box']); ?>')">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Modal di conferma eliminazione -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Conferma Eliminazione</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Sei sicuro di voler eliminare <strong id="productName"></strong>?</p>
                <p class="text-danger small">Questa azione non può essere annullata.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="delete_product">
                    <input type="hidden" name="product_id" id="deleteProductId">
                    <input type="hidden" name="product_type" id="deleteProductType">
                    <button type="submit" class="btn btn-danger">Elimina</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function confirmDelete(productId, productType, productName) {
        document.getElementById('deleteProductId').value = productId;
        document.getElementById('deleteProductType').value = productType;
        document.getElementById('productName').textContent = productName;

        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        deleteModal.show();
    }
</script>
</body>
</html>