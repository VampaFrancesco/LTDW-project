<?php
include 'header.php';

// Corretto ordine:
require_once __DIR__.'/../include/session_config.php';
require_once __DIR__.'/../include/session_manager.php';
require_once __DIR__.'/../include/config.inc.php';


// Per proteggere una pagina:
SessionManager::checkAuth(); // Reindirizza al login se non autenticato
?>

    <main class="background-custom"> <!-- Aggiunta classe specifica -->
        <div class="container">
            <div class="section">
                <!-- Contenuto della prima sezione -->
                <?php include 'sections/slider_prodotti.php'; ?>
            </div>

            <div class="section">
                <!-- Contenuto della seconda sezione -->
                <?php include 'sections/piu_venduti.php'; ?>
            </div>

            <div class="section">
                <!-- Contenuto della terza sezione -->
                <?php include 'sections/scopri_anche.php'; ?>
            </div>
        </div>
    </main>

<?php include 'footer.php'; ?>