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
                    <i class="bi bi-<?php echo $flash_message['type'] === 'danger' ? 'exclamation-triangle' : 'info-circle'; ?>"></i>
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
                    <!-- Alert dinamico per email @boxomnia.it -->
                    <div id="boxomniaAlert" class="alert alert-warning mt-2" style="display: none;">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Attenzione:</strong> Le email con dominio @boxomnia.it sono riservate agli account amministratore e non possono essere utilizzate per la registrazione normale.
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
                    <!-- Indicatore forza password -->
                    <div class="password-strength mt-2" id="passwordStrength" style="display: none;">
                        <div class="progress" style="height: 5px;">
                            <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                        </div>
                        <small class="strength-text text-muted"></small>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-3" id="submitBtn">
                    <i class="bi bi-person-plus"></i> Registrati
                </button>
            </form>

            <div class="text-center">
                <p class="mb-0">Hai già un account? <a href="<?php echo BASE_URL; ?>/pages/auth/login.php">Accedi qui</a></p>
            </div>
        </div>
    </main>


    <script>
        // Validazione email in tempo reale
        document.getElementById('email').addEventListener('input', function() {
            const email = this.value.toLowerCase();
            const boxomniaAlert = document.getElementById('boxomniaAlert');
            const submitBtn = document.getElementById('submitBtn');

            if (email.includes('@boxomnia.it')) {
                boxomniaAlert.style.display = 'block';
                this.classList.add('invalid');
                this.classList.remove('valid');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Email non consentita';
            } else {
                boxomniaAlert.style.display = 'none';
                this.classList.remove('invalid');
                if (this.value && this.checkValidity()) {
                    this.classList.add('valid');
                }
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-person-plus"></i> Registrati';
            }
        });

        // Validazione password in tempo reale
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthIndicator = document.getElementById('passwordStrength');
            const progressBar = strengthIndicator.querySelector('.progress-bar');
            const strengthText = strengthIndicator.querySelector('.strength-text');

            if (password.length > 0) {
                strengthIndicator.style.display = 'block';

                let strength = 0;
                let strengthLabel = 'Molto debole';
                let strengthColor = '#dc3545';

                // Calcola forza password
                if (password.length >= 6) strength += 25;
                if (password.length >= 8) strength += 25;
                if (/[A-Z]/.test(password)) strength += 25;
                if (/[0-9]/.test(password)) strength += 25;

                if (strength >= 75) {
                    strengthLabel = 'Forte';
                    strengthColor = '#28a745';
                } else if (strength >= 50) {
                    strengthLabel = 'Media';
                    strengthColor = '#ffc107';
                } else if (strength >= 25) {
                    strengthLabel = 'Debole';
                    strengthColor = '#fd7e14';
                }

                progressBar.style.width = strength + '%';
                progressBar.style.backgroundColor = strengthColor;
                strengthText.textContent = strengthLabel;

                // Validazione visiva
                if (password.length >= 6) {
                    this.classList.add('valid');
                    this.classList.remove('invalid');
                } else {
                    this.classList.add('invalid');
                    this.classList.remove('valid');
                }
            } else {
                strengthIndicator.style.display = 'none';
                this.classList.remove('valid', 'invalid');
            }
        });

        // Validazione form prima dell'invio
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.toLowerCase();
            const password = document.getElementById('password').value;
            const nome = document.getElementById('nome').value.trim();
            const cognome = document.getElementById('cognome').value.trim();

            // Controlla email @boxomnia.it
            if (email.includes('@boxomnia.it')) {
                e.preventDefault();
                alert('Le email con dominio @boxomnia.it non sono consentite per la registrazione normale.');
                return false;
            }

            // Controlla campi vuoti
            if (!nome || !cognome || !email || !password) {
                e.preventDefault();
                alert('Per favore compila tutti i campi obbligatori.');
                return false;
            }

            // Controlla lunghezza password
            if (password.length < 6) {
                e.preventDefault();
                alert('La password deve essere lunga almeno 6 caratteri.');
                return false;
            }

            // Feedback visivo
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.innerHTML = '<i class="spinner-border spinner-border-sm"></i> Registrazione...';
            submitBtn.disabled = true;
        });
    </script>

<?php
include __DIR__ . '/../footer.php';
?>