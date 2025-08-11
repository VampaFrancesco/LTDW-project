<?php 
// 1. PRIMA di qualsiasi output: include SessionManager e controlli 
require_once __DIR__ . '/../include/session_manager.php'; 
require_once __DIR__ . '/../include/config.inc.php'; 

// 2. Richiedi autenticazione (fa il redirect automaticamente se non loggato) 
SessionManager::requireLogin(); 

// 3. ORA è sicuro includere l'header 
include __DIR__ . '/header.php'; 

// 4. Recupera i dati utente 
$user_id = SessionManager::getUserId(); 

// Accedi alle credenziali dal global $config array 
if (!isset(
    $config['dbms']['localhost']['host'], 
    $config['dbms']['localhost']['user'], 
    $config['dbms']['localhost']['passwd'], 
    $config['dbms']['localhost']['dbname']
)) { 
    die("Errore: Credenziali database incomplete nel file di configurazione."); 
} 

$db_host   = $config['dbms']['localhost']['host']; 
$db_user   = $config['dbms']['localhost']['user']; 
$db_passwd = $config['dbms']['localhost']['passwd']; 
$db_name   = $config['dbms']['localhost']['dbname']; 

// Connessione al database 
$conn = new mysqli($db_host, $db_user, $db_passwd, $db_name); 

if ($conn->connect_error) { 
    die("Connessione al database fallita: " . $conn->connect_error); 
} 

// Abilita la reportistica degli errori MySQLi (utile per il debug) 
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); 

// CODICE AGGIORNATO: Non più necessario recuperare i nomi per la datalist
// Rimosso il codice per $card_names

// 1. Recupera le rarità e i loro colori 
$rarities_db = []; 
$rarity_colors = []; 
$sql_rarities = "SELECT id_rarita, nome_rarita, colore FROM rarita ORDER BY ordine ASC"; 
$result_rarities = $conn->query($sql_rarities); 
if ($result_rarities) { 
    while ($row = $result_rarities->fetch_assoc()) { 
        $rarities_db[$row['id_rarita']] = $row['nome_rarita']; 
        $rarity_colors[$row['nome_rarita']] = $row['colore']; 
    } 
} else { 
    error_log("Errore nel recupero delle rarità: " . $conn->error); 
} 

// 2. Recupera tutte le carte "singole" disponibili, inclusi i dati da oggetto_collezione 
// e i dati dell'utente su quelle carte 
$available_cards = []; 
$sql_available_cards = " 
    SELECT 
        o.id_oggetto, 
        o.nome_oggetto AS name, 
        o.desc_oggetto AS description, 
        r.nome_rarita AS rarity, 
        r.colore AS rarity_color, 
        i.nome_img AS image_filename, 
        co.nome_categoria AS game_category, 
        co.tipo_oggetto AS object_type, 
        oc.numero_carta AS collection_number, 
        oc.valore_stimato AS estimated_value, 
        ou.quantita_ogg AS quantity -- Quantità posseduta dall'utente 
    FROM 
        oggetto o 
    JOIN 
        rarita r ON o.fk_rarita = r.id_rarita 
    LEFT JOIN 
        immagine i ON o.id_oggetto = i.fk_oggetto 
    JOIN 
        categoria_oggetto co ON o.fk_categoria_oggetto = co.id_categoria 
    LEFT JOIN 
        oggetto_collezione oc ON o.id_oggetto = oc.fk_oggetto 
    LEFT JOIN 
        oggetto_utente ou ON o.id_oggetto = ou.fk_oggetto AND ou.fk_utente = ? 
    WHERE 
        co.tipo_oggetto = 'Carta Singola' 
    ORDER BY 
        o.nome_oggetto ASC 
"; 

$stmt_available_cards = $conn->prepare($sql_available_cards); 
if (!$stmt_available_cards) { 
    die("Errore nella preparazione della query delle carte disponibili: " . $conn->error); 
} 

$stmt_available_cards->bind_param("i", $user_id); 
$stmt_available_cards->execute(); 
$result_available = $stmt_available_cards->get_result(); 

if ($result_available) { 
    while ($row = $result_available->fetch_assoc()) { 
        $row['game_type'] = $row['game_category']; 
        $row['obtained'] = ($row['quantity'] > 0); 
        $available_cards[$row['id_oggetto']] = $row; 
    } 
} else { 
    die("Errore nel recupero delle carte disponibili: " . $conn->error); 
} 

$stmt_available_cards->close(); 
$conn->close(); 

// Prepara i dati per la visualizzazione 
$ygo_cards_data = []; 
$pk_cards_data = []; 

