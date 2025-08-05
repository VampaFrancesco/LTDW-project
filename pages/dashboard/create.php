<?php
// create.php
require_once __DIR__ . '/../../include/config.inc.php';

// Connessione al database
$db   = $config['dbms']['localhost'];
$conn = new mysqli($db['host'], $db['user'], $db['passwd'], $db['dbname']);
if ($conn->connect_error) {
    die("Connessione fallita: " . $conn->connect_error);
}

// Preleva categorie e rarità
$cats_result = $conn->query("SELECT id_categoria, nome_categoria FROM categoria_oggetto ORDER BY nome_categoria");
$categories  = [];
while ($r = $cats_result->fetch_assoc()) {
    $categories[] = $r;
}
$rars_result = $conn->query("SELECT id_rarita, nome_rarita FROM rarita ORDER BY ordine");
$rarities    = [];
while ($r = $rars_result->fetch_assoc()) {
    $rarities[] = $r;
}

$errors  = [];
$success = '';

// Tipo di entità da creare: 'oggetto' o 'mystery_box'
$type = $_POST['type'] ?? 'oggetto';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($type === 'oggetto') {
        // Campi Oggetto
        $nome      = trim($_POST['nome_oggetto']    ?? '');
        $desc      = trim($_POST['desc_oggetto']    ?? '');
        $prezzo    = $_POST['prezzo_oggetto']       ?? '';
        $fk_cat    = intval($_POST['fk_categoria_oggetto'] ?? 0);
        $fk_rarita = intval($_POST['fk_rarita']      ?? 0);

        // Validazioni Oggetto
        if ($nome === '')         $errors[] = "Il nome è obbligatorio";
        if ($desc === '')         $errors[] = "La descrizione è obbligatoria";
        if (!is_numeric($prezzo)) $errors[] = "Il prezzo deve essere un numero";
        if ($fk_cat <= 0)         $errors[] = "Devi selezionare una categoria";

        if (empty($errors)) {
            $stmt = $conn->prepare("
                INSERT INTO oggetto 
                  (nome_oggetto, desc_oggetto, prezzo_oggetto, fk_categoria_oggetto, fk_rarita)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("ssdis", $nome, $desc, $prezzo, $fk_cat, $fk_rarita);
            if ($stmt->execute()) {
                $success = "Oggetto creato con ID " . $stmt->insert_id;
                // reset campi
                $nome = $desc = $prezzo = '';
                $fk_cat = $fk_rarita = 0;
            } else {
                $errors[] = "Errore inserimento Oggetto: " . $stmt->error;
            }
            $stmt->close();
        }

    } elseif ($type === 'mystery_box') {
        // Campi Mystery Box
        $nome     = trim($_POST['nome_box']    ?? '');
        $desc     = trim($_POST['desc_box']    ?? '');
        $prezzo   = $_POST['prezzo_box']       ?? '';
        $qtà      = intval($_POST['quantita_box'] ?? 0);
        $fk_cat_b = intval($_POST['fk_categoria_box'] ?? 0);

        // Validazioni Mystery Box
        if ($nome === '')         $errors[] = "Il nome della Mystery Box è obbligatorio";
        if ($desc === '')         $errors[] = "La descrizione è obbligatoria";
        if (!is_numeric($prezzo)) $errors[] = "Il prezzo deve essere un numero";
        if ($qtà <= 0)            $errors[] = "La quantità deve essere almeno 1";
        if ($fk_cat_b <= 0)       $errors[] = "Devi selezionare una categoria";

        if (empty($errors)) {
            $stmt = $conn->prepare("
                INSERT INTO mystery_box 
                  (nome_box, desc_box, prezzo_box, quantita_box, fk_categoria_oggetto)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("ssdis", $nome, $desc, $prezzo, $qtà, $fk_cat_b);
            if ($stmt->execute()) {
                $success = "Mystery Box creata con ID " . $stmt->insert_id;
                // reset campi
                $nome = $desc = $prezzo = '';
                $qtà = $fk_cat_b = 0;
            } else {
                $errors[] = "Errore inserimento Mystery Box: " . $stmt->error;
            }
            $stmt->close();
        }

    } else {
        $errors[] = "Tipo non valido";
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Crea Entità</title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet">
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css"
        rel="stylesheet">
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-dark bg-primary mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">← Dashboard</a>
    </div>
</nav>

<div class="container">
    <h1 class="mb-4">Crea Nuova Entità</h1>

    <?php if($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" class="row g-3">
        <!-- Tipo -->
        <div class="col-12">
            <label class="form-label">Seleziona tipo:</label><br>
            <div class="form-check form-check-inline">
                <input
                    class="form-check-input"
                    type="radio"
                    name="type"
                    id="typeOggetto"
                    value="oggetto"
                    <?= $type==='oggetto' ? 'checked' : '' ?>>
                <label class="form-check-label" for="typeOggetto">Oggetto</label>
            </div>
            <div class="form-check form-check-inline">
                <input
                    class="form-check-input"
                    type="radio"
                    name="type"
                    id="typeBox"
                    value="mystery_box"
                    <?= $type==='mystery_box' ? 'checked' : '' ?>>
                <label class="form-check-label" for="typeBox">Mystery Box</label>
            </div>
        </div>

        <!-- Sezione Oggetto -->
        <div id="form-oggetto" class="<?= $type==='oggetto' ? '' : 'd-none' ?>">
            <div class="col-md-6">
                <label class="form-label">Nome Oggetto</label>
                <input type="text" name="nome_oggetto" value="<?= htmlspecialchars($nome ?? '') ?>" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">Prezzo (€)</label>
                <input type="number" step="0.01" name="prezzo_oggetto" value="<?= htmlspecialchars($prezzo ?? '') ?>" class="form-control">
            </div>
            <div class="col-12">
                <label class="form-label">Descrizione</label>
                <textarea name="desc_oggetto" class="form-control" rows="3"><?= htmlspecialchars($desc ?? '') ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Categoria</label>
                <select name="fk_categoria_oggetto" class="form-select">
                    <option value="0">-- Seleziona --</option>
                    <?php foreach($categories as $c): ?>
                        <option value="<?= $c['id_categoria'] ?>" <?= (isset($fk_cat) && $fk_cat===$c['id_categoria'])?'selected':''?>>
                            <?= $c['id_categoria'] ?> — <?= htmlspecialchars($c['nome_categoria']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Rarità</label>
                <select name="fk_rarita" class="form-select">
                    <option value="0">-- Nessuna --</option>
                    <?php foreach($rarities as $r): ?>
                        <option value="<?= $r['id_rarita'] ?>" <?= (isset($fk_rarita)&&$fk_rarita===$r['id_rarita'])?'selected':''?>>
                            <?= $r['id_rarita'] ?> — <?= htmlspecialchars($r['nome_rarita']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Sezione Mystery Box -->
        <div id="form-mystery_box" class="<?= $type==='mystery_box' ? '' : 'd-none' ?>">
            <div class="col-md-6">
                <label class="form-label">Nome Box</label>
                <input type="text" name="nome_box" value="<?= htmlspecialchars($nome ?? '') ?>" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">Prezzo (€)</label>
                <input type="number" step="0.01" name="prezzo_box" value="<?= htmlspecialchars($prezzo ?? '') ?>" class="form-control">
            </div>
            <div class="col-12">
                <label class="form-label">Descrizione Box</label>
                <textarea name="desc_box" class="form-control" rows="3"><?= htmlspecialchars($desc ?? '') ?></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label">Quantità Box</label>
                <input type="number" name="quantita_box" value="<?= htmlspecialchars($qtà ?? '') ?>" class="form-control" min="1">
            </div>
            <div class="col-md-8">
                <label class="form-label">Categoria Box</label>
                <select name="fk_categoria_box" class="form-select">
                    <option value="0">-- Seleziona --</option>
                    <?php foreach($categories as $c): ?>
                        <option value="<?= $c['id_categoria'] ?>" <?= (isset($fk_cat_b)&&$fk_cat_b===$c['id_categoria'])?'selected':''?>>
                            <?= $c['id_categoria'] ?> — <?= htmlspecialchars($c['nome_categoria']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="col-12">
            <button class="btn btn-success"><i class="bi bi-plus-circle"></i> Inserisci</button>
            <a href="dashboard.php" class="btn btn-secondary">Annulla</a>
        </div>
    </form>
</div>

<footer class="mt-auto py-3 bg-light text-center">
    &copy; <?= date('Y') ?>
</footer>

<script>
    // Mostra/nascondi sezioni al cambio di tipo
    document.querySelectorAll('input[name="type"]').forEach(radio => {
        radio.addEventListener('change', () => {
            document.getElementById('form-oggetto').classList.toggle('d-none', radio.value !== 'oggetto');
            document.getElementById('form-mystery_box').classList.toggle('d-none', radio.value !== 'mystery_box');
        });
    });
</script>
</body>
</html>
