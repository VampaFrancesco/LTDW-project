// js/live-search.js - Ricerca in tempo reale completa
class LiveSearch {
    constructor() {
        this.searchInput = null;
        this.searchResults = null;
        this.debounceTimer = null;
        this.minChars = 2;
        this.debounceDelay = 300;
        this.isVisible = false;
        this.currentQuery = '';

        this.init();
    }

    init() {
        // Trova l'input di ricerca nell'header
        this.searchInput = document.querySelector('input[name="q"], .search-input, input[aria-label="Cerca"]');

        if (!this.searchInput) {
            console.log('Input di ricerca non trovato');
            return;
        }

        console.log('LiveSearch inizializzato con input:', this.searchInput);

        // Crea il contenitore dei risultati
        this.createResultsContainer();

        // Aggiungi event listeners
        this.addEventListeners();
    }

    createResultsContainer() {
        // Crea il dropdown per i risultati
        this.searchResults = document.createElement('div');
        this.searchResults.className = 'live-search-results';
        this.searchResults.innerHTML = '';

        // Posiziona il dropdown sotto l'input
        const searchContainer = this.searchInput.closest('.form-inline, .search-form, form');
        if (searchContainer) {
            searchContainer.style.position = 'relative';
            searchContainer.appendChild(this.searchResults);
            console.log('Container dei risultati aggiunto a:', searchContainer);
        } else {
            // Fallback: aggiungi dopo l'input
            this.searchInput.parentNode.style.position = 'relative';
            this.searchInput.parentNode.appendChild(this.searchResults);
        }

        // Stili CSS inline per il dropdown
        this.addStyles();
    }

    addStyles() {
        // Controlla se gli stili sono già stati aggiunti
        if (document.getElementById('live-search-styles')) return;

        const style = document.createElement('style');
        style.id = 'live-search-styles';
        style.textContent = `
            .live-search-results {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: white;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                box-shadow: 0 10px 25px rgba(0,0,0,0.15);
                z-index: 1000;
                max-height: 400px;
                overflow-y: auto;
                display: none;
                margin-top: 5px;
            }
            
            .live-search-results.visible {
                display: block;
            }
            
            .live-search-item {
                padding: 12px 16px;
                border-bottom: 1px solid #f7fafc;
                cursor: pointer;
                transition: all 0.2s ease;
                display: flex;
                align-items: center;
                gap: 12px;
            }
            
            .live-search-item:hover {
                background-color: #f8fafc;
            }
            
            .live-search-item.selected {
                background-color: #667eea !important;
                color: white !important;
            }
            
            .live-search-item.selected .live-search-category {
                background-color: rgba(255, 255, 255, 0.2) !important;
                color: white !important;
            }
            
            .live-search-item:last-child {
                border-bottom: none;
            }
            
            .live-search-image {
                width: 40px;
                height: 40px;
                border-radius: 6px;
                object-fit: cover;
                background: #f1f5f9;
                flex-shrink: 0;
            }
            
            .live-search-content {
                flex: 1;
                min-width: 0;
            }
            
            .live-search-title {
                font-weight: 600;
                color: #2d3748;
                font-size: 0.9rem;
                margin-bottom: 2px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            
            .live-search-item.selected .live-search-title {
                color: white !important;
            }
            
            .live-search-meta {
                font-size: 0.8rem;
                color: #718096;
                display: flex;
                gap: 8px;
                align-items: center;
                flex-wrap: wrap;
            }
            
            .live-search-item.selected .live-search-meta {
                color: rgba(255, 255, 255, 0.8) !important;
            }
            
            .live-search-price {
                font-weight: 600;
                color: #27ae60;
                font-size: 0.9rem;
                flex-shrink: 0;
            }
            
            .live-search-item.selected .live-search-price {
                color: white !important;
            }
            
            .live-search-category {
                background: #e6f3ff;
                color: #0066cc;
                padding: 2px 6px;
                border-radius: 10px;
                font-size: 0.7rem;
            }
            
            .live-search-loading,
            .live-search-no-results {
                padding: 20px;
                text-align: center;
                color: #718096;
            }
            
            .live-search-view-all {
                padding: 12px 16px;
                background: #f8fafc;
                border-top: 1px solid #e2e8f0;
                text-align: center;
                font-weight: 600;
                color: #667eea;
                cursor: pointer;
                transition: all 0.2s ease;
            }
            
            .live-search-view-all:hover,
            .live-search-view-all.selected {
                background: #667eea !important;
                color: white !important;
            }
            
            @media (max-width: 768px) {
                .live-search-results {
                    left: -15px;
                    right: -15px;
                    border-radius: 12px;
                }
                
                .live-search-item {
                    padding: 10px 12px;
                    gap: 10px;
                }
                
                .live-search-image {
                    width: 35px;
                    height: 35px;
                }
                
                .live-search-title {
                    font-size: 0.85rem;
                }
                
                .live-search-meta {
                    font-size: 0.75rem;
                }
                
                .live-search-price {
                    font-size: 0.85rem;
                }
            }
        `;
        document.head.appendChild(style);
    }

