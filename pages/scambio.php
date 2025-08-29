<?php
include __DIR__ . '/ScambioManager.php';

// Connessione DB
$pdo = new PDO("mysql:host=localhost;dbname=boxomnia;charset=utf8mb4", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$scambio_manager = new ScambioManager($pdo);

// Utente loggato (simulazione)
$utente_id = 2; // Cambialo in base alla sessione

$messaggio = "";

// Gestione form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['crea_scambio'])) {
            $carte_offerte = json_decode($_POST['carte_offerte'], true);
            $carte_richieste = json_decode($_POST['carte_richieste'], true);
            $id_scambio = $scambio_manager->creaProposta($utente_id, $carte_offerte, $carte_richieste);
            $messaggio = "✅ Proposta di scambio creata con ID $id_scambio!";
        }

        if (isset($_POST['accetta'])) {
            $scambio_manager->accettaScambio($_POST['id_scambio'], $utente_id);
            $messaggio = "✅ Scambio accettato!";
        }

        if (isset($_POST['rifiuta'])) {
            $scambio_manager->rifiutaScambio($_POST['id_scambio'], $utente_id);
            $messaggio = "❌ Scambio rifiutato!";
        }
    } catch (Exception $e) {
        $messaggio = "Errore: " . $e->getMessage();
    }
}

// Recupera scambi disponibili
$scambi = $scambio_manager->getScambiDisponibili($utente_id);

// Collezione utente
$collezione = $scambio_manager->getCollezioneUtente($utente_id);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Gestione Scambi</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="background-custom">
<div class="container">

    <h1 class="fashion_taital">Gestione Scambi</h1>

    <?php if ($messaggio): ?>
        <div class="alert-custom alert-success"><?= htmlspecialchars($messaggio) ?></div>
    <?php endif; ?>

    <!-- Sezione per proporre uno scambio -->
    <div class="section">
        <h2>Proponi uno Scambio</h2>
        <form method="post">
            <label class="form-label">Le tue carte disponibili:</label>
            <select multiple class="form-control" id="carte_offerte">
                <?php foreach ($collezione as $carta): ?>
                    <option value='{"id_oggetto":<?= $carta['fk_oggetto'] ?>,"quantita":1}'>
                        <?= $carta['nome_oggetto'] ?> (x<?= $carta['quantita_ogg'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <label class="form-label mt-3">Carte che richiedi (scrivi ID oggetto e quantità):</label>
            <textarea id="carte_richieste" class="form-control" rows="3"
                placeholder='[{"id_oggetto":2,"quantita":1}]'></textarea>

            <input type="hidden" name="carte_offerte" id="input_carte_offerte">
            <input type="hidden" name="carte_richieste" id="input_carte_richieste">

            <button type="submit" name="crea_scambio" class="btn btn-primary mt-3">Crea Scambio</button>
        </form>
    </div>

    <!-- Sezione scambi disponibili -->
    <div class="section">
        <h2>Scambi Disponibili</h2>
        <?php if ($scambi): ?>
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Proponente</th>
                        <th>Offre</th>
                        <th>Chiede</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($scambi as $s): ?>
                    <tr>
                        <td><?= $s['id_scambio'] ?></td>
                        <td><?= htmlspecialchars($s['nome'] . " " . $s['cognome']) ?></td>
                        <td><?= $s['carte_offerte'] ?></td>
                        <td><?= $s['carte_richieste'] ?></td>
                        <td>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="id_scambio" value="<?= $s['id_scambio'] ?>">
                                <button type="submit" name="accetta" class="btn btn-success btn-sm">Accetta</button>
                            </form>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="id_scambio" value="<?= $s['id_scambio'] ?>">
                                <button type="submit" name="rifiuta" class="btn btn-danger btn-sm">Rifiuta</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="empty-category-message">Nessuno scambio disponibile al momento.</p>
        <?php endif; ?>
    </div>

</div>

<script>
// Trasforma le selezioni in JSON
document.querySelector("form").addEventListener("submit", function(e){
    let offerte = Array.from(document.getElementById("carte_offerte").selectedOptions)
        .map(opt => JSON.parse(opt.value));
    document.getElementById("input_carte_offerte").value = JSON.stringify(offerte);

    let richieste = document.getElementById("carte_richieste").value;
    document.getElementById("input_carte_richieste").value = richieste;
});
</script>
</body>
</html>
