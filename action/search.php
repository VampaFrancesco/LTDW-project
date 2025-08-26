<?php
// action/search_simple.php - Sistema di ricerca semplificato per hosting condivisi
require_once __DIR__ . '/../include/config.inc.php';
require_once __DIR__ . '/../include/session_manager.php';

// Inizializza variabili di output
$results = [];
$total_results = 0;
$error_message = '';

// Connessione database
try {
    $db_config = $config['dbms']['localhost'];
    $conn = new mysqli(
        $db_config['host'],
        $db_config['user'],
        $db_config['passwd'],
        $db_config['dbname']
    );

    if ($conn->connect_error) {
        throw new Exception("Connessione fallita: " . $conn->connect_error);
    }

    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    error_log("Errore connessione DB search: " . $e->getMessage());
    $error_message = 'Errore di connessione al database';

    if ($_GET['format'] === 'json') {
        header('Content-Type: application/json');
        echo json_encode(['error' => $error_message, 'results' => []]);
        exit;
    }
}

// Recupera parametri di ricerca
$query = trim($_GET['q'] ?? '');
$format = $_GET['format'] ?? 'html';
$limit = min(max(intval($_GET['limit'] ?? 20), 1), 100);
$category = trim($_GET['category'] ?? '');
$type = trim($_GET['type'] ?? '');
$min_price = !empty($_GET['min_price']) ? floatval($_GET['min_price']) : null;
$max_price = !empty($_GET['max_price']) ? floatval($_GET['max_price']) : null;
$only_available = isset($_GET['available']) ? (bool)$_GET['available'] : false;

// Validazione input
if (empty($query) || empty($conn)) {
    if ($format === 'json') {
        header('Content-Type: application/json');
        echo json_encode([
            'results' => [],
            'message' => empty($query) ? 'Inserisci un termine di ricerca' : $error_message,
            'query' => $query
        ]);
    } else {
        header('Location: ' . BASE_URL . '/pages/index.php');
    }
    exit;
}

// Sanitizza query
$query = substr($query, 0, 255);
$searchTerm = '%' . $conn->real_escape_string($query) . '%';

