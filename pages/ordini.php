<?php
// pages/ordini.php - SISTEMA ORDINI SEPARATI
require_once __DIR__.'/../include/session_manager.php';
require_once __DIR__.'/../include/config.inc.php';

SessionManager::requireLogin();

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

$user_id = SessionManager::get('user_id');

// Query semplificata - ogni ordine è una riga separata
$query = "
SELECT 
    o.id_ordine,
    o.data_ordine,
    o.tracking,
    o.stato_ordine,
    CONCAT('Via ', ind.via, ' ', ind.civico, ', ', ind.cap, ' ', ind.citta, ' (', ind.provincia, ')') as indirizzo_completo
FROM ordine o
LEFT JOIN indirizzo_spedizione ind ON o.fk_indirizzo = ind.id_indirizzo
WHERE o.fk_utente = ?
ORDER BY o.data_ordine DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
$status_map = [
        0 => 'In Elaborazione',
        1 => 'Completato',
        2 => 'Spedito',
        3 => 'Annullato',
        4 => 'Rimborsato'
];

while ($row = $result->fetch_assoc()) {
    $order_id = $row['id_ordine'];

    // Per ogni ordine, ottieni i dettagli separatamente
    $details = getOrderDetails($conn, $order_id);

    $orders[] = [
            'id' => 'ORD' . str_pad($order_id, 3, '0', STR_PAD_LEFT),
            'raw_id' => $order_id,
            'date' => date('d/m/Y H:i', strtotime($row['data_ordine'])),
            'status' => $status_map[$row['stato_ordine']] ?? 'Sconosciuto',
            'status_code' => $row['stato_ordine'],
            'address' => $row['indirizzo_completo'],
            'tracking' => $row['tracking'],
            'details' => $details
    ];
}

$stmt->close();
$conn->close();

/**
 * Ottiene i dettagli di un ordine specifico
 */
