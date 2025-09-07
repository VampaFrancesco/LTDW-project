<?php
// Include le configurazioni e le classi necessarie
require_once __DIR__.'/../include/session_manager.php';
require_once __DIR__.'/../include/config.inc.php';
SessionManager::requireLogin();
include 'header.php';

// Recupera i dati utente
$nome_completo = trim(SessionManager::get('user_nome', 'Utente') . ' ' . SessionManager::get('user_cognome', '')) ?: 'Utente';

// Connessione al database e recupero dei dati
try {
    $pdo = new PDO('mysql:host=localhost;dbname=boxomnia', 'admin', 'admin');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Recupero di TUTTI i contenuti dalla tabella contenuti_modificabili
    $stmt_contenuti = $pdo->prepare("
        SELECT id_contenuto, testo_contenuto 
        FROM contenuti_modificabili 
        WHERE id_contenuto IN (
            'testo_benvenuto', 'titolo_mystery_box', 'titolo_funko_pop', 
            'titolo_community', 'avviso_promozioni', 'community_classifica_titolo',
            'community_scambi_titolo', 'community_collezione_titolo',
            'community_classifica_desc', 'community_scambi_desc', 'community_collezione_desc'
        )
        ORDER BY data_modifica DESC
    ");
    $stmt_contenuti->execute();
    $contenuti_raw = $stmt_contenuti->fetchAll(PDO::FETCH_ASSOC);

    // Organizza i contenuti in array associativo
    $contenuti = [];
    foreach ($contenuti_raw as $contenuto) {
        $contenuti[$contenuto['id_contenuto']] = $contenuto['testo_contenuto'];
    }

    // Contenuti di default nel caso non siano presenti nel database
    $contenuti_default = [
            'testo_benvenuto' => "Esplora l'universo delle Mystery Box, Funko Pop e Carte da Collezione. Trova il tuo prossimo tesoro e unisciti alla community più appassionata del web.",
            'titolo_mystery_box' => '✨ Nuove Mystery Box',
            'titolo_funko_pop' => '🎉 Novità Funko POP',
            'titolo_community' => '🤝 La Community di BoxOmnia',
            'avviso_promozioni' => 'È possibile visionare e approfittare degli sconti solamente nelle novità essendo promozioni a tempo limitate',
            'community_classifica_titolo' => 'Classifica',
            'community_scambi_titolo' => 'Scambi di Carte',
            'community_collezione_titolo' => 'La Mia Collezione',
            'community_classifica_desc' => 'Sfida gli altri collezionisti e scala la classifica per diventare il numero uno!',
            'community_scambi_desc' => 'Scambia le tue carte doppie e completa la tua collezione con altri appassionati.',
            'community_collezione_desc' => 'Gestisci e mostra le tue carte Pokémon e Yu-Gi-Oh! alla community.'
    ];

    // Unisce contenuti DB con default (se mancanti)
    foreach ($contenuti_default as $chiave => $valore_default) {
        if (!isset($contenuti[$chiave])) {
            $contenuti[$chiave] = $valore_default;
        }
    }

    // Testo di benvenuto specifico (mantenendo la logica esistente)
    $testo_benvenuto = htmlspecialchars($contenuti['testo_benvenuto']);

    // Recupero delle novità Mystery Box con immagini (solo quelle non scadute)
    $stmt_novita_box = $pdo->prepare("
        SELECT mb.*, nb.sconto_novita, nb.desc_novita, nb.fine_novita, img.nome_img
        FROM novita_box AS nb
        JOIN mystery_box AS mb ON nb.fk_mystery_box = mb.id_box
        LEFT JOIN immagine AS img ON img.fk_mystery_box = mb.id_box
        WHERE nb.fine_novita IS NULL OR nb.fine_novita > NOW()
        ORDER BY nb.data_novita DESC
    ");
    $stmt_novita_box->execute();
    $novita_box = $stmt_novita_box->fetchAll(PDO::FETCH_ASSOC);

    // Recupero delle novità Oggetti con immagini (solo quelle non scadute)
    $stmt_novita_oggetto = $pdo->prepare("
        SELECT o.*, no.novita_sconto, no.novita_desc, no.novita_fine, img.nome_img
        FROM novita_oggetto AS no
        JOIN oggetto AS o ON no.fk_oggetto = o.id_oggetto
        LEFT JOIN immagine AS img ON img.fk_oggetto = o.id_oggetto
        WHERE no.novita_fine IS NULL OR no.novita_fine > NOW()
        ORDER BY no.novita_data DESC
    ");
    $stmt_novita_oggetto->execute();
    $novita_oggetti = $stmt_novita_oggetto->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "<p class='text-danger'>Errore di connessione al database: " . htmlspecialchars($e->getMessage()) . "</p>";
    $novita_box = [];
    $novita_oggetti = [];
    // Contenuti di default in caso di errore
    $contenuti = $contenuti_default;
    $testo_benvenuto = htmlspecialchars($contenuti['testo_benvenuto']);
}

// Funzione per ottenere le immagini dalla directory
function getCarouselImages($imageDir = '/LTDW-project/carosello/') {
    $images = [];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    // Lista predefinita di immagini come fallback
    $defaultImages = [
            '/LTDW-project/carosello/yugioh.png',
            '/LTDW-project/carosello/pokemon.png',
            '/LTDW-project/carosello/boxomnia.png'
    ];

    $fullPath = $_SERVER['DOCUMENT_ROOT'] . $imageDir;

    if (is_dir($fullPath)) {
        $files = scandir($fullPath);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($extension, $allowedExtensions)) {
                    $images[] = $imageDir . $file;
                }
            }
        }
    }

    return !empty($images) ? $images : $defaultImages;
}

