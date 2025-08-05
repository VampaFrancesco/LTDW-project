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

// Query per recuperare le Mystery Box dal database
$query = "SELECT 
    id_box as id,
    nome_box as name,
    desc_box as description, 
    prezzo_box as price,
    quantita_box,
    CASE WHEN quantita_box > 0 THEN 1 ELSE 0 END as available
FROM mystery_box 
ORDER BY id_box";

$result = $conn->query($query);
$mystery_boxes = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Genera un nome immagine basato sul nome della box
        $image_name = strtolower(str_replace([' ', '-'], '_', $row['name'])) . '.png';

        $mystery_boxes[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'price' => floatval($row['price']),
            'available' => $row['available'] == 1,
            'stock' => $row['quantita_box'],
            'image' => $image_name
        ];
    }
}

$conn->close();

// Se non ci sono Mystery Box nel database, mostra un messaggio
if (empty($mystery_boxes)) {
    $no_boxes = true;
} else {
    $no_boxes = false;
    // Applica ordinamento casuale di default
    shuffle($mystery_boxes);
}

?>

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
                             data-name="<?php echo htmlspecialchars($box['name']); ?>"
                             data-price="<?php echo htmlspecialchars($box['price']); ?>"
                             data-stock="<?php echo htmlspecialchars($box['stock']); ?>">
                            <div class="box-main <?php echo $box['available'] ? '' : 'unavailable'; ?>">
                                <div class="mystery-box-image-container">
                                    <img src="<?php echo BASE_URL; ?>../images/<?php echo $image_name; ?>"
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

                                    <?php if ($box['available'] && $box['stock'] <= 5): ?>
                                        <div class="stock-warning">
                                            <i class="bi bi-exclamation-triangle"></i>
                                            Solo <?php echo $box['stock']; ?> rimasti!
                                        </div>
                                    <?php endif; ?>

                                    <div class="mystery-box-footer d-flex justify-content-between align-items-center mt-3">
                                        <p class="mystery-box-price">€<?php echo number_format($box['price'], 2); ?></p>
                                        <?php if ($box['available']): ?>
                                            <form action="<?php echo BASE_URL; ?>/action/add_to_cart.php" method="POST" class="d-inline">
                                                <input type="hidden" name="id_prodotto" value="<?php echo $box['id']; ?>">
                                                <input type="hidden" name="nome_prodotto" value="<?php echo htmlspecialchars($box['name']); ?>">
                                                <input type="hidden" name="prezzo" value="<?php echo $box['price']; ?>">
                                                <input type="hidden" name="quantita" value="1">
                                                <input type="hidden" name="tipo" value="mystery_box">
                                                <input type="hidden" name="redirect_url" value="<?php echo $_SERVER['REQUEST_URI']; ?>">
                                                <button type="submit" class="btn btn-add-to-cart">
                                                    <i class="bi bi-cart-plus"></i> Aggiungi al carrello
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button class="btn btn-notify-me" disabled>
                                                <i class="bi bi-bell"></i> Avvisami quando disponibile
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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
                    }, 50);
                }, 300);
            });

            if (order === 'default') {
                window.location.reload();
            }
        });
    });
</script>