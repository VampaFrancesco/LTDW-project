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

// 3) Inizializza TUTTE le variabili
$nome = '';
$prezzo = '';
$descr = '';
$fk_cat = 0;
$fk_rit = 0;  // Importante: inizializza sempre questa variabile
$qty = 0;
$message = '';
$errors = [];

// 4) Se non è passato un ID, mostra la lista
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
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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

// 5) Ho un ID valido
$id = intval($_GET['id']);

// Carica le categorie
$cats_result = $conn->query("SELECT id_categoria, nome_categoria FROM categoria_oggetto ORDER BY nome_categoria");
$categories = [];
while ($row = $cats_result->fetch_assoc()) {
    $categories[] = $row;
}

// Carica le rarità (SEMPRE, anche se non è un oggetto)
$rarities = [];
$rars_result = $conn->query("SELECT id_rarita, nome_rarita FROM rarita ORDER BY ordine");
while ($row = $rars_result->fetch_assoc()) {
    $rarities[] = $row;
}

// 6) Gestione POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $prezzo = $_POST['prezzo'] ?? '';
    $descr = trim($_POST['descrizione'] ?? '');
    $fk_cat = intval($_POST['fk_cat'] ?? 0);

    if ($type === 'oggetto') {
        // PER OGGETTI: la rarità è opzionale, può essere 0 (NULL)
        $fk_rit = intval($_POST['fk_rarita'] ?? 0);

        // Validazioni
        if ($nome === '') $errors[] = "Il nome è obbligatorio";
        if ($prezzo !== '' && !is_numeric($prezzo)) $errors[] = "Il prezzo deve essere un numero";
        if ($fk_cat <= 0) $errors[] = "Seleziona una categoria";

        if (empty($errors)) {
            // Se rarità è 0, usa NULL
            $rarita_value = ($fk_rit === 0) ? null : $fk_rit;

            if ($prezzo === '' || $prezzo === null) {
                // Prezzo NULL
                $stmt = $conn->prepare("
                  UPDATE oggetto
                     SET nome_oggetto = ?,
                         prezzo_oggetto = NULL,
                         desc_oggetto = ?,
                         fk_categoria_oggetto = ?,
                         fk_rarita = ?
                   WHERE id_oggetto = ?
                ");
                $stmt->bind_param("ssiii", $nome, $descr, $fk_cat, $rarita_value, $id);
            } else {
                // Prezzo valido
                $stmt = $conn->prepare("
                  UPDATE oggetto
                     SET nome_oggetto = ?,
                         prezzo_oggetto = ?,
                         desc_oggetto = ?,
                         fk_categoria_oggetto = ?,
                         fk_rarita = ?
                   WHERE id_oggetto = ?
                ");
                $prezzo_decimal = floatval($prezzo);
                $stmt->bind_param("sdsiii", $nome, $prezzo_decimal, $descr, $fk_cat, $rarita_value, $id);
            }

            if ($stmt->execute()) {
                $message = "Oggetto #{$id} aggiornato con successo.";
            } else {
                $errors[] = "Errore aggiornamento: " . $stmt->error;
            }
            $stmt->close();
        }

    } else { // MYSTERY BOX
        $qty = intval($_POST['quantita'] ?? 0);
        $fk_rit = intval($_POST['fk_rarita'] ?? 0); // Per mystery box la rarità è obbligatoria

        // Validazioni
        if ($nome === '') $errors[] = "Il nome della box è obbligatorio";
        if ($prezzo === '' || !is_numeric($prezzo)) $errors[] = "Il prezzo è obbligatorio";
        if ($qty <= 0) $errors[] = "La quantità deve essere > 0";
        if ($fk_cat <= 0) $errors[] = "Seleziona una categoria";
        if ($fk_rit <= 0) $errors[] = "Seleziona una rarità per la mystery box";

        if (empty($errors)) {
            $stmt = $conn->prepare("
              UPDATE mystery_box
                 SET nome_box = ?,
                     prezzo_box = ?,
                     desc_box = ?,
                     quantita_box = ?,
                     fk_categoria_oggetto = ?,
                     fk_rarita = ?
               WHERE id_box = ?
            ");
            $prezzo_decimal = floatval($prezzo);
            $stmt->bind_param("sdsiibi", $nome, $prezzo_decimal, $descr, $qty, $fk_cat, $fk_rit, $id);

            if ($stmt->execute()) {
                $message = "Mystery Box #{$id} aggiornata con successo.";
            } else {
                $errors[] = "Errore aggiornamento: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// 7) Carica dati correnti (se non c'è messaggio di successo)
if (empty($message)) {
    if ($type === 'oggetto') {
        $stmt = $conn->prepare("
          SELECT 
            nome_oggetto AS nome,
            prezzo_oggetto AS prezzo,
            desc_oggetto AS descrizione,
            fk_categoria_oggetto AS fk_cat,
            COALESCE(fk_rarita, 0) AS fk_rarita
          FROM oggetto
         WHERE id_oggetto = ?
        ");
    } else {
        $stmt = $conn->prepare("
          SELECT 
            nome_box AS nome,
            prezzo_box AS prezzo,
            desc_box AS descrizione,
            quantita_box AS quantita,
            fk_categoria_oggetto AS fk_cat,
            COALESCE(fk_rarita, 0) AS fk_rarita
          FROM mystery_box
         WHERE id_box = ?
        ");
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Assegna dati solo se non c'è stato POST o ci sono errori
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !empty($errors)) {
        $nome = $data['nome'] ?? '';
        $prezzo = $data['prezzo'] ?? '';
        $descr = $data['descrizione'] ?? '';
        $fk_cat = intval($data['fk_cat'] ?? 0);
        $fk_rit = intval($data['fk_rarita'] ?? 0); // SEMPRE assegnato

        if ($type === 'mystery_box') {
            $qty = intval($data['quantita'] ?? 0);
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
        <div class="alert alert-success">
            <?= htmlspecialchars($message) ?>
            <div class="mt-2">
                <a href="update.php?type=<?= $type ?>" class="btn btn-primary">Torna alla lista</a>
                <a href="update.php?type=<?= $type ?>&id=<?= $id ?>" class="btn btn-secondary">Modifica di nuovo</a>
            </div>
        </div>
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
            <label class="form-label">Nome *</label>
            <input type="text" name="nome" value="<?= htmlspecialchars($nome) ?>" class="form-control" required>
        </div>

        <!-- Prezzo -->
        <div class="col-md-<?= $type === 'mystery_box' ? '3' : '6' ?>">
            <label class="form-label">Prezzo (€) <?= $type === 'mystery_box' ? '*' : '' ?></label>
            <input type="number" step="0.01" name="prezzo" value="<?= htmlspecialchars($prezzo) ?>"
                   class="form-control" <?= $type === 'mystery_box' ? 'required' : '' ?>>
            <?php if ($type === 'oggetto'): ?>
                <div class="form-text">Lascia vuoto se non vendibile singolarmente</div>
            <?php endif; ?>
        </div>

        <!-- Quantità (solo Mystery Box) -->
        <?php if ($type === 'mystery_box'): ?>
            <div class="col-md-3">
                <label class="form-label">Quantità *</label>
                <input type="number" name="quantita" value="<?= htmlspecialchars($qty) ?>"
                       class="form-control" min="1" required>
            </div>
        <?php endif ?>

        <!-- Descrizione -->
        <div class="col-12">
            <label class="form-label">Descrizione *</label>
            <textarea name="descrizione" class="form-control" rows="3" required><?= htmlspecialchars($descr) ?></textarea>
        </div>

        <!-- Categoria -->
        <div class="col-md-6">
            <label class="form-label">Categoria *</label>
            <select name="fk_cat" class="form-select" required>
                <option value="0">-- Seleziona --</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id_categoria'] ?>" <?= ($c['id_categoria'] == $fk_cat) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['nome_categoria']) ?>
                    </option>
                <?php endforeach ?>
            </select>
        </div>

        <!-- Rarità -->
        <div class="col-md-6">
            <label class="form-label">Rarità <?= $type === 'mystery_box' ? '*' : '' ?></label>
            <select name="fk_rarita" class="form-select" <?= $type === 'mystery_box' ? 'required' : '' ?>>
                <?php if ($type === 'oggetto'): ?>
                    <option value="0" <?= ($fk_rit == 0) ? 'selected' : '' ?>>-- Nessuna rarità --</option>
                <?php else: ?>
                    <option value="0">-- Seleziona rarità --</option>
                <?php endif; ?>
                <?php foreach ($rarities as $r): ?>
                    <option value="<?= $r['id_rarita'] ?>" <?= ($r['id_rarita'] == $fk_rit) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($r['nome_rarita']) ?>
                    </option>
                <?php endforeach ?>
            </select>
            <?php if ($type === 'oggetto'): ?>
                <div class="form-text">Opzionale per gli oggetti</div>
            <?php else: ?>
                <div class="form-text">Obbligatoria per le mystery box</div>
            <?php endif; ?>
        </div>

        <!-- Submit -->
        <div class="col-12">
            <button type="submit" class="btn btn-warning">
                <i class="bi bi-pencil-square"></i> Salva Modifiche
            </button>
            <a href="update.php?type=<?= $type ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Annulla
            </a>
        </div>
    </form>
</div>
<footer class="mt-auto py-3 bg-light text-center">
    &copy; <?= date('Y') ?> Box Omnia
</footer>
</body>
</html>