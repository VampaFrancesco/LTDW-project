<?php
// pages/wishlist.php
require_once __DIR__ . '/../include/session_manager.php';
require_once __DIR__ . '/../include/config.inc.php';

// Verifica login
SessionManager::requireLogin();
$user_id = SessionManager::getUserId();

// Connessione database
$db = $config['dbms']['localhost'];
$conn = new mysqli($db['host'], $db['user'], $db['passwd'], $db['dbname']);

if ($conn->connect_error) {
    die("Connessione fallita: " . $conn->connect_error);
}

// ✅ QUERY CORRETTA per recuperare gli items nella wishlist con immagini
$query = "
    SELECT 
        w.id_wishlist,
        w.data_aggiunta,
        COALESCE(o.id_oggetto, mb.id_box) as item_id,
        COALESCE(o.nome_oggetto, mb.nome_box) as nome,
        COALESCE(o.prezzo_oggetto, mb.prezzo_box) as prezzo,
        COALESCE(o.desc_oggetto, mb.desc_box) as descrizione,
        COALESCE(o.quant_oggetto, mb.quantita_box) as quantita,
        CASE 
            WHEN o.id_oggetto IS NOT NULL THEN 'oggetto'
            ELSE 'box'
        END as tipo,
        COALESCE(img_ogg.nome_img, img_mb.nome_img) as immagine
    FROM wishlist w
    LEFT JOIN oggetto o ON w.fk_oggetto = o.id_oggetto
    LEFT JOIN mystery_box mb ON w.fk_box = mb.id_box
    LEFT JOIN immagine img_ogg ON o.id_oggetto = img_ogg.fk_oggetto
    LEFT JOIN immagine img_mb ON mb.id_box = img_mb.fk_mystery_box
    WHERE w.fk_utente = ?
    ORDER BY w.data_aggiunta DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$wishlist_items = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include __DIR__ . '/header.php';
?>

    <main class="background-custom">
        <div class="container py-5">
            <h1 class="fashion_taital mb-5">
                <i class="bi bi-heart-fill text-danger"></i> La mia Wishlist
            </h1>

            <?php if (empty($wishlist_items)): ?>
                <div class="text-center py-5">
                    <div class="empty-wishlist-container">
                        <i class="bi bi-heart" style="font-size: 5rem; color: #ccc;"></i>
                        <h3 class="mt-4">La tua wishlist è vuota</h3>
                        <p class="text-muted">Aggiungi i tuoi prodotti preferiti per trovarli facilmente più tardi!</p>
                        <a href="<?php echo BASE_URL; ?>/pages/home_utente.php" class="btn btn-primary mt-3">
                            <i class="bi bi-shop"></i> Vai allo Shop
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <div class="col-lg-12">
                        <div class="wishlist-count mb-4">
                        <span class="badge bg-primary">
                            <?php echo count($wishlist_items); ?>
                            <?php echo count($wishlist_items) == 1 ? 'prodotto' : 'prodotti'; ?>
                        </span>
                        </div>

                        <div class="wishlist-grid">
                            <?php foreach ($wishlist_items as $item): ?>
                                <div class="wishlist-item-card" data-wishlist-id="<?php echo $item['id_wishlist']; ?>">
                                    <div class="wishlist-item-image">
                                        <?php if (!empty($item['immagine'])): ?>
                                            <img src="<?php echo BASE_URL . '/images/' . htmlspecialchars($item['immagine']); ?>"
                                                 alt="<?php echo htmlspecialchars($item['nome']); ?>"
                                                 onerror="this.src='<?php echo BASE_URL; ?>/images/default_product1.jpg';">
                                        <?php else: ?>
                                            <div class="placeholder-image">
                                                <i class="bi bi-image"></i>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Badge tipo prodotto -->
                                        <span class="badge-type <?php echo $item['tipo'] == 'box' ? 'badge-box' : 'badge-oggetto'; ?>">
                                        <?php echo $item['tipo'] == 'box' ? 'Mystery Box' : 'Oggetto'; ?>
                                    </span>
                                    </div>

                                    <div class="wishlist-item-details">
                                        <h3><?php echo htmlspecialchars($item['nome']); ?></h3>
                                        <p class="description">
                                            <?php
                                            $desc = $item['descrizione'] ?? '';
                                            echo htmlspecialchars(mb_strlen($desc) > 100 ? mb_substr($desc, 0, 100) . '...' : $desc);
                                            ?>
                                        </p>

                                        <div class="price-stock">
                                            <span class="price">€<?php echo number_format($item['prezzo'], 2); ?></span>
                                            <?php if ($item['quantita'] > 0): ?>
                                                <span class="stock in-stock">
                                                <i class="bi bi-check-circle"></i> Disponibile
                                            </span>
                                            <?php else: ?>
                                                <span class="stock out-of-stock">
                                                <i class="bi bi-x-circle"></i> Non disponibile
                                            </span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="wishlist-actions">
                                            <button class="btn btn-primary btn-add-to-cart"
                                                    data-item-id="<?php echo $item['item_id']; ?>"
                                                    data-item-type="<?php echo $item['tipo']; ?>"
                                                    <?php echo $item['quantita'] <= 0 ? 'disabled' : ''; ?>>
                                                <i class="bi bi-cart-plus"></i> Aggiungi al Carrello
                                            </button>

                                            <button class="btn btn-outline-danger btn-remove-wishlist"
                                                    data-wishlist-id="<?php echo $item['id_wishlist']; ?>">
                                                <i class="bi bi-heart-fill"></i> Rimuovi
                                            </button>
                                        </div>

                                        <small class="text-muted">
                                            Aggiunto il <?php echo date('d/m/Y', strtotime($item['data_aggiunta'])); ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- ✅ Include JavaScript wishlist -->
    <script src="<?php echo BASE_URL; ?>/js/wishlist.js"></script>

<?php
$conn->close();
include __DIR__ . '/footer.php';
?>