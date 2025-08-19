<?php
/**
 * checkout.php - SOLO VISUALIZZAZIONE E RACCOLTA DATI
 * NON PROCESSA L'ORDINE QUI!
 */
require_once __DIR__ . '/../include/session_manager.php';
require_once __DIR__ . '/../include/config.inc.php';

// Richiedi autenticazione
SessionManager::requireLogin();

// Recupera dati checkout dalla sessione (impostati da checkout_action.php)
$checkout_data = SessionManager::get('checkout_data');

if (!$checkout_data || !isset($checkout_data['items'])) {
    SessionManager::setFlashMessage('Sessione scaduta. Riprova.', 'warning');
    header('Location: ' . BASE_URL . '/pages/cart.php');
    exit();
}

// Connessione database
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

$id_utente = SessionManager::get('user_id');

// Usa i dati dal checkout_data invece di recuperarli di nuovo
$cart_items = $checkout_data['items'];
$subtotal = $checkout_data['totale'];

// Calcola spedizione
$shipping_cost = $subtotal >= 50 ? 0 : 5.00;
$total = $subtotal + $shipping_cost;

// Recupera indirizzi di spedizione esistenti
$address_query = "SELECT * FROM indirizzo_spedizione WHERE fk_utente = ? ORDER BY id_indirizzo DESC";
$address_stmt = $conn->prepare($address_query);
$address_stmt->bind_param("i", $id_utente);
$address_stmt->execute();
$address_result = $address_stmt->get_result();

$existing_addresses = [];
while ($row = $address_result->fetch_assoc()) {
    $existing_addresses[] = $row;
}

$conn->close();

