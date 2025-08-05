<?php
// pages/dashboard/update.php
require_once __DIR__ . '/../../include/config.inc.php';

// 1) Connessione al database
$db = $config['dbms']['localhost'];
$conn = new mysqli($db['host'], $db['user'], $db['passwd'], $db['dbname']);
if ($conn->connect_error) {
    die("Connessione fallita: " . $conn->connect_error);
}

// 2) Determina il tipo di entità: 'oggetto' o 'mystery_box'
$type = $_GET['type'] ?? 'oggetto';
if (!in_array($type, ['oggetto', 'mystery_box'], true)) {
    $type = 'oggetto';
}

// 3) Se non è passato un ID, mostra la lista e termina
if (!isset($_GET['id'])) {
    if ($type === 'mystery_box') {
        $title = 'Seleziona Mystery Box da modificare';
        $list = $conn->query("SELECT id_box AS id, nome_box AS nome FROM mystery_box ORDER BY id_box");
    } else {
        $title = 'Seleziona Oggetto da modificare';
        $list = $conn->query("SELECT id_oggetto AS id, nome_oggetto AS nome FROM oggetto ORDER BY id_oggetto");
    }
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <title><?= htmlspecialchars($title) ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"
              crossorigin="anonymous">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
        <link rel="stylesheet" href="../../css/dashboard.css">
    </head>
    <body class="d-flex flex-column min-vh-100">
    <nav class="navbar navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">← Dashboard</a>
        </div>
    </nav>
    <div class="container flex-grow-1 my-4">
        <!-- Switch tra Oggetti e Mystery Box -->
        <form method="get" class="mb-4">
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="type" id="optOggetto"
                       value="oggetto" onchange="this.form.submit()"
                    <?= $type === 'oggetto' ? 'checked' : '' ?>>
                <label class="form-check-label" for="optOggetto">Oggetti</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="type" id="optBox"
                       value="mystery_box" onchange="this.form.submit()"
                    <?= $type === 'mystery_box' ? 'checked' : '' ?>>
                <label class="form-check-label" for="optBox">Mystery Box</label>
            </div>
        </form>

        <h1 class="mb-3"><?= htmlspecialchars($title) ?></h1>
        <ul class="list-group">
            <?php while ($row = $list->fetch_assoc()): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <?= htmlspecialchars($row['nome']) ?>
                    <a href="update.php?type=<?= $type ?>&amp;id=<?= $row['id'] ?>"
                       class="btn btn-sm btn-warning">
                        <i class="bi bi-pencil-square"></i> Modifica
                    </a>
                </li>
            <?php endwhile ?>
        </ul>
    </div>
    <footer class="mt-auto py-3 bg-light text-center">
        &copy; <?= date('Y') ?>
    </footer>
    </body>
    </html>
    <?php
    exit;
}

// 4) Ho un ID: preparo le variabili e i metadata
$id = intval($_GET['id']);
$message = '';
$errors = [];

// Carica le categorie per entrambi i tipi
$cats = $conn->query("SELECT id_categoria, nome_categoria FROM categoria_oggetto ORDER BY nome_categoria");

// Carica le rarità solo per 'oggetto'
if ($type === 'oggetto') {
    $rars = $conn->query("SELECT id_rarita, nome_rarita FROM rarita ORDER BY id_rarita");
}