function getOrderDetails($conn, $order_id) {
    $details = [
            'items' => [],
            'total_quantity' => 0,
            'products_count' => 0,
            'total_amount' => 0.00,
            'first_product_name' => 'Ordine',
            'first_image' => BASE_URL . '/images/default_product1.jpg'
    ];

    // 1. Recupera Mystery Box dall'info_ordine
    $mb_query = "
        SELECT 
            io.quantita_ordine,
            io.totale_ordine,
            mb.nome_box as nome,
            mb.prezzo_box as prezzo,
            img.nome_img
        FROM info_ordine io
        JOIN mystery_box mb ON io.fk_box = mb.id_box
        LEFT JOIN immagine img ON mb.id_box = img.fk_mystery_box
        WHERE io.fk_ordine = ?
    ";

    $stmt = $conn->prepare($mb_query);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $mb_result = $stmt->get_result();

    while ($mb_row = $mb_result->fetch_assoc()) {
        $details['items'][] = [
                'tipo' => 'mystery_box',
                'nome' => $mb_row['nome'],
                'quantita' => intval($mb_row['quantita_ordine']),
                'prezzo' => floatval($mb_row['prezzo']),
                'totale' => floatval($mb_row['totale_ordine']),
                'immagine' => $mb_row['nome_img'] ? BASE_URL . '/images/' . $mb_row['nome_img'] : null
        ];

        $details['total_quantity'] += intval($mb_row['quantita_ordine']);
        $details['total_amount'] += floatval($mb_row['totale_ordine']);
        $details['products_count']++;

        // Primo prodotto per display
        if ($details['first_product_name'] === 'Ordine') {
            $details['first_product_name'] = $mb_row['nome'];
            if ($mb_row['nome_img']) {
                $details['first_image'] = BASE_URL . '/images/' . $mb_row['nome_img'];
            }
        }
    }
    $stmt->close();

    // 2. Recupera oggetti dal carrello collegato all'ordine
    // Prima prova con il carrello direttamente collegato
    $obj_query = "
        SELECT DISTINCT
            c.quantita,
            c.totale,
            o.nome_oggetto as nome,
            o.prezzo_oggetto as prezzo,
            img.nome_img,
            c.id_carrello
        FROM ordine ord
        JOIN carrello c ON ord.fk_carrello = c.id_carrello
        JOIN oggetto o ON c.fk_oggetto = o.id_oggetto
        LEFT JOIN immagine img ON o.id_oggetto = img.fk_oggetto
        WHERE ord.id_ordine = ?
        AND c.stato = 'completato'
        AND c.fk_oggetto IS NOT NULL
    ";

    $stmt = $conn->prepare($obj_query);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $obj_result = $stmt->get_result();

    $found_items = [];

    while ($obj_row = $obj_result->fetch_assoc()) {
        // Usa l'id_carrello come chiave per evitare duplicati
        $cart_key = $obj_row['id_carrello'];

        if (!isset($found_items[$cart_key])) {
            $details['items'][] = [
                    'tipo' => 'oggetto',
                    'nome' => $obj_row['nome'],
                    'quantita' => intval($obj_row['quantita']),
                    'prezzo' => floatval($obj_row['prezzo']),
                    'totale' => floatval($obj_row['totale']),
                    'immagine' => $obj_row['nome_img'] ? BASE_URL . '/images/' . $obj_row['nome_img'] : null
            ];

            $details['total_quantity'] += intval($obj_row['quantita']);
            $details['total_amount'] += floatval($obj_row['totale']);
            $details['products_count']++;

            // Primo prodotto per display
            if ($details['first_product_name'] === 'Ordine') {
                $details['first_product_name'] = $obj_row['nome'];
                if ($obj_row['nome_img']) {
                    $details['first_image'] = BASE_URL . '/images/' . $obj_row['nome_img'];
                }
            }

            $found_items[$cart_key] = true;
        }
    }
    $stmt->close();

    // Se non abbiamo trovato nulla dal carrello diretto, prova con ricerca temporale
    if (empty($found_items)) {
        $obj_temporal_query = "
            SELECT DISTINCT
                c.quantita,
                c.totale,
                o.nome_oggetto as nome,
                o.prezzo_oggetto as prezzo,
                img.nome_img,
                c.id_carrello
            FROM carrello c
            JOIN oggetto o ON c.fk_oggetto = o.id_oggetto
            LEFT JOIN immagine img ON o.id_oggetto = img.fk_oggetto
            WHERE c.fk_utente = (SELECT fk_utente FROM ordine WHERE id_ordine = ?)
            AND c.stato = 'completato'
            AND c.data_ultima_modifica BETWEEN 
                (SELECT DATE_SUB(data_ordine, INTERVAL 10 SECOND) FROM ordine WHERE id_ordine = ?) 
                AND 
                (SELECT DATE_ADD(data_ordine, INTERVAL 10 SECOND) FROM ordine WHERE id_ordine = ?)
            AND c.fk_oggetto IS NOT NULL
        ";

        $stmt = $conn->prepare($obj_temporal_query);
        $stmt->bind_param("iii", $order_id, $order_id, $order_id);
        $stmt->execute();
        $obj_result = $stmt->get_result();

        while ($obj_row = $obj_result->fetch_assoc()) {
            $cart_key = $obj_row['id_carrello'];

            if (!isset($found_items[$cart_key])) {
                $details['items'][] = [
                        'tipo' => 'oggetto',
                        'nome' => $obj_row['nome'],
                        'quantita' => intval($obj_row['quantita']),
                        'prezzo' => floatval($obj_row['prezzo']),
                        'totale' => floatval($obj_row['totale']),
                        'immagine' => $obj_row['nome_img'] ? BASE_URL . '/images/' . $obj_row['nome_img'] : null
                ];

                $details['total_quantity'] += intval($obj_row['quantita']);
                $details['total_amount'] += floatval($obj_row['totale']);
                $details['products_count']++;

                // Primo prodotto per display
                if ($details['first_product_name'] === 'Ordine') {
                    $details['first_product_name'] = $obj_row['nome'];
                    if ($obj_row['nome_img']) {
                        $details['first_image'] = BASE_URL . '/images/' . $obj_row['nome_img'];
                    }
                }

                $found_items[$cart_key] = true;
            }
        }
        $stmt->close();
    }

    return $details;
}