    addEventListeners() {
        // Input event per la ricerca
        this.searchInput.addEventListener('input', (e) => {
            const query = e.target.value.trim();
            this.handleInput(query);
        });

        // Focus event
        this.searchInput.addEventListener('focus', (e) => {
            const query = e.target.value.trim();
            if (query.length >= this.minChars && query === this.currentQuery) {
                this.showResults();
            }
        });

        // Keyboard navigation
        this.searchInput.addEventListener('keydown', (e) => {
            this.handleKeydown(e);
        });

        // Click outside to close
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.form-inline, .search-form, form') &&
                !e.target.closest('.live-search-results')) {
                this.hideResults();
            }
        });

        // Prevent form submission on empty search
        const form = this.searchInput.closest('form');
        if (form) {
            form.addEventListener('submit', (e) => {
                const query = this.searchInput.value.trim();
                if (query.length < this.minChars) {
                    e.preventDefault();
                    this.searchInput.focus();
                    return false;
                }
            });
        }
    }

    handleInput(query) {
        // Clear previous timer
        clearTimeout(this.debounceTimer);

        if (query.length < this.minChars) {
            this.hideResults();
            this.currentQuery = '';
            return;
        }

        // Debounce the search
        this.debounceTimer = setTimeout(() => {
            this.performSearch(query);
        }, this.debounceDelay);
    }

    handleKeydown(e) {
        if (!this.isVisible) return;

        const items = this.searchResults.querySelectorAll('.live-search-item, .live-search-view-all');
        const selected = this.searchResults.querySelector('.selected');
        let selectedIndex = Array.from(items).indexOf(selected);

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                selectedIndex = selectedIndex < items.length - 1 ? selectedIndex + 1 : 0;
                this.selectItem(items[selectedIndex]);
                break;

            case 'ArrowUp':
                e.preventDefault();
                selectedIndex = selectedIndex > 0 ? selectedIndex - 1 : items.length - 1;
                this.selectItem(items[selectedIndex]);
                break;

            case 'Enter':
                e.preventDefault();
                if (selected) {
                    this.activateSelectedItem(selected);
                }
                break;

            case 'Escape':
                this.hideResults();
                this.searchInput.blur();
                break;
        }
    }

    selectItem(item) {
        // Remove previous selection
        this.searchResults.querySelectorAll('.selected').forEach(el => {
            el.classList.remove('selected');
        });

        // Add selection to new item
        if (item) {
            item.classList.add('selected');
            // Scroll into view if necessary
            item.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    }

    activateSelectedItem(item) {
        if (item.classList.contains('live-search-view-all')) {
            // È il link "Vedi tutti"
            const query = this.currentQuery;
            this.searchAll(query);
        } else {
            // È un elemento prodotto
            const onclick = item.getAttribute('onclick');
            if (onclick) {
                // Estrai l'URL dall'onclick
                const urlMatch = onclick.match(/window\.location\.href='([^']+)'/);
                if (urlMatch) {
                    window.location.href = urlMatch[1];
                }
            }
        }
        this.hideResults();
    }

    async performSearch(query) {
        try {
            this.showLoading();
            this.currentQuery = query;

            // Determina il BASE_URL correttamente
            const baseUrl = this.getBaseUrl();
            const searchUrl = `${baseUrl}/action/search.php?format=json&q=${encodeURIComponent(query)}`;

            console.log('Eseguo ricerca:', searchUrl);

            const response = await fetch(searchUrl);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            this.displayResults(data.results || [], query);

        } catch (error) {
            console.error('Errore ricerca:', error);
            this.showError(error.message);
        }
    }

    getBaseUrl() {
        // Cerca di determinare il BASE_URL dal DOM
        const scripts = document.querySelectorAll('script');
        for (let script of scripts) {
            if (script.textContent && script.textContent.includes('BASE_URL')) {
                const match = script.textContent.match(/BASE_URL['"]\s*:\s*['"]([^'"]+)['"]/);
                if (match) return match[1];
            }
        }

        // Fallback: usa l'origin della pagina
        return window.location.origin;
    }

    showLoading() {
        this.searchResults.innerHTML = `
            <div class="live-search-loading">
                <i class="bi bi-search"></i> Ricerca in corso...
            </div>
        `;
        this.showResults();
    }

    showError(errorMessage = 'Errore durante la ricerca') {
        this.searchResults.innerHTML = `
            <div class="live-search-no-results">
                <i class="bi bi-exclamation-triangle"></i> ${errorMessage}
            </div>
        `;
        this.showResults();
    }

    displayResults(results, query) {
        if (results.length === 0) {
            this.searchResults.innerHTML = `
                <div class="live-search-no-results">
                    <i class="bi bi-search"></i> Nessun risultato per "<strong>${this.escapeHtml(query)}</strong>"
                </div>
            `;
            this.showResults();
            return;
        }

        // Limita i risultati a 5 per le preview
        const limitedResults = results.slice(0, 5);

        let html = '';

        limitedResults.forEach((item, index) => {
            const imageUrl = item.image_url || `${this.getBaseUrl()}/images/default_product1.jpg`;
            const availability = item.disponibilita > 0 ?
                `<span style="color: #27ae60;">✓ Disponibile (${item.disponibilita})</span>` :
                `<span style="color: #e74c3c;">✗ Esaurito</span>`;

            html += `
                <div class="live-search-item" onclick="window.location.href='${item.url}'" data-index="${index}">
                    <img src="${imageUrl}" 
                         alt="${this.escapeHtml(item.nome)}" 
                         class="live-search-image"
                         onerror="this.src='${this.getBaseUrl()}/images/default_product1.jpg'">
                    <div class="live-search-content">
                        <div class="live-search-title">${this.highlightQuery(item.nome, query)}</div>
                        <div class="live-search-meta">
                            <span class="live-search-category">${this.escapeHtml(item.categoria)}</span>
                            ${availability}
                        </div>
                    </div>
                    <div class="live-search-price">€${parseFloat(item.prezzo).toFixed(2)}</div>
                </div>
            `;
        });

        // Aggiungi link "Vedi tutti i risultati" se ci sono più risultati
        if (results.length > 5) {
            html += `
                <div class="live-search-view-all" data-query="${this.escapeHtml(query)}">
                    Vedi tutti i ${results.length} risultati
                </div>
            `;
        }

        this.searchResults.innerHTML = html;
        this.showResults();
    }

    highlightQuery(text, query) {
        if (!text || !query) return this.escapeHtml(text || '');

        const regex = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
        return this.escapeHtml(text).replace(regex, '<strong>$1</strong>');
    }

    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    searchAll(query) {
        const form = this.searchInput.closest('form');
        if (form) {
            this.searchInput.value = query;
            form.submit();
        } else {
            window.location.href = `${this.getBaseUrl()}/pages/search.php?q=${encodeURIComponent(query)}`;
        }
    }

    showResults() {
        this.searchResults.classList.add('visible');
        this.isVisible = true;
    }

    hideResults() {
        this.searchResults.classList.remove('visible');
        this.isVisible = false;

        // Rimuovi selezioni
        this.searchResults.querySelectorAll('.selected').forEach(el => {
            el.classList.remove('selected');
        });
    }

    // Metodo pubblico per forzare la ricerca (utile per debug)
    forceSearch(query) {
        this.performSearch(query);
    }

    // Metodo pubblico per distruggere l'istanza
    destroy() {
        if (this.searchResults && this.searchResults.parentNode) {
            this.searchResults.parentNode.removeChild(this.searchResults);
        }

        clearTimeout(this.debounceTimer);

        // Rimuovi event listeners se necessario
        // (in questo caso semplice non è critico)
    }
}

// Variabile globale per l'istanza
let liveSearchInstance = null;

// Inizializza la ricerca live quando il DOM è pronto
document.addEventListener('DOMContentLoaded', function() {
    // Attendi un momento per essere sicuri che tutto sia caricato
    setTimeout(() => {
        try {
            liveSearchInstance = new LiveSearch();
            console.log('LiveSearch inizializzato con successo');
        } catch (error) {
            console.error('Errore nell\'inizializzazione di LiveSearch:', error);
        }
    }, 100);
});

// Gestione per i dropdown della ricerca view-all (backup)
window.searchAll = function(query) {
    const baseUrl = window.location.origin;
    window.location.href = `${baseUrl}/pages/search.php?q=${encodeURIComponent(query)}`;
};

// Esponi l'istanza globalmente per debug
window.LiveSearch = LiveSearch;
window.getLiveSearchInstance = function() {
    return liveSearchInstance;
};