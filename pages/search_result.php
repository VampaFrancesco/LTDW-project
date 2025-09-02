<?php
/**
 * pages/search_results.php - Pagina risultati di ricerca
 */

require_once __DIR__ . '/../include/session_manager.php';
require_once __DIR__ . '/../include/config.inc.php';

// Ottieni risultati dalla sessione o dai parametri GET
$search_data = SessionManager::get('search_results');

// Se non ci sono dati in sessione, esegui nuova ricerca con parametri GET
if (!$search_data && isset($_GET['q'])) {
    include __DIR__ . '/../action/search.php';
    exit;
}

// Se non ci sono risultati, reindirizza
if (!$search_data) {
    SessionManager::setFlashMessage('Nessun risultato di ricerca trovato', 'warning');
    header('Location: ' . BASE_URL . '/pages/shop.php');
    exit;
}

// Estrai dati
$query = $search_data['query'] ?? '';
$results = $search_data['results'] ?? [];
$total = $search_data['total'] ?? 0;
$page = $search_data['page'] ?? 1;
$per_page = $search_data['per_page'] ?? 12;
$total_pages = $search_data['total_pages'] ?? 1;
$filters = $search_data['filters'] ?? [];

include __DIR__ . '/header.php';
?>

    <div class="container my-5">
        <!-- Header ricerca -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <h1 class="h3">
                    Risultati per: <span class="text-primary">"<?php echo htmlspecialchars($query); ?>"</span>
                </h1>
                <p class="text-muted">
                    <?php echo $total; ?> risultati trovati
                    <?php if (!empty($filters['category'])): ?>
                        in <span class="badge bg-secondary"><?php echo htmlspecialchars($filters['category']); ?></span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <button class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#filterPanel">
                    <i class="bi bi-filter"></i> Filtri avanzati
                </button>
            </div>
        </div>

        <!-- Pannello filtri -->
        <div class="collapse mb-4" id="filterPanel">
            <div class="card">
                <div class="card-body">
                    <form id="advancedSearchForm" method="GET" action="<?php echo BASE_URL; ?>/action/search.php">
                        <input type="hidden" name="q" value="<?php echo htmlspecialchars($query); ?>">

                        <div class="row g-3">
                            <!-- Categoria -->
                            <div class="col-md-3">
                                <label class="form-label">Categoria</label>
                                <select name="category" class="form-select">
                                    <option value="">Tutte</option>
                                    <option value="Pokémon" <?php echo $filters['category'] === 'Pokémon' ? 'selected' : ''; ?>>Pokémon</option>
                                    <option value="Yu-Gi-Oh!" <?php echo $filters['category'] === 'Yu-Gi-Oh!' ? 'selected' : ''; ?>>Yu-Gi-Oh!</option>
                                    <option value="Universale" <?php echo $filters['category'] === 'Universale' ? 'selected' : ''; ?>>Universale</option>
                                </select>
                            </div>

                            <!-- Tipo -->
                            <div class="col-md-3">
                                <label class="form-label">Tipo prodotto</label>
                                <select name="type" class="form-select">
                                    <option value="">Tutti</option>
                                    <option value="mystery_box" <?php echo $filters['type'] === 'mystery_box' ? 'selected' : ''; ?>>Mystery Box</option>
                                    <option value="funko_pop" <?php echo $filters['type'] === 'funko_pop' ? 'selected' : ''; ?>>Funko Pop</option>
                                    <option value="accessorio" <?php echo $filters['type'] === 'accessorio' ? 'selected' : ''; ?>>Accessori</option>
                                </select>
                            </div>

                            <!-- Range prezzo -->
                            <div class="col-md-2">
                                <label class="form-label">Prezzo min</label>
                                <input type="number" name="min_price" class="form-control"
                                       value="<?php echo $filters['min_price'] ?? ''; ?>"
                                       min="0" step="0.01" placeholder="€">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Prezzo max</label>
                                <input type="number" name="max_price" class="form-control"
                                       value="<?php echo $filters['max_price'] ?? ''; ?>"
                                       min="0" step="0.01" placeholder="€">
                            </div>

                            <!-- Ordinamento -->
                            <div class="col-md-2">
                                <label class="form-label">Ordina per</label>
                                <select name="order" class="form-select">
                                    <option value="relevance" <?php echo $filters['order'] === 'relevance' ? 'selected' : ''; ?>>Rilevanza</option>
                                    <option value="price_asc" <?php echo $filters['order'] === 'price_asc' ? 'selected' : ''; ?>>Prezzo ↑</option>
                                    <option value="price_desc" <?php echo $filters['order'] === 'price_desc' ? 'selected' : ''; ?>>Prezzo ↓</option>
                                    <option value="name_asc" <?php echo $filters['order'] === 'name_asc' ? 'selected' : ''; ?>>Nome A-Z</option>
                                    <option value="name_desc" <?php echo $filters['order'] === 'name_desc' ? 'selected' : ''; ?>>Nome Z-A</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search"></i> Applica filtri
                            </button>
                            <a href="<?php echo BASE_URL; ?>/action/search.php?q=<?php echo urlencode($query); ?>"
                               class="btn btn-outline-secondary">
                                <i class="bi bi-times"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Risultati -->
        <?php if (empty($results)): ?>
            <div class="alert alert-info">
                <h5><i class="bi bi-info-circle"></i> Nessun risultato trovato</h5>
                <p>Prova a:</p>
                <ul>
                    <li>Controllare l'ortografia</li>
                    <li>Usare termini di ricerca più generici</li>
                    <li>Rimuovere alcuni filtri</li>
                </ul>
                <a href="<?php echo BASE_URL; ?>/pages/shop.php" class="btn btn-primary mt-3">
                    <i class="bi bi-shopping-bag"></i> Vai allo Shop
                </a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($results as $product): ?>
                    <div class="col-lg-3 col-md-4 col-sm-6">
                        <div class="card h-100 product-card">
                            <!-- Badge tipo -->
                            <div class="position-absolute top-0 start-0 m-2 z-1">
                            <span class="badge bg-<?php echo $product['tipo'] === 'mystery_box' ? 'primary' : ($product['tipo'] === 'funko_pop' ? 'success' : 'secondary'); ?>">
                                <?php
                                echo $product['tipo'] === 'mystery_box' ? 'Mystery Box' :
                                    ($product['tipo'] === 'funko_pop' ? 'Funko Pop' : 'Accessorio');
                                ?>
                            </span>
                            </div>

                            <!-- Badge rarità se presente -->
                            <?php if (!empty($product['rarita'])): ?>
                                <div class="position-absolute top-0 end-0 m-2 z-1">
                                <span class="badge" style="background-color: <?php echo htmlspecialchars($product['colore_rarita']); ?>">
                                    <?php echo htmlspecialchars($product['rarita']); ?>
                                </span>
                                </div>
                            <?php endif; ?>

                            <!-- Immagine -->
                            <div class="product-image-wrapper">
                                <img src="<?php echo BASE_URL; ?>/images/<?php echo htmlspecialchars($product['immagine']); ?>"
                                     class="card-img-top"
                                     alt="<?php echo htmlspecialchars($product['nome']); ?>"
                                     onerror="this.src='<?php echo BASE_URL; ?>/images/default_product.png'">
                            </div>

                            <div class="card-body d-flex flex-column">
                                <!-- Nome prodotto con evidenziazione -->
                                <h5 class="card-title">
                                    <?php
                                    if (!empty($query)) {
                                        // Evidenzia il termine di ricerca nel nome
                                        $highlighted = str_ireplace(
                                            $query,
                                            '<mark>' . htmlspecialchars($query) . '</mark>',
                                            htmlspecialchars($product['nome'])
                                        );
                                        echo $highlighted;
                                    } else {
                                        echo htmlspecialchars($product['nome']);
                                    }
                                    ?>
                                </h5>

                                <!-- Categoria -->
                                <?php if (!empty($product['categoria'])): ?>
                                    <small class="text-muted mb-2">
                                        <i class="bi bi-tag"></i> <?php echo htmlspecialchars($product['categoria']); ?>
                                    </small>
                                <?php endif; ?>

                                <!-- Descrizione breve -->
                                <?php if (!empty($product['descrizione'])): ?>
                                    <p class="card-text small text-muted flex-grow-1">
                                        <?php echo htmlspecialchars(substr($product['descrizione'], 0, 100)); ?>
                                        <?php echo strlen($product['descrizione']) > 100 ? '...' : ''; ?>
                                    </p>
                                <?php endif; ?>

                                <!-- Prezzo e disponibilità -->
                                <div class="mt-auto">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="h5 mb-0 text-primary">
                                        €<?php echo number_format($product['prezzo'], 2, ',', '.'); ?>
                                    </span>
                                        <?php if ($product['stock'] !== null && $product['stock'] == 0): ?>
                                            <span class="badge bg-danger">Esaurito</span>
                                        <?php elseif ($product['stock'] !== null && $product['stock'] <= 5): ?>
                                            <span class="badge bg-warning">Ultimi <?php echo $product['stock']; ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Azioni -->
                                    <?php if ($product['stock'] === null || $product['stock'] > 0): ?>
                                        <form class="add-to-cart-form" method="POST" action="<?php echo BASE_URL; ?>/action/add_to_cart.php">
                                            <input type="hidden" name="id_prodotto" value="<?php echo $product['id']; ?>">
                                            <input type="hidden" name="nome_prodotto" value="<?php echo htmlspecialchars($product['nome']); ?>">
                                            <input type="hidden" name="prezzo" value="<?php echo $product['prezzo']; ?>">
                                            <input type="hidden" name="tipo" value="<?php echo $product['tipo']; ?>">
                                            <input type="hidden" name="quantita" value="1">
                                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                                <i class="bi bi-cart-plus"></i> Aggiungi al Carrello
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-secondary btn-sm w-100" disabled>
                                            Non Disponibile
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Paginazione -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Paginazione risultati" class="mt-5">
                    <ul class="pagination justify-content-center">
                        <!-- Precedente -->
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link"
                               href="<?php echo BASE_URL; ?>/action/search.php?q=<?php echo urlencode($query); ?>&page=<?php echo $page - 1; ?>&<?php echo http_build_query($filters); ?>">
                                Precedente
                            </a>
                        </li>

                        <!-- Numeri pagina -->
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link"
                                   href="<?php echo BASE_URL; ?>/action/search.php?q=<?php echo urlencode($query); ?>&page=<?php echo $i; ?>&<?php echo http_build_query($filters); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <!-- Successiva -->
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link"
                               href="<?php echo BASE_URL; ?>/action/search.php?q=<?php echo urlencode($query); ?>&page=<?php echo $page + 1; ?>&<?php echo http_build_query($filters); ?>">
                                Successiva
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <style>
        .product-card {
            transition: transform 0.3s ease;
        }

        .product-card:hover {
            transform: translateY(-5px);
        }

        .product-image-wrapper {
            height: 200px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
        }

        .product-image-wrapper img {
            max-height: 100%;
            width: auto;
            object-fit: contain;
        }

        mark {
            background-color: #ffeb3b;
            padding: 0.1em;
        }
    </style>

<?php
// Pulisci risultati dalla sessione
SessionManager::remove('search_results');
include __DIR__ . '/footer.php';
?>