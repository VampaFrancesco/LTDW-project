<?php
// delete.php
require_once __DIR__ . '/../../include/config.inc.php';
$db   = $config['dbms']['localhost'];
$conn = new mysqli($db['host'], $db['user'], $db['passwd'], $db['dbname']);
if ($conn->connect_error) {
    die("Connessione fallita: " . $conn->connect_error);
}

// 1) Determina il tipo di entità
$type = $_GET['type'] ?? 'oggetto';
if (!in_array($type, ['oggetto','mystery_box'], true)) {
    $type = 'oggetto';
}

// 2) Se c'è un ID, tenta la cancellazione
$message = '';
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    if ($id > 0) {
        if ($type === 'oggetto') {
            $stmt = $conn->prepare("DELETE FROM oggetto WHERE id_oggetto = ?");
        } else {
            $stmt = $conn->prepare("DELETE FROM mystery_box WHERE id_box = ?");
        }
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = ucfirst($type) . " #{$id} eliminato con successo.";
        } else {
            $message = "Errore eliminazione: " . $stmt->error;
        }
        $stmt->close();
    }
}

// 3) Preleva la lista aggiornata
if ($type === 'oggetto') {
    $title    = 'Elimina Oggetti';
    $rows     = $conn->query("SELECT id_oggetto AS id, nome_oggetto AS nome FROM oggetto ORDER BY id_oggetto");
} else {
    $title    = 'Elimina Mystery Box';
    $rows     = $conn->query("SELECT id_box   AS id, nome_box   AS nome FROM mystery_box ORDER BY id_box");
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?></title>
    <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
            rel="stylesheet" crossorigin="anonymous">
    <link
            href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css"
            rel="stylesheet">
    <link rel="stylesheet" href="../../css/dashboard.css">
</head>
<body class="d-flex flex-column min-vh-100">

<nav class="navbar navbar-dark bg-primary mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">← Dashboard</a>
    </div>
</nav>

<div class="container">
    <!-- Selettore di entità -->
    <form method="get" class="mb-3">
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="type" id="optOggetto"
                   value="oggetto" onchange="this.form.submit()"
                <?= $type==='oggetto' ? 'checked' : '' ?>>
            <label class="form-check-label" for="optOggetto">Oggetti</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="type" id="optBox"
                   value="mystery_box" onchange="this.form.submit()"
                <?= $type==='mystery_box' ? 'checked' : '' ?>>
            <label class="form-check-label" for="optBox">Mystery Box</label>
        </div>
    </form>

    <h1 class="mb-3"><?= htmlspecialchars($title) ?></h1>

    <?php if ($message): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
    <?php endif ?>

    <ul class="list-group">
        <?php while ($r = $rows->fetch_assoc()): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <?= htmlspecialchars($r['nome']) ?>
                <a href="delete.php?type=<?= $type ?>&id=<?= $r['id'] ?>"
                   class="btn btn-sm btn-danger"
                   onclick="return confirm('Sei sicuro di eliminare <?= addslashes($r['nome']) ?>?');">
                    <i class="bi bi-trash"></i> Elimina
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
