<?php
// C:\xampp\htdocs\LTDW-project\pages\collezione.php

$configPath = __DIR__ . '/../include/config.inc.php';
if (!file_exists($configPath)) {
    // Gestione errore grave se il file di configurazione non esiste
    header('Location: /error_page.php?code=config_missing'); // Reindirizza a una pagina di errore generica
    exit();
}
require_once $configPath; // Questo rende l'array $config disponibile

// Inclusione dell'header DOPO che la sessione è stata avviata e BASE_URL è definito
include __DIR__ . '/header.php';

// Controlla se l'utente è loggato
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/pages/auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id']; // ID dell'utente loggato

// Accedi alle credenziali dal global $config array
if (!isset($config['dbms']['localhost']['host'], $config['dbms']['localhost']['user'], $config['dbms']['localhost']['passwd'], $config['dbms']['localhost']['dbname'])) {
     die("Errore: Credenziali database incomplete nel file di configurazione.");
}

$db_host = $config['dbms']['localhost']['host'];
$db_user = $config['dbms']['localhost']['user'];
$db_passwd = $config['dbms']['localhost']['passwd'];
$db_name = $config['dbms']['localhost']['dbname'];

// Connessione al database
$conn = new mysqli(
    $db_host,
    $db_user,
    $db_passwd,
    $db_name
);

if ($conn->connect_error) {
    die("Connessione al database fallita: " . $conn->connect_error);
}

// Abilita la reportistica degli errori MySQLi (utile per il debug)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

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

// 2. Recupera tutte le carte "singole" disponibili
// Ora filtriamo per il campo 'tipo_oggetto' nella tabella 'oggetto'
// E recuperiamo il 'nome_categoria' dalla tabella 'categoria_oggetto'
$available_cards = [];
$sql_available_cards = "
    SELECT
        o.id_oggetto,
        o.nome_oggetto AS name,
        o.desc_oggetto AS description,
        r.nome_rarita AS rarity,
        r.colore AS rarity_color,
        i.nome_img AS image_filename,
        co.nome_categoria AS game_category
    FROM
        oggetto o
    JOIN
        rarita r ON o.fk_rarita = r.id_rarita
    LEFT JOIN
        immagine i ON o.id_oggetto = i.fk_oggetto
    JOIN
        categoria_oggetto co ON o.fk_categoria_oggetto = co.id_categoria
    WHERE
        o.tipo_oggetto = 'Carta Singola'
    ORDER BY
        o.nome_oggetto ASC;
";
// Non è più necessario preparare la query con bind_param per 'Carta Singola' se è un valore fisso nella WHERE.
$result_available = $conn->query($sql_available_cards);

if ($result_available) {
    while ($row = $result_available->fetch_assoc()) {
        // 'game_type' ora viene direttamente da 'nome_categoria'
        $row['game_type'] = $row['game_category']; 
        $available_cards[$row['id_oggetto']] = $row;
    }
} else {
    die("Errore nel recupero delle carte disponibili: " . $conn->error);
}

// 3. Recupera le carte possedute dall'utente
$user_collection = [];
$sql_user_cards = "SELECT fk_oggetto, quantita_ogg FROM oggetto_utente WHERE fk_utente = ?";
$stmt_user_cards = $conn->prepare($sql_user_cards);
$stmt_user_cards->bind_param("i", $user_id);
$stmt_user_cards->execute();
$result_user_cards = $stmt_user_cards->get_result();

while ($row = $result_user_cards->fetch_assoc()) {
    $user_collection[$row['fk_oggetto']] = $row['quantita_ogg'];
}
$stmt_user_cards->close();

$conn->close();

// Prepara i dati per la visualizzazione
$ygo_cards_data = [];
$pk_cards_data = [];

foreach ($available_cards as $card_id => $card) {
    $card['obtained'] = isset($user_collection[$card_id]) && $user_collection[$card_id] > 0;
    $card['quantity'] = $user_collection[$card_id] ?? 0;
    
    // Costruisci il percorso completo dell'immagine.
    $card['image_url'] = (defined('BASE_URL') ? BASE_URL : '') . 'images/' . $card['image_filename'];

    // Usa 'game_type' (che è il nome della categoria) per popolare gli array
    if ($card['game_type'] === 'Yu-Gi-Oh!') {
        $ygo_cards_data[] = $card;
    } elseif ($card['game_type'] === 'Pokémon') {
        $pk_cards_data[] = $card;
    }
    // Puoi aggiungere altri elseif per altre categorie se necessario
}
?>

