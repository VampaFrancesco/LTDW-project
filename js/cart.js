/**
 * js/cart.js - Gestione carrello lato client
 */

class CartManager {
    constructor(baseUrl) {
        this.baseUrl = baseUrl;
        this.cartCountElement = null;
        this.init();
    }

    init() {
        // Trova elemento contatore carrello
        this.cartCountElement = document.querySelector('.cart-count, #cart-count');

        // Aggiungi event listener per form di aggiunta al carrello
        document.addEventListener('DOMContentLoaded', () => {
            this.attachAddToCartListeners();
        });
    }

    /**
     * Aggiunge listener ai form di aggiunta al carrello
     */
    attachAddToCartListeners() {
        const forms = document.querySelectorAll('.add-to-cart-form');
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleAddToCart(form);
            });
        });

        // Gestisci anche bottoni singoli
        const buttons = document.querySelectorAll('.add-to-cart-btn');
        buttons.forEach(btn => {
            if (!btn.closest('form')) {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.handleAddToCartButton(btn);
                });
            }
        });
    }

    /**
     * Gestisce l'aggiunta al carrello da form
     */
    async handleAddToCart(form) {
        const formData = new FormData(form);
        const submitBtn = form.querySelector('button[type="submit"]');

        // Disabilita il bottone
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Aggiunta...';
        }

        try {
            formData.append('action', 'add');
            const response = await fetch(`LTDW-project/action/cart_ajax.php`, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.showNotification(data.message, 'success');
                this.updateCartCount(data.totals.total_items);

                // Animazione aggiunta al carrello
                this.animateAddToCart(submitBtn);
            } else {
                this.showNotification(data.message, 'error');
            }
        } catch (error) {
            console.error('Errore:', error);
            this.showNotification('Errore durante l\'aggiunta al carrello', 'error');
        } finally {
            // Ripristina il bottone
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-cart-plus"></i> Aggiungi al Carrello';
            }
        }
    }

    /**
     * Gestisce l'aggiunta al carrello da bottone singolo
     */
    async handleAddToCartButton(button) {
        const productData = {
            id_prodotto: button.dataset.productId,
            tipo: button.dataset.productType,
            nome_prodotto: button.dataset.productName,
            prezzo: button.dataset.productPrice,
            quantita: button.dataset.quantity || 1
        };

        // Crea FormData
        const formData = new FormData();
        Object.keys(productData).forEach(key => {
            formData.append(key, productData[key]);
        });

        // Disabilita il bottone
        button.disabled = true;
        const originalHtml = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        try {
            formData.append('action', 'add');
            const response = await fetch(`/action/cart_ajax.php`, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.showNotification(data.message, 'success');
                this.updateCartCount(data.totals.total_items);
                this.animateAddToCart(button);
            } else {
                this.showNotification(data.message, 'error');
            }
        } catch (error) {
            console.error('Errore:', error);
            this.showNotification('Errore durante l\'aggiunta al carrello', 'error');
        } finally {
            button.disabled = false;
            button.innerHTML = originalHtml;
        }
    }

    /**
     * Aggiunge l'azione al FormData
     */
    appendAction(formData, action) {
        formData.append('action', action);
        return formData;
    }

    /**
     * Aggiorna il contatore del carrello
     */
    updateCartCount(count) {
        if (this.cartCountElement) {
            this.cartCountElement.textContent = count;

            // Animazione pulse
            this.cartCountElement.classList.add('pulse');
            setTimeout(() => {
                this.cartCountElement.classList.remove('pulse');
            }, 600);
        }

        // Aggiorna anche altri elementi con classe cart-count
        document.querySelectorAll('.cart-items-count').forEach(el => {
            el.textContent = count;
        });
    }

    /**
     * Mostra notifica
     */
    showNotification(message, type = 'info') {
        // Cerca container notifiche o crealo
        let container = document.getElementById('notification-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notification-container';
            container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;';
            document.body.appendChild(container);
        }

        // Crea notifica
        const notification = document.createElement('div');
        notification.className = `alert alert-${this.getBootstrapClass(type)} alert-dismissible fade show`;
        notification.style.cssText = 'min-width:300px;margin-bottom:10px;animation:slideIn 0.3s;';
        notification.innerHTML = `
            <i class="fas fa-${this.getIcon(type)}"></i> ${message}
            <button type="button" class="btn-close" aria-label="Close"></button>
        `;

        container.appendChild(notification);

        // Auto-rimozione
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 150);
        }, 5000);

        // Gestisci chiusura manuale
        const closeBtn = notification.querySelector('.btn-close');
        closeBtn.addEventListener('click', () => {
            notification.remove();
        });
    }

    /**
     * Ottieni classe Bootstrap per tipo messaggio
     */
    getBootstrapClass(type) {
        const map = {
            'success': 'success',
            'error': 'danger',
            'warning': 'warning',
            'info': 'info'
        };
        return map[type] || 'info';
    }

    /**
     * Ottieni icona per tipo messaggio
     */
    getIcon(type) {
        const map = {
            'success': 'check-circle',
            'error': 'exclamation-circle',
            'warning': 'exclamation-triangle',
            'info': 'info-circle'
        };
        return map[type] || 'info-circle';
    }

    /**
     * Animazione aggiunta al carrello
     */
    animateAddToCart(element) {
        if (!element) return;

        // Crea elemento volante
        const flyingElement = document.createElement('div');
        flyingElement.innerHTML = '<i class="fas fa-shopping-cart"></i>';
        flyingElement.style.cssText = `
            position: fixed;
            z-index: 9999;
            color: #28a745;
            font-size: 24px;
            pointer-events: none;
            transition: all 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        `;

        // Posizione iniziale
        const rect = element.getBoundingClientRect();
        flyingElement.style.left = rect.left + rect.width / 2 + 'px';
        flyingElement.style.top = rect.top + rect.height / 2 + 'px';

        document.body.appendChild(flyingElement);

        // Trova icona carrello nella navbar
        const cartIcon = document.querySelector('.navbar .fa-shopping-cart, #cart-icon');
        if (cartIcon) {
            const cartRect = cartIcon.getBoundingClientRect();

            // Anima verso il carrello
            setTimeout(() => {
                flyingElement.style.left = cartRect.left + cartRect.width / 2 + 'px';
                flyingElement.style.top = cartRect.top + cartRect.height / 2 + 'px';
                flyingElement.style.transform = 'scale(0)';
                flyingElement.style.opacity = '0';
            }, 100);
        }

        // Rimuovi dopo animazione
        setTimeout(() => {
            flyingElement.remove();
        }, 900);
    }

    /**
     * Carica stato carrello
     */
    async loadCartStatus() {
        try {
            const formData = new FormData();
            formData.append('action', 'add');

            const response = await fetch(`/LTDW-project//action/cart_ajax.php`, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.updateCartCount(data.totals.total_items);
            }
        } catch (error) {
            console.error('Errore caricamento stato carrello:', error);
        }
    }
}

// Stile CSS per animazioni
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes pulse {
        0% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.2);
        }
        100% {
            transform: scale(1);
        }
    }
    
    .pulse {
        animation: pulse 0.6s ease;
    }
`;
document.head.appendChild(style);

// Inizializza quando DOM è pronto
document.addEventListener('DOMContentLoaded', () => {
    // Ottieni base URL dal meta tag o usa default
    const baseUrlMeta = document.querySelector('meta[name="base-url"]');
    const baseUrl = baseUrlMeta ? baseUrlMeta.content : '';

    // Crea istanza globale
    window.cartManager = new CartManager(baseUrl);

    // Carica stato iniziale
    window.cartManager.loadCartStatus();
});