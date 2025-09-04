<?php
/**
 * pages/conferma_ordine.php - VERSIONE FINALE PULITA
 * Supporta sia Mystery Box che oggetti singoli
 */

require_once __DIR__.'/../include/session_manager.php';
require_once __DIR__.'/../include/config.inc.php';

SessionManager::requireLogin();

$ultimo_ordine = SessionManager::get('ultimo_ordine');

if (!$ultimo_ordine) {
    SessionManager::setFlashMessage('Nessun ordine da visualizzare.', 'info');
    header('Location: ' . BASE_URL . '/pages/cart.php');
    exit();
}

// Recupera dettagli dell'ordine dal database
$db_config = $config['dbms']['localhost'];
$conn = new mysqli(
        $db_config['host'],
        $db_config['user'],
        $db_config['passwd'],
        $db_config['dbname']
);

$ordine_dettagli = null;
$prodotti_ordine = [];

if (!$conn->connect_error) {
    // Recupera dettagli ordine
    $stmt = $conn->prepare("
        SELECT 
            o.id_ordine,
            o.data_ordine,
            o.stato_ordine,
            CONCAT(i.via, ' ', i.civico, ', ', i.cap, ' ', i.citta, ' (', i.provincia, ')') as indirizzo_completo
        FROM ordine o
        JOIN indirizzo_spedizione i ON o.fk_indirizzo = i.id_indirizzo
        WHERE o.id_ordine = ? AND o.fk_utente = ?
    ");

    if ($stmt) {
        $current_user_id = SessionManager::get('user_id');
        $stmt->bind_param("ii", $ultimo_ordine['id'], $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $ordine_dettagli = $result->fetch_assoc();
        }
        $stmt->close();

        // Recupera prodotti dell'ordine
        $stmt = $conn->prepare("
            SELECT 
                io.quantita_ordine,
                io.totale_ordine,
                io.fk_box,
                io.fk_oggetto,
                CASE 
                    WHEN io.fk_box IS NOT NULL THEN mb.nome_box
                    WHEN io.fk_oggetto IS NOT NULL THEN o.nome_oggetto
                END as nome_prodotto,
                CASE 
                    WHEN io.fk_box IS NOT NULL THEN mb.prezzo_box
                    WHEN io.fk_oggetto IS NOT NULL THEN o.prezzo_oggetto
                END as prezzo_unitario,
                CASE 
                    WHEN io.fk_box IS NOT NULL THEN mb.desc_box
                    WHEN io.fk_oggetto IS NOT NULL THEN o.desc_oggetto
                END as descrizione_prodotto,
                CASE 
                    WHEN io.fk_box IS NOT NULL THEN 'Mystery Box'
                    WHEN io.fk_oggetto IS NOT NULL THEN 'Oggetto'
                END as tipo_prodotto
            FROM info_ordine io
            LEFT JOIN mystery_box mb ON io.fk_box = mb.id_box
            LEFT JOIN oggetto o ON io.fk_oggetto = o.id_oggetto
            WHERE io.fk_ordine = ?
        ");

        if ($stmt) {
            $stmt->bind_param("i", $ultimo_ordine['id']);
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $prodotti_ordine[] = $row;
            }
            $stmt->close();
        }
    }

    $conn->close();
}

include __DIR__ . '/header.php';
?>

    <style>
        .confirmation-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }

        .confirmation-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }

        .success-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 3rem 2rem;
            text-align: center;
        }

        .order-details {
            padding: 2rem;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .detail-row:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 1.2em;
            color: #28a745;
        }

        .product-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid #28a745;
            transition: all 0.3s ease;
        }

        .product-item:hover {
            background: #e9ecef;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .mystery-box-item {
            border-left-color: #6f42c1;
        }

        .oggetto-item {
            border-left-color: #fd7e14;
        }

        .product-type-badge {
            font-size: 0.8em;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-weight: 600;
        }

        .badge-mystery-box {
            background: #6f42c1;
            color: white;
        }

        .badge-oggetto {
            background: #fd7e14;
            color: white;
        }

        .action-buttons {
            padding: 2rem;
            background: #f8f9fa;
            text-align: center;
        }

        .btn-custom {
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            margin: 0 10px;
            transition: all 0.3s ease;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            color: white;
        }

        .btn-outline-custom {
            background: transparent;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-outline-custom:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }

        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
            margin-top: 1rem;
        }

        .status-processing {
            background: #fff3cd;
            color: #856404;
        }

        .animation-bounce {
            animation: bounce 2s infinite;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }

        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            border: 1px solid #e9ecef;
        }

        .text-purple { color: #6f42c1 !important; }
        .text-orange { color: #fd7e14 !important; }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }
    </style>

    <div class="confirmation-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="confirmation-card">

                        <!-- Header di Successo -->
                        <div class="success-header">
                            <div class="animation-bounce mb-3">
                                <i class="bi bi-check-circle-fill" style="font-size: 4rem;"></i>
                            </div>
                            <h1 class="mb-2">Ordine Completato!</h1>
                            <h3 class="mb-0">Numero Ordine: #<?php echo $ultimo_ordine['id']; ?></h3>

                            <?php if ($ordine_dettagli): ?>
                                <div class="status-badge status-processing">
                                    <i class="bi bi-clock me-2"></i>
                                    <?php
                                    $stati = [0 => 'In elaborazione', 1 => 'Spedito', 2 => 'Consegnato', 3 => 'Annullato', 4 => 'Rimborsato'];
                                    echo $stati[$ordine_dettagli['stato_ordine']] ?? 'Sconosciuto';
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Statistiche Ordine -->
                        <div class="order-details">
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <div class="h4 mb-1 text-success">€<?php echo number_format($ultimo_ordine['totale'], 2); ?></div>
                                    <small class="text-muted">Totale Pagato</small>
                                </div>
                                <div class="stat-card">
                                    <div class="h4 mb-1 text-info"><?php echo $ultimo_ordine['quantita']; ?></div>
                                    <small class="text-muted">Articoli Totali</small>
                                </div>
                                <div class="stat-card">
                                    <div class="h4 mb-1 text-primary"><?php echo $ultimo_ordine['prodotti_inseriti'] ?? count($prodotti_ordine); ?></div>
                                    <small class="text-muted">Prodotti Elaborati</small>
                                </div>
                                <?php if (isset($ultimo_ordine['mystery_boxes']) && $ultimo_ordine['mystery_boxes'] > 0): ?>
                                    <div class="stat-card">
                                        <div class="h4 mb-1 text-purple"><?php echo $ultimo_ordine['mystery_boxes']; ?></div>
                                        <small class="text-muted">Mystery Box</small>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($ultimo_ordine['oggetti']) && $ultimo_ordine['oggetti'] > 0): ?>
                                    <div class="stat-card">
                                        <div class="h4 mb-1 text-orange"><?php echo $ultimo_ordine['oggetti']; ?></div>
                                        <small class="text-muted">Oggetti</small>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <h5><i class="bi bi-receipt me-2"></i>Riepilogo Ordine</h5>

                                    <div class="detail-row">
                                        <span>Subtotale:</span>
                                        <span>€<?php echo number_format($ultimo_ordine['subtotale'] ?? $ultimo_ordine['totale'], 2); ?></span>
                                    </div>

                                    <div class="detail-row">
                                        <span>Spedizione:</span>
                                        <span>
                                        <?php
                                        $spedizione = $ultimo_ordine['spedizione'] ?? 0;
                                        echo $spedizione == 0 ? 'GRATUITA' : '€' . number_format($spedizione, 2);
                                        ?>
                                    </span>
                                    </div>

                                    <div class="detail-row">
                                        <span>TOTALE PAGATO:</span>
                                        <span>€<?php echo number_format($ultimo_ordine['totale'], 2); ?></span>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <h5><i class="bi bi-info-circle me-2"></i>Informazioni Ordine</h5>

                                    <div class="detail-row">
                                        <span>Metodo di pagamento:</span>
                                        <span><?php echo ucfirst(str_replace('_', ' ', $ultimo_ordine['metodo_pagamento'])); ?></span>
                                    </div>

                                    <?php if ($ordine_dettagli && !empty($ordine_dettagli['data_ordine'])): ?>
                                        <div class="detail-row">
                                            <span>Data ordine:</span>
                                            <span><?php echo date('d/m/Y H:i', strtotime($ordine_dettagli['data_ordine'])); ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (isset($ultimo_ordine['errori']) && $ultimo_ordine['errori'] > 0): ?>
                                        <div class="detail-row">
                                            <span>Avvisi:</span>
                                            <span class="text-warning"><?php echo $ultimo_ordine['errori']; ?> item non processati</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Prodotti nell'ordine -->
                            <?php if (!empty($prodotti_ordine)): ?>
                                <div class="mt-4">
                                    <h5><i class="bi bi-box me-2"></i>Prodotti Ordinati</h5>
                                    <?php foreach ($prodotti_ordine as $prodotto): ?>
                                        <div class="product-item <?php echo $prodotto['tipo_prodotto'] == 'Mystery Box' ? 'mystery-box-item' : 'oggetto-item'; ?>">
                                            <div class="row align-items-center">
                                                <div class="col-md-7">
                                                    <div class="d-flex align-items-center mb-1">
                                                        <h6 class="mb-0 me-2"><?php echo htmlspecialchars($prodotto['nome_prodotto']); ?></h6>
                                                        <span class="product-type-badge <?php echo $prodotto['tipo_prodotto'] == 'Mystery Box' ? 'badge-mystery-box' : 'badge-oggetto'; ?>">
                                                        <?php echo $prodotto['tipo_prodotto']; ?>
                                                    </span>
                                                    </div>
                                                    <?php if (!empty($prodotto['descrizione_prodotto'])): ?>
                                                        <small class="text-muted"><?php echo htmlspecialchars($prodotto['descrizione_prodotto']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-2 text-center">
                                                    <span class="badge bg-secondary">Qtà: <?php echo $prodotto['quantita_ordine']; ?></span>
                                                </div>
                                                <div class="col-md-2 text-center">
                                                    <small class="text-muted">€<?php echo number_format($prodotto['prezzo_unitario'], 2); ?> cad.</small>
                                                </div>
                                                <div class="col-md-1 text-end">
                                                    <strong>€<?php echo number_format($prodotto['totale_ordine'], 2); ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>

                                    <!-- Riepilogo tipologie -->
                                    <?php
                                    $mystery_count = 0;
                                    $oggetti_count = 0;
                                    foreach ($prodotti_ordine as $prodotto) {
                                        if ($prodotto['tipo_prodotto'] == 'Mystery Box') {
                                            $mystery_count++;
                                        } elseif ($prodotto['tipo_prodotto'] == 'Oggetto') {
                                            $oggetti_count++;
                                        }
                                    }
                                    ?>
                                    <div class="mt-3 p-3 bg-light border-radius">
                                        <small class="text-muted">
                                            <i class="bi bi-graph-up me-1"></i>
                                            <strong>Riepilogo:</strong>
                                            <?php if ($mystery_count > 0): ?>
                                                <?php echo $mystery_count; ?> Mystery Box
                                            <?php endif; ?>
                                            <?php if ($mystery_count > 0 && $oggetti_count > 0): ?> + <?php endif; ?>
                                            <?php if ($oggetti_count > 0): ?>
                                                <?php echo $oggetti_count; ?> Oggetto<?php echo $oggetti_count > 1 ? 'i' : ''; ?>
                                            <?php endif; ?>
                                            = <?php echo count($prodotti_ordine); ?> prodotto<?php echo count($prodotti_ordine) > 1 ? 'i' : ''; ?> diverso<?php echo count($prodotti_ordine) > 1 ? 'i' : ''; ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Indirizzo di spedizione -->
                            <?php if ($ordine_dettagli && !empty($ordine_dettagli['indirizzo_completo'])): ?>
                                <div class="mt-4">
                                    <h5><i class="bi bi-geo-alt me-2"></i>Indirizzo di Spedizione</h5>
                                    <div class="product-item">
                                        <i class="bi bi-house-door me-2"></i>
                                        <?php echo htmlspecialchars($ordine_dettagli['indirizzo_completo']); ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Note aggiuntive -->
                            <?php if (!empty($ultimo_ordine['note'])): ?>
                                <div class="mt-4">
                                    <h5><i class="bi bi-chat-text me-2"></i>Note</h5>
                                    <div class="product-item">
                                        <em><?php echo htmlspecialchars($ultimo_ordine['note']); ?></em>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Pulsanti di Azione -->
                        <div class="action-buttons">
                            <a href="<?php echo BASE_URL; ?>/pages/ordini.php" class="btn-custom btn-primary-custom">
                                <i class="bi bi-list-ul me-2"></i>I Miei Ordini
                            </a>
                            <a href="<?php echo BASE_URL; ?>/" class="btn-custom btn-outline-custom">
                                <i class="bi bi-house me-2"></i>Continua Shopping
                            </a>

                            <div class="mt-4">
                                <div class="alert alert-success">
                                    <i class="bi bi-check-circle me-2"></i>
                                    <strong>Ordine Confermato!</strong><br>
                                    <small>
                                        Il tuo ordine è stato ricevuto e verrà elaborato entro 24 ore<br>
                                        Riceverai una email di conferma con tutti i dettagli<br>
                                        Ti invieremo il codice di tracking una volta spedito<br>
                                        Per assistenza, contatta il nostro supporto clienti
                                    </small>
                                </div>

                                <?php if (isset($ultimo_ordine['errori']) && $ultimo_ordine['errori'] > 0): ?>
                                    <div class="alert alert-warning">
                                        <i class="bi bi-exclamation-triangle me-2"></i>
                                        <strong>Attenzione:</strong>
                                        <small><?php echo $ultimo_ordine['errori']; ?> articoli del carrello non sono stati processati. Contatta l'assistenza se ritieni sia un errore.</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Effetto confetti
        function createConfetti() {
            const colors = ['#6f42c1', '#fd7e14', '#28a745', '#007bff', '#ffc107'];

            for (let i = 0; i < 50; i++) {
                setTimeout(() => {
                    const confetti = document.createElement('div');
                    confetti.style.position = 'fixed';
                    confetti.style.left = Math.random() * 100 + 'vw';
                    confetti.style.top = '-10px';
                    confetti.style.width = '8px';
                    confetti.style.height = '8px';
                    confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                    confetti.style.pointerEvents = 'none';
                    confetti.style.borderRadius = '50%';
                    confetti.style.zIndex = '9999';
                    confetti.style.animation = 'fall 3s linear forwards';

                    document.body.appendChild(confetti);

                    setTimeout(() => {
                        confetti.remove();
                    }, 3000);
                }, i * 100);
            }
        }

        // CSS per l'animazione
        const style = document.createElement('style');
        style.textContent = `
    @keyframes fall {
        to {
            transform: translateY(100vh) rotate(360deg);
        }
    }
`;
        document.head.appendChild(style);

        // Avvia confetti
        window.addEventListener('load', () => {
            setTimeout(createConfetti, 500);
        });
    </script>

<?php
// Pulisci i dati dell'ultimo ordine dopo la visualizzazione
SessionManager::remove('ultimo_ordine');
include __DIR__ . '/footer.php';
?>