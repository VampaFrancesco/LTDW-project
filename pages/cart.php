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

            <!-- Container per alert personalizzati -->
            <div id="alertContainer"></div>

            <?php if (empty($cart_data['items'])): ?>
                <!-- Carrello vuoto -->
                <div class="empty-cart-container fade-in-up">
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
                <div class="cart-container fade-in-up">
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
        // Funzione per creare alert personalizzati
        function showCustomAlert(message, type = 'danger', duration = 5000) {
            const alertContainer = document.getElementById('alertContainer');

            const alertElement = document.createElement('div');
            alertElement.className = `alert-custom alert-custom-${type} alert-dismissible fade show`;
            alertElement.innerHTML = `
                <i class="bi bi-${type === 'danger' ? 'exclamation-triangle' : type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
                <div class="alert-custom-content">
                    <strong>${type === 'danger' ? 'Errore!' : type === 'success' ? 'Successo!' : type === 'warning' ? 'Attenzione!' : 'Info:'}</strong>
                    ${message}
                </div>
                <button type="button" class="alert-custom-close" onclick="this.parentElement.remove()">
                    <i class="bi bi-x"></i>
                </button>
            `;

            alertContainer.appendChild(alertElement);

            // Auto-rimuovi dopo il duration specificato
            if (duration > 0) {
                setTimeout(() => {
                    if (alertElement && alertElement.parentElement) {
                        alertElement.classList.add('fade-out');
                        setTimeout(() => alertElement.remove(), 300);
                    }
                }, duration);
            }
        }

        function updateQuantity(itemKey, change, newValue = null) {
            let quantity;

            if (newValue !== null) {
                quantity = parseInt(newValue);
            } else {
                const input = document.querySelector(`[data-item-id="${itemKey}"] .quantity-input`);
                quantity = parseInt(input.value) + change;
            }

            if (quantity < 1) {
                showCustomAlert('La quantità minima è 1.', 'warning', 3000);
                quantity = 1;
            }
            if (quantity > 99) {
                showCustomAlert('La quantità massima è 99.', 'warning', 3000);
                quantity = 99;
            }

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

            // Mostra feedback visivo
            const cartItem = document.querySelector(`[data-item-id="${itemKey}"]`);
            if (cartItem) {
                cartItem.style.opacity = '0.6';
                cartItem.style.transform = 'scale(0.98)';
            }

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
                        showCustomAlert('Quantità aggiornata con successo!', 'success', 2000);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showCustomAlert(data.message || 'Impossibile aggiornare la quantità', 'danger');
                        // Ripristina valore precedente
                        if (input) {
                            input.value = input.defaultValue;
                        }
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    showCustomAlert('Errore di connessione. Riprova più tardi.', 'danger');
                    // Ripristina valore precedente
                    if (input) {
                        input.value = input.defaultValue;
                    }
                })
                .finally(() => {
                    // Ripristina stile visivo
                    if (cartItem) {
                        cartItem.style.opacity = '1';
                        cartItem.style.transform = 'scale(1)';
                    }
                });
        }

        function removeItem(itemKey) {
            const cartItem = document.querySelector(`[data-item-id="${itemKey}"]`);
            const itemName = cartItem?.querySelector('.cart-item-name')?.textContent || 'questo articolo';

            // Mostra conferma personalizzata
            showRemoveConfirmation(itemName, () => {
                console.log('Removing item:', itemKey);

                // Animazione di rimozione
                if (cartItem) {
                    cartItem.style.transform = 'translateX(-100%)';
                    cartItem.style.opacity = '0.3';
                }

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
                            showCustomAlert('Articolo rimosso dal carrello!', 'success', 2000);
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showCustomAlert(data.message || 'Impossibile rimuovere l\'articolo', 'danger');
                            // Ripristina stile
                            if (cartItem) {
                                cartItem.style.transform = '';
                                cartItem.style.opacity = '';
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Remove error:', error);
                        showCustomAlert('Errore nella rimozione. Riprova più tardi.', 'danger');
                        // Ripristina stile
                        if (cartItem) {
                            cartItem.style.transform = '';
                            cartItem.style.opacity = '';
                        }
                    });
            });
        }

        function showRemoveConfirmation(itemName, onConfirm) {
            const alertElement = document.createElement('div');
            alertElement.className = 'alert-custom alert-custom-warning alert-dismissible fade show';
            alertElement.style.position = 'fixed';
            alertElement.style.top = '50%';
            alertElement.style.left = '50%';
            alertElement.style.transform = 'translate(-50%, -50%)';
            alertElement.style.zIndex = '9999';
            alertElement.style.maxWidth = '400px';
            alertElement.style.boxShadow = '0 10px 40px rgba(0,0,0,0.3)';

            alertElement.innerHTML = `
                <i class="bi bi-question-circle"></i>
                <div class="alert-custom-content">
                    <strong>Conferma rimozione</strong>
                    Sei sicuro di voler rimuovere "${itemName}" dal carrello?
                </div>
                <div style="margin-top: 15px; display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="this.closest('.alert-custom').remove()">
                        Annulla
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.alert-custom').remove(); (${onConfirm.toString()})()">
                        Rimuovi
                    </button>
                </div>
            `;

            document.body.appendChild(alertElement);
        }

        function proceedToCheckout() {
            const button = event.target;
            const originalText = button.innerHTML;

            // Mostra feedback
            button.innerHTML = '<i class="spinner-border spinner-border-sm"></i> Preparazione...';
            button.disabled = true;

            showCustomAlert('Preparazione checkout in corso...', 'info', 2000);

            // Reindirizza a checkout_action.php che preparerà i dati per pagamento.php
            setTimeout(() => {
                window.location.href = '<?php echo BASE_URL; ?>/action/checkout_action.php';
            }, 1000);
        }

        // Animazioni al caricamento pagina
        document.addEventListener('DOMContentLoaded', function() {
            // Aggiungi animazioni agli elementi del carrello
            const cartItems = document.querySelectorAll('.cart-item');
            cartItems.forEach((item, index) => {
                item.style.animationDelay = `${index * 0.1}s`;
                item.classList.add('fade-in-up');
            });

            // Auto-salva input quantità quando si cambia focus
            const quantityInputs = document.querySelectorAll('.quantity-input');
            quantityInputs.forEach(input => {
                let originalValue = input.value;

                input.addEventListener('focus', function() {
                    originalValue = this.value;
                });

                input.addEventListener('blur', function() {
                    if (this.value !== originalValue && this.value >= 1 && this.value <= 99) {
                        const itemKey = this.closest('.cart-item').dataset.itemId;
                        updateQuantity(itemKey, 0, this.value);
                    }
                });

                input.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        this.blur();
                    }
                });
            });
        });
    </script>

<?php include __DIR__ . '/footer.php'; ?>