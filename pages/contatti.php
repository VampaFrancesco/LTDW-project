<?php 
include __DIR__ . '/header.php';
?>

<main class="background-custom">
    <div class="container section contact-page">
        
        <h1 class="fashion_taital h1-contact">Contattaci</h1>
        
        <p class="lead">
            Hai domande, suggerimenti o richieste particolari?
            Compila il form qui sotto oppure utilizza i nostri recapiti: saremo felici di risponderti!
        </p>

        <div class="contact-content">
            
            <div class="contact-info-section">
                <h2>I nostri recapiti</h2>
                <p><strong>Email:</strong> info@boxomnia.it</p>
                <p><strong>Telefono:</strong> +39 000 000 000</p>
                <p><strong>Indirizzo:</strong> Via delle Sorprese, 123 - Roma</p>
                <p><strong>Orari:</strong> Lun - Ven: 8:30 - 16:30</p>
            </div>

<div class="contact-form-section">
    <form action="<?php echo BASE_URL; ?>/action/send_contact.php" method="POST">
        
        <div class="form-group">
            <label for="name">Nome e Cognome</label>
            <input type="text" id="name" name="name" class="form-control" 
                   value="<?php echo SessionManager::isLoggedIn() ? htmlspecialchars(SessionManager::get('user_nome', '') . ' ' . SessionManager::get('user_cognome', '')) : ''; ?>" 
                   required>
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" class="form-control" 
                   value="<?php echo SessionManager::isLoggedIn() ? htmlspecialchars(SessionManager::get('user_email', '')) : ''; ?>" 
                   required>
        </div>

        <div class="form-group">
            <label for="message">Messaggio</label>
            <textarea id="message" name="message" class="form-control" rows="5" 
                      placeholder="Descrivi nel dettaglio la tua richiesta. Riceverai una risposta il prima possibile..." required></textarea>
        </div>

        <button type="submit" class="btn-add-to-cart">
            <i class="bi bi-send"></i> Invia Richiesta
        </button>
    </form>
</div>

        </div>
    </div>
</main>

<?php 
include __DIR__ . '/footer.php';
?>