foreach ($available_cards as $card_id => $card) { 
    $base_url_prefix = defined('BASE_URL') ? BASE_URL : ''; 
    $image_filename_to_use = $card['image_filename'] ?? 'default_product1.jpg'; 
    $card['image_url'] = $base_url_prefix . '/images/' . $image_filename_to_use; 

    if ($card['game_type'] === 'Yu-Gi-Oh!') { 
        $ygo_cards_data[] = $card; 
    } elseif ($card['game_type'] === 'Pokémon') { 
        $pk_cards_data[] = $card; 
    } 
} 
?>

<main class="background-custom">
    <div>
        <div class="container">
            <div class="collection-header">
                <h1 class="fashion_taverage mb-5">La mia Collezione</h1>
                <div class="category-nav-filter-container">
                    <a href="#yu-gi-oh-collection" class="btn btn-filter-nav">Yu-Gi-Oh!</a>
                    <a href="#pokemon-collection" class="btn btn-filter-nav">Pokémon</a>
                    <a href="#add-card-section" class="btn btn-filter-nav">Aggiungi Carta</a>
                </div>
            </div>

            <?php if (isset($_GET['add_status'])): ?>
                <div class="alert mt-3 <?php echo $_GET['add_status'] == 'success' ? 'alert-success' : 'alert-danger'; ?>">
                    <?php echo htmlspecialchars($_GET['add_message']); ?>
                </div>
            <?php endif; ?>

            <div class="rarity-filter-section mb-5">
                <h3 class="filter-title">Filtra per Rarità:</h3>
                <div class="filter-buttons-container">
                    <button class="btn btn-filter active" data-rarity="all">Tutte</button>
                    <?php foreach ($rarities_db as $id => $rarity_name): ?>
                        <button class="btn btn-filter"
                                data-rarity="<?php echo strtolower(str_replace(' ', '-', $rarity_name)); ?>"
                                style="border-color: <?php echo htmlspecialchars($rarity_colors[$rarity_name] ?? '#ccc'); ?>;">
                            <?php echo htmlspecialchars($rarity_name); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card-category-section mb-5" id="yu-gi-oh-collection">
                <h2 class="category-title mb-4">Collezione Yu-Gi-Oh!</h2>
                <div class="row card-grid">
                    <?php foreach ($ygo_cards_data as $card): ?>
                        <?php 
                        $rarity_class = strtolower(str_replace(' ', '-', $card['rarity']));
                        $border_color = $card['rarity_color'] ?? '#ccc';
                        ?>
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-4 card-item <?php echo $rarity_class; ?>"
                             data-game="yu-gi-oh"
                             data-rarity="<?php echo $rarity_class; ?>"
                             data-card-id="<?php echo htmlspecialchars($card['id_oggetto']); ?>">
                            <div class="card-box <?php echo $card['obtained'] ? '' : 'not-obtained'; ?>"
                                 style="border: 3px solid <?php echo htmlspecialchars($border_color); ?>;">
                                <?php if ($card['obtained']): ?>
                                    <?php if (!empty($card['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($card['image_url']); ?>"
                                             alt="<?php echo htmlspecialchars($card['name']); ?>"
                                             class="card-img-top">
                                    <?php endif; ?>
                                    <div class="card-info">
                                        <h4 class="card-name"><?php echo htmlspecialchars($card['name']); ?></h4>
                                        <?php if (!empty($card['collection_number'])): ?>
                                            <p class="card-number">Numero: <?php echo htmlspecialchars($card['collection_number']); ?></p>
                                        <?php endif; ?>
                                        <?php if (!is_null($card['estimated_value'])): ?>
                                            <p class="card-value">Valore stimato: <?php echo htmlspecialchars($card['estimated_value']); ?>€</p>
                                        <?php endif; ?>
                                        <p class="card-description"><?php echo htmlspecialchars($card['description']); ?> (Rarità: <?php echo htmlspecialchars($card['rarity']); ?>)</p>
                                        <div class="card-quantity">Quantità: <span><?php echo htmlspecialchars($card['quantity']); ?></span></div>
                                    </div>
                                <?php else: ?>
                                    <div class="card-empty">
                                        <?php if (!empty($card['image_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($card['image_url']); ?>"
                                                 alt="<?php echo htmlspecialchars($card['name']); ?>"
                                                 class="card-img-top greyed-out">
                                        <?php else: ?>
                                            <i class="bi bi-question-circle-fill"></i>
                                        <?php endif; ?>
                                        <p>Carta non ottenuta</p>
                                        <?php if (!empty($card['collection_number'])): ?>
                                            <p class="card-number">Numero: <?php echo htmlspecialchars($card['collection_number']); ?></p>
                                        <?php endif; ?>
                                        <?php if (!is_null($card['estimated_value'])): ?>
                                            <p class="card-value">Valore stimato: <?php echo htmlspecialchars($card['estimated_value']); ?>€</p>
                                        <?php endif; ?>
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
                    <?php foreach ($pk_cards_data as $card): ?>
                        <?php 
                        $rarity_class = strtolower(str_replace(' ', '-', $card['rarity']));
                        $border_color = $card['rarity_color'] ?? '#ccc';
                        ?>
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-4 card-item <?php echo $rarity_class; ?>"
                             data-game="pokemon"
                             data-rarity="<?php echo $rarity_class; ?>"
                             data-card-id="<?php echo htmlspecialchars($card['id_oggetto']); ?>">
                            <div class="card-box <?php echo $card['obtained'] ? '' : 'not-obtained'; ?>"
                                 style="border: 3px solid <?php echo htmlspecialchars($border_color); ?>;">
                                <?php if ($card['obtained']): ?>
                                    <?php if (!empty($card['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($card['image_url']); ?>"
                                             alt="<?php echo htmlspecialchars($card['name']); ?>"
                                             class="card-img-top">
                                    <?php endif; ?>
                                    <div class="card-info">
                                        <h4 class="card-name"><?php echo htmlspecialchars($card['name']); ?></h4>
                                        <?php if (!empty($card['collection_number'])): ?>
                                            <p class="card-number">Numero: <?php echo htmlspecialchars($card['collection_number']); ?></p>
                                        <?php endif; ?>
                                        <?php if (!is_null($card['estimated_value'])): ?>
                                            <p class="card-value">Valore stimato: <?php echo htmlspecialchars($card['estimated_value']); ?>€</p>
                                        <?php endif; ?>
                                        <p class="card-description"><?php echo htmlspecialchars($card['description']); ?> (Rarità: <?php echo htmlspecialchars($card['rarity']); ?>)</p>
                                        <div class="card-quantity">Quantità: <span><?php echo htmlspecialchars($card['quantity']); ?></span></div>
                                    </div>
                                <?php else: ?>
                                    <div class="card-empty">
                                        <?php if (!empty($card['image_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($card['image_url']); ?>"
                                                 alt="<?php echo htmlspecialchars($card['name']); ?>"
                                                 class="card-img-top greyed-out">
                                        <?php else: ?>
                                            <i class="bi bi-question-circle-fill"></i>
                                        <?php endif; ?>
                                        <p>Carta non ottenuta</p>
                                        <?php if (!empty($card['collection_number'])): ?>
                                            <p class="card-number">Numero: <?php echo htmlspecialchars($card['collection_number']); ?></p>
                                        <?php endif; ?>
                                        <?php if (!is_null($card['estimated_value'])): ?>
                                            <p class="card-value">Valore stimato: <?php echo htmlspecialchars($card['estimated_value']); ?>€</p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="add-card-section mb-5" id="add-card-section">
                <h2 class="category-title mb-4">Aggiungi una Nuova Carta</h2>
                <div class="add-card-form-container">
                    <form id="addCardForm" action="<?php echo BASE_URL; ?>/action/agg_carta_collezione.php" method="post">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="cardName" class="form-label">Nome Carta</label>
                                <input type="text" class="form-control" id="cardName" name="card_name" placeholder="Scrivi il nome della carta..." required>
                                <small class="form-text text-muted">Inserisci il nome esatto della carta che vuoi aggiungere alla collezione.</small>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label for="cardQuantity" class="form-label">Quantità</label>
                            <input type="number" class="form-control" id="cardQuantity" name="card_quantity" value="1" min="0" required>
                            <small class="form-text text-muted">Inserisci la quantità di questa carta che possiedi.</small>
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
    document.addEventListener('DOMContentLoaded', function () {
        const filterButtons = document.querySelectorAll('.btn-filter');
        const cardItems = document.querySelectorAll('.card-item');

        filterButtons.forEach(button => {
            button.addEventListener('click', function () {
                filterButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');

                const selectedRarity = this.dataset.rarity;

                cardItems.forEach(card => {
                    const cardRarity = card.dataset.rarity;

                    if (selectedRarity === 'all' || cardRarity === selectedRarity) {
                        card.style.display = 'block';
                        setTimeout(() => {
                            card.style.opacity = '1';
                            card.style.transform = 'translateY(0)';
                        }, 50);
                    } else {
                        card.style.opacity = '0';
                        card.style.transform = 'translateY(10px)';
                        setTimeout(() => {
                            card.style.display = 'none';
                        }, 500);
                    }
                });
            });
        });

        const alert = document.querySelector('.alert');
        if (alert) {
            setTimeout(() => {
                alert.remove();
            }, 5000);
        }
    });
</script>