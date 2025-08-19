<?php
/**
 * cart.php - Pagina del carrello con gestione AJAX corretta
 * Compatibile con update_cart.php e get_cart.php
 */

// 1. PRIMA di qualsiasi output
require_once __DIR__.'/../include/session_manager.php';
require_once __DIR__.'/../include/config.inc.php';

// 2. Include la logica per recuperare il carrello
$cart_data = include __DIR__.'/../action/get_cart.php';

// 3. Include header DOPO aver processato la logica
include __DIR__.'/header.php';
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
                                    <div class="cart-item" data-item-id="<?php echo htmlspecialchars($item['cart_key']); ?>">
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
                                                €<?php echo number_format($item['prezzo'], 2, ',', '.'); ?>
                                            </div>
                                        </div>
                                        <div class="cart-item-quantity">
                                            <label>Quantità:</label>
                                            <div class="quantity-controls">
                                                <button type="button" class="btn-quantity"
                                                        onclick="updateQuantity('<?php echo htmlspecialchars($item['cart_key']); ?>', -1)">
                                                    -
                                                </button>
                                                <input type="number"
                                                       value="<?php echo $item['quantita']; ?>"
                                                       min="1"
                                                       max="99"
                                                       class="quantity-input"
                                                       data-original-value="<?php echo $item['quantita']; ?>"
                                                       onchange="updateQuantity('<?php echo htmlspecialchars($item['cart_key']); ?>', 0, this.value)">
                                                <button type="button" class="btn-quantity"
                                                        onclick="updateQuantity('<?php echo htmlspecialchars($item['cart_key']); ?>', 1)">
                                                    +
                                                </button>
                                            </div>
                                        </div>
                                        <div class="cart-item-total">
                                            €<?php echo number_format($item['prezzo'] * $item['quantita'], 2, ',', '.'); ?>
                                        </div>
                                        <div class="cart-item-remove">
                                            <button type="button" class="btn-remove"
                                                    onclick="removeItem('<?php echo htmlspecialchars($item['cart_key']); ?>')">
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
                                    <span id="cart-subtotal">€<?php echo number_format($cart_data['subtotal'], 2, ',', '.'); ?></span>
                                </div>
                                <div class="summary-item">
                                    <span>Spedizione:</span>
                                    <span id="shipping-cost">
                                    <?php if($cart_data['subtotal'] >= 50): ?>
                                        <span class="text-success">GRATIS</span>
                                    <?php else: ?>
                                        €<?php echo number_format($cart_data['shipping_cost'], 2, ',', '.'); ?>
                                    <?php endif; ?>
                                </span>
                                </div>
                                <?php if($cart_data['subtotal'] < 50): ?>
                                    <div class="free-shipping-notice">
                                        <i class="bi bi-info-circle"></i>
                                        Spendi ancora €<?php echo number_format(50 - $cart_data['subtotal'], 2, ',', '.'); ?>
                                        per la spedizione gratuita!
                                    </div>
                                <?php endif; ?>
                                <hr>
                                <div class="summary-total">
                                    <strong>Totale:</strong>
                                    <strong id="cart-total">€<?php echo number_format($cart_data['total'], 2, ',', '.'); ?></strong>
                                </div>
                                <button type="button" class="btn btn-primary btn-lg w-100 mt-3"
                                        onclick="proceedToCheckout()">
                                    <i class="bi bi-lock-fill"></i> Procedi al Checkout
                                </button>
                                <a href="<?php echo BASE_URL; ?>/pages/pokémon.php" class="btn btn-outline-secondary w-100 mt-2">
                                    Continua lo Shopping
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Stili CSS -->
    <style>
        .cart-container {
            margin-top: 20px;
        }

        .cart-item {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .cart-item:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }

        .cart-item-image {
            width: 100px;
            height: 100px;
            flex-shrink: 0;
        }

        .cart-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }

        .cart-item-details {
            flex: 1;
        }

        .cart-item-name {
            margin: 0 0 10px 0;
            font-size: 1.1rem;
        }

        .cart-item-price {
            font-size: 1.2rem;
            color: #28a745;
            font-weight: 600;
        }

        .cart-item-quantity {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-quantity {
            width: 35px;
            height: 35px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-quantity:hover {
            background: #f8f9fa;
            border-color: #007bff;
        }

        .quantity-input {
            width: 60px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 5px;
        }

        .cart-item-total {
            font-size: 1.3rem;
            font-weight: bold;
            min-width: 100px;
            text-align: right;
        }

        .btn-remove {
            background: #dc3545;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-remove:hover {
            background: #c82333;
        }

        .cart-summary {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 20px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .summary-total {
            display: flex;
            justify-content: space-between;
            font-size: 1.3rem;
            margin-top: 15px;
        }

        .free-shipping-notice {
            background: #fff3cd;
            color: #856404;
            padding: 10px;
            border-radius: 5px;
            margin: 15px 0;
            font-size: 0.9rem;
        }

        .empty-cart-container {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .empty-cart-icon {
            font-size: 5rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }

        /* Alert personalizzati */
        .alert-custom {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            position: relative;
            animation: slideIn 0.3s ease;
        }

        .alert-custom-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-custom-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-custom-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .fade-in-up {
            animation: fadeInUp 0.5s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .cart-item {
                flex-direction: column;
                text-align: center;
            }

            .cart-item-total {
                text-align: center;
                margin: 10px 0;
            }

            .cart-summary {
                margin-top: 30px;
                position: static;
            }
        }
    </style>

    <!-- JavaScript -->
    <script>
        // Funzione per mostrare alert personalizzati
        function showCustomAlert(message, type = 'success', duration = 3000) {
            const alertContainer = document.getElementById('alertContainer');
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert-custom alert-custom-${type}`;
            alertDiv.innerHTML = `
            <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : 'info-circle'}"></i>
            ${message}
        `;

            alertContainer.appendChild(alertDiv);

            setTimeout(() => {
                alertDiv.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => alertDiv.remove(), 300);
            }, duration);
        }

        // Funzione per aggiornare la quantità
        function updateQuantity(itemKey, change, directValue = null) {
            console.log('updateQuantity chiamata:', { itemKey, change, directValue });

            const cartItem = document.querySelector(`[data-item-id="${itemKey}"]`);
            const input = cartItem?.querySelector('.quantity-input');

            if (!input) {
                console.error('Input non trovato per:', itemKey);
                return;
            }

            let quantity;
            if (directValue !== null) {
                quantity = parseInt(directValue);
            } else {
                quantity = parseInt(input.value) + change;
            }

            // Validazione quantità
            if (quantity < 1) {
                showCustomAlert('La quantità minima è 1', 'warning');
                quantity = 1;
            }
            if (quantity > 99) {
                showCustomAlert('La quantità massima è 99', 'warning');
                quantity = 99;
            }

            // Aggiorna visualmente l'input
            input.value = quantity;

            // Feedback visivo
            if (cartItem) {
                cartItem.style.opacity = '0.6';
            }

            // Chiamata AJAX
            const formData = new FormData();
            formData.append('action', 'update');
            formData.append('item_key', itemKey);
            formData.append('quantity', quantity);

            fetch('<?php echo BASE_URL; ?>/action/update_cart.php', {
                method: 'POST',
                body: formData
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(text => {
                    console.log('Response raw:', text);

                    // Prova a parsare come JSON
                    try {
                        const data = JSON.parse(text);
                        console.log('Parsed data:', data);

                        if (data.success) {
                            // Aggiorna UI
                            updateCartUI(data);
                            showCustomAlert('Quantità aggiornata!', 'success');

                            // Salva il nuovo valore come originale
                            input.setAttribute('data-original-value', quantity);
                        } else {
                            throw new Error(data.message || 'Errore sconosciuto');
                        }
                    } catch (e) {
                        console.error('Parse error:', e);
                        // Se il response contiene HTML di errore PHP
                        if (text.includes('Fatal error') || text.includes('Warning')) {
                            console.error('PHP Error:', text);
                            throw new Error('Errore del server');
                        } else {
                            throw e;
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showCustomAlert('Errore: ' + error.message, 'danger');

                    // Ripristina valore originale
                    const originalValue = input.getAttribute('data-original-value');
                    if (originalValue) {
                        input.value = originalValue;
                    }
                })
                .finally(() => {
                    // Ripristina opacità
                    if (cartItem) {
                        cartItem.style.opacity = '1';
                    }
                });
        }

        // Funzione per rimuovere un item
        function removeItem(itemKey) {
            const cartItem = document.querySelector(`[data-item-id="${itemKey}"]`);
            const itemName = cartItem?.querySelector('.cart-item-name')?.textContent || 'questo articolo';

            if (!confirm(`Sei sicuro di voler rimuovere "${itemName}" dal carrello?`)) {
                return;
            }

            // Animazione rimozione
            if (cartItem) {
                cartItem.style.opacity = '0.3';
                cartItem.style.transform = 'translateX(-20px)';
            }

            const formData = new FormData();
            formData.append('action', 'remove');
            formData.append('item_key', itemKey);

            fetch('<?php echo BASE_URL; ?>/action/update_cart.php', {
                method: 'POST',
                body: formData
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(text => {
                    console.log('Remove response:', text);

                    try {
                        const data = JSON.parse(text);

                        if (data.success) {
                            showCustomAlert('Prodotto rimosso dal carrello', 'success');

                            // Rimuovi elemento con animazione
                            if (cartItem) {
                                cartItem.style.transition = 'all 0.3s ease';
                                cartItem.style.transform = 'translateX(-100%)';
                                cartItem.style.opacity = '0';
                                setTimeout(() => {
                                    cartItem.remove();

                                    // Se il carrello è vuoto, ricarica la pagina
                                    if (document.querySelectorAll('.cart-item').length === 0) {
                                        location.reload();
                                    } else {
                                        // Aggiorna UI
                                        updateCartUI(data);
                                    }
                                }, 300);
                            }
                        } else {
                            throw new Error(data.message || 'Errore nella rimozione');
                        }
                    } catch (e) {
                        console.error('Parse error:', e);
                        throw new Error('Errore del server');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showCustomAlert('Errore: ' + error.message, 'danger');

                    // Ripristina visualizzazione
                    if (cartItem) {
                        cartItem.style.opacity = '1';
                        cartItem.style.transform = '';
                    }
                });
        }

        // Funzione per aggiornare l'UI del carrello
        function updateCartUI(data) {
            // Aggiorna subtotale
            const subtotalEl = document.getElementById('cart-subtotal');
            if (subtotalEl) {
                subtotalEl.textContent = '€' + data.cart_subtotal;
            }

            // Aggiorna spedizione
            const shippingEl = document.getElementById('shipping-cost');
            if (shippingEl) {
                if (data.is_shipping_free) {
                    shippingEl.innerHTML = '<span class="text-success">GRATIS</span>';
                } else {
                    shippingEl.textContent = '€' + data.shipping_cost;
                }
            }

            // Aggiorna totale
            const totalEl = document.getElementById('cart-total');
            if (totalEl) {
                totalEl.textContent = '€' + data.cart_total;
            }

            // Aggiorna contatore nel header se esiste
            const cartCountEl = document.querySelector('.cart-count');
            if (cartCountEl) {
                cartCountEl.textContent = data.cart_total_items;
            }
        }

        // Funzione per procedere al checkout
        function proceedToCheckout() {
            window.location.href = '<?php echo BASE_URL; ?>/action/checkout_action.php';
        }

        // Inizializzazione al caricamento della pagina
        document.addEventListener('DOMContentLoaded', function() {
            // Aggiungi animazioni
            const cartItems = document.querySelectorAll('.cart-item');
            cartItems.forEach((item, index) => {
                item.style.animationDelay = `${index * 0.1}s`;
                item.classList.add('fade-in-up');
            });

            // Gestione input quantità con Enter
            const quantityInputs = document.querySelectorAll('.quantity-input');
            quantityInputs.forEach(input => {
                input.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        const itemKey = this.closest('.cart-item').dataset.itemId;
                        updateQuantity(itemKey, 0, this.value);
                    }
                });
            });
        });
    </script>

<?php include __DIR__.'/footer.php'; ?>