// 5) Se è arrivato un POST, valida e aggiorna il record
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $prezzo = $_POST['prezzo'] ?? '';
    $descr = trim($_POST['descrizione'] ?? '');
    $fk_cat = intval($_POST['fk_cat'] ?? 0);

    if ($type === 'oggetto') {
        $fk_rit = intval($_POST['fk_rarita'] ?? 0);
        // Validazioni
        if ($nome === '') $errors[] = "Il nome è obbligatorio";
        if ($prezzo !== '' && !is_numeric($prezzo)) $errors[] = "Il prezzo deve essere un numero";
        if ($fk_cat <= 0) $errors[] = "Seleziona una categoria";
        // Se tutto OK, esegui UPDATE
        if (empty($errors)) {
            $stmt = $conn->prepare("
              UPDATE oggetto
                 SET nome_oggetto       = ?,
                     prezzo_oggetto     = ?,
                     desc_oggetto       = ?,
                     fk_categoria_oggetto = ?,
                     fk_rarita          = ?
               WHERE id_oggetto        = ?
            ");
            $stmt->bind_param("sdsiii", $nome, $prezzo, $descr, $fk_cat, $fk_rit, $id);
            if ($stmt->execute()) {
                $message = "Oggetto #{$id} aggiornato con successo.";
            } else {
                $errors[] = "Errore in aggiornamento: " . $stmt->error;
            }
            $stmt->close();
        }

    } else {
        $qty = intval($_POST['quantita'] ?? 0);
        // Validazioni
        if ($nome === '') $errors[] = "Il nome della box è obbligatorio";
        if ($prezzo !== '' && $prezzo !== null && !is_numeric($prezzo)) $errors[] = "Il prezzo deve essere un numero";
        if ($qty <= 0) $errors[] = "La quantità deve essere > 0";
        if ($fk_cat <= 0) $errors[] = "Seleziona una categoria";
        // Se tutto OK, esegui UPDATE
        if (empty($errors)) {
            $stmt = $conn->prepare("
              UPDATE mystery_box
                 SET nome_box           = ?,
                     prezzo_box         = ?,
                     desc_box           = ?,
                     quantita_box       = ?,
                     fk_categoria_oggetto = ?
               WHERE id_box             = ?
            ");
            $stmt->bind_param("sdsiii", $nome, $prezzo, $descr, $qty, $fk_cat, $id);
            if ($stmt->execute()) {
                $message = "Mystery Box #{$id} aggiornata con successo.";
            } else {
                $errors[] = "Errore in aggiornamento: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// 6) Pre‐riempi il form con i valori correnti (solo su GET o dopo POST con errori)
if (empty($message)) {
    if ($type === 'oggetto') {
        $stmt = $conn->prepare("
          SELECT 
            nome_oggetto       AS nome,
            prezzo_oggetto     AS prezzo,
            desc_oggetto       AS descrizione,
            fk_categoria_oggetto AS fk_cat,
            fk_rarita          AS fk_rarita
          FROM oggetto
         WHERE id_oggetto = ?
        ");
    } else {
        $stmt = $conn->prepare("
          SELECT 
            nome_box           AS nome,
            prezzo_box         AS prezzo,
            desc_box           AS descrizione,
            quantita_box       AS quantita,
            fk_categoria_oggetto AS fk_cat
          FROM mystery_box
         WHERE id_box = ?
        ");
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Assegna alle variabili se non già fornite da POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !empty($errors)) {
        $nome = $data['nome'];
        $prezzo = $data['prezzo'];
        $descr = $data['descrizione'];
        $fk_cat = $data['fk_cat'];
        if ($type === 'oggetto') {
            $fk_rit = $data['fk_rarita'];
        } else {
            $qty = $data['quantita'];
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title><?= $type === 'oggetto' ? "Modifica Oggetto #$id" : "Modifica Mystery Box #$id" ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"
          crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../../css/dashboard.css">
</head>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="update.php?type=<?= $type ?>">← Torna alla lista</a>
    </div>
</nav>
<div class="container flex-grow-1 my-4">
    <h1 class="mb-4"><?= $type === 'oggetto' ? "Modifica Oggetto #$id" : "Modifica Mystery Box #$id" ?></h1>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php elseif ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach ?>
            </ul>
        </div>
    <?php endif ?>

    <form method="post" class="row g-3">
        <!-- Nome -->
        <div class="col-md-6">
            <label class="form-label">Nome</label>
            <input type="text" name="nome" value="<?= htmlspecialchars($nome) ?>" class="form-control">
        </div>
        <!-- Prezzo -->
        <div class="col-md-3">
            <label class="form-label">Prezzo (€)</label>
            <input type="number" step="0.01" name="prezzo" value="<?= htmlspecialchars($prezzo) ?>"
                   class="form-control">
        </div>
        <!-- Quantità (solo Mystery Box) -->
        <?php if ($type === 'mystery_box'): ?>
            <div class="col-md-3">
                <label class="form-label">Quantità</label>
                <input type="number" name="quantita" value="<?= htmlspecialchars($qty) ?>" class="form-control" min="1">
            </div>
        <?php endif ?>
        <!-- Descrizione -->
        <div class="col-12">
            <label class="form-label">Descrizione</label>
            <textarea name="descrizione" class="form-control" rows="3"><?= htmlspecialchars($descr) ?></textarea>
        </div>
        <!-- Categoria -->
        <div class="col-md-6">
            <label class="form-label">Categoria</label>
            <select name="fk_cat" class="form-select">
                <option value="0">-- Seleziona --</option>
                <?php while ($c = $cats->fetch_assoc()): ?>
                    <option value="<?= $c['id_categoria'] ?>" <?= ($c['id_categoria'] === $fk_cat) ? 'selected' : '' ?>>
                        <?= $c['id_categoria'] ?> — <?= htmlspecialchars($c['nome_categoria']) ?>
                    </option>
                <?php endwhile ?>
            </select>
        </div>
        <!-- Rarità (solo Oggetto) -->
        <?php if ($type === 'oggetto'): ?>
            <div class="col-md-6">
                <label class="form-label">Rarità</label>
                <select name="fk_rarita" class="form-select">
                    <option value="0">-- Nessuna --</option>
                    <?php while ($r = $rars->fetch_assoc()): ?>
                        <option value="<?= $r['id_rarita'] ?>" <?= ($r['id_rarita'] === $fk_rit) ? 'selected' : '' ?>>
                            <?= $r['id_rarita'] ?> — <?= htmlspecialchars($r['nome_rarita']) ?>
                        </option>
                    <?php endwhile ?>
                </select>
            </div>
        <?php endif ?>
        <!-- Submit -->
        <div class="col-12">
            <button class="btn btn-warning">
                <i class="bi bi-pencil-square"></i> Salva Modifiche
            </button>
        </div>
    </form>
</div>
<footer class="mt-auto py-3 bg-light text-center">
    &copy; <?= date('Y') ?>
</footer>
</body>
</html>
