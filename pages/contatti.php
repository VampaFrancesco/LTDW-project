<?php 
include __DIR__ . '/header.php';
?>

<main class="background-custom">
    <div class="container section">
        
        <h1 class="fashion_taital">Contattaci</h1>
        
        <p class="lead" style="text-align: center; max-width: 800px; margin: 0 auto;">
            Hai domande, suggerimenti o richieste particolari?  
            Compila il form qui sotto oppure utilizza i nostri recapiti: saremo felici di risponderti!
        </p>

        <div style="display: flex; flex-wrap: wrap; gap: 40px; margin-top: 40px;">
            
            <!-- Info contatti -->
            <div style="flex: 1 1 300px; min-width: 300px;">
                <h2>I nostri recapiti</h2>
                <p><strong>Email:</strong> <a href="mailto:info@mysterybox.it">info@mysterybox.it</a></p>
                <p><strong>Telefono:</strong> <a href="tel:+390000000000">+39 000 000 000</a></p>
                <p><strong>Indirizzo:</strong> Via delle Sorprese, 123 - Roma</p>
                <p><strong>Orari:</strong> Lun - Ven: 9:00 - 18:00</p>
            </div>

            <!-- Form contatti -->
            <div style="flex: 2 1 400px; min-width: 300px;">
                <form action="send_contact.php" method="POST" style="background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label for="name">Nome e Cognome</label>
                        <input type="text" id="name" name="name" class="form-control" required>
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label for="message">Messaggio</label>
                        <textarea id="message" name="message" class="form-control" rows="5" required></textarea>
                    </div>

                    <button type="submit" class="btn-add-to-cart" style="width: 100%;">Invia</button>
                </form>
            </div>

        </div>
    </div>
</main>

<?php 
include __DIR__ . '/footer.php';
?>
