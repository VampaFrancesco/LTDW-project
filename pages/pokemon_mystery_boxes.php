<?php
require_once __DIR__ . '/../include/session_manager.php';
require_once __DIR__ . '/../include/config.inc.php';

include __DIR__ . '/header.php';

// Connessione database
$db_config = $config['dbms']['localhost'];
$conn = new mysqli(
    $db_config['host'],
    $db_config['user'],
    $db_config['passwd'],
    $db_config['dbname']
);

if ($conn->connect_error) {
    die("Errore di connessione: " . $conn->connect_error);
}

// Funzione per ottenere il percorso dell'immagine in base all'ID dell'oggetto o della box
function getImagePathById($itemId, $itemType, $conn) {
    if ($itemType === 'funko_pop') {
        $fkColumn = 'fk_oggetto';
    } elseif ($itemType === 'mystery_box') {
        $fkColumn = 'fk_mystery_box';
    } else {
        return BASE_URL . '/images/mystery_box_pokemon.png'; // Tipo non supportato
    }

    $stmt = $conn->prepare("SELECT nome_img FROM immagine WHERE {$fkColumn} = ? LIMIT 1");
    if ($stmt === false) {
        // Gestione errore preparazione
        error_log("Errore nella preparazione della query: " . $conn->error);
        return BASE_URL . '/images/mystery_box_pokemon.png';
    }

    $stmt->bind_param('i', $itemId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return BASE_URL . '/images/' . htmlspecialchars($row['nome_img']);
    }

    return BASE_URL . '/images/mystery_box_pokemon.png';
}

// Query per recuperare le Mystery Box Pokémon.
$query_mystery_box = "SELECT
    mb.id_box as id,
    mb.nome_box as name,
    mb.desc_box as description,
    mb.prezzo_box as price,
    mb.quantita_box,
    r.nome_rarita as rarity_name,
    r.colore as rarity_color
FROM mystery_box mb
INNER JOIN categoria_oggetto co ON mb.fk_categoria_oggetto = co.id_categoria
LEFT JOIN rarita r ON mb.fk_rarita = r.id_rarita
WHERE co.nome_categoria = 'Pokémon'
  AND co.tipo_oggetto = 'Mystery Box'
ORDER BY mb.id_box";

$result_mystery_box = $conn->query($query_mystery_box);
$mystery_boxes = [];
if ($result_mystery_box->num_rows > 0) {
    while ($row = $result_mystery_box->fetch_assoc()) {
        $mystery_boxes[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'price' => floatval($row['price']),
            'stock' => $row['quantita_box'],
            'available' => $row['quantita_box'] > 0,
            'image_url' => getImagePathById($row['id'], 'mystery_box', $conn),
            'rarity_name' => $row['rarity_name'],
            'rarity_color' => $row['rarity_color']
        ];
    }
}
$no_boxes = empty($mystery_boxes);

// Recupera le rarità per i filtri
$query_rarita = "SELECT id_rarita, nome_rarita FROM rarita ORDER BY ordine";
$result_rarita = $conn->query($query_rarita);
$rarities = [];
if ($result_rarita->num_rows > 0) {
    while ($row = $result_rarita->fetch_assoc()) {
        $rarities[] = $row;
    }
}

$conn->close();

