<?php
// C:\xampp\htdocs\LTDW-project\pages\collezione.php

// 1. Includi il file di configurazione
$configPath = __DIR__ . '/../include/config.inc.php';
if (!file_exists($configPath)) {
    // Gestione errore grave se il file di configurazione non esiste
    header('Location: /error_page.php?code=config_missing'); // Reindirizza a una pagina di errore generica
    exit();
}
require_once $configPath; // Questo rende l'array $config disponibile

// 2. Assicurati che session_start(); sia chiamato PRIMA di qualsiasi output HTML.
// Se header.php lo include, assicurati che header.php sia la PRIMA cosa che genera output.
// Altrimenti, mettilo qui:
session_start(); // <-- Assicurati che sia qui o in header.php come prima riga.

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
        ou.quantita_ogg AS quantity        -- Quantità posseduta dall'utente
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
        oggetto_utente ou ON o.id_oggetto = ou.fk_oggetto AND ou.fk_utente = ? -- JOIN con oggetto_utente per recuperare i dati specifici dell'utente
    WHERE
        co.tipo_oggetto = 'Carta Singola'
    ORDER BY
        o.nome_oggetto ASC;
";

$stmt_available_cards = $conn->prepare($sql_available_cards);
if (!$stmt_available_cards) {
    die("Errore nella preparazione della query delle carte disponibili: " . $conn->error);
}
$stmt_available_cards->bind_param("i", $user_id); // Associa l'ID utente per la LEFT JOIN
$stmt_available_cards->execute();
$result_available = $stmt_available_cards->get_result();