try {
    // =============================
    // RICERCA NEGLI OGGETTI
    // =============================

    // Costruisci WHERE clause per oggetti
    $where_conditions = ["(o.nome_oggetto LIKE ? OR o.desc_oggetto LIKE ? OR co.nome_categoria LIKE ? OR co.tipo_oggetto LIKE ?)"];
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
    $types = "ssss";

    if (!empty($category)) {
        $where_conditions[] = "co.nome_categoria = ?";
        $params[] = $category;
        $types .= "s";
    }

    if (!empty($type)) {
        $where_conditions[] = "co.tipo_oggetto = ?";
        $params[] = $type;
        $types .= "s";
    }

    if ($min_price !== null) {
        $where_conditions[] = "o.prezzo_oggetto >= ?";
        $params[] = $min_price;
        $types .= "d";
    }

    if ($max_price !== null) {
        $where_conditions[] = "o.prezzo_oggetto <= ?";
        $params[] = $max_price;
        $types .= "d";
    }

    if ($only_available) {
        $where_conditions[] = "o.quant_oggetto > 0";
    }

    $where_clause = "WHERE " . implode(" AND ", $where_conditions);

    $sql_oggetti = "
        SELECT 
            'oggetto' as tipo,
            o.id_oggetto as id,
            o.nome_oggetto as nome,
            o.desc_oggetto as descrizione,
            o.prezzo_oggetto as prezzo,
            o.quant_oggetto as disponibilita,
            co.nome_categoria as categoria,
            co.tipo_oggetto as tipo_prodotto,
            COALESCE(r.nome_rarita, '') as rarita,
            COALESCE(r.colore, '') as colore_rarita,
            COALESCE(i.nome_img, '') as immagine,
            CASE 
                WHEN o.nome_oggetto LIKE ? THEN 1
                WHEN o.desc_oggetto LIKE ? THEN 2
                WHEN co.nome_categoria LIKE ? THEN 3
                ELSE 4
            END as rilevanza
        FROM oggetto o
        JOIN categoria_oggetto co ON o.fk_categoria_oggetto = co.id_categoria
        LEFT JOIN rarita r ON o.fk_rarita = r.id_rarita
        LEFT JOIN immagine i ON o.id_oggetto = i.fk_oggetto
        $where_clause
        ORDER BY rilevanza ASC, o.quant_oggetto DESC, o.nome_oggetto ASC
    ";

    // Aggiungi parametri per rilevanza
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    $types .= "sss";

    $stmt = $conn->prepare($sql_oggetti);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $results[] = processSearchResult($row, 'oggetto');
        }
        $stmt->close();
    }

    // =============================
    // RICERCA NELLE MYSTERY BOX
    // =============================

    if (count($results) < $limit) {
        // Ricostruisci parametri per mystery box
        $where_conditions = ["(mb.nome_box LIKE ? OR mb.desc_box LIKE ? OR co.nome_categoria LIKE ? OR co.tipo_oggetto LIKE ?)"];
        $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
        $types = "ssss";

        if (!empty($category)) {
            $where_conditions[] = "co.nome_categoria = ?";
            $params[] = $category;
            $types .= "s";
        }

        if (!empty($type)) {
            $where_conditions[] = "co.tipo_oggetto = ?";
            $params[] = $type;
            $types .= "s";
        }

        if ($min_price !== null) {
            $where_conditions[] = "mb.prezzo_box >= ?";
            $params[] = $min_price;
            $types .= "d";
        }

        if ($max_price !== null) {
            $where_conditions[] = "mb.prezzo_box <= ?";
            $params[] = $max_price;
            $types .= "d";
        }

        if ($only_available) {
            $where_conditions[] = "mb.quantita_box > 0";
        }

        $where_clause = "WHERE " . implode(" AND ", $where_conditions);
        $remaining_limit = $limit - count($results);

        $sql_mystery = "
            SELECT 
                'mystery_box' as tipo,
                mb.id_box as id,
                mb.nome_box as nome,
                mb.desc_box as descrizione,
                mb.prezzo_box as prezzo,
                mb.quantita_box as disponibilita,
                co.nome_categoria as categoria,
                co.tipo_oggetto as tipo_prodotto,
                COALESCE(r.nome_rarita, '') as rarita,
                COALESCE(r.colore, '') as colore_rarita,
                COALESCE(i.nome_img, '') as immagine,
                CASE 
                    WHEN mb.nome_box LIKE ? THEN 1
                    WHEN mb.desc_box LIKE ? THEN 2
                    WHEN co.nome_categoria LIKE ? THEN 3
                    ELSE 4
                END as rilevanza
            FROM mystery_box mb
            JOIN categoria_oggetto co ON mb.fk_categoria_oggetto = co.id_categoria
            LEFT JOIN rarita r ON mb.fk_rarita = r.id_rarita
            LEFT JOIN immagine i ON mb.id_box = i.fk_mystery_box
            $where_clause
            ORDER BY rilevanza ASC, mb.quantita_box DESC, mb.nome_box ASC
            LIMIT ?
        ";

        // Aggiungi parametri per rilevanza e limit
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $remaining_limit]);
        $types .= "sssi";

        $stmt = $conn->prepare($sql_mystery);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $results[] = processSearchResult($row, 'mystery_box');
            }
            $stmt->close();
        }
    }

    // Limita i risultati finali
    $results = array_slice($results, 0, $limit);
    $total_results = count($results); // Semplificato per performance

    // Log ricerca (semplificato)
    try {
        $log_stmt = $conn->prepare("INSERT INTO search_stats (search_term, results_count) VALUES (?, ?) ON DUPLICATE KEY UPDATE search_count = search_count + 1, results_count = ?, last_searched = CURRENT_TIMESTAMP");
        if ($log_stmt) {
            $log_stmt->bind_param('sii', $query, $total_results, $total_results);
            $log_stmt->execute();
            $log_stmt->close();
        }
    } catch (Exception $e) {
        // Ignora errori di logging
    }

} catch (Exception $e) {
    error_log("Errore ricerca: " . $e->getMessage());
    $error_message = 'Errore durante la ricerca';
    $results = [];
}

