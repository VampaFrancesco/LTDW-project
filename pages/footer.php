<?php ?>
<footer class="bg-dark text-light pt-5">
    <div class="container">
        <div class="row">
            <div class="col-md-6 mb-4">
                <h5>Iscriviti alla newsletter</h5>
                <form class="form-inline">
                    <label>
                        <input type="email" class="form-control mr-2 mb-2" placeholder="La tua email">
                    </label>
                    <button type="submit" class="btn btn-primary mb-2">Iscriviti</button>
                </form>
                <p class="small mt-3 text-footer">Box Omnia è il punto di riferimento ufficiale per tutti i veri Collezionisti di carte! Scopri un mondo di Carte Originali, Giochi, Accessori esclusivi e tanto altro.</p>
            </div>
            <div class="col-md-3 mb-4">
                <h6>Informazioni</h6>
                <ul class="list-unstyled">
                    <li><a href="about.php">Chi siamo</a></li>
                    <li><a href="contatti.php">Contatti</a></li>
                </ul>
            </div>
            <div class="col-md-3 mb-4">
                <h6>Assistenza</h6>
                <ul class="list-unstyled">
                    <li><a href="info_pagamenti.php">Metodi di pagamento</a></li>
                    <li><a href="domande_frequenti.php">Domande frequenti</a></li>
                    
                </ul>
            </div>
        </div>
        <div class="text-center py-3 border-top">
            <p class="text-footer">Orari servizio clienti: Lun - Ven 08:30 - 16:30 | <a href="mailto:info@boxomnia.it" class="text-footer-email">info@boxomnia.it</a></p>
            <p class="text-footer">© <?php echo date('Y'); ?> Box Omnia. Tutti i diritti riservati.</p>
        </div>
    </div>
</footer>

<!-- JS -->
<script src="<?= BASE_URL ?>/js/custom.js" defer></script>
<script src="<?= BASE_URL ?>/js/register.js" defer></script>
<script src="<?= BASE_URL ?>/js/session_manager.js" defer></script>
<script src="<?= BASE_URL ?>/js/cart.js" defer></script>
<script src="<?= BASE_URL ?>/js/search.js" defer></script>
<script src="<?= BASE_URL ?>/js/wishlist.js" defer></script>
