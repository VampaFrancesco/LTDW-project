<?php
// pages/catalog.php

// Includi l'header (che include BASE_URL e la navbar)
include __DIR__ . '/header.php';

// Dati di esempio per le Mystery Box
$mystery_boxes = [
    [
        'id' => 1,
        'name' => 'Mystery Box Drago Mistico Yu-Gi-Oh!',
        'game' => 'yu-gi-oh',
        'description' => 'Contiene 3 buste di espansione Yu-Gi-Oh! casuali, 1 carta promo esclusiva e accessori a tema Drago.',
        'price' => 29.99,
        'available' => true,
        'image' => 'ygo_mystery_box_1.png'
    ],
    [
        'id' => 2,
        'name' => 'Mystery Box Collezione Leggendaria Pokémon',
        'game' => 'pokemon',
        'description' => 'Include 2 buste di espansione Pokémon rare, 1 carta olografica garantita e adesivi Pokémon.',
        'price' => 39.99,
        'available' => true,
        'image' => 'pk_mystery_box_1.png'
    ],
    [
        'id' => 3,
        'name' => 'Mystery Box Duellante Oscuro Yu-Gi-Oh!',
        'game' => 'yu-gi-oh',
        'description' => 'Focalizzata su carte e accessori per i duellanti più oscuri. Potrebbe contenere carte Bandito Keith.',
        'price' => 24.99,
        'available' => false, // Esaurita
        'image' => 'ygo_mystery_box_2.png'
    ],
    [
        'id' => 4,
        'name' => 'Mystery Box Starter Set Pokémon',
        'game' => 'pokemon',
        'description' => 'Perfetta per i nuovi allenatori! Contiene un mazzo precostituito, dadi e un tappetino da gioco.',
        'price' => 19.99,
        'available' => true,
        'image' => 'pk_mystery_box_2.png'
    ],
    [
        'id' => 5,
        'name' => 'Mystery Box Antiche Divinità Yu-Gi-Oh!',
        'game' => 'yu-gi-oh',
        'description' => 'Un tuffo nel passato con carte iconiche e rare. Per veri intenditori e collezionisti.',
        'price' => 49.99,
        'available' => true,
        'image' => 'ygo_mystery_box_3.png'
    ],
    [
        'id' => 6,
        'name' => 'Mystery Box Evoluzioni di Eevee Pokémon',
        'game' => 'pokemon',
        'description' => 'Dedicata alle diverse evoluzioni di Eevee. Troverai buste e accessori a tema Eeveelutions.',
        'price' => 34.99,
        'available' => false, // Esaurita
        'image' => 'pk_mystery_box_3.png'
    ],
];

// Mescola casualmente le box all'inizio
shuffle($mystery_boxes);

?>

