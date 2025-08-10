<?php
// pages/checkout.php - Versione corretta per admin_boxomnia.sql
require_once __DIR__ . '/../include/session_manager.php';
require_once __DIR__ . '/../include/config.inc.php';

// Richiedi autenticazione
SessionManager::requireLogin();

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

// ✅ RECUPERA IL CARRELLO DAL DATABASE (admin_boxomnia.sql ha carrello completo)
$cart_query = "
    SELECT 
        c.*,
        COALESCE(mb.nome_box, o.nome_oggetto) as nome_prodotto,
        COALESCE(mb.desc_box, o.desc_oggetto) as descrizione_prodotto,
        COALESCE(mb.prezzo_box, o.prezzo_oggetto) as prezzo_unitario,
        COALESCE(
            CONCAT('" . BASE_URL . "/images/', mb.nome_box, '.png'),
            CONCAT('" . BASE_URL . "/images/', img.nome_img),
            '" . BASE_URL . "/images/default_product1.jpg'
        ) as immagine_prodotto
    FROM carrello c
    LEFT JOIN mystery_box mb ON c.fk_mystery_box = mb.id_box
    LEFT JOIN oggetto o ON c.fk_oggetto = o.id_oggetto
    LEFT JOIN immagine img ON o.id_oggetto = img.fk_oggetto
    WHERE c.fk_utente = ? AND c.id_carrello NOT IN (SELECT fk_carrello FROM ordine)
    ORDER BY c.id_carrello DESC
";

$stmt = $conn->prepare($cart_query);
$stmt->bind_param("i", $id_utente);
$stmt->execute();
$cart_result = $stmt->get_result();

$cart_items = [];
$subtotal = 0;

while ($row = $cart_result->fetch_assoc()) {
    $cart_items[] = $row;
    $subtotal += $row['totale'];
}

// Se il carrello è vuoto, reindirizza al carrello
if (empty($cart_items)) {
    header('Location: ' . BASE_URL . '/pages/cart.php');
    exit();
}

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

