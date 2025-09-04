<?php
/**
 * pages/cart.php - Pagina carrello con sistema robusto
 */

require_once __DIR__.'/../include/session_manager.php';
require_once __DIR__.'/../include/config.inc.php';
require_once __DIR__.'/../include/Cart.php';

// Inizializza variabili con valori di default sicuri
$cart_items = [];
$total_items = 0;
$subtotal = 0;
$shipping_cost = 5.00;
$total = 5.00;

// Inizializza carrello con configurazione database
try {
    $cart = new Cart($config['dbms']['localhost']);
    // Ottieni dati carrello
    $cart_data = $cart->getTotals();

    // Valida e assegna solo se la struttura è corretta
        $cart_items = $cart_data['items'];
        $array_prodotti = $cart_items;
        $total_items = intval($cart_data['total_items'] ?? 0);
        $subtotal = floatval($cart_data['subtotal'] ?? 0);
        $shipping_cost = floatval($cart_data['shipping'] ?? 5.00);
        $total = floatval($cart_data['total'] ?? 5.00);
} catch (Exception $e) {
    error_log("Errore inizializzazione carrello: " . $e->getMessage());
    // I valori di default sono già impostati sopra
}

include __DIR__.'/header.php';
?>

    <div class="container my-5">
        <h1 class="mb-4">Il Tuo Carrello</h1>

        <!-- Alert container per messaggi dinamici -->
        <div id="alert-container"></div>

        <?php if (empty($array_prodotti)): ?>
            <!-- Carrello vuoto -->
            <div class="alert alert-info text-center py-5">
                <i class="bi bi-shopping-cart fa-3x mb-3 text-muted"></i>
                <h3>Il tuo carrello è vuoto</h3>
                <p class="mb-4">Aggiungi alcuni prodotti per iniziare il tuo shopping!</p>
                <a href="<?php echo BASE_URL; ?>/pages/home_utente.php" class="btn btn-primary btn-lg">
                    <i class="bi bi-shopping-bag"></i> Vai allo Shop
                </a>
            </div>

        <?php else: ?>
            <!-- Carrello con prodotti -->
            <div class="row">
                <!-- Lista prodotti -->
                <div class="col-lg-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">
                                <i class="bi bi-shopping-cart"></i>
                                Prodotti nel carrello (<?php echo count($array_prodotti); ?>)
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <?php foreach ($array_prodotti as $item): ?>
                                <div class="cart-item border-bottom p-3"
                                     id="item-<?php echo htmlspecialchars($item['cart_key']); ?>"
                                     data-price="<?php echo $item['prezzo']; ?>">
                                    <div class="row align-items-center">
                                        <!-- Immagine prodotto -->
                                        <div class="col-md-2 col-3">
                                            <img src="<?php echo htmlspecialchars($item['image']); ?>"
                                                 class="img-fluid rounded border"
                                                 alt="<?php echo htmlspecialchars($item['nome']); ?>"
                                                 style="max-height: 80px; object-fit: cover;"
                                                 onerror="this.src='<?php echo BASE_URL; ?>/images/default_product.png'">
                                        </div>

                                        <!-- Dettagli prodotto -->
                                        <div class="col-md-4 col-9">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($item['nome']); ?></h6>
                                            <span class="badge bg-<?php echo $item['tipo'] === 'mystery_box' ? 'primary' : 'secondary'; ?> mb-1">
                                            <?php echo $item['tipo'] === 'mystery_box' ? 'Mystery Box' : 'Oggetto'; ?>
                                        </span>
                                            <br>
                                            <small class="text-muted">
                                                Prezzo unitario: €<?php echo number_format($item['prezzo'], 2, ',', '.'); ?>
                                            </small>
                                            <?php if ($item['stock_disponibile'] !== null): ?>
                                                <br>
                                                <small class="text-warning">
                                                    Stock: <?php echo $item['stock_disponibile']; ?> disponibili
                                                </small>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Controlli quantità -->
                                        <div class="col-md-3 col-6">
                                            <div class="input-group input-group-sm">
                                                <button class="btn btn-outline-secondary" type="button"
                                                        onclick="updateQuantity('<?php echo htmlspecialchars($item['cart_key']); ?>', -1)">
                                                    <i class="bi bi-minus"></i>
                                                </button>
                                                <input type="number"
                                                       id="qty-<?php echo htmlspecialchars($item['cart_key']); ?>"
                                                       class="form-control text-center quantity-input"
                                                       value="<?php echo intval($item['quantita']); ?>"
                                                       min="1" max="99"
                                                       data-original="<?php echo intval($item['quantita']); ?>"
                                                       data-key="<?php echo htmlspecialchars($item['cart_key']); ?>"
                                                       onchange="validateAndUpdateQuantity('<?php echo htmlspecialchars($item['cart_key']); ?>')">
                                                <button class="btn btn-outline-secondary" type="button"
                                                        onclick="updateQuantity('<?php echo htmlspecialchars($item['cart_key']); ?>', 1)">
                                                    <i class="bi bi-plus"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Totale e rimozione -->
                                        <div class="col-md-2 col-6 text-end">
                                            <div class="mb-2">
                                                <strong class="item-total">
                                                    €<?php echo number_format($item['totale'], 2, ',', '.'); ?>
                                                </strong>
                                            </div>
                                            <button class="btn btn-sm btn-outline-danger"
                                                    onclick="removeFromCart('<?php echo htmlspecialchars($item['cart_key']); ?>')"
                                                    title="Rimuovi prodotto">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Riepilogo ordine -->
                <div class="col-lg-4">
                    <div class="card shadow-sm sticky-top" style="top: 20px;">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">
                                <i class="bi bi-calculator"></i> Riepilogo Ordine
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotale:</span>
                                <span id="cart-subtotal">€<?php echo number_format($subtotal, 2, ',', '.'); ?></span>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span>Spedizione:</span>
                                <span id="cart-shipping" class="<?php echo $shipping_cost == 0 ? 'text-success' : ''; ?>">
                                <?php if ($shipping_cost == 0): ?>
                                    <strong>GRATIS</strong>
                                <?php else: ?>
                                    €<?php echo number_format($shipping_cost, 2, ',', '.'); ?>
                                <?php endif; ?>
                            </span>
                            </div>

                            <?php if ($subtotal < 50 && $subtotal > 0): ?>
                                <div class="alert alert-info py-2 small" id="free-shipping-info">
                                    <i class="bi bi-info-circle"></i>
                                    Spendi altri €<span id="amount-for-free-shipping"><?php echo number_format(50 - $subtotal, 2, ',', '.'); ?></span>
                                    per la spedizione gratuita!
                                </div>
                            <?php endif; ?>

                            <hr>

                            <div class="d-flex justify-content-between mb-3">
                                <strong>Totale:</strong>
                                <strong id="cart-total">€<?php echo number_format($total, 2, ',', '.'); ?></strong>
                            </div>

                            <div class="d-grid gap-2">
                                <form method="POST" action="<?php echo BASE_URL; ?>/action/checkout_action.php" style="display: inline;">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="bi bi-credit-card"></i>
                                        Procedi al Checkout
                                    </button>
                                </form>

                                <a href="<?php echo BASE_URL; ?>/pages/home_utente.php"
                                   class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left"></i> Continua Shopping
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- JavaScript per gestire il carrello -->
    <script>
        // Funzione per mostrare alert personalizzati
        function showAlert(message, type = 'info') {
            const alertContainer = document.getElementById('alert-container');
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
            alertContainer.appendChild(alertDiv);

            // Auto-remove dopo 5 secondi
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        // Aggiorna quantità con bottoni + e -
        function updateQuantity(itemKey, delta) {
            const qtyInput = document.getElementById('qty-' + itemKey);
            if (!qtyInput) return;

            let currentQty = parseInt(qtyInput.value) || 1;
            let newQty = currentQty + delta;

            if (newQty < 1) newQty = 1;
            if (newQty > 99) newQty = 99;

            if (newQty === currentQty) return;

            qtyInput.value = newQty;
            performQuantityUpdate(itemKey, newQty);
        }

        // Valida e aggiorna quantità quando utente modifica input direttamente
        function validateAndUpdateQuantity(itemKey) {
            const qtyInput = document.getElementById('qty-' + itemKey);
            if (!qtyInput) return;

            let newQty = parseInt(qtyInput.value) || 1;

            if (newQty < 1) newQty = 1;
            if (newQty > 99) newQty = 99;

            qtyInput.value = newQty;

            // Controlla se è cambiata rispetto al valore originale
            const originalQty = parseInt(qtyInput.getAttribute('data-original'));
            if (newQty !== originalQty) {
                performQuantityUpdate(itemKey, newQty);
            }
        }

        // Esegui aggiornamento quantità via AJAX
        function performQuantityUpdate(itemKey, newQty) {
            const qtyInput = document.getElementById('qty-' + itemKey);
            const cartItem = document.getElementById('item-' + itemKey);

            // Disabilita input durante aggiornamento
            qtyInput.disabled = true;
            if (cartItem) cartItem.style.opacity = '0.6';

            fetch('<?php echo BASE_URL; ?>/action/cart_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update&item_key=${encodeURIComponent(itemKey)}&quantity=${newQty}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('Quantità aggiornata!', 'success');

                        // Aggiorna valore originale
                        qtyInput.setAttribute('data-original', newQty);

                        // Aggiorna totali
                        updateCartTotals(data.totals);

                        // Aggiorna totale riga
                        const itemPrice = parseFloat(cartItem.getAttribute('data-price'));
                        const itemTotal = cartItem.querySelector('.item-total');
                        if (itemTotal) {
                            itemTotal.textContent = '€' + (itemPrice * newQty).toFixed(2).replace('.', ',');
                        }
                    } else {
                        throw new Error(data.message || 'Errore nell\'aggiornamento');
                    }
                })
                .catch(error => {
                    console.error('Errore:', error);
                    showAlert('Errore: ' + error.message, 'danger');
                    // Ripristina valore originale
                    qtyInput.value = qtyInput.getAttribute('data-original');
                })
                .finally(() => {
                    qtyInput.disabled = false;
                    if (cartItem) cartItem.style.opacity = '1';
                });
        }

        // Rimuovi prodotto dal carrello
        function removeFromCart(itemKey) {
            if (!confirm('Sei sicuro di voler rimuovere questo prodotto dal carrello?')) {
                return;
            }

            const cartItem = document.getElementById('item-' + itemKey);
            if (cartItem) cartItem.style.opacity = '0.5';

            fetch('<?php echo BASE_URL; ?>/action/cart_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=remove&item_key=${encodeURIComponent(itemKey)}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('Prodotto rimosso dal carrello', 'success');

                        // Rimuovi elemento dal DOM
                        if (cartItem) {
                            cartItem.remove();
                        }

                        // Aggiorna totali
                        updateCartTotals(data.totals);

                        // Se carrello vuoto, ricarica pagina
                        if (data.totals.total_items === 0) {
                            setTimeout(() => location.reload(), 1000);
                        }
                    } else {
                        throw new Error(data.message || 'Errore nella rimozione');
                    }
                })
                .catch(error => {
                    console.error('Errore:', error);
                    showAlert('Errore: ' + error.message, 'danger');
                    if (cartItem) cartItem.style.opacity = '1';
                });
        }

        // Aggiorna totali carrello nel DOM
        function updateCartTotals(totals) {
            // Aggiorna subtotale
            const subtotalEl = document.getElementById('cart-subtotal');
            if (subtotalEl) {
                subtotalEl.textContent = '€' + totals.subtotal.toFixed(2).replace('.', ',');
            }

            // Aggiorna spedizione
            const shippingEl = document.getElementById('cart-shipping');
            if (shippingEl) {
                if (totals.shipping === 0) {
                    shippingEl.innerHTML = '<strong>GRATIS</strong>';
                    shippingEl.className = 'text-success';
                } else {
                    shippingEl.textContent = '€' + totals.shipping.toFixed(2).replace('.', ',');
                    shippingEl.className = '';
                }
            }

            // Aggiorna totale
            const totalEl = document.getElementById('cart-total');
            if (totalEl) {
                totalEl.textContent = '€' + totals.total.toFixed(2).replace('.', ',');
            }

            // Aggiorna info spedizione gratuita
            const freeShippingInfo = document.getElementById('free-shipping-info');
            const amountForFreeShipping = document.getElementById('amount-for-free-shipping');

            if (totals.subtotal < 50 && totals.subtotal > 0) {
                const remaining = 50 - totals.subtotal;
                if (amountForFreeShipping) {
                    amountForFreeShipping.textContent = remaining.toFixed(2).replace('.', ',');
                }
                if (freeShippingInfo) {
                    freeShippingInfo.style.display = 'block';
                }
            } else if (freeShippingInfo) {
                freeShippingInfo.style.display = 'none';
            }
        }
    </script>

<?php include __DIR__.'/footer.php'; ?>