// wishlist.js - Versione corretta
document.addEventListener('DOMContentLoaded', function() {
    // Variabili globali
    const BASE_URL = window.BASE_URL || '';
    const isLoggedIn = window.isLoggedIn || false;

    // Aggiungi gli stili CSS necessari
    addWishlistStyles();

    // Inizializza la gestione wishlist
    initWishlistButtons();

    // Carica lo stato iniziale dei bottoni
    loadWishlistStates();
});

function addWishlistStyles() {
    if (document.getElementById('wishlist-styles')) return;

    const style = document.createElement('style');
    style.id = 'wishlist-styles';
    style.textContent = `
        /* Bottone wishlist */
        .wishlist-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 10 !important;
            background: rgba(255, 255, 255, 0.95) !important;
            border: 2px solid #dc3545 !important;
            color: #dc3545 !important;
            width: 40px !important;
            height: 40px !important;
            border-radius: 50% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
            padding: 0 !important;
            font-size: 1.2rem !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2) !important;
            pointer-events: auto !important;
        }
        
        .wishlist-btn:hover {
            background: #dc3545 !important;
            color: white !important;
            transform: scale(1.1) !important;
            box-shadow: 0 4px 12px rgba(220,53,69,0.4) !important;
        }
        
        .wishlist-btn.in-wishlist {
            background: #dc3545 !important;
            color: white !important;
        }
        
        .wishlist-btn:disabled {
            opacity: 0.6 !important;
            cursor: not-allowed !important;
            transform: none !important;
        }
        
        /* Container per posizionamento corretto */
        .wishlist-container {
            position: relative !important;
        }
        
        /* Assicurati che i container delle immagini abbiano position relative */
        .mystery-box-image-container,
        .accessory-card .card-img-top,
        .funko-image-container {
            position: relative !important;
        }
        
        /* Evita conflitti con altri elementi */
        .item-link {
            position: relative !important;
            z-index: 1 !important;
        }
        
        /* Notifiche */
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        
        .wishlist-notification {
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 500;
            position: fixed;
            top: 80px;
            right: 20px;
            min-width: 250px;
            z-index: 10000;
            animation: slideInRight 0.3s ease;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        
        .alert-info {
            background-color: #d1ecf1;
            border-color: #bee5eb;
            color: #0c5460;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
    `;
    document.head.appendChild(style);
}

function initWishlistButtons() {
    // Rimuovi event listeners esistenti per evitare duplicati
    const existingButtons = document.querySelectorAll('.wishlist-btn');
    existingButtons.forEach(btn => {
        const newBtn = btn.cloneNode(true);
        btn.parentNode.replaceChild(newBtn, btn);
    });

    // Aggiungi nuovi event listeners
    const buttons = document.querySelectorAll('.wishlist-btn');
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();

            // Verifica login
            if (!isLoggedIn || isLoggedIn === 'false') {
                const currentPage = window.location.href;
                window.location.href = `${BASE_URL}/pages/auth/login.php?redirect=${encodeURIComponent(currentPage)}`;
                return;
            }

            toggleWishlist(this);
        });
    });
}

async function toggleWishlist(button) {
    const itemId = button.dataset.itemId;
    const itemType = button.dataset.itemType;
    const icon = button.querySelector('i');
    const isInWishlist = icon.classList.contains('bi-heart-fill');

    // Disabilita temporaneamente il bottone
    button.disabled = true;

    try {
        let response;

        if (isInWishlist) {
            // Rimuovi dalla wishlist
            let wishlistId = button.dataset.wishlistId;
            if (!wishlistId) {
                wishlistId = await getWishlistId(itemId, itemType);
            }

            const formData = new FormData();
            formData.append('action', 'remove');
            formData.append('wishlist_id', wishlistId);

            response = await fetch(`${BASE_URL}/action/wishlist_action.php`, {
                method: 'POST',
                body: formData
            });
        } else {
            // Aggiungi alla wishlist
            const formData = new FormData();
            formData.append('action', 'add');
            formData.append('item_id', itemId);
            formData.append('item_type', itemType);

            response = await fetch(`${BASE_URL}/action/wishlist_action.php`, {
                method: 'POST',
                body: formData
            });
        }

        const data = await response.json();

        if (data.success) {
            // Aggiorna l'icona
            if (isInWishlist) {
                icon.classList.remove('bi-heart-fill');
                icon.classList.add('bi-heart');
                button.classList.remove('in-wishlist');
                button.title = 'Aggiungi alla wishlist';
                showNotification('Rimosso dalla wishlist', 'info');
            } else {
                icon.classList.remove('bi-heart');
                icon.classList.add('bi-heart-fill');
                button.classList.add('in-wishlist');
                button.title = 'Rimuovi dalla wishlist';
                showNotification('Aggiunto alla wishlist!', 'success');
            }

            // Aggiorna il contatore nella navbar
            updateWishlistCounter(data.wishlist_count);
        } else {
            showNotification(data.message || 'Errore', 'error');
        }
    } catch (error) {
        console.error('Errore:', error);
        showNotification('Si è verificato un errore', 'error');
    } finally {
        // Riabilita il bottone
        button.disabled = false;
    }
}

