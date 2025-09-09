<?php
// pages/wishlist.php
require_once __DIR__ . '/../include/session_manager.php';
require_once __DIR__ . '/../include/config.inc.php';

// Verifica che l'utente sia loggato, altrimenti reindirizza alla pagina di login.
SessionManager::requireLogin();
$user_id = SessionManager::getUserId();

// Stabilisce la connessione al database.
$db = $config['dbms']['localhost'];
$conn = new mysqli($db['host'], $db['user'], $db['passwd'], $db['dbname']);

// Controlla per eventuali errori di connessione.
if ($conn->connect_error) {
    // Interrompe l'esecuzione e mostra un errore generico per non esporre dettagli.
    error_log("Errore di connessione al DB: " . $conn->connect_error);
    die("Siamo spiacenti, si è verificato un errore tecnico. Riprova più tardi.");
}

// Query per recuperare tutti gli articoli nella wishlist dell'utente.
// Utilizza LEFT JOIN per ottenere dettagli del prodotto e immagini sia da 'oggetto' che da 'mystery_box'.
// COALESCE viene usato per selezionare il primo valore non nullo, unendo i dati dalle diverse tabelle.
// La subquery per l'immagine è ottimizzata per prendere solo la prima immagine disponibile per l'articolo.
$query = "
    SELECT 
        w.id_wishlist,
        w.data_aggiunta,
        COALESCE(o.id_oggetto, mb.id_box) as item_id,
        COALESCE(o.nome_oggetto, mb.nome_box) as nome,
        COALESCE(o.prezzo_oggetto, mb.prezzo_box) as prezzo,
        COALESCE(o.quant_oggetto, mb.quantita_box) as quantita,
        CASE 
            WHEN w.fk_oggetto IS NOT NULL THEN 'oggetto'
            ELSE 'box'
        END as tipo,
        (SELECT nome_img FROM immagine 
         WHERE fk_oggetto = o.id_oggetto OR fk_mystery_box = mb.id_box 
         ORDER BY id_immagine ASC LIMIT 1) as immagine
    FROM wishlist w
    LEFT JOIN oggetto o ON w.fk_oggetto = o.id_oggetto
    LEFT JOIN mystery_box mb ON w.fk_box = mb.id_box
    WHERE w.fk_utente = ?
    ORDER BY w.data_aggiunta DESC
";

