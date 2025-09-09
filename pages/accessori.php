<?php
// C:\xampp\htdocs\LTDW-project\pages\accessori.php

// Includi il file di configurazione
$configPath = __DIR__ . '/../include/config.inc.php';
if (!file_exists($configPath)) {
    header('Location: ' . BASE_URL . '/error_page.php?code=config_missing');
    exit();
}
require_once $configPath;

require_once __DIR__ . '/../include/session_manager.php';

// Richiedi autenticazione (fa il redirect automaticamente se non loggato)
SessionManager::requireLogin();

// Inclusione dell'header
include __DIR__ . '/header.php';

// Connessione al database
if (!isset($config['dbms']['localhost']['host'], $config['dbms']['localhost']['user'], $config['dbms']['localhost']['passwd'], $config['dbms']['localhost']['dbname'])) {
    die("Errore: Credenziali database incomplete nel file di configurazione.");
}

$db_host = $config['dbms']['localhost']['host'];
$db_user = $config['dbms']['localhost']['user'];
$db_passwd = $config['dbms']['localhost']['passwd'];
$db_name = $config['dbms']['localhost']['dbname'];

$conn = new mysqli(
    $db_host,
    $db_user,
    $db_passwd,
    $db_name
);

if ($conn->connect_error) {
    die("Connessione al database fallita: " . $conn->connect_error);
}

// Abilita la reportistica degli errori MySQLi
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Query per recuperare tutti gli accessori
$accessori_by_type = [];
$accessory_types = ['Porta mazzi', 'Scatole porta carte', 'Proteggicarte', 'Plance di gioco'];

foreach ($accessory_types as $type) {
    $sql_accessories = "
        SELECT
            o.id_oggetto,
            o.nome_oggetto AS name,
            o.desc_oggetto AS description,
            o.prezzo_oggetto AS price,
            o.quant_oggetto AS availability,
            i.nome_img AS image_filename,
            co.nome_categoria AS category_name,
            co.tipo_oggetto AS object_type
        FROM
            oggetto o
        JOIN
            categoria_oggetto co ON o.fk_categoria_oggetto = co.id_categoria
        LEFT JOIN
            immagine i ON o.id_oggetto = i.fk_oggetto
        WHERE
            co.nome_categoria = 'Universale' AND co.tipo_oggetto = ?
        ORDER BY
            o.nome_oggetto ASC;
    ";

    $stmt = $conn->prepare($sql_accessories);
    if (!$stmt) {
        die("Errore nella preparazione della query: " . $conn->error);
    }
    $stmt->bind_param("s", $type);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['image_url'] = (defined('BASE_URL') ? BASE_URL : '') . '/images/' . ($row['image_filename'] ?? 'default_product1.jpg');
            $accessori_by_type[$type][] = $row;
        }
    } else {
        error_log("Errore nel recupero degli accessori di tipo '{$type}': " . $conn->error);
    }
    $stmt->close();
}

$conn->close();
?>

