<?php
$pdo = new PDO('mysql:host=localhost;dbname=boxomnia', 'admin', 'admin');
$stmt = $pdo->prepare("SELECT * FROM mystery_box ORDER BY id_box DESC LIMIT 6");
$stmt->execute();
$best = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<section class="section">
    <h2>Più Venduti</h2>
    <div class="row">
        <?php foreach ($best as $p):
            // Assegna valori di default se i campi sono null
            $immagine = $p['immagine'] ?? '/LTDW-project/images/prod_yugioh.png';
            $nome = $p['nome'] ?? 'Prodotto senza nome';
            $prezzo = $p['prezzo'] ?? 0;
            ?>
            <div class="col-lg-4 col-sm-6 mb-4">
                <div class="card h-100">
                    <img src="<?= htmlspecialchars($immagine); ?>" class="card-img-top" alt="<?= htmlspecialchars($nome); ?>">
                    <div class="card-body">
                        <h5 class="card-title"><?= htmlspecialchars($nome); ?></h5>
                        <p class="card-text">€ <?= number_format($prezzo, 2, ',', '.'); ?></p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>