?>
<main class="background-custom">
    <div class="container">
        <h1 class="fashion_taital mb-5">Mystery Box Pokémon</h1>

        <div class="d-flex justify-content-between align-items-start mb-4">
            <h2 class="category-title mb-0">Mystery Box</h2>
            <div class="filter-dropdown-container">
                <div class="dropdown mb-2">
                    <button class="btn btn-secondary dropdown-toggle" type="button" id="rarityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        Filtra per rarità
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="rarityDropdown">
                        <li><a class="dropdown-item active" href="#" data-rarity="all">Tutte</a></li>
                        <?php foreach ($rarities as $rarity): ?>
                            <li><a class="dropdown-item" href="#" data-rarity="<?php echo htmlspecialchars($rarity['nome_rarita']); ?>">
                                <?php echo htmlspecialchars($rarity['nome_rarita']); ?>
                            </a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="dropdown">
                    <button class="btn btn-secondary dropdown-toggle" type="button" id="priceDropdownMystery" data-bs-toggle="dropdown" aria-expanded="false">
                        Filtra per prezzo
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="priceDropdownMystery">
                        <li><a class="dropdown-item active" href="#" data-price="all">Tutti</a></li>
                        <li><a class="dropdown-item" href="#" data-price="under10">&lt; 10€</a></li>
                        <li><a class="dropdown-item" href="#" data-price="10-25">10-25€</a></li>
                        <li><a class="dropdown-item" href="#" data-price="25-50">25-50€</a></li>
                        <li><a class="dropdown-item" href="#" data-price="over50">&gt; 50€</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" data-price="asc">Prezzo crescente</a></li>
                        <li><a class="dropdown-item" href="#" data-price="desc">Prezzo decrescente</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="row" id="mysteryBoxGrid">
            <?php if ($no_boxes): ?>
                <div class="col-12">
                    <div class="alert alert-info text-center" role="alert">
                        <i class="bi bi-info-circle me-2"></i>
                        <h4>Nessuna Mystery Box disponibile</h4>
                        <p>Al momento non ci sono Mystery Box nel catalogo. Torna più tardi per nuove sorprese!</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($mystery_boxes as $box): ?>
                    <div class="col-lg-4 col-md-6 col-sm-12 mb-4 mystery-box-item" 
                        style="display: block !important; opacity: 1 !important; visibility: visible !important;"
                        data-name="<?php echo htmlspecialchars($box['name']); ?>"
                        data-price="<?php echo htmlspecialchars($box['price']); ?>"
                        data-rarity="<?php echo htmlspecialchars($box['rarity_name']); ?>">
                        <div class="box-main <?php echo $box['available'] ? '' : 'unavailable'; ?>">
                            <a href="#" class="item-link" data-bs-toggle="modal" data-bs-target="#boxModal_<?php echo $box['id']; ?>">
                                <div class="mystery-box-image-container position-relative">
                                    <!-- Bottone Wishlist -->
                                    <?php if (SessionManager::isLoggedIn()): ?>
                                        <button class="wishlist-btn"
                                                data-item-id="<?php echo $box['id']; ?>"
                                                data-item-type="box"
                                                title="Aggiungi alla wishlist">
                                            <i class="bi bi-heart"></i>
                                        </button>
                                    <?php endif; ?>

                                    <img src="<?php echo htmlspecialchars($box['image_url']); ?>"
                                         alt="<?php echo htmlspecialchars($box['name']); ?>"
                                         class="img-fluid mystery-box-img">
                                    <?php if (!$box['available']): ?>
                                        <div class="unavailable-overlay">
                                            <p>ESAURITO</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="mystery-box-info">
                                    <h4 class="mystery-box-name"><?php echo htmlspecialchars($box['name']); ?></h4>
                                    <p class="mystery-box-description"><?php echo htmlspecialchars($box['description']); ?></p>
                                    <div class="d-flex justify-content-center align-items-center mb-3">
                                        <span class="badge rounded-pill" style="background-color: <?php echo htmlspecialchars($box['rarity_color']); ?>;">
                                            <?php echo htmlspecialchars($box['rarity_name']); ?>
                                        </span>
                                    </div>
                                    <?php if ($box['available'] && $box['stock'] <= 5): ?>
                                        <div class="stock-warning">
                                            <i class="bi bi-exclamation-triangle"></i>
                                            Solo <?php echo $box['stock']; ?> rimasti!
                                        </div>
                                    <?php endif; ?>
                                    <div class="mystery-box-footer d-flex justify-content-between align-items-center mt-3">
                                        <p class="mystery-box-price">€<?php echo number_format($box['price'], 2); ?></p>
                                        <span class="info-link-text">Premi e aggiungilo alla squadra!</span>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="modal fade" id="boxModal_<?php echo $box['id']; ?>" tabindex="-1" aria-labelledby="boxModalLabel_<?php echo $box['id']; ?>" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="boxModalLabel_<?php echo $box['id']; ?>"><?php echo htmlspecialchars($box['name']); ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <img src="<?php echo htmlspecialchars($box['image_url']); ?>" alt="<?php echo htmlspecialchars($box['name']); ?>" class="img-fluid mb-3">
                                    <p><?php echo htmlspecialchars($box['description']); ?></p>
                                    <p><strong>Prezzo:</strong> €<?php echo number_format($box['price'], 2); ?></p>
                                    <p class="stock-info"><strong>Disponibilità:</strong> <?php echo $box['stock']; ?> pezzi</p>
                                    <form action="<?php echo BASE_URL; ?>/action/add_to_cart.php" method="POST">
                                        <input type="hidden" name="id_prodotto" value="<?php echo $box['id']; ?>">
                                        <input type="hidden" name="nome_prodotto" value="<?php echo htmlspecialchars($box['name']); ?>">
                                        <input type="hidden" name="prezzo" value="<?php echo $box['price']; ?>">
                                        <input type="hidden" name="tipo" value="mystery_box">
                                        <input type="hidden" name="redirect_url" value="<?php echo BASE_URL; ?>/pages/pokemon_mystery_boxes.php">
                                        <div class="input-group mb-3">
                                            <label for="quantityBox_<?php echo $box['id']; ?>" class="input-group-text">Quantità</label>
                                            <input type="number" name="quantita" id="quantityBox_<?php echo $box['id']; ?>" class="form-control" value="1" min="1" max="<?php echo $box['stock']; ?>">
                                        </div>
                                        <button type="submit" class="btn btn-add-to-cart w-100" <?php echo $box['available'] ? '' : 'disabled'; ?>>Aggiungi al carrello</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php include __DIR__ . '/footer.php'; ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded, inizializzazione filtri...');
        
        const mysteryBoxGrid = document.getElementById('mysteryBoxGrid');

        // Debug: controlla se gli elementi esistono
        console.log('Mystery Box Grid:', mysteryBoxGrid);

        // Forza la visibilità di tutte le card all'avvio
        const allItems = document.querySelectorAll('.mystery-box-item');
        allItems.forEach(item => {
            item.style.display = 'block';
            item.style.opacity = '1';
            item.style.visibility = 'visible';
            console.log('Item reso visibile:', item);
        });

        const rarityDropdownMenu = document.getElementById('rarityDropdown')?.nextElementSibling;
        const priceDropdownMysteryMenu = document.getElementById('priceDropdownMystery')?.nextElementSibling;

        if (rarityDropdownMenu) {
            rarityDropdownMenu.addEventListener('click', function(e) {
                if (e.target.classList.contains('dropdown-item')) {
                    e.preventDefault();
                    rarityDropdownMenu.querySelectorAll('.dropdown-item').forEach(item => item.classList.remove('active'));
                    e.target.classList.add('active');
                    document.getElementById('rarityDropdown').textContent = e.target.textContent;
                    applyMysteryBoxFilters();
                }
            });
        }

        if (priceDropdownMysteryMenu) {
            priceDropdownMysteryMenu.addEventListener('click', function(e) {
                if (e.target.classList.contains('dropdown-item')) {
                    e.preventDefault();
                    priceDropdownMysteryMenu.querySelectorAll('.dropdown-item').forEach(item => item.classList.remove('active'));
                    e.target.classList.add('active');
                    document.getElementById('priceDropdownMystery').textContent = e.target.textContent;
                    applyMysteryBoxFilters();
                }
            });
        }

        function applyMysteryBoxFilters() {
            const selectedRarity = rarityDropdownMenu?.querySelector('.dropdown-item.active')?.dataset.rarity || 'all';
            const selectedPriceRange = priceDropdownMysteryMenu?.querySelector('.dropdown-item.active')?.dataset.price || 'all';
            filterAndSortItems(mysteryBoxGrid.querySelectorAll('.mystery-box-item'), mysteryBoxGrid, selectedRarity, selectedPriceRange);
        }

        function filterAndSortItems(items, grid, rarityValue, priceRangeValue) {
            console.log('Applicazione filtri:', { rarityValue, priceRangeValue, itemsCount: items.length });
            
            let sortedItems = Array.from(items);

            if (rarityValue !== 'all') {
                sortedItems = sortedItems.filter(item => item.dataset.rarity === rarityValue);
            }

            if (priceRangeValue === 'asc' || priceRangeValue === 'desc') {
                sortedItems.sort((a, b) => {
                    const priceA = parseFloat(a.dataset.price);
                    const priceB = parseFloat(b.dataset.price);
                    return priceRangeValue === 'asc' ? priceA - priceB : priceB - priceA;
                });
            } else if (priceRangeValue !== 'all') {
                sortedItems = sortedItems.filter(item => {
                    const itemPrice = parseFloat(item.dataset.price);
                    if (priceRangeValue === 'under10') return itemPrice < 10;
                    if (priceRangeValue === '10-25') return itemPrice >= 10 && itemPrice <= 25;
                    if (priceRangeValue === '25-50') return itemPrice > 25 && itemPrice <= 50;
                    if (priceRangeValue === 'over50') return itemPrice > 50;
                    return true;
                });
            }

            // Remove previous no-items message if it exists
            const existingNoItemsMessage = grid.querySelector('.alert');
            if (existingNoItemsMessage) {
                existingNoItemsMessage.parentNode.removeChild(existingNoItemsMessage);
            }

            // Hide all items FIRST
            items.forEach(item => {
                item.style.display = 'none';
                item.style.opacity = '0';
            });
            
            if (sortedItems.length > 0) {
                // Show filtered items with explicit styling
                sortedItems.forEach(item => {
                    item.style.display = 'block';
                    item.style.opacity = '1';
                    item.style.visibility = 'visible';
                });
                console.log('Items mostrati:', sortedItems.length);
            } else {
                const noItemsMessage = document.createElement('div');
                noItemsMessage.className = 'col-12';
                noItemsMessage.innerHTML = `
                    <div class="alert alert-info text-center" role="alert">
                        <i class="bi bi-info-circle me-2"></i>
                        <h4>Nessun elemento trovato</h4>
                        <p>Nessun articolo corrisponde ai filtri selezionati.</p>
                    </div>
                `;
                grid.appendChild(noItemsMessage);
            }
        }

        // Debug finale: verifica lo stato delle card
        setTimeout(() => {
            const visibleCards = document.querySelectorAll('.mystery-box-item:not([style*="display: none"])');
            console.log('Card visibili dopo inizializzazione:', visibleCards.length);
            visibleCards.forEach((card, index) => {
                console.log(`Card ${index + 1}:`, {
                    display: window.getComputedStyle(card).display,
                    opacity: window.getComputedStyle(card).opacity,
                    visibility: window.getComputedStyle(card).visibility
                });
            });
        }, 1000);
    });
</script>