<main class="background-custom filter-container accessory-section">
    <div class="container">
        <h1 class="fashion_taital mb-5">Accessori</h1>
        
        <div class="d-flex justify-content-start align-items-start mb-4">
            <div class="d-flex flex-column align-items-end ms-auto">
                <div class="dropdown mb-2">
                    <button class="btn btn-secondary dropdown-toggle filtro-uniforme" type="button" id="categoryFilterButton" data-bs-toggle="dropdown" aria-expanded="false">
                        Filtra per categoria
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" id="category-filter-menu">
                        <li><a class="dropdown-item filter-link active-filter" href="#tutti" data-filter-type="category" data-filter-value="tutti">Tutti</a></li>
                        <?php foreach ($accessory_types as $type): ?>
                            <li><a class="dropdown-item filter-link" href="#<?php echo str_replace(' ', '_', $type); ?>" data-filter-type="category" data-filter-value="<?php echo str_replace(' ', '_', $type); ?>"><?php echo htmlspecialchars($type); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="dropdown">
                    <button class="btn btn-secondary dropdown-toggle filtro-uniforme" type="button" id="priceFilterButton" data-bs-toggle="dropdown" aria-expanded="false">
                        Filtra per prezzo
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" id="price-filter-menu">
                        <li><a class="dropdown-item filter-link active-filter" href="#" data-filter-type="price" data-filter-value="all">Tutti</a></li>
                        <li><a class="dropdown-item filter-link" href="#" data-filter-type="price" data-filter-value="<5">&lt; 5€</a></li>
                        <li><a class="dropdown-item filter-link" href="#" data-filter-type="price" data-filter-value="5-10">5-10€</a></li>
                        <li><a class="dropdown-item filter-link" href="#" data-filter-type="price" data-filter-value=">10">&gt; 10€</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item filter-link" href="#" data-filter-type="price" data-filter-value="asc">Prezzo crescente</a></li>
                        <li><a class="dropdown-item filter-link" href="#" data-filter-type="price" data-filter-value="desc">Prezzo decrescente</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="all-accessories-container" id="all-accessories-container">
            <?php foreach ($accessory_types as $type): ?>
                <div id="<?php echo str_replace(' ', '_', $type); ?>" class="accessory-category-section mb-5" data-category="<?php echo str_replace(' ', '_', $type); ?>">
                    <h2 class="category-title mb-4"><?php echo htmlspecialchars($type); ?></h2>
                    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 category-items-container">
                        <?php if (!empty($accessori_by_type[$type])): ?>
                            <?php foreach ($accessori_by_type[$type] as $accessory): ?>
                                <div class="col accessory-item" data-price="<?php echo htmlspecialchars($accessory['price'] ?? 0); ?>" data-category="<?php echo str_replace(' ', '_', $type); ?>">
                                    <div class="accessory-card card h-100"
                                         data-id="<?php echo htmlspecialchars($accessory['id_oggetto']); ?>"
                                         data-name="<?php echo htmlspecialchars($accessory['name']); ?>"
                                         data-description="<?php echo htmlspecialchars($accessory['description']); ?>"
                                         data-price="<?php echo htmlspecialchars($accessory['price']); ?>"
                                         data-availability="<?php echo htmlspecialchars($accessory['availability']); ?>"
                                         data-image-url="<?php echo htmlspecialchars($accessory['image_url']); ?>">
                                        <div class="card-img-container">
                                            <div class="card-img-container">
                                                <?php if (SessionManager::isLoggedIn()): ?>
                                                    <button class="wishlist-btn"
                                                            data-item-id="<?php echo $accessory['id_oggetto']; ?>"
                                                            data-item-type="oggetto"
                                                            title="Aggiungi alla wishlist">
                                                        <i class="bi bi-heart"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <img src="<?php echo htmlspecialchars($accessory['image_url']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($accessory['name']); ?>">
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo htmlspecialchars($accessory['name']); ?></h5>
                                            <p class="card-text text-muted"><?php echo htmlspecialchars($accessory['description']); ?></p>
                                        </div>
                                        <div class="card-footer text-center">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <p class="card-text mb-0">Disponibilità: <span class="fw-bold"><?php echo htmlspecialchars($accessory['availability'] ?? '0'); ?></span></p>
                                                <p class="card-text card-price mb-0 fs-5"><?php echo isset($accessory['price']) ? htmlspecialchars($accessory['price']) . '€' : 'Prezzo non disponibile'; ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12 empty-category-message">
    <div class="avviso-info">
        <div class="icona-avviso">i</div>
        <h2 class="titolo-avviso">Nessun prodotto trovato</h2>
        <p class="messaggio-avviso">Nessun articolo corrisponde ai filtri selezionati</p>
    </div>
</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>

