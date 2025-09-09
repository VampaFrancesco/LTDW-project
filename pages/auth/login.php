<?php
// pages/auth/login.php
ob_start();

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
$form_data = SessionManager::get('login_form_data', []);
SessionManager::remove('login_form_data');
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accedi - Box Omnia</title>

    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="icon" href="<?php echo BASE_URL; ?>/images/favicon.ico" type="image/gif"/>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/style.css">
</head>

<main class="background-custom">
    <div class="login-container">
        <div class="text-center mb-4">
            <img src="<?php echo BASE_URL; ?>/images/boxomnia.png" alt="Box Omnia" style="max-width: 200px;">
        </div>

        <h2 class="text-center mb-4">Accedi al tuo account</h2>

        <!-- ✅ ALERT PER ERRORI DI LOGIN -->
        <?php if ($flash_message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($flash_message['type']); ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-<?php echo $flash_message['type'] === 'danger' ? 'exclamation-triangle' : 'check-circle'; ?> me-2"></i>
                <?php echo htmlspecialchars($flash_message['content']); ?>
            </div>
        <?php endif; ?>

        <form action="<?php echo BASE_URL; ?>/action/login_action.php" method="POST" id="loginForm">
            <div class="form-group mb-3">
                <label for="email" class="form-label">
                    Email:
                </label>
                <input type="email"
                       class="form-control"
                       id="email"
                       name="email"
                       value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>"
                       placeholder="Inserisci la tua email"
                       required
                       autofocus>
            </div>

            <div class="form-group mb-4">
                <label for="password" class="form-label">
                    Password:
                </label>
                <input type="password"
                       class="form-control"
                       id="password"
                       name="password"
                       placeholder="Inserisci la tua password"
                       required>
            </div>

            <button type="submit" class="btn btn-primary w-100 mb-3">
                <i class="bi bi-box-arrow-in-right me-2"></i>Accedi
            </button>
        </form>

        <div class="text-center">
            <p class="mb-0">Non hai un account?
                <a href="<?php echo BASE_URL; ?>/pages/auth/register.php" class="register-link">
                    Registrati qui
                </a>
            </p>
        </div>
    </div>
</main>

<!-- JavaScript -->
<script src="<?php echo BASE_URL; ?>/js/bootstrap.bundle.min.js"></script>

<!-- ✅ SOLO VALIDAZIONE SEMPLICE PER LOGIN -->
<script>
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value.trim();

        // Validazione base
        if (!email || !password) {
            e.preventDefault();
            alert('Inserisci email e password');
            return false;
        }

        // Validazione email semplice
        if (!email.includes('@') || !email.includes('.')) {
            e.preventDefault();
            alert('Inserisci un indirizzo email valido');
            document.getElementById('email').focus();
            return false;
        }
    });

    // Auto-focus su campo email se vuoto
    window.addEventListener('DOMContentLoaded', function() {
        const emailField = document.getElementById('email');
        if (!emailField.value) {
            emailField.focus();
        }
    });
</script>
</body>
</html>