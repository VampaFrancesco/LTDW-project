<?php
require_once __DIR__.'/../include/session_manager.php';
require_once __DIR__.'/../include/config.inc.php';
include __DIR__ . '/header.php';

// Richiede login, reindirizza se non loggato
SessionManager::requireLogin();

// Recupera i dati salvati in sessione
$nome        = SessionManager::get('user_nome', 'Utente');
$cognome     = SessionManager::get('user_cognome', '');
$email       = SessionManager::get('user_email', '');
$data_reg    = SessionManager::get('user_data_reg', '');

?>

<main class="background-custom">
    <div class="container section">
        
        <h1 class="fashion_taital">Profilo Utente</h1>

        <div style="max-width: 800px; margin: 0 auto;">
            <div style="background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
                
                <!-- Info profilo -->
                <div style="margin-bottom: 20px;">
                    <div class="order-info-grid">
                        <div class="info-item">
                            <strong>Nome:</strong>
                            <span><?php echo htmlspecialchars($nome); ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Cognome:</strong>
                            <span><?php echo htmlspecialchars($cognome); ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Email:</strong>
                            <span><?php echo htmlspecialchars($email); ?></span>
                        </div>
                        <?php if (!empty($data_reg)): ?>
                        <div class="info-item">
                            <strong>Data registrazione:</strong>
                            <span><?php echo date("d/m/Y", strtotime($data_reg)); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>


                <!-- Pulsanti azione -->
                <div style="display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin-top: 20px;">
                    <a href="ordini.php" class="btn-add-to-cart" style="text-decoration: none;">📦I miei ordini</a>
                    <a href="<?php echo (defined('BASE_URL') ? BASE_URL : ''); ?>/pages/modifica_profilo.php" class="btn-add-to-cart" style="text-decoration: none; background-color: #24B1D9;">✏️ Modifica profilo</a>
                    <a href="<?php echo (defined('BASE_URL') ? BASE_URL : ''); ?>/pages/auth/logout.php" class="btn-add-to-cart" style="text-decoration: none; background-color: #dc3545;">🚪 Logout</a>
                </div>
            </div>
        </div>

    </div>
</main>

<?php
include __DIR__ . '/footer.php';
?>
