// wishlist.js - Versione corretta e riorganizzata
document.addEventListener('DOMContentLoaded', function() {
    // --- VARIABILI GLOBALI ---
    const BASE_URL = window.BASE_URL || '';

    // --- INIZIALIZZAZIONE ---
    addWishlistStyles();
    initGlobalWishlistButtons(); // Per i bottoni a forma di cuore
    initWishlistPageActions();  // Per i bottoni nella pagina /wishlist.php
    loadWishlistStates();
});

/**
 * Aggiunge stili CSS per la wishlist e le notifiche.
 */
function addWishlistStyles() {
    if (document.getElementById('wishlist-styles')) return;

    const style = document.createElement('style');
    style.id = 'wishlist-styles';
    style.textContent = `
        /* Stile per i bottoni a cuore */
        .wishlist-btn {
            position: absolute; top: 10px; right: 10px; z-index: 10 !important;
            background: rgba(255, 255, 255, 0.95) !important; border: 2px solid #dc3545 !important;
            color: #dc3545 !important; width: 40px !important; height: 40px !important;
            border-radius: 50% !important; display: flex !important; align-items: center !important;
            justify-content: center !important; cursor: pointer !important; transition: all 0.3s ease !important;
            padding: 0 !important; font-size: 1.2rem !important; box-shadow: 0 2px 8px rgba(0,0,0,0.2) !important;
        }
        .wishlist-btn:hover {
            background: #dc3545 !important; color: white !important;
            transform: scale(1.1) !important;
        }
        .wishlist-btn.in-wishlist { background: #dc3545 !important; color: white !important; }
        .wishlist-container, .mystery-box-image-container, .accessory-card .card-img-top, .funko-image-container { position: relative !important; }

        /* Animazioni per le notifiche */
        @keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes slideOutRight { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100%); opacity: 0; } }

        /* Stile per le notifiche */
        .wishlist-notification {
            position: fixed; top: 80px; right: 20px; min-width: 250px; z-index: 10000;
            padding: 12px 20px; border-radius: 8px; font-weight: 500;
            animation: slideInRight 0.3s ease; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            color: #fff;
        }
        .alert-success { background: linear-gradient(45deg, #28a745, #218838); }
        .alert-info { background: linear-gradient(45deg, #17a2b8, #138496); }
        .alert-danger { background: linear-gradient(45deg, #dc3545, #c82333); }
    `;
    document.head.appendChild(style);
}

/**
 * Inizializza i listener per i bottoni a cuore globali.
 */
function initGlobalWishlistButtons() {
    document.body.addEventListener('click', function(e) {
        const button = e.target.closest('.wishlist-btn');
        if (!button) return;
        e.preventDefault();
        e.stopPropagation();

        const isLoggedIn = window.isLoggedIn || false;
        if (!isLoggedIn || isLoggedIn === 'false') {
            window.location.href = `${window.BASE_URL || ''}/pages/auth/login.php?redirect=${encodeURIComponent(window.location.href)}`;
            return;
        }
        toggleWishlist(button);
    });
}

/**
 * Inizializza le azioni sulla pagina della wishlist (Aggiungi/Rimuovi).
 */
function initWishlistPageActions() {
    const container = document.querySelector('.wishlist-page-container');
    if (!container) return;

    container.addEventListener('click', function(e) {
        const removeButton = e.target.closest('.btn-remove-wishlist');
        if (removeButton) {
            handleRemoveFromWishlistPage(removeButton);
        }

        const addToCartButton = e.target.closest('.btn-add-to-cart');
        if (addToCartButton) {
            handleAddToCartFromWishlist(addToCartButton);
        }
    });
}

/**
 * Gestisce l'aggiunta/rimozione di un item tramite i bottoni a cuore.
 */
async function toggleWishlist(button) {
    if (button.disabled) return;
    button.disabled = true;

    const itemId = button.dataset.itemId;
    const itemType = button.dataset.itemType;
    const icon = button.querySelector('i');
    const isInWishlist = icon.classList.contains('bi-heart-fill');

    const formData = new FormData();
    if (isInWishlist) {
        formData.append('action', 'remove');
        formData.append('wishlist_id', button.dataset.wishlistId || await getWishlistId(itemId, itemType));
    } else {
        formData.append('action', 'add');
        formData.append('item_id', itemId);
        formData.append('item_type', itemType);
    }

    try {
        const response = await fetch(`${window.BASE_URL || ''}/action/wishlist_action.php`, { method: 'POST', body: formData });
        const data = await response.json();
        if (data.success) {
            if (isInWishlist) {
                icon.classList.remove('bi-heart-fill');
                icon.classList.add('bi-heart');
                button.classList.remove('in-wishlist');
                showNotification('Rimosso dalla wishlist', 'info');
            } else {
                icon.classList.remove('bi-heart');
                icon.classList.add('bi-heart-fill');
                button.classList.add('in-wishlist');
                button.dataset.wishlistId = data.wishlist_id;
                showNotification('Aggiunto alla wishlist!', 'success');
            }
            updateWishlistCounter(data.wishlist_count);
        } else {
            showNotification(data.message || 'Errore', 'danger');
        }
    } catch (error) {
        showNotification('Errore di comunicazione col server', 'danger');
    } finally {
        button.disabled = false;
    }
}

