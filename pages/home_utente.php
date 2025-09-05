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
            <p>Esplora l'universo delle Mystery Box, Funko Pop e Carte da Collezione. Trova il tuo prossimo tesoro e unisciti alla community più appassionata del web.</p>
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
            <h2 class="section-title">✨ Nuove Mystery Box</h2>
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
                <p><strong>Attenzione:</strong> è possibile visionare e approfittare degli sconti solamente nelle novità essendo promozioni a tempo limitate</p>
            </div>
        </section>

        <hr>

        <section class="product-slider-section">
            <h2 class="section-title">🎉 Novità Funko POP</h2>
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
                <p><strong>Attenzione:</strong> è possibile visionare e approfittare degli sconti solamente nelle novità essendo promozioni a tempo limitate</p>
            </div>
        </section>

        <hr>

        <section class="community-section">
            <h2 class="section-title">🤝 La Community di BoxOmnia</h2>
            <div class="community-grid">
                <a href="<?= BASE_URL; ?>/pages/classifica.php" class="community-card">
                    <i class="bi bi-chart-line community-icon"></i>
                    <h3>Classifica</h3>
                    <p>Sfida gli altri collezionisti e scala la classifica per diventare il numero uno!</p>
                </a>
                <a href="<?= BASE_URL; ?>/pages/scambi.php" class="community-card">
                    <i class="bi bi-exchange-alt community-icon"></i>
                    <h3>Scambi di Carte</h3>
                    <p>Scambia le tue carte doppie e completa la tua collezione con altri appassionati.</p>
                </a>
                <a href="<?= BASE_URL; ?>/pages/collezione.php" class="community-card">
                    <i class="bi bi-dragon community-icon"></i>
                    <h3>La Mia Collezione</h3>
                    <p>Gestisci e mostra le tue carte Pokémon e Yu-Gi-Oh! alla community.</p>
                </a>
            </div>
        </section>
    </div>
</main>

<script>
// Carosello automatico delle foto
let currentSlide = 0;
const slides = document.querySelectorAll('.photo-slide');
const indicators = document.querySelectorAll('.indicator');
const totalSlides = slides.length;

function showSlide(index) {
    // Rimuovi classe active da tutti gli slide e indicatori
    slides.forEach(slide => slide.classList.remove('active'));
    indicators.forEach(indicator => indicator.classList.remove('active'));
    
    // Aggiungi classe active al slide e indicatore corrente
    slides[index].classList.add('active');
    indicators[index].classList.add('active');
    
    currentSlide = index;
}

function nextSlide() {
    const next = (currentSlide + 1) % totalSlides;
    showSlide(next);
}

function goToSlide(index) {
    showSlide(index);
}

// Avvia il carosello automatico
setInterval(nextSlide, 4000); // Cambia slide ogni 4 secondi

// Slider prodotti
const sliders = {};

function initSlider(sliderId) {
    const slider = document.getElementById(sliderId);
    if (!slider) return;
    
    const items = slider.querySelectorAll('.item');
    const containerWidth = slider.parentElement.offsetWidth - 120; // Sottrae spazio per i bottoni
    const firstItem = items[0];
    const itemStyles = window.getComputedStyle(firstItem);
    const itemWidth = firstItem.offsetWidth + parseInt(itemStyles.marginRight || 0); // larghezza reale con margine
    const visibleItems = Math.floor(containerWidth / itemWidth);

    
    sliders[sliderId] = {
        currentIndex: 0,
        totalItems: items.length,
        visibleItems: Math.max(1, visibleItems),
        maxIndex: Math.max(0, items.length - Math.max(1, visibleItems)),
        itemWidth: itemWidth
    };
    
    // Reset della posizione
    slider.style.transform = 'translateX(0px)';
    sliders[sliderId].currentIndex = 0;
    
    updateSliderButtons(sliderId);
    
    console.log(`Slider ${sliderId} initialized:`, sliders[sliderId]);
}

function slideProducts(sliderId, direction) {
    const sliderData = sliders[sliderId];
    if (!sliderData) {
        console.log(`Slider data not found for ${sliderId}`);
        return;
    }
    
    const slider = document.getElementById(sliderId);
    if (!slider) {
        console.log(`Slider element not found: ${sliderId}`);
        return;
    }
    
    const newIndex = sliderData.currentIndex + direction;
    
    console.log(`Sliding ${sliderId}: current=${sliderData.currentIndex}, new=${newIndex}, max=${sliderData.maxIndex}`);
    
    if (newIndex >= 0 && newIndex <= sliderData.maxIndex) {
        sliderData.currentIndex = newIndex;
        const translateX = -(newIndex * sliderData.itemWidth);
        slider.style.transform = `translateX(${translateX}px)`;
        
        updateSliderButtons(sliderId);
        console.log(`Slider moved to position: ${translateX}px`);
    } else {
        console.log(`Movement blocked: newIndex=${newIndex}, maxIndex=${sliderData.maxIndex}`);
    }
}

function updateSliderButtons(sliderId) {
    const sliderData = sliders[sliderId];
    if (!sliderData) return;
    
    const prevBtn = document.getElementById(sliderId.replace('Slider', 'Prev'));
    const nextBtn = document.getElementById(sliderId.replace('Slider', 'Next'));
    
    if (prevBtn) {
        prevBtn.disabled = sliderData.currentIndex === 0;
        prevBtn.style.opacity = sliderData.currentIndex === 0 ? '0.3' : '1';
    }
    
    if (nextBtn) {
        nextBtn.disabled = sliderData.currentIndex >= sliderData.maxIndex;
        nextBtn.style.opacity = sliderData.currentIndex >= sliderData.maxIndex ? '0.3' : '1';
    }
    
    console.log(`Buttons updated for ${sliderId}: prev disabled=${sliderData.currentIndex === 0}, next disabled=${sliderData.currentIndex >= sliderData.maxIndex}`);
}

// Inizializza gli slider al caricamento della pagina
document.addEventListener('DOMContentLoaded', function() {
    initSlider('mysteryBoxSlider');
    initSlider('oggettiSlider');
    
    // Reinizializza gli slider quando la finestra viene ridimensionata
    window.addEventListener('resize', function() {
        setTimeout(() => {
            initSlider('mysteryBoxSlider');
            initSlider('oggettiSlider');
        }, 100);
    });
});

// Supporto per il touch sui dispositivi mobili
let startX = 0;
let currentX = 0;
let activeSlider = null;

document.addEventListener('touchstart', function(e) {
    const slider = e.target.closest('.product-slider');
    if (slider) {
        startX = e.touches[0].clientX;
        activeSlider = slider.id;
    }
});

document.addEventListener('touchmove', function(e) {
    if (!activeSlider) return;
    e.preventDefault();
    currentX = e.touches[0].clientX;
});

document.addEventListener('touchend', function(e) {
    if (!activeSlider) return;
    
    const diffX = startX - currentX;
    const threshold = 50;
    
    if (Math.abs(diffX) > threshold) {
        if (diffX > 0) {
            slideProducts(activeSlider, 1); // Slide a destra
        } else {
            slideProducts(activeSlider, -1); // Slide a sinistra
        }
    }
    
    activeSlider = null;
});
</script>

<?php include 'footer.php'; ?>