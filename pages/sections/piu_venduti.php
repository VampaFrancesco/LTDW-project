<?php
$pdo = new PDO('mysql:host=localhost;dbname=boxomnia', 'root', 'root');
$stmt = $pdo->prepare("SELECT * FROM mystery_box ORDER BY id_box DESC LIMIT 6");
$stmt->execute();
$best = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<section class="section">
    <h2>Più Venduti</h2>
    <div class="row">
        <?php foreach ($best as $p): ?>
            <div class="col-lg-4 col-sm-6 mb-4">
                <div class="card h-100">
                    <img src="../uploads/<?= htmlspecialchars($p['immagine']); ?>" class="card-img-top" alt="<?= htmlspecialchars($p['nome']); ?>">
                    <div class="card-body">
                        <img src="/LTDW-project/images/prod_yugioh.png" alt="box yugioh">
                        <h5 class="card-title"><?= htmlspecialchars($p['nome']); ?></h5>
                        <p class="card-text">€ <?= number_format($p['prezzo'], 2, ',', '.'); ?></p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>