<?php
$hideNav = true;
require_once __DIR__ . '/../../include/session_manager.php';
require_once __DIR__ . '/../../include/config.inc.php';

// Se già loggato, redirect a home
if (SessionManager::isLoggedIn()) {
    header('Location: ' . BASE_URL . '/pages/home_utente.php');
    exit();
}

// Recupera messaggi flash
$flash_message = SessionManager::getFlashMessage();
$form_data = SessionManager::get('register_form_data', []);
SessionManager::remove('register_form_data');

include __DIR__ . '/../header.php';
?>

    <main class="background-custom">
        <div class="register-container">
            <div class="text-center mb-4">
                <img src="<?php echo BASE_URL; ?>/images/boxomnia.png" alt="Box Omnia" style="max-width: 200px;">
            </div>

            <h2 class="text-center mb-4">Registra il tuo account</h2>

            <!-- Mostra messaggi flash -->
            <?php if ($flash_message): ?>
                <div class="alert alert-<?php echo htmlspecialchars($flash_message['type']); ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($flash_message['content']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form action="<?php echo BASE_URL; ?>/action/register_action.php" method="POST" id="registerForm">
                <div class="form-group mb-3">
                    <label for="nome" class="form-label">Nome:</label>
                    <input type="text"
                           class="form-control"
                           id="nome"
                           name="nome"
                           value="<?php echo htmlspecialchars($form_data['nome'] ?? ''); ?>"
                           required
                           autofocus>
                </div>

                <div class="form-group mb-3">
                    <label for="cognome" class="form-label">Cognome:</label>
                    <input type="text"
                           class="form-control"
                           id="cognome"
                           name="cognome"
                           value="<?php echo htmlspecialchars($form_data['cognome'] ?? ''); ?>"
                           required>
                </div>

                <div class="form-group mb-3">
                    <label for="email" class="form-label">Email:</label>
                    <input type="email"
                           class="form-control"
                           id="email"
                           name="email"
                           value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>"
                           required>
                    <div class="form-text">
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i>
                            Note: Le registrazioni con email @boxomnia.it sono riservate agli amministratori.
                        </small>
                    </div>
                </div>

                <div class="form-group mb-4">
                    <label for="password" class="form-label">Password:</label>
                    <input type="password"
                           class="form-control"
                           id="password"
                           name="password"
                           required>
                    <div class="form-text">
                        <small class="text-muted">Minimo 6 caratteri</small>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-3">Registrati</button>
            </form>

            <div class="text-center">
                <p class="mb-0">Hai già un account? <a href="<?php echo BASE_URL; ?>/pages/auth/login.php">Accedi qui</a></p>
            </div>
        </div>
    </main>

    <style>
        .register-container {
            max-width: 450px;
            margin: 30px auto;
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

        .form-text {
            margin-top: 5px;
        }
    </style>

<?php
include __DIR__ . '/../footer.php';
?>