$conn->close();

// =============================
// OUTPUT RISULTATI
// =============================

if ($format === 'json') {
    header('Content-Type: application/json');
    $response = [
        'results' => $results,
        'query' => $query,
        'total' => $total_results,
        'showing' => count($results)
    ];

    if (!empty($error_message)) {
        $response['error'] = $error_message;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// =============================
// PAGINA HTML
// =============================
$pageTitle = "Ricerca: " . htmlspecialchars($query);
$hideNav = false;
include __DIR__ . '/../pages/header.php';
?>

    <style>
        .search-results {
            padding: 2rem 0;
            min-height: 60vh;
        }

        .search-header {
            text-align: center;
            margin-bottom: 2rem;
            padding: 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }

        .search-stats {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-top: 1rem;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 2rem;
            padding: 1rem 0;
        }

        .product-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 35px rgba(0,0,0,0.15);
        }

        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: linear-gradient(45deg, #f0f2f5, #e9ecef);
        }

        .product-content {
            padding: 1.5rem;
        }

        .product-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #2d3748;
            line-height: 1.3;
        }

        .product-description {
            color: #718096;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .product-category {
            background: #e6f3ff;
            color: #0066cc;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-weight: 500;
        }

        .product-type {
            background: #f0f8e6;
            color: #33a002;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.8rem;
        }

        .product-price {
            font-size: 1.4rem;
            font-weight: 700;
            color: #27ae60;
            margin-bottom: 1rem;
        }

        .product-availability {
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .available { color: #27ae60; }
        .unavailable { color: #e74c3c; }

        .btn-primary-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 0.7rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: block;
            text-align: center;
        }

        .btn-primary-gradient:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            color: white;
            text-decoration: none;
        }

        .rarity-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            color: white;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }

        .no-results {
            text-align: center;
            padding: 4rem 2rem;
            color: #718096;
        }

        .no-results-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .error-message {
            background: #fee;
            color: #c53030;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            text-align: center;
        }

        @media (max-width: 768px) {
            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 1rem;
            }

            .search-header {
                padding: 1.5rem;
            }
        }
    </style>

    <main class="search-results">
        <div class="container">
            <!-- Header ricerca -->
            <div class="search-header">
                <h1>🔍 Risultati di ricerca</h1>
                <p>Hai cercato: "<strong><?php echo htmlspecialchars($query); ?></strong>"</p>
                <div class="search-stats">
                    <?php if ($total_results > 0): ?>
                        Trovati <strong><?php echo $total_results; ?></strong> risultati
                    <?php else: ?>
                        Nessun risultato trovato
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    ⚠️ <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($total_results > 0): ?>
                <!-- Griglia risultati -->
                <div class="product-grid">
                    <?php foreach ($results as $product): ?>
                        <div class="product-card">
                            <?php if (!empty($product['rarita']) && !empty($product['colore_rarita'])): ?>
                                <div class="rarity-badge" style="background-color: <?php echo htmlspecialchars($product['colore_rarita']); ?>">
                                    <?php echo htmlspecialchars($product['rarita']); ?>
                                </div>
                            <?php endif; ?>

                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>"
                                 alt="<?php echo htmlspecialchars($product['nome']); ?>"
                                 class="product-image"
                                 onerror="this.src='<?php echo BASE_URL; ?>/images/default_product1.jpg'"
                                 loading="lazy">

                            <div class="product-content">
                                <h3 class="product-title">
                                    <?php echo highlightSearchTerm($product['nome'], $query); ?>
                                </h3>

                                <?php if (!empty($product['descrizione'])): ?>
                                    <p class="product-description">
                                        <?php echo highlightSearchTerm(mb_substr($product['descrizione'], 0, 120) . '...', $query); ?>
                                    </p>
                                <?php endif; ?>

                                <div class="product-meta">
                                <span class="product-category">
                                    <?php echo htmlspecialchars($product['categoria']); ?>
                                </span>

                                    <?php if (!empty($product['tipo_prodotto'])): ?>
                                        <span class="product-type">
                                        <?php echo htmlspecialchars($product['tipo_prodotto']); ?>
                                    </span>
                                    <?php endif; ?>
                                </div>

                                <div class="product-price">
                                    €<?php echo number_format($product['prezzo'], 2, ',', '.'); ?>
                                </div>

                                <div class="product-availability">
                                    <?php if ($product['disponibilita'] > 0): ?>
                                        <span class="available">
                                        ✅ Disponibile (<?php echo $product['disponibilita']; ?> pz)
                                    </span>
                                    <?php else: ?>
                                        <span class="unavailable">❌ Non disponibile</span>
                                    <?php endif; ?>
                                </div>

                                <a href="<?php echo htmlspecialchars($product['url']); ?>"
                                   class="btn-primary-gradient">
                                    Visualizza dettagli
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>
                <!-- Nessun risultato -->
                <div class="no-results">
                    <div class="no-results-icon">🔍</div>
                    <h3>Nessun risultato trovato</h3>
                    <p>Non siamo riusciti a trovare prodotti corrispondenti a "<strong><?php echo htmlspecialchars($query); ?></strong>"</p>

                    <div style="margin: 2rem 0;">
                        <p>Suggerimenti:</p>
                        <ul style="list-style: none; padding: 0; color: #4a5568;">
                            <li>• Usa termini più generici</li>
                            <li>• Controlla l'ortografia</li>
                            <li>• Prova parole chiave diverse</li>
                        </ul>
                    </div>

                    <a href="<?php echo BASE_URL; ?>/pages/index.php" class="btn-primary-gradient" style="display: inline-block; width: auto; padding: 0.8rem 2rem;">
                        Torna alla Home
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>

