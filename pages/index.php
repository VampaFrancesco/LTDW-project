<?php
// IMPORTANTE: Prima tutti i require e controlli di autenticazione
require_once __DIR__.'/../include/session_config.php';
require_once __DIR__.'/../include/session_manager.php';
require_once __DIR__.'/../include/config.inc.php';

// Controlla autenticazione PRIMA di qualsiasi output HTML
SessionManager::requireLogin();

// SOLO DOPO il controllo auth, includi header.php
include 'header.php';
?>

    <main class="background-custom">
        <div class="container">
            <!-- Messaggio di benvenuto personalizzato -->
            <div class="welcome-section py-4">
                <h2>Benvenuto, <?php echo htmlspecialchars(SessionManager::get('user_nome', 'Utente')); ?>!</h2>
                <p>Esplora le nostre collezioni e scopri le ultime novità.</p>
            </div>

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