<main class="background-custom">
    <div>
        <div class="container">
            <h1 class="fashion_taital mb-5">Le Nostre Mystery Box</h1>

            <div class="sorting-section mb-5 d-flex justify-content-end align-items-center">
                <label for="sortOrder" class="form-label mb-0 me-3">Ordina per:</label>
                <select class="form-select w-auto" id="sortOrder">
                    <option value="default">Casuale (Default)</option>
                    <option value="name-asc">Nome (A-Z)</option>
                    <option value="name-desc">Nome (Z-A)</option>
                    <option value="price-asc">Prezzo (Crescente)</option>
                    <option value="price-desc">Prezzo (Decrescente)</option>
                </select>
            </div>

            <div class="row" id="mysteryBoxGrid">
                <?php foreach ($mystery_boxes as $box): ?>
                    <div class="col-lg-4 col-md-6 col-sm-12 mb-4 mystery-box-item"
                         data-name="<?php echo htmlspecialchars($box['name']); ?>"
                         data-price="<?php echo htmlspecialchars($box['price']); ?>">
                        <div class="box-main <?php echo $box['available'] ? '' : 'unavailable'; ?>">
                            <div class="mystery-box-image-container">
                                <img src="<?php echo BASE_URL; ?>/images/<?php echo $box['image']; ?>"
                                     alt="<?php echo $box['name']; ?>" class="img-fluid mystery-box-img">
                                <?php if (!$box['available']): ?>
                                    <div class="unavailable-overlay">
                                        <p>ESAURITO</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="mystery-box-info">
                                <h4 class="mystery-box-name"><?php echo $box['name']; ?></h4>
                                <p class="mystery-box-description"><?php echo $box['description']; ?></p>
                                <div class="mystery-box-footer d-flex justify-content-between align-items-center mt-3">
                                    <p class="mystery-box-price"><?php echo number_format($box['price'], 2, ',', '.'); ?> €</p>
                                    <?php if ($box['available']): ?>
                                        <button class="btn btn-add-to-cart">Aggiungi al carrello</button>
                                    <?php else: ?>
                                        <button class="btn btn-notify-me" disabled>Avvisami quando disponibile</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sortSelect = document.getElementById('sortOrder');
    const mysteryBoxGrid = document.getElementById('mysteryBoxGrid');
    const boxItems = Array.from(mysteryBoxGrid.querySelectorAll('.mystery-box-item'));

    // Funzione per animazione di entrata
    function animateBoxes() {
        boxItems.forEach((item, index) => {
            item.style.opacity = '0';
            item.style.transform = 'translateY(20px)';
            setTimeout(() => {
                item.style.transition = 'opacity 0.6s ease-out, transform 0.6s ease-out';
                item.style.opacity = '1';
                item.style.transform = 'translateY(0)';
            }, index * 100); // Ritardo progressivo per un effetto "a cascata"
        });
    }

    // Esegui l'animazione all'inizio
    animateBoxes();

    sortSelect.addEventListener('change', function() {
        const order = this.value;

        boxItems.sort((a, b) => {
            const nameA = a.dataset.name.toLowerCase();
            const nameB = b.dataset.name.toLowerCase();
            const priceA = parseFloat(a.dataset.price);
            const priceB = parseFloat(b.dataset.price);

            if (order === 'name-asc') {
                return nameA.localeCompare(nameB);
            } else if (order === 'name-desc') {
                return nameB.localeCompare(nameA);
            } else if (order === 'price-asc') {
                return priceA - priceB;
            } else if (order === 'price-desc') {
                return priceB - priceA;
            }
            // Per 'default', non fare nulla (l'ordine iniziale è casuale)
            return 0;
        });

        // Rimuovi tutte le box esistenti dalla griglia
        while (mysteryBoxGrid.firstChild) {
            mysteryBoxGrid.removeChild(mysteryBoxGrid.firstChild);
        }

        // Aggiungi le box ordinate con animazione di uscita e rientro
        boxItems.forEach(item => {
            // Animazione di uscita rapida prima di riposizionare
            item.style.opacity = '0';
            item.style.transform = 'translateY(-20px)';
            item.style.transition = 'opacity 0.3s ease-in, transform 0.3s ease-in';
            
            // Re-append dopo un breve ritardo per consentire l'animazione di uscita
            setTimeout(() => {
                mysteryBoxGrid.appendChild(item);
                // Resetta lo stato per l'animazione di entrata
                item.style.opacity = '0';
                item.style.transform = 'translateY(20px)';
                // Attiva l'animazione di entrata
                setTimeout(() => {
                    item.style.transition = 'opacity 0.6s ease-out, transform 0.6s ease-out';
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0)';
                }, 50); // Piccolo ritardo per assicurare che il browser riconosca il cambio di stato
            }, 300); // Questo ritardo dovrebbe essere maggiore della transizione di uscita
        });

        if (order === 'default') {
            // Per "Casuale", ricarica la pagina per ottenere un nuovo shuffle PHP (soluzione semplice per demo)
            // In un ambiente reale, faresti una nuova richiesta AJAX per ottenere un ordine casuale dal server.
             window.location.reload();
        }
    });
});
</script>