$carouselImages = getCarouselImages();
?>

    <main class="home-page-container">
        <section class="welcome-card-section">
            <div class="welcome-card">
                <h1>Bentornato, <?= htmlspecialchars($nome_completo); ?>!</h1>
                <p><?= $testo_benvenuto; ?></p>
            </div>
        </section>

        <section class="photo-carousel-section">
            <div class="photo-carousel-container" id="photoCarousel">
                <?php foreach ($carouselImages as $index => $image): ?>
                    <div class="photo-slide <?= $index === 0 ? 'active' : ''; ?>">
                        <img src="<?= htmlspecialchars($image); ?>" alt="Slide <?= $index + 1; ?>">
                    </div>
                <?php endforeach; ?>

                <div class="carousel-indicators">
                    <?php foreach ($carouselImages as $index => $image): ?>
                        <span class="indicator <?= $index === 0 ? 'active' : ''; ?>" onclick="goToSlide(<?= $index; ?>)"></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <div class="content-wrapper">
            <section class="product-slider-section">
                <h2 class="section-title"><?= htmlspecialchars($contenuti['titolo_mystery_box']); ?></h2>
                <div class="carousel-container">
                    <button class="carousel-nav prev" onclick="slideProducts('mysteryBoxSlider', -1)" id="mysteryBoxPrev">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <div class="product-slider" id="mysteryBoxSlider">
                        <?php if (!empty($novita_box)): ?>
                            <?php foreach ($novita_box as $box):
                                $disponibile = ($box['quantita_box'] > 0);
                                $prezzo_scontato = $box['prezzo_box'] - ($box['prezzo_box'] * ($box['sconto_novita'] / 100));
                                $immagine_path = !empty($box['nome_img']) ? '/LTDW-project/images/' . $box['nome_img'] : '/LTDW-project/images/default.png';

                                // Formattiamo la data di fine novità
                                $fine_novita_text = 'In corso';
                                if (!empty($box['fine_novita']) && $box['fine_novita'] !== null) {
                                    $fine_novita_date = new DateTime($box['fine_novita']);
                                    $fine_novita_text = 'Fino al ' . $fine_novita_date->format('d/m/Y');
                                }
                                ?>
                                <div class="item">
                                    <div class="product-card <?= !$disponibile ? 'out-of-stock' : ''; ?>">
                                        <?php if (!$disponibile): ?>
                                            <div class="badge-overlay">Esaurito</div>
                                        <?php endif; ?>
                                        <?php if ($box['sconto_novita'] > 0): ?>
                                            <span class="discount-badge-left">-<?= htmlspecialchars($box['sconto_novita']); ?>%</span>
                                        <?php endif; ?>
                                        <img src="<?= htmlspecialchars($immagine_path); ?>"
                                             alt="<?= htmlspecialchars($box['nome_box'] ?? 'Prodotto'); ?>" class="product-img">
                                        <div class="product-info">
                                            <h3 class="product-name"><?= htmlspecialchars($box['nome_box']); ?></h3>
                                            <div class="promotion-end">
                                                <span class="promotion-text"><?= htmlspecialchars($fine_novita_text); ?></span>
                                            </div>
                                            <div class="price-container">
                                                <?php if ($box['sconto_novita'] > 0): ?>
                                                    <span class="old-price">€ <?= number_format($box['prezzo_box'], 2, ',', '.'); ?></span>
                                                    <span class="product-price">€ <?= number_format($prezzo_scontato, 2, ',', '.'); ?></span>
                                                <?php else: ?>
                                                    <span class="product-price">€ <?= number_format($box['prezzo_box'], 2, ',', '.'); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <form action="<?= BASE_URL; ?>/action/add_to_cart.php" method="POST">
                                                <input type="hidden" name="id_prodotto" value="<?= htmlspecialchars($box['id_box']); ?>">
                                                <input type="hidden" name="nome_prodotto" value="<?= htmlspecialchars($box['nome_box']); ?>">
                                                <input type="hidden" name="prezzo" value="<?= htmlspecialchars($prezzo_scontato); ?>">
                                                <input type="hidden" name="tipo" value="mystery_box">
                                                <button type="submit" class="btn btn-add-to-cart" <?= !$disponibile ? 'disabled' : ''; ?>>
                                                    <?= $disponibile ? 'Aggiungi al carrello' : 'Esaurito'; ?>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="item">
                                <p class="text-info text-center w-100">Nessuna Mystery Box in evidenza al momento.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button class="carousel-nav next" onclick="slideProducts('mysteryBoxSlider', 1)" id="mysteryBoxNext">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
                <div class="promotion-warning">
                    <p><strong>Attenzione:</strong> <?= htmlspecialchars($contenuti['avviso_promozioni']); ?></p>
                </div>
            </section>

            <hr>

            <section class="product-slider-section">
                <h2 class="section-title"><?= htmlspecialchars($contenuti['titolo_funko_pop']); ?></h2>
                <div class="carousel-container">
                    <button class="carousel-nav prev" onclick="slideProducts('oggettiSlider', -1)" id="oggettiPrev">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <div class="product-slider" id="oggettiSlider">
                        <?php if (!empty($novita_oggetti)): ?>
                            <?php foreach ($novita_oggetti as $oggetto):
                                $disponibile = ($oggetto['quant_oggetto'] > 0);
                                $prezzo_scontato = $oggetto['prezzo_oggetto'] - ($oggetto['prezzo_oggetto'] * ($oggetto['novita_sconto'] / 100));
                                $immagine_path = !empty($oggetto['nome_img']) ? '/LTDW-project/images/' . $oggetto['nome_img'] : '/LTDW-project/images/default.png';

                                // Formattiamo la data di fine novità
                                $fine_novita_text = 'In corso';
                                if (!empty($oggetto['novita_fine']) && $oggetto['novita_fine'] !== null) {
                                    $fine_novita_date = new DateTime($oggetto['novita_fine']);
                                    $fine_novita_text = 'Fino al ' . $fine_novita_date->format('d/m/Y');
                                }
                                ?>
                                <div class="item">
                                    <div class="product-card <?= !$disponibile ? 'out-of-stock' : ''; ?>">
                                        <?php if (!$disponibile): ?>
                                            <div class="badge-overlay">Esaurito</div>
                                        <?php endif; ?>
                                        <?php if ($oggetto['novita_sconto'] > 0): ?>
                                            <span class="discount-badge-left">-<?= htmlspecialchars($oggetto['novita_sconto']); ?>%</span>
                                        <?php endif; ?>
                                        <img src="<?= htmlspecialchars($immagine_path); ?>"
                                             alt="<?= htmlspecialchars($oggetto['nome_oggetto'] ?? 'Oggetto'); ?>" class="product-img">
                                        <div class="product-info">
                                            <h3 class="product-name"><?= htmlspecialchars($oggetto['nome_oggetto']); ?></h3>
                                            <div class="promotion-end">
                                                <span class="promotion-text"><?= htmlspecialchars($fine_novita_text); ?></span>
                                            </div>
                                            <div class="price-container">
                                                <?php if ($oggetto['novita_sconto'] > 0): ?>
                                                    <span class="old-price">€ <?= number_format($oggetto['prezzo_oggetto'], 2, ',', '.'); ?></span>
                                                    <span class="product-price">€ <?= number_format($prezzo_scontato, 2, ',', '.'); ?></span>
                                                <?php else: ?>
                                                    <span class="product-price">€ <?= number_format($oggetto['prezzo_oggetto'], 2, ',', '.'); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <form action="<?= BASE_URL; ?>/action/add_to_cart.php" method="POST">
                                                <input type="hidden" name="id_prodotto" value="<?= htmlspecialchars($oggetto['id_oggetto']); ?>">
                                                <input type="hidden" name="nome_prodotto" value="<?= htmlspecialchars($oggetto['nome_oggetto']); ?>">
                                                <input type="hidden" name="prezzo" value="<?= htmlspecialchars($prezzo_scontato); ?>">
                                                <input type="hidden" name="tipo" value="oggetto">
                                                <button type="submit" class="btn btn-add-to-cart" <?= !$disponibile ? 'disabled' : ''; ?>>
                                                    <?= $disponibile ? 'Aggiungi al carrello' : 'Esaurito'; ?>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="item">
                                <p class="text-info text-center w-100">Nessun nuovo oggetto da visualizzare al momento.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button class="carousel-nav next" onclick="slideProducts('oggettiSlider', 1)" id="oggettiNext">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
                <div class="promotion-warning">
                    <p><strong>Attenzione:</strong> <?= htmlspecialchars($contenuti['avviso_promozioni']); ?></p>
                </div>
            </section>

            <hr>

            <section class="community-section">
                <h2 class="section-title"><?= htmlspecialchars($contenuti['titolo_community']); ?></h2>
                <div class="community-grid">
                    <a href="<?= BASE_URL; ?>/pages/classifica.php" class="community-card">
                        <i class="bi bi-chart-line community-icon"></i>
                        <h3><?= htmlspecialchars($contenuti['community_classifica_titolo']); ?></h3>
                        <p><?= htmlspecialchars($contenuti['community_classifica_desc']); ?></p>
                    </a>
                    <a href="<?= BASE_URL; ?>/pages/scambi.php" class="community-card">
                        <i class="bi bi-exchange-alt community-icon"></i>
                        <h3><?= htmlspecialchars($contenuti['community_scambi_titolo']); ?></h3>
                        <p><?= htmlspecialchars($contenuti['community_scambi_desc']); ?></p>
                    </a>
                    <a href="<?= BASE_URL; ?>/pages/collezione.php" class="community-card">
                        <i class="bi bi-dragon community-icon"></i>
                        <h3><?= htmlspecialchars($contenuti['community_collezione_titolo']); ?></h3>
                        <p><?= htmlspecialchars($contenuti['community_collezione_desc']); ?></p>
                    </a>
                </div>
            </section>
        </div>
    </main>
<?php include 'footer.php'; ?>