async function getWishlistId(itemId, itemType) {
    try {
        const response = await fetch(`${BASE_URL}/action/wishlist_action.php?action=get_wishlist_id&item_id=${itemId}&item_type=${itemType}`);
        const data = await response.json();
        return data.wishlist_id;
    } catch (error) {
        console.error('Errore nel recupero ID wishlist:', error);
        return null;
    }
}

async function loadWishlistStates() {
    if (!isLoggedIn || isLoggedIn === 'false') return;

    const buttons = document.querySelectorAll('.wishlist-btn');

    for (const button of buttons) {
        const itemId = button.dataset.itemId;
        const itemType = button.dataset.itemType;

        try {
            const response = await fetch(`${BASE_URL}/action/wishlist_action.php?action=check&item_id=${itemId}&item_type=${itemType}`);
            const data = await response.json();

            if (data.success && data.in_wishlist) {
                const icon = button.querySelector('i');
                icon.classList.remove('bi-heart');
                icon.classList.add('bi-heart-fill');
                button.classList.add('in-wishlist');
                button.title = 'Rimuovi dalla wishlist';
                button.dataset.wishlistId = data.wishlist_id;
            }
        } catch (error) {
            console.error('Errore nel verificare stato wishlist:', error);
        }
    }
}

function updateWishlistCounter(count) {
    const wishlistLink = document.querySelector('a[href*="wishlist.php"]');
    if (wishlistLink) {
        let badge = wishlistLink.querySelector('.badge');

        if (count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'badge bg-danger position-absolute top-0 start-100 translate-middle';
                wishlistLink.appendChild(badge);
            }
            badge.textContent = count;
        } else if (badge) {
            badge.remove();
        }
    }
}

function showNotification(message, type) {
    // Rimuovi notifiche esistenti
    const existingNotifications = document.querySelectorAll('.wishlist-notification');
    existingNotifications.forEach(n => n.remove());

    const notification = document.createElement('div');
    notification.className = `wishlist-notification alert alert-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'}`;
    notification.innerHTML = `
        <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'}"></i>
        ${message}
    `;

    document.body.appendChild(notification);

    // Rimuovi dopo 3 secondi
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Gestione rimozione dalla pagina wishlist
document.addEventListener('DOMContentLoaded', function() {
    // Gestione rimozione dalla wishlist nella pagina dedicata
    document.querySelectorAll('.btn-remove-wishlist').forEach(button => {
        button.addEventListener('click', function() {
            const wishlistId = this.dataset.wishlistId;
            const card = this.closest('.wishlist-item-card');

            if (confirm('Vuoi rimuovere questo prodotto dalla wishlist?')) {
                fetch(`${BASE_URL}/action/wishlist_action.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=remove&wishlist_id=${wishlistId}`
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            card.style.transition = 'opacity 0.5s, transform 0.5s';
                            card.style.opacity = '0';
                            card.style.transform = 'scale(0.8)';

                            setTimeout(() => {
                                card.remove();

                                // Controlla se la wishlist è vuota
                                if (document.querySelectorAll('.wishlist-item-card').length === 0) {
                                    location.reload();
                                }
                            }, 500);
                        } else {
                            alert(data.message || 'Errore nella rimozione');
                        }
                    })
                    .catch(error => {
                        console.error('Errore:', error);
                        alert('Si è verificato un errore');
                    });
            }
        });
    });

    // Gestione aggiunta al carrello dalla wishlist
    document.querySelectorAll('.btn-add-to-cart').forEach(button => {
        button.addEventListener('click', function() {
            const itemId = this.dataset.itemId;
            const itemType = this.dataset.itemType;

            const formData = new FormData();
            formData.append('id_prodotto', itemId);
            formData.append('item_type', itemType);
            formData.append('tipo', itemType === 'box' ? 'mystery_box' : 'oggetto');
            formData.append('quantita', '1');

// Ottieni nome e prezzo dal DOM della card
            const card = this.closest('.wishlist-item-card');
            const nome = card.querySelector('h3').textContent.trim();
            const prezzo = card.querySelector('.price').textContent.replace('€', '').replace(',', '.').trim();

            formData.append('nome_prodotto', nome);
            formData.append('prezzo', prezzo);

            fetch(`${BASE_URL}/action/add_to_cart.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `action=add&item_id=${itemId}&item_type=${itemType}&quantity=1`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Prodotto aggiunto al carrello!', 'success');
                        updateCartCounter(data.cart_count);
                    } else {
                        alert(data.message || 'Errore nell\'aggiunta al carrello');
                    }
                })
                .catch(error => {
                    console.error('Errore:', error);
                    alert('Si è verificato un errore');
                });
        });
    });
});

function updateCartCounter(count) {
    const cartBadge = document.querySelector('.bi-cart-fill').nextElementSibling;
    if (cartBadge && cartBadge.classList.contains('badge')) {
        cartBadge.textContent = count;
    } else if (count > 0) {
        const newBadge = document.createElement('span');
        newBadge.className = 'badge bg-danger';
        newBadge.textContent = count;
        document.querySelector('.bi-cart-fill').parentElement.appendChild(newBadge);
    }
}