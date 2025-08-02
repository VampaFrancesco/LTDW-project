<?php
include __DIR__ . '/header.php';

// Array delle rarità aggiornato
$rarities = [
    'Comune',
    'Rara',
    'Super Rara',
    'Epica',
    'Mitica',
    'Leggendaria'
];

// Array dei tipi di gioco disponibili per il form di aggiunta (now unused for the form but kept if needed elsewhere)
$game_types = [
    'Yu-Gi-Oh!',
    'Pokémon'
];

?>

<main class="background-custom">
    <div>
        <div class="container">
            <h1 class="fashion_taital mb-5">La mia Collezione</h1>

            <div class="rarity-filter-section mb-5">
                <h3 class="filter-title">Filtra per Rarità:</h3>
                <div class="filter-buttons-container">
                    <button class="btn btn-filter active" data-rarity="all">Tutte</button>
                    <?php foreach ($rarities as $rarity): ?>
                        <button class="btn btn-filter" data-rarity="<?php echo strtolower(str_replace(' ', '-', $rarity)); ?>"><?php echo $rarity; ?></button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card-category-section mb-5" id="yu-gi-oh-collection">
                <h2 class="category-title mb-4">Collezione Yu-Gi-Oh!</h2>
                <div class="row card-grid">
                    <?php
                    // Esempi di 6 carte Yu-Gi-Oh con le nuove rarità, quantità e URL delle immagini
                    $ygo_cards_data = [
                        ['id' => 1, 'name' => 'Drago Bianco Occhi Blu', 'description' => 'Un drago leggendario la cui potenza distruttiva è incontenibile.', 'rarity' => 'Leggendaria', 'obtained' => true, 'quantity' => 3, 'image_url' => 'assets/images/ygo_blue_eyes.jpg'],
                        ['id' => 2, 'name' => 'Mago Nero', 'description' => 'Il più grande di tutti i maghi in termini di attacco e difesa.', 'rarity' => 'Mitica', 'obtained' => true, 'quantity' => 1, 'image_url' => 'assets/images/ygo_dark_magician.jpg'],
                        ['id' => 3, 'name' => 'Kuriboh', 'description' => 'Una piccola creatura pelosa che può proteggerti da grandi danni.', 'rarity' => 'Comune', 'obtained' => true, 'quantity' => 5, 'image_url' => 'assets/images/ygo_kuriboh.jpg'],
                        ['id' => 4, 'name' => 'Guerriero Bassa Fusione', 'description' => 'Un guerriero che non si è ancora fuso, pronto per il campo.', 'rarity' => 'Rara', 'obtained' => false, 'quantity' => 0, 'image_url' => 'assets/images/ygo_fusion_substitute.jpg'], // Esempio: carta non ottenuta, ma con URL immagine
                        ['id' => 5, 'name' => 'Cavaliere Gaia, il Feroce', 'description' => 'Un cavaliere leggendario che carica in battaglia.', 'rarity' => 'Epica', 'obtained' => true, 'quantity' => 2, 'image_url' => 'assets/images/ygo_gaia.jpg'],
                        ['id' => 6, 'name' => 'Marshmallon', 'description' => 'Una creatura soffice che non può essere distrutta in battaglia.', 'rarity' => 'Super Rara', 'obtained' => false, 'quantity' => 0, 'image_url' => 'assets/images/ygo_marshmallon.jpg'], // Esempio: carta non ottenuta, ma con URL immagine
                    ];

                    foreach ($ygo_cards_data as $card):
                        $rarity_class = strtolower(str_replace(' ', '-', $card['rarity']));
                        ?>
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-4 card-item <?php echo $rarity_class; ?>" data-game="yu-gi-oh" data-rarity="<?php echo $rarity_class; ?>">
                            <div class="card-box <?php echo $card['obtained'] ? '' : 'not-obtained'; ?>">
                                <?php if ($card['obtained']): ?>
                                    <?php if (isset($card['image_url']) && !empty($card['image_url'])): ?>
                                        <img src="<?php echo $card['image_url']; ?>" alt="<?php echo $card['name']; ?>" class="card-img-top">
                                    <?php endif; ?>
                                    <div class="card-info">
                                        <h4 class="card-name"><?php echo $card['name']; ?></h4>
                                        <p class="card-description"><?php echo $card['description']; ?> (Rarità: <?php echo $card['rarity']; ?>)</p>
                                        <p class="card-number">Numero: YGO-00<?php echo $card['id']; ?></p>
                                        <div class="card-quantity">Quantità: <span><?php echo $card['quantity']; ?></span></div>
                                    </div>
                                <?php else: ?>
                                    <div class="card-empty">
                                        <?php if (isset($card['image_url']) && !empty($card['image_url'])): ?>
                                            <img src="<?php echo $card['image_url']; ?>" alt="<?php echo $card['name']; ?>" class="card-img-top greyed-out">
                                        <?php else: ?>
                                            <i class="bi bi-question-circle-fill"></i>
                                        <?php endif; ?>
                                        <p>Carta non ottenuta</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card-category-section" id="pokemon-collection">
                <h2 class="category-title mb-4">Collezione Pokémon</h2>
                <div class="row card-grid">
                    <?php
                    // Esempi di 6 carte Pokémon con le nuove rarità, quantità e URL delle immagini
                    $pk_cards_data = [
                        ['id' => 1, 'name' => 'Charizard VMAX', 'description' => 'Un Pokémon potentissimo, capace di ardere gli avversari con fiammate incandescenti.', 'rarity' => 'Leggendaria', 'obtained' => true, 'quantity' => 1, 'image_url' => 'assets/images/pk_charizard.jpg'],
                        ['id' => 2, 'name' => 'Pikachu V', 'description' => 'Il compagno leale di molti Allenatori, con un attacco fulmineo.', 'rarity' => 'Mitica', 'obtained' => true, 'quantity' => 2, 'image_url' => 'assets/images/pk_pikachu.jpg'],
                        ['id' => 3, 'name' => 'Rattata', 'description' => 'Un Pokémon comune che si trova ovunque. Molto adattabile.', 'rarity' => 'Comune', 'obtained' => true, 'quantity' => 4, 'image_url' => '../images/rattata.png'],
                        ['id' => 4, 'name' => 'Snorlax', 'description' => 'Un Pokémon che mangia e dorme molto, bloccando spesso le strade.', 'rarity' => 'Rara', 'obtained' => false, 'quantity' => 0, 'image_url' => '../images/snorlax.png'],
                        ['id' => 5, 'name' => 'Mewtwo VSTAR', 'description' => 'Un Pokémon leggendario creato dalla manipolazione genetica.', 'rarity' => 'Epica', 'obtained' => true, 'quantity' => 1, 'image_url' => 'assets/images/pk_mewtwo.jpg'],
                        ['id' => 6, 'name' => 'Jigglypuff', 'description' => 'Un Pokémon canterino che fa addormentare chiunque lo ascolti.', 'rarity' => 'Super Rara', 'obtained' => false, 'quantity' => 0, 'image_url' => 'assets/images/pk_Jigglypuff.jpg'],
                    ];

                    foreach ($pk_cards_data as $card):
                        $rarity_class = strtolower(str_replace(' ', '-', $card['rarity']));
                        ?>
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-4 card-item <?php echo $rarity_class; ?>" data-game="pokemon" data-rarity="<?php echo $rarity_class; ?>">
                            <div class="card-box <?php echo $card['obtained'] ? '' : 'not-obtained'; ?>">
                                <?php if ($card['obtained']): ?>
                                    <?php if (isset($card['image_url']) && !empty($card['image_url'])): ?>
                                        <img src="<?php echo $card['image_url']; ?>" alt="<?php echo $card['name']; ?>" class="card-img-top">
                                    <?php endif; ?>
                                    <div class="card-info">
                                        <h4 class="card-name"><?php echo $card['name']; ?></h4>
                                        <p class="card-description"><?php echo $card['description']; ?> (Rarità: <?php echo $card['rarity']; ?>)</p>
                                        <p class="card-number">Numero: PKM-00<?php echo $card['id']; ?></p>
                                        <div class="card-quantity">Quantità: <span><?php echo $card['quantity']; ?></span></div>
                                    </div>
                                <?php else: ?>
                                    <div class="card-empty">
                                        <?php if (isset($card['image_url']) && !empty($card['image_url'])): ?>
                                            <img src="<?php echo $card['image_url']; ?>" alt="<?php echo $card['name']; ?>" class="card-img-top greyed-out">
                                        <?php else: ?>
                                            <i class="bi bi-question-circle-fill"></i>
                                        <?php endif; ?>
                                        <p>Carta non ottenuta</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="add-card-section mb-5">
                <h2 class="category-title mb-4">Aggiungi una Nuova Carta</h2>
                <div class="add-card-form-container">
                    <form id="addCardForm" action="#" method="post">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="cardName" class="form-label">Nome Carta</label>
                                <input type="text" class="form-control" id="cardName" placeholder="Es. Mago Nero" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label for="cardQuantity" class="form-label">Quantità</label>
                            <input type="number" class="form-control" id="cardQuantity" value="1" min="0" required>
                            <small class="form-text text-muted">Inserisci la quantità di questa carta che possiedi.</small>
                        </div>
                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" value="" id="cardObtained" checked>
                            <label class="form-check-label" for="cardObtained">
                                Carta ottenuta (seleziona se hai già questa carta)
                            </label>
                        </div>
                        <button type="submit" class="btn btn-add-card">Aggiungi Carta alla Collezione</button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterButtons = document.querySelectorAll('.btn-filter');
    const cardItems = document.querySelectorAll('.card-item');

    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            filterButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');

            const selectedRarity = this.dataset.rarity;

            cardItems.forEach(card => {
                const cardRarity = card.dataset.rarity;
                
                if (selectedRarity === 'all' || cardRarity === selectedRarity) {
                    card.style.display = 'block';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                } else {
                    card.style.display = 'none';
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(10px)';
                }
            });
        });
    });
});
</script>