<?php include __DIR__ . '/../pages/footer.php'; ?>

<?php
// =============================
// FUNZIONI DI SUPPORTO
// =============================

function processSearchResult($row, $type) {
    // Genera URL del prodotto
    if ($type === 'mystery_box') {
        $product_url = BASE_URL . '/pages/mystery_box_detail.php?id=' . $row['id'];
    } else {
        switch($row['tipo_prodotto']) {
            case 'Mystery Box':
                $product_url = BASE_URL . '/pages/mystery_box_detail.php?id=' . $row['id'];
                break;
            case 'Carta Singola':
                $product_url = BASE_URL . '/pages/carte.php#card-' . $row['id'];
                break;
            case 'Funko Pop':
                $product_url = BASE_URL . '/pages/funko.php#funko-' . $row['id'];
                break;
            default:
                $product_url = BASE_URL . '/pages/accessori.php#accessory-' . $row['id'];
        }
    }

    // Genera URL immagine
    $image_url = !empty($row['immagine']) ?
        BASE_URL . '/images/' . $row['immagine'] :
        BASE_URL . '/images/default_product1.jpg';

    return [
        'tipo' => $row['tipo'],
        'id' => intval($row['id']),
        'nome' => $row['nome'],
        'descrizione' => $row['descrizione'],
        'prezzo' => floatval($row['prezzo']),
        'disponibilita' => intval($row['disponibilita']),
        'categoria' => $row['categoria'],
        'tipo_prodotto' => $row['tipo_prodotto'],
        'rarita' => $row['rarita'],
        'colore_rarita' => $row['colore_rarita'],
        'immagine' => $row['immagine'],
        'image_url' => $image_url,
        'url' => $product_url,
        'rilevanza' => intval($row['rilevanza']),
        'stato_disponibilita' => $row['disponibilita'] > 0 ? 'disponibile' : 'non_disponibile'
    ];
}

function highlightSearchTerm($text, $searchTerm) {
    if (empty($text) || empty($searchTerm)) {
        return htmlspecialchars($text);
    }

    $escapedText = htmlspecialchars($text);
    $escapedTerm = htmlspecialchars($searchTerm);

    return preg_replace(
        '/(' . preg_quote($escapedTerm, '/') . ')/i',
        '<mark style="background: #fff3cd; font-weight: bold;">$1</mark>',
        $escapedText
    );
}
?>