// Include header
include __DIR__ . '/header.php';
?>

    <style>
        .checkout-container {
            background: #f8f9fa;
            min-height: 100vh;
            padding: 2rem 0;
        }

        .checkout-section {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .checkout-item {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            background: #fafafa;
        }

        .address-option {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            background: #f8f9fa;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .address-option:hover {
            border-color: #007bff;
            background: #e3f2fd;
        }

        .payment-method {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            background: #f8f9fa;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .payment-method:hover {
            border-color: #007bff;
            background: #e3f2fd;
        }

        .order-summary {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            position: sticky;
            top: 2rem;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .summary-total {
            border-top: 2px solid #007bff;
            padding-top: 1rem;
            margin-top: 1rem;
            font-size: 1.2rem;
            font-weight: bold;
        }

        .btn-complete-order {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            color: white;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-complete-order:hover {
            background: linear-gradient(135deg, #218838, #1ea789);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .new-address-form {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 1rem;
            border: 1px solid #dee2e6;
        }
    </style>

    <main class="checkout-container">
        <div class="container py-4">
            <h1 class="text-center mb-4">
                <i class="bi bi-credit-card me-2"></i>Completa il tuo ordine
            </h1>

            <div class="row">
                <!-- Colonna principale -->
                <div class="col-lg-8">
                    <!-- Riepilogo Ordine -->
                    <div class="checkout-section">
                        <h3><i class="bi bi-bag-check me-2"></i>Riepilogo Ordine</h3>

                        <?php foreach ($cart_items as $item): ?>
                            <div class="checkout-item">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <h5><?php echo htmlspecialchars($item['nome_prodotto']); ?></h5>
                                        <?php if(isset($item['descrizione_prodotto'])): ?>
                                            <p class="text-muted mb-0">
                                                <?php echo htmlspecialchars(substr($item['descrizione_prodotto'], 0, 80)) . '...'; ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-2 text-center">
                                        <span class="badge bg-primary">Qtà: <?php echo $item['quantita']; ?></span>
                                    </div>
                                    <div class="col-md-2 text-center">
                                        €<?php echo number_format($item['prezzo_unitario'] ?? 0, 2); ?>
                                    </div>
                                    <div class="col-md-2 text-end">
                                        <strong>€<?php echo number_format($item['totale'], 2); ?></strong>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- FORM CHE PUNTA A process_payment_action.php -->
                    <form method="POST" action="<?php echo BASE_URL; ?>/action/process_payment_action.php" id="checkout-form">

                        <!-- Indirizzo di Spedizione -->
                        <div class="checkout-section">
                            <h3><i class="bi bi-truck me-2"></i>Indirizzo di Spedizione</h3>

                            <?php if (!empty($existing_addresses)): ?>
                                <?php foreach ($existing_addresses as $address): ?>
                                    <div class="form-check address-option">
                                        <input class="form-check-input" type="radio"
                                               name="indirizzo_id"
                                               value="<?php echo $address['id_indirizzo']; ?>"
                                               id="addr_<?php echo $address['id_indirizzo']; ?>"
                                                <?php echo ($address === reset($existing_addresses)) ? 'checked' : ''; ?>>
                                        <label class="form-check-label w-100" for="addr_<?php echo $address['id_indirizzo']; ?>">
                                            <strong><?php echo htmlspecialchars($address['via']); ?> <?php echo htmlspecialchars($address['civico']); ?></strong><br>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($address['cap']); ?> <?php echo htmlspecialchars($address['citta']); ?>
                                                (<?php echo htmlspecialchars($address['provincia']); ?>)
                                            </small>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    Nessun indirizzo salvato.
                                    <a href="<?php echo BASE_URL; ?>/pages/gestione_indirizzi.php">Aggiungi un indirizzo</a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Metodo di Pagamento -->
                        <div class="checkout-section">
                            <h3><i class="bi bi-credit-card me-2"></i>Metodo di Pagamento</h3>

                            <div class="payment-methods">
                                <div class="form-check payment-method">
                                    <input class="form-check-input" type="radio"
                                           name="payment_method"
                                           value="carta_credito"
                                           id="credit_card"
                                           checked>
                                    <label class="form-check-label payment-option w-100" for="credit_card">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-credit-card-2-front fs-4 me-3 text-primary"></i>
                                            <div>
                                                <strong>Carta di Credito</strong>
                                                <small class="d-block text-muted">Visa, Mastercard, American Express</small>
                                            </div>
                                        </div>
                                    </label>
                                </div>

                                <div class="form-check payment-method">
                                    <input class="form-check-input" type="radio"
                                           name="payment_method"
                                           value="paypal"
                                           id="paypal">
                                    <label class="form-check-label payment-option w-100" for="paypal">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-paypal fs-4 me-3 text-primary"></i>
                                            <div>
                                                <strong>PayPal</strong>
                                                <small class="d-block text-muted">Paga con il tuo account PayPal</small>
                                            </div>
                                        </div>
                                    </label>
                                </div>

                                <div class="form-check payment-method">
                                    <input class="form-check-input" type="radio"
                                           name="payment_method"
                                           value="bonifico"
                                           id="bank_transfer">
                                    <label class="form-check-label payment-option w-100" for="bank_transfer">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-bank fs-4 me-3 text-primary"></i>
                                            <div>
                                                <strong>Bonifico Bancario</strong>
                                                <small class="d-block text-muted">Riceverai le coordinate via email</small>
                                            </div>
                                        </div>
                                    </label>
                                </div>

                                <div class="form-check payment-method">
                                    <input class="form-check-input" type="radio"
                                           name="payment_method"
                                           value="contrassegno"
                                           id="cash_on_delivery">
                                    <label class="form-check-label payment-option w-100" for="cash_on_delivery">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-cash fs-4 me-3 text-primary"></i>
                                            <div>
                                                <strong>Contrassegno</strong>
                                                <small class="d-block text-muted">Paga alla consegna (+€2.00)</small>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Note ordine (opzionale) -->
                        <div class="checkout-section">
                            <h3><i class="bi bi-chat-left-text me-2"></i>Note per l'ordine (opzionale)</h3>
                            <textarea class="form-control"
                                      name="note_ordine"
                                      rows="3"
                                      placeholder="Aggiungi eventuali note per la consegna..."></textarea>
                        </div>
                    </form>
                </div>

                <!-- Sidebar Riepilogo -->
                <div class="col-lg-4">
                    <div class="order-summary">
                        <h4><i class="bi bi-receipt me-2"></i>Riepilogo Prezzi</h4>

                        <div class="summary-row">
                            <span>Subtotale:</span>
                            <span>€<?php echo number_format($subtotal, 2); ?></span>
                        </div>

                        <div class="summary-row">
                            <span>Spedizione:</span>
                            <span class="<?php echo $shipping_cost == 0 ? 'text-success fw-bold' : ''; ?>">
                            <?php echo $shipping_cost == 0 ? 'GRATUITA' : '€' . number_format($shipping_cost, 2); ?>
                        </span>
                        </div>

                        <?php if ($subtotal < 50): ?>
                            <div class="alert alert-info mt-2 p-2">
                                <small>
                                    <i class="bi bi-info-circle me-1"></i>
                                    Spendi altri €<?php echo number_format(50 - $subtotal, 2); ?> per la spedizione gratuita!
                                </small>
                            </div>
                        <?php endif; ?>

                        <div class="summary-row summary-total">
                            <span>Totale:</span>
                            <span>€<?php echo number_format($total, 2); ?></span>
                        </div>

                        <!-- IL BOTTONE INVIA IL FORM A process_payment_action.php -->
                        <button type="submit" form="checkout-form" class="btn-complete-order mt-3">
                            <i class="bi bi-check-circle me-2"></i>Completa Ordine
                        </button>

                        <div class="text-center mt-3">
                            <small class="text-muted">
                                <i class="bi bi-shield-check me-1"></i>
                                I tuoi dati sono protetti con crittografia SSL
                            </small>
                        </div>

                        <hr>

                        <div class="text-center">
                            <a href="<?php echo BASE_URL; ?>/pages/cart.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Torna al carrello
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Validazione form
        document.getElementById('checkout-form').addEventListener('submit', function(e) {
            const addressSelected = document.querySelector('input[name="indirizzo_id"]:checked');
            const paymentSelected = document.querySelector('input[name="payment_method"]:checked');

            if (!addressSelected) {
                e.preventDefault();
                alert('Seleziona un indirizzo di spedizione');
                return false;
            }

            if (!paymentSelected) {
                e.preventDefault();
                alert('Seleziona un metodo di pagamento');
                return false;
            }

            // Conferma ordine
            if (!confirm('Confermi di voler completare questo ordine?')) {
                e.preventDefault();
                return false;
            }
        });
    </script>

<?php include __DIR__ . '/footer.php'; ?>