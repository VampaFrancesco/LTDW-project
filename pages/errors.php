<?php
require_once __DIR__ . '/../include/session_manager.php';
include 'header.php';
?>
    <main class="background-custom">
        <div class="container">
            <h1>Errore di Sistema</h1>
            <p>Si è verificato un errore. <a href="<?php echo (defined('BASE_URL') ? BASE_URL : ''); ?>/pages/index.php">Torna alla home</a></p>
        </div>
    </main>
<?php include 'footer.php'; ?>