if ($result_available) {
    while ($row = $result_available->fetch_assoc()) {
        $row['game_type'] = $row['game_category']; 
        // Determina se la carta è ottenuta basandosi sulla quantità
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
    // Costruisci il percorso completo dell'immagine.
    $base_url_prefix = defined('BASE_URL') ? BASE_URL : ''; 
    $image_filename_to_use = $card['image_filename'] ?? 'default_card.png';
    $card['image_url'] = $base_url_prefix . '/images/' . $image_filename_to_use; 

    if ($card['game_type'] === 'Yu-Gi-Oh!') {
        $ygo_cards_data[] = $card;
    } elseif ($card['game_type'] === 'Pokémon') {
        $pk_cards_data[] = $card;
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>La mia Collezione</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="<?php echo (defined('BASE_URL') ? BASE_URL : ''); ?>/css/style.css">
    <style>
        /* Stili per le rarità */
        .rarity-filter-section .btn-filter {
            border: 2px solid transparent; /* Default */
            transition: border-color 0.3s ease;
        }
        .rarity-filter-section .btn-filter.active {
            font-weight: bold;
        }
        /* Stili specifici per i bordi dei filtri rarità (copiati dal tuo CSS precedente) */
        .rarity-filter-section .btn-filter[data-rarity="comune"].active { border-color: #A0A0A0; }
        .rarity-filter-section .btn-filter[data-rarity="rara"].active { border-color: #ADD8E6; }
        .rarity-filter-section .btn-filter[data-rarity="ultra-rara"].active { border-color: #FFD700; }
        .rarity-filter-section .btn-filter[data-rarity="segreta-rara"].active { border-color: #C0C0C0; }

        /* Stili per le card non ottenute */
        .card-box.not-obtained img.greyed-out {
            filter: grayscale(100%) brightness(50%); /* Rende l'immagine sbiadita */
            transition: filter 0.3s ease;
        }
        .card-box.not-obtained .card-empty p {
            color: #777;
        }
        .card-box.not-obtained .card-empty .card-number,
        .card-box.not-obtained .card-empty .card-value {
             color: #999;
        }
        .card-box.not-obtained .card-box {
            border-color: #555 !important; /* Bordo più scuro per le carte non ottenute */
        }
    </style>
</head>
<body>

<main class="background-custom">
    <div>
        <div class="container">
            <h1 class="fashion_taverage mb-5">La mia Collezione</h1>

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
                    <?php
                    foreach ($ygo_cards_data as $card):
                        $rarity_class = strtolower(str_replace(' ', '-', $card['rarity']));
                        $border_color = $card['rarity_color'] ?? '#ccc'; 
                        ?>
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-4 card-item <?php echo $rarity_class; ?>" 
                             data-game="yu-gi-oh" 
                             data-rarity="<?php echo $rarity_class; ?>"
                             data-card-id="<?php echo htmlspecialchars($card['id_oggetto']); ?>">
                            <div class="card-box <?php echo $card['obtained'] ? '' : 'not-obtained'; ?>" style="border: 3px solid <?php echo htmlspecialchars($border_color); ?>;">
                                <?php if ($card['obtained']): ?>
                                    <?php if (isset($card['image_url']) && !empty($card['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($card['image_url']); ?>" alt="<?php echo htmlspecialchars($card['name']); ?>" class="card-img-top">
                                    <?php endif; ?>
                                    <div class="card-info">
                                        <h4 class="card-name"><?php echo htmlspecialchars($card['name']); ?></h4>
                                        <?php if (isset($card['collection_number']) && !empty($card['collection_number'])): ?>
                                            <p class="card-number">Numero: <?php echo htmlspecialchars($card['collection_number']); ?></p>
                                        <?php endif; ?>
                                        <?php if (isset($card['estimated_value']) && !is_null($card['estimated_value'])): ?>
                                            <p class="card-value">Valore stimato: <?php echo htmlspecialchars($card['estimated_value']); ?>€</p>
                                        <?php endif; ?>
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
                                        <?php if (isset($card['collection_number']) && !empty($card['collection_number'])): ?>
                                            <p class="card-number">Numero: <?php echo htmlspecialchars($card['collection_number']); ?></p>
                                        <?php endif; ?>
                                        <?php if (isset($card['estimated_value']) && !is_null($card['estimated_value'])): ?>
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
                    <?php
                    foreach ($pk_cards_data as $card):
                        $rarity_class = strtolower(str_replace(' ', '-', $card['rarity']));
                        $border_color = $card['rarity_color'] ?? '#ccc'; 
                        ?>
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-4 card-item <?php echo $rarity_class; ?>" 
                             data-game="pokemon" 
                             data-rarity="<?php echo $rarity_class; ?>"
                             data-card-id="<?php echo htmlspecialchars($card['id_oggetto']); ?>">
                            <div class="card-box <?php echo $card['obtained'] ? '' : 'not-obtained'; ?>" style="border: 3px solid <?php echo htmlspecialchars($border_color); ?>;">
                                <?php if ($card['obtained']): ?>
                                    <?php if (isset($card['image_url']) && !empty($card['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($card['image_url']); ?>" alt="<?php echo htmlspecialchars($card['name']); ?>" class="card-img-top">
                                    <?php endif; ?>
                                    <div class="card-info">
                                        <h4 class="card-name"><?php echo htmlspecialchars($card['name']); ?></h4>
                                        <?php if (isset($card['collection_number']) && !empty($card['collection_number'])): ?>
                                            <p class="card-number">Numero: <?php echo htmlspecialchars($card['collection_number']); ?></p>
                                        <?php endif; ?>
                                        <?php if (isset($card['estimated_value']) && !is_null($card['estimated_value'])): ?>
                                            <p class="card-value">Valore stimato: <?php echo htmlspecialchars($card['estimated_value']); ?>€</p>
                                        <?php endif; ?>
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
                                        <?php if (isset($card['collection_number']) && !empty($card['collection_number'])): ?>
                                            <p class="card-number">Numero: <?php echo htmlspecialchars($card['collection_number']); ?></p>
                                        <?php endif; ?>
                                        <?php if (isset($card['estimated_value']) && !is_null($card['estimated_value'])): ?>
                                            <p class="card-value">Valore stimato: <?php echo htmlspecialchars($card['estimated_value']); ?>€</p>
                                        <?php endif; ?>
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
</body>
</html>