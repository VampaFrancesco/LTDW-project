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
        return BASE_URL . '/images/default_product1.jpg'; // Tipo non supportato
    }

    $stmt = $conn->prepare("SELECT nome_img FROM immagine WHERE {$fkColumn} = ? LIMIT 1");
    if ($stmt === false) {
        // Gestione errore preparazione
        error_log("Errore nella preparazione della query: " . $conn->error);
        return BASE_URL . '/images/default_product1.jpg';
    }

    $stmt->bind_param('i', $itemId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return BASE_URL . '/images/' . htmlspecialchars($row['nome_img']);
    }

    return BASE_URL . '/images/default_product1.jpg';
}

// Query per recuperare i Funko Pop Pokémon.
$query_funko_pop = "SELECT
    o.id_oggetto as id,
    o.nome_oggetto as name,
    o.desc_oggetto as description,
    o.prezzo_oggetto as price,
    o.quant_oggetto as stock,
    o.quant_oggetto > 0 as available,
    r.nome_rarita as rarity_name,
    r.colore as rarity_color
FROM oggetto o
INNER JOIN categoria_oggetto co ON o.fk_categoria_oggetto = co.id_categoria
LEFT JOIN rarita r ON o.fk_rarita = r.id_rarita
WHERE co.nome_categoria = 'Pokémon'
  AND co.tipo_oggetto = 'Funko Pop'
ORDER BY o.id_oggetto";

$result_funko_pop = $conn->query($query_funko_pop);
$funko_pops = [];
if ($result_funko_pop->num_rows > 0) {
    while ($row = $result_funko_pop->fetch_assoc()) {
        $funko_pops[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'price' => floatval($row['price']),
            'stock' => $row['stock'],
            'available' => $row['available'] == 1,
            'image_url' => getImagePathById($row['id'], 'funko_pop', $conn),
            'rarity_name' => $row['rarity_name'],
            'rarity_color' => $row['rarity_color']
        ];
    }
}
$no_funko_pops = empty($funko_pops);

$conn->close();

