<?php
// 1. PRIMA di qualsiasi output
require_once __DIR__.'/../include/session_manager.php';
require_once __DIR__.'/../include/config.inc.php';

// 2. Il carrello potrebbe essere visibile anche per utenti non loggati ma noi lo chiediamo
SessionManager::requireLogin();

// 3. Include header
include __DIR__ . '/header.php';
?>

    <main class="background-custom">
        <div class="container py-5">
            <h1 class="fashion_taital mb-5">Il tuo Carrello</h1>

            <!-- Contenuto del carrello qui -->
            <div class="alert alert-info text-center" role="alert">
                Il tuo carrello è vuoto
            </div>

            <div class="text-center mt-4">
                <a href="<?php echo BASE_URL; ?>/pages/catalogo.php" class="btn btn-primary">
                    Continua lo Shopping
                </a>
            </div>
        </div>
    </main>

<?php include __DIR__ . '/footer.php'; ?>