include __DIR__ . '/header.php';
?>

    <style>
        .orders-container {
            max-width: 1290px;
            margin: 0 auto;
            margin-bottom: 50px;
        }

        .orders-header {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
        }

        .orders-header::after {
            content: '';
            width: 80px;
            height: 4px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            border-radius: 2px;
        }

        .orders-grid {
            display: grid;
            gap: 30px;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        }

        .order-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.15);
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            cursor: pointer;
        }

        .order-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 25%, #f093fb 50%, #f5576c 75%, #4facfe 100%);
        }

        .order-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 60px rgba(31, 38, 135, 0.25);
        }

        .order-header {
            padding: 25px 25px 20px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .order-number {
            font-size: 1.4rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .order-number::before {
            content: '📦';
            font-size: 1.2rem;
        }

        .order-date {
            color: #718096;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .order-date::before {
            content: '📅';
        }

        .order-status {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: absolute;
            top: 15px;
            right: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .status-in-elaborazione {
            background: linear-gradient(135deg, #ffd89b 0%, #19547b 100%);
            color: white;
        }

        .status-completato {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            color: #2d3748;
        }

        .status-spedito {
            background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
            color: #2d3748;
        }

        .status-annullato {
            background: linear-gradient(135deg, #fc4a1a 0%, #f7b733 100%);
            color: white;
        }

        .status-rimborsato {
            background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);
            color: white;
        }

        .order-content {
            padding: 25px;
        }

        .order-image-container {
            width: 80px;
            height: 80px;
            border-radius: 15px;
            overflow: hidden;
            margin-bottom: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .order-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .order-card:hover .order-image {
            transform: scale(1.1);
        }

        .order-summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .order-total {
            font-size: 1.2rem;
            font-weight: 700;
            color: #059669;
        }

        .order-items-preview {
            background: rgba(102, 126, 234, 0.05);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .click-hint {
            text-align: center;
            color: #667eea;
            font-size: 0.85rem;
            font-weight: 600;
            margin-top: 10px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .order-card:hover .click-hint {
            opacity: 1;
        }

        .tracking-section {
            background: linear-gradient(135deg, rgba(132, 250, 176, 0.2), rgba(143, 211, 244, 0.2));
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
            text-align: center;
        }

        .tracking-code {
            font-family: 'Courier New', monospace;
            background: rgba(255, 255, 255, 0.8);
            padding: 8px 12px;
            border-radius: 6px;
            font-weight: bold;
            color: #2d3748;
            display: inline-block;
            margin-top: 5px;
            letter-spacing: 1px;
        }

        /* Modal per dettagli ordine */
        .modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 20px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 20px 20px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 30px;
        }

        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            background: none;
            border: none;
        }

        .close:hover {
            opacity: 0.7;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .item-details {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .item-image {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            object-fit: cover;
            background: #f8f9fa;
        }

        .item-info h4 {
            margin: 0;
            font-size: 1.1rem;
            color: #2d3748;
        }

        .item-info p {
            margin: 5px 0 0 0;
            color: #718096;
            font-size: 0.9rem;
        }

        .item-total {
            font-weight: 600;
            color: #2d3748;
        }

        .empty-orders {
            text-align: center;
            padding: 80px 20px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.15);
            max-width: 500px;
            margin: 0 auto;
        }

        .btn-shop {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .btn-shop:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            color: white;
            text-decoration: none;
        }

        @media (max-width: 768px) {
            .orders-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
        }
    </style>

    <main class="background-custom">
        <div class="orders-container">
                <h1 class="fashion_taital mb-5">I tuoi Ordini</h1>


            <?php if (empty($orders)): ?>
                <div class="avviso-ordini-vuoti">
        <div class="icona-carrello-vuoto">🛒</div>
        <h3 class="titolo-ordini-vuoti">Nessun ordine trovato</h3>
        <p class="messaggio-ordini-vuoti">Non hai ancora effettuato nessun ordine. Inizia a esplorare il nostro catalogo!</p>
        <div style="margin-top: 20px;">
        </div>
    </div>
            <?php else: ?>
                <div class="orders-grid">
                    <?php foreach ($orders as $order): ?>
                        <div class="order-card" onclick="openOrderModal(<?php echo $order['raw_id']; ?>)">
                            <div class="order-header">
                                <div class="order-number">
                                    Ordine #<?php echo htmlspecialchars($order['id']); ?>
                                </div>
                                <div class="order-date">
                                    <?php echo htmlspecialchars($order['date']); ?>
                                </div>
                                <div class="order-status status-<?php echo strtolower(str_replace([' ', 'à'], ['', 'a'], $order['status'])); ?>">
                                    <?php echo htmlspecialchars($order['status']); ?>
                                </div>
                            </div>

                            <div class="order-content">
                                <div class="order-image-container">
                                    <img src="<?php echo htmlspecialchars($order['details']['first_image']); ?>"
                                         alt="Ordine <?php echo htmlspecialchars($order['id']); ?>"
                                         class="order-image"
                                         onerror="this.src='<?php echo BASE_URL; ?>/images/default_product1.jpg';">
                                </div>

                                <div class="order-summary">
                                    <div>
                                        <div style="font-weight: 600; color: #2d3748;">
                                            <?php echo htmlspecialchars($order['details']['first_product_name']); ?>
                                        </div>
                                        <?php if ($order['details']['products_count'] > 1): ?>
                                            <div style="color: #718096; font-size: 0.9rem;">
                                                + altri <?php echo ($order['details']['products_count'] - 1); ?> prodotti
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="order-total">
                                        €<?php echo number_format($order['details']['total_amount'], 2); ?>
                                    </div>
                                </div>

                                <div class="order-items-preview">
                                    <div style="font-size: 0.9rem; color: #667eea; font-weight: 600; margin-bottom: 5px;">
                                        Riepilogo Ordine
                                    </div>
                                    <div style="display: flex; justify-content: space-between; color: #2d3748;">
                                        <span><?php echo $order['details']['products_count']; ?> prodotti</span>
                                        <span><?php echo $order['details']['total_quantity']; ?> articoli totali</span>
                                    </div>
                                </div>

                                <?php if (!empty($order['tracking'])): ?>
                                    <div class="tracking-section">
                                        <div style="margin-bottom: 5px; font-weight: 600; color: #2d3748;">
                                            🚚 Codice Tracking
                                        </div>
                                        <div class="tracking-code">
                                            <?php echo htmlspecialchars($order['tracking']); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="click-hint">
                                    👆 Clicca per vedere tutti i dettagli
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal per dettagli ordine -->
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalOrderTitle">Dettagli Ordine</h2>
                <button class="close" onclick="closeOrderModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalOrderBody">
                <!-- Contenuto caricato dinamicamente -->
            </div>
        </div>
    </div>

    <script>
        // Dati ordini per JavaScript
        const ordersData = <?php echo json_encode($orders, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        function openOrderModal(orderId) {
            // Trova l'ordine nei dati
            const order = ordersData.find(o => o.raw_id == orderId);
            if (!order) return;

            // Imposta il titolo
            document.getElementById('modalOrderTitle').textContent = `Dettagli ${order.id}`;

            // Genera il contenuto del modal
            let modalContent = `
        <div style="background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1)); padding: 20px; border-radius: 10px; margin-bottom: 20px;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div>
                    <strong>Data Ordine:</strong><br>
                    ${order.date}
                </div>
                <div>
                    <strong>Stato:</strong><br>
                    <span class="order-status status-${order.status.toLowerCase().replace(/ /g, '').replace(/à/g, 'a')}">${order.status}</span>
                </div>
                ${order.tracking ? `
                <div>
                    <strong>Tracking:</strong><br>
                    <code style="background: rgba(255,255,255,0.8); padding: 4px 8px; border-radius: 4px;">${order.tracking}</code>
                </div>` : ''}
                <div>
                    <strong>Totale:</strong><br>
                    <span style="font-size: 1.2rem; color: #059669; font-weight: 700;">€${order.details.total_amount.toFixed(2)}</span>
                </div>
            </div>
        </div>

        <div style="margin-bottom: 20px;">
            <h3 style="color: #2d3748; margin-bottom: 15px;">Indirizzo di Spedizione</h3>
            <div style="background: #f8f9fa; padding: 15px; border-radius: 10px;">
                ${order.address}
            </div>
        </div>

        <div>
            <h3 style="color: #2d3748; margin-bottom: 15px;">Prodotti Ordinati (${order.details.products_count})</h3>
            <div style="space-y: 10px;">
    `;

            // Aggiungi ogni prodotto
            order.details.items.forEach(item => {
                const imageUrl = item.immagine || '<?php echo BASE_URL; ?>/images/default_product1.jpg';
                modalContent += `
            <div class="detail-item">
                <div class="item-details">
                    <img src="${imageUrl}" alt="${item.nome}" class="item-image"
                         onerror="this.src='<?php echo BASE_URL; ?>/images/default_product1.jpg';">
                    <div class="item-info">
                        <h4>${item.nome}</h4>
                        <p>${item.tipo === 'mystery_box' ? 'Mystery Box' : 'Oggetto'} - Quantità: ${item.quantita}</p>
                        <p>Prezzo unitario: €${item.prezzo.toFixed(2)}</p>
                    </div>
                </div>
                <div class="item-total">
                    €${item.totale.toFixed(2)}
                </div>
            </div>
        `;
            });

            modalContent += `
            </div>
        </div>

        <div style="background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1)); padding: 20px; border-radius: 10px; margin-top: 20px; text-align: center;">
            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 1.2rem; font-weight: 700; color: #2d3748;">
                <span>Totale Ordine:</span>
                <span style="color: #059669;">€${order.details.total_amount.toFixed(2)}</span>
            </div>
        </div>
    `;

            // Imposta il contenuto e mostra il modal
            document.getElementById('modalOrderBody').innerHTML = modalContent;
            document.getElementById('orderModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeOrderModal() {
            document.getElementById('orderModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Chiudi modal cliccando fuori
        document.getElementById('orderModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeOrderModal();
            }
        });

        // Chiudi modal con ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeOrderModal();
            }
        });
    </script>

<?php include __DIR__ . '/footer.php'; ?>