?>
<main class="background-custom">
    <div class="container">
        <h1 class="fashion_taital mb-5">Funko Pop Pokémon</h1>

        <div class="d-flex justify-content-between align-items-start mb-4">
            <h2 class="category-title mb-0">Funko Pop</h2>
            <div class="filter-dropdown-container">
                <div class="dropdown">
                    <button class="btn btn-secondary dropdown-toggle" type="button" id="priceDropdownFunko" data-bs-toggle="dropdown" aria-expanded="false">
                        Filtra per prezzo
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="priceDropdownFunko">
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

        <div class="row" id="funkoPopGrid">
            <?php if ($no_funko_pops): ?>
                <div class="col-12">
                    <div class="alert alert-info text-center" role="alert">
                        <i class="bi bi-info-circle me-2"></i>
                        <h4>Nessun Funko Pop disponibile</h4>
                        <p>Al momento non ci sono Funko Pop nel catalogo. Torna più tardi!</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($funko_pops as $funko): ?>
                    <div class="col-lg-4 col-md-6 col-sm-12 mb-4 funko-pop-item" 
                        style="display: block !important; opacity: 1 !important; visibility: visible !important;"
                        data-name="<?php echo htmlspecialchars($funko['name']); ?>"
                        data-price="<?php echo htmlspecialchars($funko['price']); ?>"
                        data-rarity="<?php echo htmlspecialchars($funko['rarity_name']); ?>">
                        <div class="box-main <?php echo $funko['available'] ? '' : 'unavailable'; ?>">
                            <a href="#" class="item-link" data-bs-toggle="modal" data-bs-target="#funkoModal_<?php echo $funko['id']; ?>">
                                <div class="mystery-box-image-container">
                                    <img src="<?php echo htmlspecialchars($funko['image_url']); ?>"
                                        alt="<?php echo htmlspecialchars($funko['name']); ?>"
                                        class="img-fluid mystery-box-img">
                                    <?php if (!$funko['available']): ?>
                                        <div class="unavailable-overlay">
                                            <p>ESAURITO</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="mystery-box-info">
                                    <h4 class="mystery-box-name"><?php echo htmlspecialchars($funko['name']); ?></h4>
                                    <p class="mystery-box-description"><?php echo htmlspecialchars($funko['description']); ?></p>
                                    <div class="d-flex justify-content-center align-items-center mb-3">
                                        <span class="badge rounded-pill" style="background-color: <?php echo htmlspecialchars($funko['rarity_color']); ?>;">
                                            <?php echo htmlspecialchars($funko['rarity_name']); ?>
                                        </span>
                                    </div>
                                    <?php if ($funko['available'] && $funko['stock'] <= 5): ?>
                                        <div class="stock-warning">
                                            <i class="bi bi-exclamation-triangle"></i>
                                            Solo <?php echo $funko['stock']; ?> rimasti!
                                        </div>
                                    <?php endif; ?>
                                    <div class="mystery-box-footer d-flex justify-content-between align-items-center mt-3">
                                        <p class="mystery-box-price">€<?php echo number_format($funko['price'], 2); ?></p>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <div class="modal fade" id="funkoModal_<?php echo $funko['id']; ?>" tabindex="-1" aria-labelledby="funkoModalLabel_<?php echo $funko['id']; ?>" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="funkoModalLabel_<?php echo $funko['id']; ?>"><?php echo htmlspecialchars($funko['name']); ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <img src="<?php echo htmlspecialchars($funko['image_url']); ?>" alt="<?php echo htmlspecialchars($funko['name']); ?>" class="img-fluid mb-3">
                                    <p><?php echo htmlspecialchars($funko['description']); ?></p>
                                    <p><strong>Prezzo:</strong> €<?php echo number_format($funko['price'], 2); ?></p>
                                    <p class="stock-info"><strong>Disponibilità:</strong> <?php echo $funko['stock']; ?> pezzi</p>
                                    <form action="<?php echo BASE_URL; ?>/action/add_to_cart.php" method="POST">
                                        <input type="hidden" name="id_prodotto" value="<?php echo $funko['id']; ?>">
                                        <input type="hidden" name="nome_prodotto" value="<?php echo htmlspecialchars($funko['name']); ?>">
                                        <input type="hidden" name="prezzo" value="<?php echo $funko['price']; ?>">
                                        <input type="hidden" name="tipo" value="funko_pop">
                                        <div class="input-group mb-3">
                                            <label for="quantityFunko_<?php echo $funko['id']; ?>" class="input-group-text">Quantità</label>
                                            <input type="number" name="quantita" id="quantityFunko_<?php echo $funko['id']; ?>" class="form-control" value="1" min="1" max="<?php echo $funko['stock']; ?>">
                                        </div>
                                        <button type="submit" class="btn btn-add-to-cart w-100" <?php echo $funko['available'] ? '' : 'disabled'; ?>>Aggiungi al carrello</button>
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
        
        const funkoPopGrid = document.getElementById('funkoPopGrid');

        // Debug: controlla se gli elementi esistono
        console.log('Funko Pop Grid:', funkoPopGrid);

        // Forza la visibilità di tutte le card all'avvio
        const allItems = document.querySelectorAll('.funko-pop-item');
        allItems.forEach(item => {
            item.style.display = 'block';
            item.style.opacity = '1';
            item.style.visibility = 'visible';
            console.log('Item reso visibile:', item);
        });

        const priceDropdownFunkoMenu = document.getElementById('priceDropdownFunko')?.nextElementSibling;

        if (priceDropdownFunkoMenu) {
            priceDropdownFunkoMenu.addEventListener('click', function(e) {
                if (e.target.classList.contains('dropdown-item')) {
                    e.preventDefault();
                    priceDropdownFunkoMenu.querySelectorAll('.dropdown-item').forEach(item => item.classList.remove('active'));
                    e.target.classList.add('active');
                    document.getElementById('priceDropdownFunko').textContent = e.target.textContent;
                    applyFunkoPopFilters();
                }
            });
        }

        function applyFunkoPopFilters() {
            const selectedPriceRange = priceDropdownFunkoMenu?.querySelector('.dropdown-item.active')?.dataset.price || 'all';
            filterAndSortItems(funkoPopGrid.querySelectorAll('.funko-pop-item'), funkoPopGrid, 'all', selectedPriceRange);
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
            const visibleCards = document.querySelectorAll('.funko-pop-item:not([style*="display: none"])');
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