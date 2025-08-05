<?php
// read.php
require_once __DIR__ . '/../../include/config.inc.php';

// Connessione
$db   = $config['dbms']['localhost'];
$conn = new mysqli($db['host'], $db['user'], $db['passwd'], $db['dbname']);
if ($conn->connect_error) {
    die("Connessione fallita: " . $conn->connect_error);
}

// Scegli il tipo da visualizzare: oggetto o mystery_box
$type = $_GET['type'] ?? 'oggetto';
if (!in_array($type, ['oggetto', 'mystery_box'], true)) {
    $type = 'oggetto';
}

// Imposta query, titolo, colonne e link di creazione
if ($type === 'mystery_box') {
    $title      = 'Lista Mystery Box';
    $createLink = 'create.php?type=mystery_box';
    $columns    = ['ID','Nome Box','Categoria','Prezzo','Quantità','Azioni'];
    $sql = "
      SELECT
        m.id_box     AS id,
        m.nome_box   AS nome,
        c.nome_categoria AS categoria,
        m.prezzo_box    AS prezzo,
        m.quantita_box  AS quantita
      FROM mystery_box m
      JOIN categoria_oggetto c ON m.fk_categoria_oggetto = c.id_categoria
      ORDER BY m.id_box
    ";
} else {
    $title      = 'Lista Oggetti';
    $createLink = 'create.php?type=oggetto';
    $columns    = ['ID','Nome Oggetto','Categoria','Rarità','Prezzo','Azioni'];
    $sql = "
      SELECT
        o.id_oggetto AS id,
        o.nome_oggetto AS nome,
        c.nome_categoria AS categoria,
        COALESCE(r.nome_rarita, '-') AS rarita,
        o.prezzo_oggetto AS prezzo
      FROM oggetto o
      JOIN categoria_oggetto c ON o.fk_categoria_oggetto = c.id_categoria
      LEFT JOIN rarita r ON o.fk_rarita = r.id_rarita
      ORDER BY o.id_oggetto
    ";
}

$res = $conn->query($sql);
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

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">Dashboard</a>
    </div>
</nav>

<div class="container">

    <!-- Selettore di entità -->
    <form method="get" class="mb-4">
        <div class="form-check form-check-inline">
            <input
                    class="form-check-input"
                    type="radio"
                    name="type"
                    id="optOggetto"
                    value="oggetto"
                    onchange="this.form.submit()"
                <?= $type==='oggetto' ? 'checked' : '' ?>>
            <label class="form-check-label" for="optOggetto">Oggetti</label>
        </div>
        <div class="form-check form-check-inline">
            <input
                    class="form-check-input"
                    type="radio"
                    name="type"
                    id="optBox"
                    value="mystery_box"
                    onchange="this.form.submit()"
                <?= $type==='mystery_box' ? 'checked' : '' ?>>
            <label class="form-check-label" for="optBox">Mystery Box</label>
        </div>
    </form>

    <!-- Titolo e bottone Crea -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1><?= htmlspecialchars($title) ?></h1>
        <a href="<?= $createLink ?>" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Nuovo
        </a>
    </div>

    <!-- Tabella dinamica -->
    <table class="table table-striped">
        <thead>
        <tr>
            <?php foreach ($columns as $col): ?>
                <th><?= htmlspecialchars($col) ?></th>
            <?php endforeach ?>
        </tr>
        </thead>
        <tbody>
        <?php while ($row = $res->fetch_assoc()): ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['nome']) ?></td>
                <td><?= htmlspecialchars($row['categoria']) ?></td>

                <?php if ($type === 'oggetto'): ?>
                    <td><?= htmlspecialchars($row['rarita']) ?></td>
                <?php endif ?>

                <td>
                    <?php if ($row['prezzo'] !== null): ?>
                        <?= number_format((float)$row['prezzo'], 0, ',', '.') ?> €
                    <?php else: ?>
                        &mdash;
                    <?php endif ?>
                </td>

                <?php if ($type === 'mystery_box'): ?>
                    <td><?= $row['quantita'] ?></td>
                <?php endif ?>

                <td>
                    <a
                            href="update.php?type=<?= $type ?>&amp;id=<?= $row['id'] ?>"
                            class="btn btn-warning btn-sm">
                        <i class="bi bi-pencil-square"></i>
                    </a>
                    <a
                            href="delete.php?type=<?= $type ?>&amp;id=<?= $row['id'] ?>"
                            class="btn btn-danger btn-sm">
                        <i class="bi bi-trash"></i>
                    </a>
                </td>
            </tr>
        <?php endwhile ?>
        </tbody>
    </table>
</div>

<footer class="mt-auto py-3 bg-light text-center">
    &copy; <?= date('Y') ?> BOX OMNIA
</footer>

</body>
</html>
