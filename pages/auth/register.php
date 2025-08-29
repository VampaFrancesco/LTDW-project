<?php
// pages/auth/register.php
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
$form_data = SessionManager::get('register_form_data', []);
SessionManager::remove('register_form_data');
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registrati - Box Omnia</title>

    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="icon" href="<?php echo BASE_URL; ?>/images/favicon.ico" type="image/gif"/>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/style.css">

    <style>

    </style>
</head>
<body>
<main class="background-custom">
    <div class="register-container">
        <div class="text-center mb-4">
            <img src="<?php echo BASE_URL; ?>/images/boxomnia.png" alt="Box Omnia" style="max-width: 200px;">
        </div>

        <h2 class="text-center mb-4">Registra il tuo account</h2>

        <!-- ✅ ALERT PER ERRORI DI REGISTRAZIONE -->
        <?php if ($flash_message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($flash_message['type']); ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-<?php echo $flash_message['type'] === 'danger' ? 'exclamation-triangle' : ($flash_message['type'] === 'success' ? 'check-circle' : 'info-circle'); ?> me-2"></i>
                <?php echo htmlspecialchars($flash_message['content']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form action="<?php echo BASE_URL; ?>/action/register_action.php" method="POST" id="registerForm" novalidate>
            <div class="form-group mb-3">
                <label for="nome" class="form-label">
                    <i class="bi bi-person me-2"></i>Nome:
                </label>
                <input type="text"
                       class="form-control"
                       id="nome"
                       name="nome"
                       value="<?php echo htmlspecialchars($form_data['nome'] ?? ''); ?>"
                       placeholder="Inserisci il tuo nome"
                       required
                       autofocus>
                <div class="invalid-feedback">Il nome è obbligatorio</div>
            </div>

            <div class="form-group mb-3">
                <label for="cognome" class="form-label">
                    <i class="bi bi-person me-2"></i>Cognome:
                </label>
                <input type="text"
                       class="form-control"
                       id="cognome"
                       name="cognome"
                       value="<?php echo htmlspecialchars($form_data['cognome'] ?? ''); ?>"
                       placeholder="Inserisci il tuo cognome"
                       required>
                <div class="invalid-feedback">Il cognome è obbligatorio</div>
            </div>

            <div class="form-group mb-3">
                <label for="email" class="form-label">
                    <i class="bi bi-envelope me-2"></i>Email:
                </label>
                <input type="email"
                       class="form-control"
                       id="email"
                       name="email"
                       value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>"
                       placeholder="Inserisci la tua email"
                       required>
                <div class="invalid-feedback">Inserisci un indirizzo email valido</div>
                <!-- ✅ WARNING PER EMAIL @boxomnia.it -->
                <div class="email-warning" id="emailWarning">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Le email con dominio @boxomnia.it sono riservate agli amministratori
                </div>
            </div>

            <div class="form-group mb-3">
                <label for="password" class="form-label">
                    <i class="bi bi-lock me-2"></i>Password:
                </label>
                <input type="password"
                       class="form-control"
                       id="password"
                       name="password"
                       placeholder="Crea una password sicura"
                       required>
                <div class="invalid-feedback">La password non soddisfa i requisiti</div>
                <!-- ✅ INDICAZIONI SICUREZZA PASSWORD -->
                <div class="password-requirements" id="passwordRequirements">
                    <i class="bi bi-info-circle me-1"></i>
                    La password deve contenere almeno 6 caratteri
                </div>
            </div>

            <div class="form-group mb-4">
                <label for="confirm_password" class="form-label">
                    <i class="bi bi-lock-fill me-2"></i>Conferma Password:
                </label>
                <input type="password"
                       class="form-control"
                       id="confirm_password"
                       name="confirm_password"
                       placeholder="Conferma la password"
                       required>
                <div class="invalid-feedback">Le password non corrispondono</div>
            </div>

            <button type="submit" class="btn btn-primary w-100 mb-3">
                <i class="bi bi-person-plus me-2"></i>Registrati
            </button>
        </form>

        <div class="text-center">
            <p class="mb-0">Hai già un account?
                <a href="<?php echo BASE_URL; ?>/pages/auth/login.php" class="login-link">
                    Accedi qui
                </a>
            </p>
        </div>
    </div>
</main>

<!-- JavaScript -->
<script src="<?php echo BASE_URL; ?>/js/bootstrap.bundle.min.js"></script>

<!-- ✅ VALIDAZIONI COMPLETE PER REGISTRAZIONE -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('registerForm');
        const nomeField = document.getElementById('nome');
        const cognomeField = document.getElementById('cognome');
        const emailField = document.getElementById('email');
        const passwordField = document.getElementById('password');
        const confirmPasswordField = document.getElementById('confirm_password');
        const emailWarning = document.getElementById('emailWarning');
        const passwordRequirements = document.getElementById('passwordRequirements');

        // ✅ VALIDAZIONE EMAIL @boxomnia.it
        emailField.addEventListener('input', function() {
            const email = this.value.toLowerCase();

            if (email.endsWith('@boxomnia.it')) {
                emailWarning.style.display = 'block';
                this.classList.add('is-invalid');
                this.classList.remove('is-valid');
            } else if (this.validity.valid && email.length > 0) {
                emailWarning.style.display = 'none';
                this.classList.add('is-valid');
                this.classList.remove('is-invalid');
            } else {
                emailWarning.style.display = 'none';
                this.classList.remove('is-valid', 'is-invalid');
            }
        });

        // ✅ VALIDAZIONE PASSWORD CON INDICAZIONI
        passwordField.addEventListener('input', function() {
            const password = this.value;
            const isValid = password.length >= 6;

            if (password.length === 0) {
                passwordRequirements.textContent = 'La password deve contenere almeno 6 caratteri';
                passwordRequirements.className = 'password-requirements';
                this.classList.remove('is-valid', 'is-invalid');
            } else if (isValid) {
                passwordRequirements.textContent = '✓ Password valida';
                passwordRequirements.className = 'password-requirements valid';
                this.classList.add('is-valid');
                this.classList.remove('is-invalid');
            } else {
                passwordRequirements.textContent = `✗ Servono almeno ${6 - password.length} caratteri in più`;
                passwordRequirements.className = 'password-requirements invalid';
                this.classList.add('is-invalid');
                this.classList.remove('is-valid');
            }

            // Rivalidare conferma password
            if (confirmPasswordField.value) {
                validatePasswordMatch();
            }
        });

        // ✅ VALIDAZIONE CONFERMA PASSWORD
        function validatePasswordMatch() {
            const password = passwordField.value;
            const confirmPassword = confirmPasswordField.value;

            if (confirmPassword.length === 0) {
                confirmPasswordField.classList.remove('is-valid', 'is-invalid');
            } else if (password === confirmPassword) {
                confirmPasswordField.classList.add('is-valid');
                confirmPasswordField.classList.remove('is-invalid');
            } else {
                confirmPasswordField.classList.add('is-invalid');
                confirmPasswordField.classList.remove('is-valid');
            }
        }

        confirmPasswordField.addEventListener('input', validatePasswordMatch);

        // ✅ VALIDAZIONE NOME E COGNOME
        [nomeField, cognomeField].forEach(field => {
            field.addEventListener('input', function() {
                if (this.value.trim().length >= 2) {
                    this.classList.add('is-valid');
                    this.classList.remove('is-invalid');
                } else if (this.value.length > 0) {
                    this.classList.add('is-invalid');
                    this.classList.remove('is-valid');
                } else {
                    this.classList.remove('is-valid', 'is-invalid');
                }
            });
        });

        // ✅ VALIDAZIONE FINALE AL SUBMIT
        form.addEventListener('submit', function(e) {
            let isValid = true;

            // Validazione nome
            if (nomeField.value.trim().length < 2) {
                nomeField.classList.add('is-invalid');
                isValid = false;
            }

            // Validazione cognome
            if (cognomeField.value.trim().length < 2) {
                cognomeField.classList.add('is-invalid');
                isValid = false;
            }

            // Validazione email
            if (!emailField.validity.valid || emailField.value.toLowerCase().endsWith('@boxomnia.it')) {
                emailField.classList.add('is-invalid');
                isValid = false;

                if (emailField.value.toLowerCase().endsWith('@boxomnia.it')) {
                    alert('Non è possibile registrarsi con email del dominio aziendale @boxomnia.it');
                }
            }

            // Validazione password
            if (passwordField.value.length < 6) {
                passwordField.classList.add('is-invalid');
                isValid = false;
            }

            // Validazione conferma password
            if (passwordField.value !== confirmPasswordField.value) {
                confirmPasswordField.classList.add('is-invalid');
                isValid = false;
            }

            if (!isValid) {
                e.preventDefault();
                alert('Correggi gli errori nel form prima di continuare');
                return false;
            }
        });
    });
</script>
</body>
</html>