<?php
// 1. PRIMA di qualsiasi output
require_once __DIR__.'/../include/session_manager.php';
require_once __DIR__.'/../include/config.inc.php';

// 2. Include la logica per recuperare il carrello
$cart_data = include '../action/get_cart.php';

// 3. Include header
include  'header.php';
?>

    <main class="background-custom">
        <div class="container py-5">
            <h1 class="fashion_taital mb-5">Il tuo Carrello</h1>

            <?php if (empty($cart_data['items'])): ?>
                <!-- Carrello vuoto -->
                <div class="empty-cart-container">
                    <div class="empty-cart-icon">
                        <i class="bi bi-cart-x"></i>
                    </div>
                    <h3>Il tuo carrello è vuoto</h3>
                    <p>Aggiungi qualche prodotto dal nostro catalogo per iniziare!</p>
                    <a href="<?php echo BASE_URL; ?>/pages/pokémon.php" class="btn btn-primary btn-lg">
                        <i class="bi bi-shop"></i> Vai al Catalogo
                    </a>
                </div>
            <?php else: ?>
                <!-- Carrello con prodotti -->
                <div class="cart-container">
                    <div class="row">
                        <!-- Lista prodotti -->
                        <div class="col-lg-8">
                            <div class="cart-items">
                                <?php foreach ($cart_data['items'] as $item): ?>
                                    <div class="cart-item" data-item-id="<?php echo $item['cart_key']; ?>">
                                        <div class="cart-item-image">
                                            <img src="<?php echo htmlspecialchars($item['image']); ?>"
                                                 alt="<?php echo htmlspecialchars($item['nome']); ?>"
                                                 onerror="this.src='<?php echo BASE_URL; ?>/images/default_product.png'">
                                        </div>
                                        <div class="cart-item-details">
                                            <h5 class="cart-item-name"><?php echo htmlspecialchars($item['nome']); ?></h5>
                                            <p class="cart-item-type">
                                            <span class="badge bg-<?php echo $item['tipo'] === 'mystery_box' ? 'primary' : 'secondary'; ?>">
                                                <?php echo $item['tipo'] === 'mystery_box' ? 'Mystery Box' : 'Accessorio'; ?>
                                            </span>
                                            </p>
                                            <div class="cart-item-price">
                                                €<?php echo number_format($item['prezzo'], 2); ?>
                                            </div>
                                        </div>
                                        <div class="cart-item-quantity">
                                            <label>Quantità:</label>
                                            <div class="quantity-controls">
                                                <button type="button" class="btn-quantity" onclick="updateQuantity('<?php echo $item['cart_key']; ?>', -1)">-</button>
                                                <input type="number" value="<?php echo $item['quantita']; ?>"
                                                       min="1" max="99" class="quantity-input"
                                                       onchange="updateQuantity('<?php echo $item['cart_key']; ?>', 0, this.value)">
                                                <button type="button" class="btn-quantity" onclick="updateQuantity('<?php echo $item['cart_key']; ?>', 1)">+</button>
                                            </div>
                                        </div>
                                        <div class="cart-item-total">
                                            €<?php echo number_format($item['prezzo'] * $item['quantita'], 2); ?>
                                        </div>
                                        <div class="cart-item-remove">
                                            <button type="button" class="btn-remove" onclick="removeItem('<?php echo $item['cart_key']; ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Riepilogo ordine -->
                        <div class="col-lg-4">
                            <div class="cart-summary">
                                <h4>Riepilogo Ordine</h4>
                                <div class="summary-item">
                                    <span>Subtotale (<?php echo $cart_data['total_items']; ?> articoli):</span>
                                    <span>€<?php echo number_format($cart_data['subtotal'], 2); ?></span>
                                </div>
                                <div class="summary-item">
                                    <span>Spedizione:</span>
                                    <span><?php echo $cart_data['subtotal'] >= 50 ? 'Gratuita' : '€5.00'; ?></span>
                                </div>
                                <?php if ($cart_data['subtotal'] < 50): ?>
                                    <div class="summary-note">
                                        <i class="bi bi-info-circle"></i>
                                        Spendi altri €<?php echo number_format(50 - $cart_data['subtotal'], 2); ?> per la spedizione gratuita!
                                    </div>
                                <?php endif; ?>
                                <hr>
                                <div class="summary-total">
                                    <span>Totale:</span>
                                    <span>€<?php echo number_format($cart_data['total'], 2); ?></span>
                                </div>

                                <?php if (SessionManager::isLoggedIn()): ?>
                                    <button class="btn btn-success btn-lg w-100 mt-3" onclick="proceedToCheckout()">
                                        <i class="bi bi-credit-card"></i> Procedi al Pagamento
                                    </button>
                                <?php else: ?>
                                    <div class="login-required mt-3">
                                        <p><i class="bi bi-info-circle"></i> Devi effettuare l'accesso per completare l'acquisto</p>
                                        <a href="<?php echo BASE_URL; ?>/pages/auth/login.php" class="btn btn-primary w-100">
                                            <i class="bi bi-person"></i> Accedi
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <a href="<?php echo BASE_URL; ?>/pages/pokémon.php" class="btn btn-outline-primary w-100 mt-2">
                                    <i class="bi bi-arrow-left"></i> Continua Shopping
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function updateQuantity(itemKey, change, newValue = null) {
            let quantity;

            if (newValue !== null) {
                quantity = parseInt(newValue);
            } else {
                const input = document.querySelector(`[data-item-id="${itemKey}"] .quantity-input`);
                quantity = parseInt(input.value) + change;
            }

            if (quantity < 1) quantity = 1;
            if (quantity > 99) quantity = 99;

            // Aggiorna l'input visivamente
            const input = document.querySelector(`[data-item-id="${itemKey}"] .quantity-input`);
            if (input) {
                input.value = quantity;
            }

            console.log('Sending request with:', {
                action: 'update',
                item_key: itemKey,
                quantity: quantity
            });

            // Aggiorna via AJAX
            fetch('<?php echo BASE_URL; ?>/action/update_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update&item_key=${encodeURIComponent(itemKey)}&quantity=${quantity}`
            })
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response headers:', response.headers);

                    // Prima ottieni il testo della risposta
                    return response.text().then(text => {
                        console.log('Raw response text:', text);

                        // Controlla se la risposta inizia con '<' (potrebbe essere HTML di errore)
                        if (text.trim().startsWith('<')) {
                            throw new Error('Received HTML instead of JSON. Check PHP errors.');
                        }

                        // Prova a fare il parse del JSON
                        try {
                            const data = JSON.parse(text);
                            return data;
                        } catch (e) {
                            console.error('JSON parse error:', e);
                            console.error('Failed to parse:', text);
                            throw new Error('Invalid JSON response');
                        }
                    });
                })
                .then(data => {
                    console.log('Parsed data:', data);
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Errore: ' + (data.message || 'Impossibile aggiornare la quantità'));
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    alert('Errore nell\'aggiornare la quantità. Apri la console del browser per vedere i dettagli.');
                });
        }

        function removeItem(itemKey) {
            if (confirm('Sei sicuro di voler rimuovere questo articolo dal carrello?')) {
                console.log('Removing item:', itemKey);

                fetch('<?php echo BASE_URL; ?>/action/update_cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=remove&item_key=${encodeURIComponent(itemKey)}`
                })
                    .then(response => {
                        console.log('Remove response status:', response.status);

                        return response.text().then(text => {
                            console.log('Raw remove response:', text);

                            if (text.trim().startsWith('<')) {
                                throw new Error('Received HTML instead of JSON');
                            }

                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                console.error('JSON parse error:', e);
                                throw new Error('Invalid JSON response');
                            }
                        });
                    })
                    .then(data => {
                        console.log('Remove response data:', data);
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Errore: ' + (data.message || 'Impossibile rimuovere l\'articolo'));
                        }
                    })
                    .catch(error => {
                        console.error('Remove error:', error);
                        alert('Errore nella rimozione. Controlla la console per i dettagli.');
                    });
            }
        }

        function proceedToCheckout() {
            window.location.href = '<?php echo BASE_URL; ?>/pages/checkout.php';
        }
    </script>

<?php include __DIR__ . '/footer.php'; ?>