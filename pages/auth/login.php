<?php
// Previeni output prima degli header
ob_start();

$hideNav = true; // Nascondi navbar nella pagina di login
require_once __DIR__ . '/../../include/session_manager.php';
require_once __DIR__ . '/../../include/config.inc.php';

// Se già loggato, redirect a home
if (SessionManager::isLoggedIn()) {
    header('Location: ' . BASE_URL . '/pages/home_utente.php');
    exit();
}

// Recupera messaggi flash
$flash_message = SessionManager::getFlashMessage();
$form_data = SessionManager::get('login_form_data', []);
SessionManager::remove('login_form_data');

include __DIR__ . '/../../pages/header.php';
?>

    <main class="background-custom">
        <div class="login-container">
            <div class="text-center mb-4">
                <img src="<?php echo BASE_URL; ?>/images/boxomnia.png" alt="Box Omnia" style="max-width: 200px;">
            </div>

            <h2 class="text-center mb-4">Accedi al tuo account</h2>

            <!-- Mostra messaggi flash -->
            <?php if ($flash_message): ?>
                <div class="alert alert-<?php echo htmlspecialchars($flash_message['type']); ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($flash_message['content']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form action="<?php echo BASE_URL; ?>/action/login_action.php" method="POST">
                <div class="form-group mb-3">
                    <label for="email" class="form-label">Email:</label>
                    <input type="email"
                           class="form-control"
                           id="email"
                           name="email"
                           value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>"
                           required
                           autofocus>
                </div>

                <div class="form-group mb-4">
                    <label for="password" class="form-label">Password:</label>
                    <input type="password"
                           class="form-control"
                           id="password"
                           name="password"
                           required>
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-3">Accedi</button>
            </form>

            <div class="text-center">
                <p class="mb-0">Non hai un account? <a href="<?php echo BASE_URL; ?>/pages/auth/register.php">Registrati qui</a></p>
            </div>
        </div>
    </main>

    <style>
        .login-container {
            max-width: 400px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }

        .background-custom {
            min-height: 100vh;
            background-color: #f8f9fa;
            padding-top: 20px;
        }
    </style>

<?php
include __DIR__ . '/../../pages/footer.php';
ob_end_flush();
?>