// Gestione form di checkout
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_order'])) {
    $selected_address_id = $_POST['address_id'] ?? null;
    $payment_method = $_POST['payment_method'] ?? '';

    // Validazione
    if (!$selected_address_id && !isset($_POST['new_address'])) {
        $errors[] = "Seleziona un indirizzo di spedizione";
    }

    if (empty($payment_method)) {
        $errors[] = "Seleziona un metodo di pagamento";
    }

    // Se è un nuovo indirizzo, valida i campi
    if (isset($_POST['new_address'])) {
        $via = trim($_POST['via'] ?? '');
        $civico = trim($_POST['civico'] ?? '');
        $cap = trim($_POST['cap'] ?? '');
        $citta = trim($_POST['citta'] ?? '');
        $provincia = trim($_POST['provincia'] ?? '');

        if (empty($via) || empty($civico) || empty($cap) || empty($citta) || empty($provincia)) {
            $errors[] = "Tutti i campi dell'indirizzo sono obbligatori";
        }
    }

    // Se non ci sono errori, procedi con l'ordine
    if (empty($errors)) {
        try {
            $conn->begin_transaction();

            // Se è un nuovo indirizzo, inseriscilo
            if (isset($_POST['new_address'])) {
                $address_insert = "INSERT INTO indirizzo_spedizione (fk_utente, via, civico, cap, citta, provincia, nazione) VALUES (?, ?, ?, ?, ?, ?, 'Italia')";
                $addr_stmt = $conn->prepare($address_insert);
                $addr_stmt->bind_param("isssss", $id_utente, $via, $civico, $cap, $citta, $provincia);
                $addr_stmt->execute();
                $selected_address_id = $conn->insert_id;
            }

            // ✅ CREA L'ORDINE PER OGNI ITEM DEL CARRELLO (con fk_carrello)
            foreach ($cart_items as $item) {
                $order_insert = "INSERT INTO ordine (fk_utente, fk_carrello, fk_indirizzo, data_ordine, stato_ordine) VALUES (?, ?, ?, NOW(), 0)";
                $order_stmt = $conn->prepare($order_insert);
                $order_stmt->bind_param("iii", $id_utente, $item['id_carrello'], $selected_address_id);
                $order_stmt->execute();
                $order_id = $conn->insert_id;

                // ✅ CREA LA FATTURA COLLEGATA ALL'UTENTE
                $fattura_insert = "INSERT INTO fattura (tipo, totale_fattura, data_emissione, fk_utente) VALUES (?, ?, NOW(), ?)";
                $fattura_stmt = $conn->prepare($fattura_insert);
                $total_cents = $item['totale'] * 100; // Converti in centesimi per bigint
                $fattura_stmt->bind_param("sii", $payment_method, $total_cents, $id_utente);
                $fattura_stmt->execute();
            }

            $conn->commit();

            // Reindirizza alla pagina di successo
            SessionManager::setFlashMessage('Ordine completato con successo!', 'success');
            header('Location: ' . BASE_URL . '/pages/order_success.php?total=' . urlencode(number_format($total, 2)));
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            error_log("Errore checkout: " . $e->getMessage());
            $errors[] = "Errore nel completamento dell'ordine. Riprova più tardi.";
        }
    }
}

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

        .checkout-item img {
            max-height: 80px;
            object-fit: cover;
        }

        .address-option {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }

        .address-option:hover {
            border-color: #007bff;
            background: #e3f2fd;
        }

        .address-option input[type="radio"]:checked + label {
            color: #007bff;
            font-weight: 600;
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

        .payment-method input[type="radio"]:checked + .payment-option {
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

        .quantity-badge {
            background: #007bff;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
            font-size: 0.85rem;
        }

        .new-address-form {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 1rem;
            border: 1px solid #dee2e6;
        }

        .alert {
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
    </style>

    <main class="checkout-container">
        <div class="container py-4">
            <h1 class="text-center mb-4">
                <i class="bi bi-credit-card me-2"></i>Completa il tuo ordine
            </h1>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <h6><i class="bi bi-exclamation-triangle me-2"></i>Errori rilevati:</h6>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Colonna principale -->
                <div class="col-lg-8">
                    <!-- Riepilogo Ordine -->
                    <div class="checkout-section">
                        <h3><i class="bi bi-bag-check me-2"></i>Riepilogo Ordine</h3>

                        <?php foreach ($cart_items as $item): ?>
                            <div class="checkout-item">
                                <div class="row align-items-center">
                                    <div class="col-md-2">
                                        <img src="<?php echo htmlspecialchars($item['immagine_prodotto']); ?>"
                                             alt="<?php echo htmlspecialchars($item['nome_prodotto']); ?>"
                                             class="img-fluid rounded">
                                    </div>
                                    <div class="col-md-6">
                                        <h5><?php echo htmlspecialchars($item['nome_prodotto']); ?></h5>
                                        <p class="text-muted"><?php echo htmlspecialchars(substr($item['descrizione_prodotto'], 0, 80)) . '...'; ?></p>
                                    </div>
                                    <div class="col-md-2 text-center">
                                        <span class="quantity-badge">Qtà: <?php echo $item['quantita']; ?></span>
                                    </div>
                                    <div class="col-md-2 text-end">
                                        <strong>€<?php echo number_format($item['totale'], 2); ?></strong>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Form Checkout -->
                    <form method="POST" id="checkout-form">
                        <!-- Indirizzo di Spedizione -->
                        <div class="checkout-section">
                            <h3><i class="bi bi-truck me-2"></i>Indirizzo di Spedizione</h3>

                            <?php if (!empty($existing_addresses)): ?>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Seleziona un indirizzo esistente:</label>
                                    <?php foreach ($existing_addresses as $address): ?>
                                        <div class="form-check address-option">
                                            <input class="form-check-input" type="radio" name="address_id"
                                                   value="<?php echo $address['id_indirizzo']; ?>"
                                                   id="addr_<?php echo $address['id_indirizzo']; ?>"
                                                   onclick="hideNewAddressForm()">
                                            <label class="form-check-label w-100" for="addr_<?php echo $address['id_indirizzo']; ?>">
                                                <strong><?php echo htmlspecialchars($address['via']); ?> <?php echo htmlspecialchars($address['civico']); ?></strong><br>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($address['cap']); ?> <?php echo htmlspecialchars($address['citta']); ?>
                                                    (<?php echo htmlspecialchars($address['provincia']); ?>)
                                                </small>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>

                                    <div class="form-check address-option">
                                        <input class="form-check-input" type="radio" name="new_address" value="1"
                                               id="new_address" onclick="showNewAddressForm()">
                                        <label class="form-check-label fw-bold" for="new_address">
                                            <i class="bi bi-plus-circle me-2"></i>Aggiungi nuovo indirizzo
                                        </label>
                                    </div>
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="new_address" value="1">
                            <?php endif; ?>

                            <!-- Form nuovo indirizzo -->
                            <div id="new-address-form" class="new-address-form" style="<?php echo !empty($existing_addresses) ? 'display: none;' : ''; ?>">
                                <h5><i class="bi bi-geo-alt me-2"></i>Nuovo Indirizzo</h5>
                                <div class="row">
                                    <div class="col-md-8">
                                        <label for="via" class="form-label">Via/Viale/Piazza</label>
                                        <input type="text" class="form-control" id="via" name="via"
                                               placeholder="es. Via Roma" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="civico" class="form-label">Numero civico</label>
                                        <input type="text" class="form-control" id="civico" name="civico"
                                               placeholder="es. 123" required>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-4">
                                        <label for="cap" class="form-label">CAP</label>
                                        <input type="text" class="form-control" id="cap" name="cap"
                                               placeholder="es. 00100" pattern="[0-9]{5}" required>
                                    </div>
                                    <div class="col-md-5">
                                        <label for="citta" class="form-label">Città</label>
                                        <input type="text" class="form-control" id="citta" name="citta"
                                               placeholder="es. Roma" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="provincia" class="form-label">Provincia</label>
                                        <input type="text" class="form-control" id="provincia" name="provincia"
                                               placeholder="es. RM" maxlength="2" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Metodo di Pagamento -->
                        <div class="checkout-section">
                            <h3><i class="bi bi-credit-card me-2"></i>Metodo di Pagamento</h3>

                            <div class="payment-methods">
                                <div class="form-check payment-method">
                                    <input class="form-check-input" type="radio" name="payment_method"
                                           value="Carta di Credito" id="credit_card">
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
                                    <input class="form-check-input" type="radio" name="payment_method"
                                           value="PayPal" id="paypal">
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
                                    <input class="form-check-input" type="radio" name="payment_method"
                                           value="Bonifico Bancario" id="bank_transfer">
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
                                    <input class="form-check-input" type="radio" name="payment_method"
                                           value="Contrassegno" id="cash_on_delivery">
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

                        <button type="submit" form="checkout-form" name="complete_order" class="btn-complete-order mt-3">
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
        function showNewAddressForm() {
            document.getElementById('new-address-form').style.display = 'block';

            // Rendi obbligatori i campi del nuovo indirizzo
            const fields = ['via', 'civico', 'cap', 'citta', 'provincia'];
            fields.forEach(field => {
                document.getElementById(field).required = true;
            });

            // Deseleziona gli indirizzi esistenti
            const existingAddresses = document.querySelectorAll('input[name="address_id"]');
            existingAddresses.forEach(addr => addr.checked = false);
        }

        function hideNewAddressForm() {
            document.getElementById('new-address-form').style.display = 'none';

            // Rendi non obbligatori i campi del nuovo indirizzo
            const fields = ['via', 'civico', 'cap', 'citta', 'provincia'];
            fields.forEach(field => {
                const element = document.getElementById(field);
                if (element) {
                    element.required = false;
                }
            });
        }

        // Validazione form
        document.getElementById('checkout-form').addEventListener('submit', function(e) {
            const addressSelected = document.querySelector('input[name="address_id"]:checked') ||
                document.querySelector('input[name="new_address"]:checked');
            const paymentSelected = document.querySelector('input[name="payment_method"]:checked');

            if (!addressSelected) {
                e.preventDefault();
                alert('Seleziona un indirizzo di spedizione');
                return;
            }

            if (!paymentSelected) {
                e.preventDefault();
                alert('Seleziona un metodo di pagamento');
                return;
            }

            // Se è selezionato "nuovo indirizzo", verifica che i campi siano compilati
            if (document.querySelector('input[name="new_address"]:checked')) {
                const fields = ['via', 'civico', 'cap', 'citta', 'provincia'];
                for (let field of fields) {
                    const element = document.getElementById(field);
                    if (!element || !element.value.trim()) {
                        e.preventDefault();
                        alert('Compila tutti i campi dell\'indirizzo');
                        element.focus();
                        return;
                    }
                }
            }

            // Conferma ordine
            if (!confirm('Sei sicuro di voler completare questo ordine?')) {
                e.preventDefault();
            }
        });
    </script>

<?php include __DIR__ . '/footer.php'; ?>