<main class="background-custom">
    <div>
        <div class="container">
            <h1 class="fashion_taverage mb-5">La mia Collezione</h1>

            <div class="rarity-filter-section mb-5">
                <h3 class="filter-title">Filtra per Rarità:</h3>
                <div class="filter-buttons-container">
                    <button class="btn btn-filter active" data-rarity="all">Tutte</button>
                    <?php foreach ($rarities_db as $id => $rarity_name): ?>
                        <button class="btn btn-filter" data-rarity="<?php echo strtolower(str_replace(' ', '-', $rarity_name)); ?>"><?php echo htmlspecialchars($rarity_name); ?></button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card-category-section mb-5" id="yu-gi-oh-collection">
                <h2 class="category-title mb-4">Collezione Yu-Gi-Oh!</h2>
                <div class="row card-grid">
                    <?php
                    foreach ($ygo_cards_data as $card):
                        $rarity_class = strtolower(str_replace(' ', '-', $card['rarity']));
                        $border_color = $card['rarity_color'] ?? '#ccc'; // Usa il colore dal DB
                        ?>
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-4 card-item <?php echo $rarity_class; ?>" data-game="yu-gi-oh" data-rarity="<?php echo $rarity_class; ?>">
                            <div class="card-box <?php echo $card['obtained'] ? '' : 'not-obtained'; ?>" style="border: 3px solid <?php echo htmlspecialchars($border_color); ?>;">
                                <?php if ($card['obtained']): ?>
                                    <?php if (isset($card['image_url']) && !empty($card['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($card['image_url']); ?>" alt="<?php echo htmlspecialchars($card['name']); ?>" class="card-img-top">
                                    <?php endif; ?>
                                    <div class="card-info">
                                        <h4 class="card-name"><?php echo htmlspecialchars($card['name']); ?></h4>
                                        <p class="card-description"><?php echo htmlspecialchars($card['description']); ?> (Rarità: <?php echo htmlspecialchars($card['rarity']); ?>)</p>
                                        <div class="card-quantity">Quantità: <span><?php echo htmlspecialchars($card['quantity']); ?></span></div>
                                    </div>
                                <?php else: ?>
                                    <div class="card-empty">
                                        <?php if (isset($card['image_url']) && !empty($card['image_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($card['image_url']); ?>" alt="<?php echo htmlspecialchars($card['name']); ?>" class="card-img-top greyed-out">
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
                    foreach ($pk_cards_data as $card):
                        $rarity_class = strtolower(str_replace(' ', '-', $card['rarity']));
                        $border_color = $card['rarity_color'] ?? '#ccc'; // Usa il colore dal DB
                        ?>
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-4 card-item <?php echo $rarity_class; ?>" data-game="pokemon" data-rarity="<?php echo $rarity_class; ?>">
                            <div class="card-box <?php echo $card['obtained'] ? '' : 'not-obtained'; ?>" style="border: 3px solid <?php echo htmlspecialchars($border_color); ?>;">
                                <?php if ($card['obtained']): ?>
                                    <?php if (isset($card['image_url']) && !empty($card['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($card['image_url']); ?>" alt="<?php echo htmlspecialchars($card['name']); ?>" class="card-img-top">
                                    <?php endif; ?>
                                    <div class="card-info">
                                        <h4 class="card-name"><?php echo htmlspecialchars($card['name']); ?></h4>
                                        <p class="card-description"><?php echo htmlspecialchars($card['description']); ?> (Rarità: <?php echo htmlspecialchars($card['rarity']); ?>)</p>
                                        <div class="card-quantity">Quantità: <span><?php echo htmlspecialchars($card['quantity']); ?></span></div>
                                    </div>
                                <?php else: ?>
                                    <div class="card-empty">
                                        <?php if (isset($card['image_url']) && !empty($card['image_url'])): ?>
                                            <img src="<?php echo htmlspecialchars($card['image_url']); ?>" alt="<?php echo htmlspecialchars($card['name']); ?>" class="card-img-top greyed-out">
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
                    <form id="addCardForm" action="<?php echo (defined('BASE_URL') ? BASE_URL : '') . '/action/agg_carta_collezione.php'; ?>" method="post">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="cardName" class="form-label">Nome Carta</label>
                                <input type="text" class="form-control" id="cardName" name="card_name" placeholder="Es. Mago Nero" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label for="cardQuantity" class="form-label">Quantità</label>
                            <input type="number" class="form-control" id="cardQuantity" name="card_quantity" value="1" min="0" required>
                            <small class="form-text text-muted">Inserisci la quantità di questa carta che possiedi.</small>
                        </div>
                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" value="1" id="cardObtained" name="card_obtained" checked>
                            <label class="form-check-label" for="cardObtained">
                                Carta ottenuta (seleziona se hai già questa carta)
                            </label>
                        </div>
                        <button type="submit" class="btn btn-add-card">Aggiungi Carta alla Collezione</button>
                    </form>
                    <?php if (isset($_GET['add_status'])): ?>
                        <div class="alert mt-3 <?php echo $_GET['add_status'] == 'success' ? 'alert-success' : 'alert-danger'; ?>">
                            <?php echo htmlspecialchars($_GET['add_message']); ?>
                        </div>
                    <?php endif; ?>
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
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 50); // Small delay to trigger transition
                } else {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(10px)';
                    setTimeout(() => {
                        card.style.display = 'none';
                    }, 500); // Hide after transition
                }
            });
        });
    });

    // Rimuovi l'alert dopo un po'
    const alert = document.querySelector('.alert');
    if (alert) {
        setTimeout(() => {
            alert.remove();
        }, 5000); // Rimuovi l'alert dopo 5 secondi
    }
});
</script>