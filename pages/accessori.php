<?php
// C:\xampp\htdocs\LTDW-project\pages\accessori.php

// Includi il file di configurazione
$configPath = __DIR__ . '/../include/config.inc.php';
if (!file_exists($configPath)) {
    header('Location: /error_page.php?code=config_missing');
    exit();
}
require_once $configPath;

// Includi il SessionManager per una gestione sicura della sessione
require_once __DIR__ . '/../include/session_manager.php';

// Avvia la sessione. Usiamo startSecureSession() perché la pagina non richiede l'autenticazione.
SessionManager::startSecureSession();

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
$accessory_types = ['Plance di gioco', 'Scatole porta mazzi', 'Proteggicarte', 'Porta carte'];

foreach ($accessory_types as $type) {
    $sql_accessories = "
        SELECT
            o.id_oggetto,
            o.nome_oggetto AS name,
            o.desc_oggetto AS description,
            o.prezzo_oggetto AS price,
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
            $row['image_url'] = (defined('BASE_URL') ? BASE_URL : '') . '/images/' . ($row['image_filename'] ?? 'default_accessory.png');
            $accessori_by_type[$type][] = $row;
        }
    } else {
        error_log("Errore nel recupero degli accessori di tipo '{$type}': " . $conn->error);
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accessori</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo (defined('BASE_URL') ? BASE_URL : ''); ?>/css/style.css">
    <style>
        .filter-container {
            padding: 1rem 0;
            margin-bottom: 2rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: flex-end;
            align-items: center;
        }
        .accessory-section {
            scroll-margin-top: 100px;
        }
    </style>
</head>
<body>

<main class="background-custom">
    <div class="container">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="fashion_taverage m-0">Accessori</h1>
            <div class="dropdown">
                <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    Filtra per categoria
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item filter-link" href="#tutti">Tutti</a></li>
                    <?php foreach ($accessory_types as $type): ?>
                        <li><a class="dropdown-item filter-link" href="#<?php echo str_replace(' ', '_', $type); ?>"><?php echo htmlspecialchars($type); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        
        <?php foreach ($accessory_types as $type): ?>
            <div id="<?php echo str_replace(' ', '_', $type); ?>" class="accessory-section mb-5">
                <h2 class="category-title mb-4"><?php echo htmlspecialchars($type); ?></h2>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                    <?php if (!empty($accessori_by_type[$type])): ?>
                        <?php foreach ($accessori_by_type[$type] as $accessory): ?>
                            <div class="col">
                                <div class="accessory-card card h-100" data-id="<?php echo htmlspecialchars($accessory['id_oggetto']); ?>" data-description="<?php echo htmlspecialchars($accessory['description']); ?>">
                                    <div class="card-img-container">
                                        <img src="<?php echo htmlspecialchars($accessory['image_url']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($accessory['name']); ?>">
                                    </div>
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($accessory['name']); ?></h5>
                                        <p class="card-text text-muted"><?php echo htmlspecialchars($accessory['description']); ?></p>
                                        <p class="card-text card-price"><?php echo isset($accessory['price']) ? htmlspecialchars($accessory['price']) . '€' : 'Prezzo non disponibile'; ?></p>
                                    </div>
                                    <div class="card-footer text-center">
                                        <button class="btn btn-add-to-cart">Aggiungi al carrello</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <p>Nessun accessorio di questo tipo disponibile.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const accessoryCards = document.querySelectorAll('.accessory-card');
    const accessoryModal = new bootstrap.Modal(document.getElementById('accessoryModal'));

    accessoryCards.forEach(card => {
        card.addEventListener('click', function(event) {
            if (event.target.closest('.btn-add-to-cart')) {
                return;
            }

            const id = this.dataset.id;
            // Recupera la descrizione dall'attributo data-description
            const description = this.dataset.description; 
            const img = this.querySelector('.card-img-top');
            const title = this.querySelector('.card-title').innerText;
            const price = this.querySelector('.card-price').innerText;
            
            const modalBody = document.querySelector('#accessoryModal .modal-body');
            modalBody.innerHTML = `
                <div class="row">
                    <div class="col-md-6 text-center">
                        <img src="${img.src}" class="img-fluid mb-3" alt="${title}">
                    </div>
                    <div class="col-md-6">
                        <h4>${title}</h4>
                        <p class="text-muted">${description}</p>
                        <p class="fs-4 fw-bold">${price}</p>
                        <hr>
                        <form action="#" method="post">
                            <div class="mb-3">
                                <label for="modal-quantity" class="form-label">Quantità:</label>
                                <input type="number" class="form-control" id="modal-quantity" name="quantita" value="1" min="1">
                            </div>
                            <button type="submit" class="btn btn-add-to-cart w-100">Aggiungi al carrello</button>
                        </form>
                    </div>
                </div>
            `;
            
            accessoryModal.show();
        });
    });

    const cartButtons = document.querySelectorAll('.btn-add-to-cart');
    cartButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            alert('Aggiungi al carrello cliccato per l\'oggetto ID: ' + this.closest('.accessory-card, .modal-body').querySelector('input[name="id_oggetto"], .accessory-card').dataset.id);
        });
    });

    const filterLinks = document.querySelectorAll('.filter-link');
    filterLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
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
});
</script>
</body>
</html>