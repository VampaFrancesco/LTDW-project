<?php
$pdo = new PDO('mysql:host=localhost;dbname=boxomnia', 'root', 'root');
$stmt = $pdo->prepare("SELECT * FROM prodotto ORDER BY id DESC LIMIT 5");
$stmt->execute();
$novita = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div id="novitaCarousel" class="carousel slide mb-5" data-ride="carousel">
    <div class="carousel-inner">
        <?php foreach ($novita as $i => $p): ?>
            <div class="carousel-item <?= $i === 0 ? 'active' : ''; ?>">
                <img src="../uploads/<?= htmlspecialchars($p['immagine']); ?>" class="d-block w-100" alt="<?= htmlspecialchars($p['nome']); ?>">
                <div class="carousel-caption d-none d-md-block">
                    <h5><?= htmlspecialchars($p['nome']); ?></h5>
                    <p><?= htmlspecialchars($p['descrizione']); ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <a class="carousel-control-prev" href="#novitaCarousel" role="button" data-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="sr-only">Previous</span>
    </a>
    <a class="carousel-control-next" href="#novitaCarousel" role="button" data-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="sr-only">Next</span>
    </a>
</div>