// ✅ FIX: La riga 'ORDER BY id_img ASC LIMIT 1' è stata corretta in 'ORDER BY id_immagine ASC LIMIT 1'
// per corrispondere al nome della colonna nel tuo database.
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$wishlist_items = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// Include l'header della pagina.
include __DIR__ . '/header.php';
?>

    <style>
        .wishlist-header {
            border-bottom: 2px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 40px;
        }

        .wishlist-count-badge {
            font-size: 1rem;
            font-weight: 600;
            padding: 0.5em 0.8em;
        }

        /* Stile per la card di un singolo prodotto */
        .wishlist-item-card {
            background-color: #fff;
            border: 1px solid #e9ecef;
            border-radius: 15px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
        }

        .wishlist-item-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .wishlist-item-image-wrapper {
            position: relative;
            padding-top: 100%; /* Crea un contenitore quadrato per l'immagine */
            overflow: hidden;
        }

        .wishlist-item-image-wrapper img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .badge-type {
            position: absolute;
            top: 15px;
            left: 15px;
            font-size: 0.75rem;
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 20px;
            color: #fff;
            text-transform: uppercase;
            z-index: 1;
        }

        .badge-box { background: linear-gradient(45deg, #007bff, #0056b3); }
        .badge-oggetto { background: linear-gradient(45deg, #28a745, #218838); }

        .wishlist-item-details {
            padding: 20px;
            display: flex;
            flex-direction: column;
            flex-grow: 1; /* Fa in modo che la sezione occupi tutto lo spazio verticale disponibile */
        }

        .wishlist-item-details h5 {
            font-weight: 600;
            margin-bottom: 10px;
            flex-grow: 1; /* Spinge il contenuto sottostante verso il basso */
        }

        .price-stock {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .price {
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
        }

        .stock { font-weight: 600; }
        .in-stock { color: #28a745; }
        .out-of-stock { color: #dc3545; }

        .wishlist-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: auto; /* Allinea i bottoni in fondo alla card */
        }

        .date-added {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 15px;
            text-align: center;
        }

        /* Stile per il messaggio di wishlist vuota */
        .empty-wishlist-container {
            background-color: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 15px;
            padding: 60px 30px;
            text-align: center;
            margin-top: 40px;
        }

        .empty-wishlist-container .icon {
            font-size: 4rem;
            color: #ced4da;
        }
    </style>

    <main class="background-custom">
        <div class="container py-5">
            <div class="wishlist-header d-flex justify-content-between align-items-center">
                <h1 class="fashion_taital mb-0">
                    <i class="bi bi-heart-fill text-danger"></i> La mia Wishlist
                </h1>
                <?php if (!empty($wishlist_items)): ?>
                    <span class="badge bg-primary wishlist-count-badge">
                    <?php echo count($wishlist_items); ?>
                    <?php echo (count($wishlist_items) == 1) ? 'prodotto' : 'prodotti'; ?>
                </span>
                <?php endif; ?>
            </div>

            <?php if (empty($wishlist_items)): ?>
                <div class="empty-wishlist-container">
                    <div class="icon"><i class="bi bi-emoji-frown"></i></div>
                    <h3 class="mt-4">La tua wishlist è ancora vuota.</h3>
                    <p class="text-muted">Nessun problema! Inizia ad esplorare i nostri prodotti e aggiungi qui i tuoi preferiti.</p>
                </div>
            <?php else: ?>
                <div class="wishlist-page-container">
                    <div class="row">
                        <?php foreach ($wishlist_items as $item): ?>
                            <div class="col-lg-4 col-md-6 mb-4">
                                <div class="wishlist-item-card">
                                    <div class="wishlist-item-image-wrapper">
                                        <img src="<?php echo BASE_URL . '/images/' . (!empty($item['immagine']) ? htmlspecialchars($item['immagine']) : 'default_product.jpg'); ?>"
                                             alt="<?php echo htmlspecialchars($item['nome']); ?>">

                                        <span class="badge-type <?php echo ($item['tipo'] == 'box') ? 'badge-box' : 'badge-oggetto'; ?>">
                                        <?php echo ($item['tipo'] == 'box') ? 'Mystery Box' : 'Oggetto'; ?>
                                    </span>
                                    </div>

                                    <div class="wishlist-item-details">
                                        <h5><?php echo htmlspecialchars($item['nome']); ?></h5>

                                        <div class="price-stock">
                                            <span class="price">€<?php echo number_format($item['prezzo'], 2, ',', '.'); ?></span>
                                            <?php if ($item['quantita'] > 0): ?>
                                                <span class="stock in-stock">
                                                <i class="bi bi-check-circle-fill"></i> Disponibile
                                            </span>
                                            <?php else: ?>
                                                <span class="stock out-of-stock">
                                                <i class="bi bi-x-circle-fill"></i> Esaurito
                                            </span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="wishlist-actions">
                                            <button class="btn btn-primary btn-add-to-cart"
                                                    data-item-id="<?php echo htmlspecialchars($item['item_id']); ?>"
                                                    data-item-type="<?php echo htmlspecialchars($item['tipo']); ?>"
                                                    <?php echo ($item['quantita'] <= 0) ? 'disabled' : ''; ?>>
                                                <i class="bi bi-cart-plus"></i> Al Carrello
                                            </button>

                                            <button class="btn btn-outline-danger btn-remove-wishlist"
                                                    data-wishlist-id="<?php echo htmlspecialchars($item['id_wishlist']); ?>">
                                                <i class="bi bi-trash3"></i> Rimuovi
                                            </button>
                                        </div>
                                        <small class="date-added">
                                            Aggiunto il <?php echo date('d/m/Y', strtotime($item['data_aggiunta'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="<?php echo BASE_URL; ?>/js/wishlist.js"></script>

<?php
// Include il footer della pagina.
include __DIR__ . '/footer.php';
?>