/**
 * ✅ FIX: Gestisce la rimozione immediata dalla pagina wishlist, senza popup.
 */
function handleRemoveFromWishlistPage(button) {
    const wishlistId = button.dataset.wishlistId;
    const card = button.closest('.col-lg-4'); // Seleziona l'intera colonna per una rimozione pulita

    if (!wishlistId || !card) return;

    fetch(`${window.BASE_URL || ''}/action/wishlist_action.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=remove&wishlist_id=${wishlistId}`
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Prodotto rimosso con successo!', 'success');

                // Animazione di scomparsa e rimozione della card
                card.style.transition = 'opacity 0.5s, transform 0.5s';
                card.style.opacity = '0';
                card.style.transform = 'scale(0.8)';
                setTimeout(() => {
                    card.remove();
                    updateWishlistCounter(data.wishlist_count);
                    if (document.querySelectorAll('.wishlist-item-card').length === 0) {
                        location.reload(); // Ricarica per mostrare il messaggio di wishlist vuota
                    }
                }, 500);
            } else {
                showNotification(data.message || 'Errore nella rimozione', 'danger');
            }
        })
        .catch(error => {
            showNotification('Errore di rete', 'danger');
        });
}

/**
 * ✅ FIX: Gestisce l'aggiunta al carrello, leggendo i dati dalla card corretta.
 */
function handleAddToCartFromWishlist(button) {
    const card = button.closest('.wishlist-item-card');
    // Cerca gli elementi DENTRO la card relativa al bottone premuto
    const nomeElement = card.querySelector('h5');
    const prezzoElement = card.querySelector('.price');

    if (button.disabled || !nomeElement || !prezzoElement) return;

    const itemId = button.dataset.itemId;
    const itemType = button.dataset.itemType;
    const nome = nomeElement.textContent.trim();
    const prezzo = prezzoElement.textContent.replace('€', '').replace(',', '.').trim();

    const formData = new FormData();
    formData.append('id_prodotto', itemId);
    formData.append('nome_prodotto', nome);
    formData.append('prezzo', prezzo);
    formData.append('tipo', itemType === 'box' ? 'mystery_box' : 'oggetto');
    formData.append('quantita', '1');

    button.disabled = true;
    const originalContent = button.innerHTML;
    button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

    fetch(`${window.BASE_URL || ''}/action/add_to_cart.php`, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Prodotto aggiunto al carrello!', 'success');
                updateCartCounter(data.cart_count);
            } else {
                showNotification(data.message || 'Errore', 'danger');
            }
        })
        .catch(error => {
            showNotification('Errore di rete', 'danger');
        })
        .finally(() => {
            button.disabled = false;
            button.innerHTML = originalContent;
        });
}

// --- FUNZIONI HELPER (invariate) ---

async function loadWishlistStates() {
    if (!window.isLoggedIn || window.isLoggedIn === 'false') return;
    try {
        const response = await fetch(`${window.BASE_URL || ''}/action/wishlist_action.php?action=get_user_wishlist`);
        const data = await response.json();
        if (!data.success) return;

        const userWishlist = data.wishlist;
        document.querySelectorAll('.wishlist-btn').forEach(button => {
            const itemId = button.dataset.itemId;
            const itemType = button.dataset.itemType;
            const wishlistItem = userWishlist.find(item => item.item_id == itemId && item.item_type == itemType);

            if (wishlistItem) {
                const icon = button.querySelector('i');
                icon.classList.remove('bi-heart');
                icon.classList.add('bi-heart-fill');
                button.classList.add('in-wishlist');
                button.dataset.wishlistId = wishlistItem.wishlist_id;
            }
        });
    } catch (error) {
        console.error('Errore caricamento stati wishlist:', error);
    }
}

async function getWishlistId(itemId, itemType) {
    try {
        const response = await fetch(`${window.BASE_URL || ''}/action/wishlist_action.php?action=get_wishlist_id&item_id=${itemId}&item_type=${itemType}`);
        const data = await response.json();
        return data.success ? data.wishlist_id : null;
    } catch (error) {
        return null;
    }
}

function updateWishlistCounter(count) {
    const wishlistBadge = document.querySelector('.wishlist-count-badge');
    if (wishlistBadge) {
        if (count > 0) {
            wishlistBadge.textContent = `${count} ${count == 1 ? 'prodotto' : 'prodotti'}`;
            wishlistBadge.style.display = 'inline-block';
        } else {
            wishlistBadge.style.display = 'none';
        }
    }
}

function updateCartCounter(count) {
    const cartBadge = document.querySelector('.cart-count'); // Assicurati di avere un elemento con questa classe nell'header
    if (cartBadge) {
        cartBadge.textContent = count;
        cartBadge.style.display = count > 0 ? 'inline-block' : 'none';
    }
}

function showNotification(message, type = 'info') {
    document.querySelectorAll('.wishlist-notification').forEach(n => n.remove());

    const notification = document.createElement('div');
    notification.className = `wishlist-notification alert-${type}`;
    notification.innerHTML = `<i class="bi bi-${type === 'success' ? 'check-circle-fill' : (type === 'info' ? 'info-circle-fill' : 'exclamation-triangle-fill')}"></i> ${message}`;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease forwards';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