<div class="modal fade" id="accessoryModal" tabindex="-1" aria-labelledby="accessoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="accessoryModalLabel">Dettagli Accessorio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const accessoryCards = document.querySelectorAll('.accessory-card');
    const accessoryModal = new bootstrap.Modal(document.getElementById('accessoryModal'));
    const filterLinks = document.querySelectorAll('.filter-link');
    const accessorySections = document.querySelectorAll('.accessory-category-section');
    const allAccessoryItems = document.querySelectorAll('.accessory-item');

    // Riferimenti ai bottoni dropdown
    const categoryDropdownButton = document.getElementById('categoryFilterButton');
    const priceDropdownButton = document.getElementById('priceFilterButton');

    let activeCategoryFilter = 'tutti';
    let activePriceFilter = 'all';

    // Funzioni per aggiornare il testo dei bottoni
    function updateCategoryButtonText(selectedText) {
        if (selectedText === 'Tutti') {
            categoryDropdownButton.textContent = 'Filtra per categoria';
        } else {
            categoryDropdownButton.textContent = selectedText;
        }
    }

    function updatePriceButtonText(selectedText) {
        if (selectedText === 'Tutti') {
            priceDropdownButton.textContent = 'Filtra per prezzo';
        } else {
            priceDropdownButton.textContent = selectedText;
        }
    }

    function applyFilters() {
        const container = document.getElementById('all-accessories-container');
        document.querySelectorAll('.empty-category-message').forEach(el => el.remove());

        // Rimuovi il contenitore di ordinamento temporaneo se esiste
        container.querySelector('#sorted-results-container')?.remove();

        let totalResults = 0;

        accessorySections.forEach(section => {
            const categoryId = section.dataset.category;
            const itemsContainer = section.querySelector('.category-items-container');
            
            // Nascondi la sezione per default
            section.style.display = 'none';
            
            // Mostra la sezione solo se il filtro categoria è "tutti" o corrisponde
            if (activeCategoryFilter === 'tutti' || categoryId === activeCategoryFilter) {
                section.style.display = 'block';

                // Filtra gli elementi che appartengono a questa sezione
                let sectionItems = Array.from(allAccessoryItems)
                    .filter(item => item.dataset.category === categoryId);
                
                // Se c'è un filtro per prezzo a intervalli, filtra gli elementi
                if (activePriceFilter !== 'all' && activePriceFilter !== 'asc' && activePriceFilter !== 'desc') {
                    sectionItems = sectionItems.filter(item => {
                        const price = parseFloat(item.dataset.price);
                        switch (activePriceFilter) {
                            case '<5': return price <5;
                            case '5-10': return price >= 5 && price <=10;
                            case '>10': return price > 10;
                        }
                    });
                }
                
                // Se c'è un filtro di ordinamento, ordina gli elementi di questa sezione
                if (activePriceFilter === 'asc' || activePriceFilter === 'desc') {
                    sectionItems.sort((a, b) => {
                        const priceA = parseFloat(a.dataset.price);
                        const priceB = parseFloat(b.dataset.price);
                        return activePriceFilter === 'asc' ? priceA - priceB : priceB - priceA;
                    });
                }
                
                // Pulisci e riaggiungi gli elementi filtrati/ordinati al contenitore della categoria
                itemsContainer.innerHTML = '';
                if (sectionItems.length > 0) {
                    sectionItems.forEach(item => {
                        item.style.display = 'block';
                        itemsContainer.appendChild(item);
                    });
                    totalResults += sectionItems.length;
                } else {
                    const emptyMessage = document.createElement('div');
                    emptyMessage.classList.add('col-12', 'empty-category-message');
                    emptyMessage.innerHTML = `
    <div class="avviso-info">
        <div class="icona-avviso">i</div>
        <h2 class="titolo-avviso">Nessun prodotto trovato</h2>
        <p class="messaggio-avviso">Nessun articolo corrisponde ai filtri selezionati</p>
    </div>
`;
                    itemsContainer.appendChild(emptyMessage);
                }
            }
        });

        // Gestisci il caso in cui "Tutti" e nessun risultato
        if (activeCategoryFilter === 'tutti' && totalResults === 0) {
            const emptyMessage = document.createElement('div');
            emptyMessage.classList.add('col-12', 'empty-category-message', 'mt-4');
            emptyMessage.innerHTML = `
    <div class="avviso-info">
        <div class="icona-avviso">i</div>
        <h2 class="titolo-avviso">Nessun prodotto trovato</h2>
        <p class="messaggio-avviso">Nessun articolo corrisponde ai filtri selezionati</p>
    </div>
`;
            container.appendChild(emptyMessage);
        }
    }

    filterLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const filterType = this.dataset.filterType;
            const filterValue = this.dataset.filterValue;
            const selectedText = this.textContent.trim();

            document.querySelectorAll(`.filter-link[data-filter-type="${filterType}"]`).forEach(el => el.classList.remove('active-filter'));
            this.classList.add('active-filter');

            if (filterType === 'category') {
                activeCategoryFilter = filterValue;
                updateCategoryButtonText(selectedText);
            } else if (filterType === 'price') {
                activePriceFilter = filterValue;
                updatePriceButtonText(selectedText);
            }

            applyFilters();
        });
    });

    const categoryScrollLinks = document.querySelectorAll('.filter-link[data-filter-type="category"]');
    categoryScrollLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href').substring(1);
            if (targetId === 'tutti') {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } else {
                const targetElement = document.getElementById(targetId);
                if (targetElement) {
                    targetElement.scrollIntoView({ behavior: 'smooth' });
                }
            }
        });
    });
    
    // Modal e Carrello
    accessoryCards.forEach(card => {
        card.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.dataset.name;
            const description = this.dataset.description;
            const price = this.dataset.price;
            const availability = this.dataset.availability;
            const imageUrl = this.dataset.imageUrl;

            const modalBody = document.querySelector('#accessoryModal .modal-body');
            modalBody.innerHTML = `
                <div class="row">
                    <div class="col-md-6 text-center">
                        <img src="${imageUrl}" class="img-fluid mb-3" alt="${name}">
                    </div>
                    <div class="col-md-6">
                        <h4>${name}</h4>
                        <p class="text-muted">${description}</p>
                        <p class="fs-4 fw-bold">${price}€</p>
                        <p class="mb-3">Disponibilità: <span class="fw-bold">${availability}</span></p>
                        <hr>
                        <form id="addToCartForm" action="<?php echo BASE_URL; ?>/action/add_to_cart.php" method="post">
                            <input type="hidden" name="id_prodotto" value="${id}">
                            <input type="hidden" name="nome_prodotto" value="${name}">
                            <input type="hidden" name="prezzo" value="${price}">
                            <input type="hidden" name="tipo" value="accessorio">
                            <input type="hidden" name="redirect_url" value="<?php echo BASE_URL; ?>/pages/accessori.php">
                            <div class="mb-3">
                                <label for="modal-quantity" class="form-label">Quantità:</label>
                                <input type="number" class="form-control" id="modal-quantity" name="quantita" value="1" min="1" max="${availability}">
                            </div>
                            <button type="submit" class="btn btn-add-to-cart w-100" ${availability <= 0 ? 'disabled' : ''}>Aggiungi al carrello</button>
                        </form>
                    </div>
                </div>
            `;
            accessoryModal.show();
        });
    });
});
</script>