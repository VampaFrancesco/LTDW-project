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

// Recupera messaggi flash e conteggio tentativi
$flash_message = SessionManager::getFlashMessage();
$form_data = SessionManager::get('login_form_data', []);
$failed_attempts = SessionManager::get('login_failed_attempts', 0);
$last_attempt_time = SessionManager::get('last_login_attempt', 0);

SessionManager::remove('login_form_data');

include __DIR__ . '/../../pages/header.php';
?>

    <main class="background-custom">
        <div class="login-container">
            <div class="text-center mb-4">
                <img src="<?php echo BASE_URL; ?>/images/boxomnia.png" alt="Box Omnia" style="max-width: 200px;">
            </div>

            <h2 class="text-center mb-4">Accedi al tuo account</h2>

            <!-- Container per alert personalizzati -->
            <div id="alertContainer"></div>

            <!-- Mostra messaggi flash con tipologie specifiche -->
            <?php if ($flash_message): ?>
                <div class="alert-custom alert-custom-<?php echo htmlspecialchars($flash_message['type']); ?> alert-dismissible fade show" role="alert">
                    <i class="bi bi-<?php echo $flash_message['type'] === 'danger' ? 'exclamation-triangle' : ($flash_message['type'] === 'success' ? 'check-circle' : 'info-circle'); ?>"></i>
                    <div class="alert-custom-content">
                        <strong>
                            <?php
                            echo $flash_message['type'] === 'danger' ? 'Errore di accesso!' :
                                    ($flash_message['type'] === 'success' ? 'Successo!' : 'Informazione:');
                            ?>
                        </strong>
                        <?php echo htmlspecialchars($flash_message['content']); ?>
                        <?php if ($failed_attempts > 0 && $flash_message['type'] === 'danger'): ?>
                            <br><small>Tentativi falliti: <?php echo $failed_attempts; ?>/5</small>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="alert-custom-close" onclick="this.parentElement.remove()">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Avviso per troppi tentativi -->
            <?php if ($failed_attempts >= 3): ?>
                <div class="alert-custom alert-custom-warning" id="securityWarning">
                    <i class="bi bi-shield-exclamation"></i>
                    <div class="alert-custom-content">
                        <strong>Attenzione alla sicurezza!</strong>
                        Sono stati rilevati multipli tentativi di accesso. Verifica le tue credenziali.
                        <?php if ($failed_attempts >= 5): ?>
                            <br><small>Account temporaneamente limitato per sicurezza.</small>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <form action="<?php echo BASE_URL; ?>/action/login_action.php" method="POST" id="loginForm">
                <div class="form-group mb-3">
                    <label for="email" class="form-label">Email:</label>
                    <div class="input-container">
                        <input type="email"
                               class="form-control"
                               id="email"
                               name="email"
                               value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>"
                               required
                               autofocus
                               autocomplete="email">
                        <div class="input-feedback" id="emailFeedback"></div>
                    </div>
                </div>

                <div class="form-group mb-4">
                    <label for="password" class="form-label">Password:</label>
                    <div class="input-container">
                        <div class="password-input-wrapper">
                            <input type="password"
                                   class="form-control"
                                   id="password"
                                   name="password"
                                   required
                                   autocomplete="current-password"
                                   minlength="6">
                            <button type="button" class="password-toggle" id="togglePassword">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="input-feedback" id="passwordFeedback"></div>
                        <div class="form-text">
                            <small class="text-muted">Minimo 6 caratteri</small>
                        </div>
                    </div>
                </div>

                <!-- Captcha semplice se troppi tentativi -->
                <?php if ($failed_attempts >= 3): ?>
                    <div class="form-group mb-3">
                        <label for="captcha" class="form-label">Verifica di sicurezza:</label>
                        <?php
                        $num1 = rand(1, 10);
                        $num2 = rand(1, 10);
                        $captcha_answer = $num1 + $num2;
                        ?>
                        <input type="hidden" name="captcha_answer" value="<?php echo $captcha_answer; ?>">
                        <div class="captcha-container">
                            <span class="captcha-question"><?php echo $num1; ?> + <?php echo $num2; ?> = ?</span>
                            <input type="number"
                                   class="form-control captcha-input"
                                   id="captcha"
                                   name="captcha"
                                   required
                                   min="0"
                                   max="20"
                                   placeholder="Risultato">
                        </div>
                    </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary w-100 mb-3" id="submitBtn"
                        <?php echo ($failed_attempts >= 5) ? 'disabled title="Troppi tentativi. Riprova più tardi."' : ''; ?>>
                    <i class="bi bi-box-arrow-in-right"></i>
                    <span id="btnText">Accedi</span>
                </button>
            </form>

            <div class="text-center">
                <p class="mb-2">Non hai un account? <a href="<?php echo BASE_URL; ?>/pages/auth/register.php">Registrati qui</a></p>
                <?php if ($failed_attempts > 0): ?>
                    <p class="mb-0">
                        <small>
                            <a href="#" onclick="showPasswordRecoveryInfo()" class="text-muted">
                                <i class="bi bi-question-circle"></i> Hai dimenticato la password?
                            </a>
                        </small>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Indicatore di sicurezza -->
            <div class="security-indicator mt-3" id="securityIndicator">
                <small class="text-muted">
                    <i class="bi bi-shield-check text-success"></i>
                    Connessione sicura
                </small>
            </div>
        </div>
    </main>
    <script>
        // Configurazione
        const CONFIG = {
            maxAttempts: 5,
            currentAttempts: <?php echo $failed_attempts; ?>,
            lastAttemptTime: <?php echo $last_attempt_time; ?>,
            lockoutDuration: 300000, // 5 minuti in millisecondi
        };

        // Funzione per creare alert personalizzati specifici per il login
        function showLoginAlert(message, type = 'danger', duration = 5000, details = null) {
            const alertContainer = document.getElementById('alertContainer');

            const alertElement = document.createElement('div');
            alertElement.className = `alert-custom alert-custom-${type} alert-dismissible fade show`;

            let icon = 'exclamation-triangle';
            let title = 'Errore!';

            switch(type) {
                case 'success':
                    icon = 'check-circle';
                    title = 'Successo!';
                    break;
                case 'warning':
                    icon = 'exclamation-triangle';
                    title = 'Attenzione!';
                    break;
                case 'info':
                    icon = 'info-circle';
                    title = 'Informazione:';
                    break;
                case 'security':
                    icon = 'shield-exclamation';
                    title = 'Avviso di sicurezza!';
                    type = 'warning';
                    break;
            }

            alertElement.innerHTML = `
                <i class="bi bi-${icon}"></i>
                <div class="alert-custom-content">
                    <strong>${title}</strong>
                    ${message}
                    ${details ? `<br><small>${details}</small>` : ''}
                </div>
                <button type="button" class="alert-custom-close" onclick="this.parentElement.remove()">
                    <i class="bi bi-x"></i>
                </button>
            `;

            alertContainer.appendChild(alertElement);

            // Auto-rimuovi
            if (duration > 0) {
                setTimeout(() => {
                    if (alertElement && alertElement.parentElement) {
                        alertElement.classList.add('fade-out');
                        setTimeout(() => alertElement.remove(), 300);
                    }
                }, duration);
            }
        }

        // Validazione email in tempo reale
        document.getElementById('email').addEventListener('input', function() {
            const email = this.value.trim();
            const feedback = document.getElementById('emailFeedback');

            if (email.length === 0) {
                this.classList.remove('valid', 'invalid');
                feedback.textContent = '';
                return;
            }

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            if (emailRegex.test(email)) {
                this.classList.add('valid');
                this.classList.remove('invalid');
                feedback.textContent = '✓ Email valida';
                feedback.className = 'input-feedback success';
            } else {
                this.classList.add('invalid');
                this.classList.remove('valid');
                feedback.textContent = '✗ Formato email non valido';
                feedback.className = 'input-feedback error';
            }
        });

        // Validazione password in tempo reale
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const feedback = document.getElementById('passwordFeedback');

            if (password.length === 0) {
                this.classList.remove('valid', 'invalid');
                feedback.textContent = '';
                return;
            }

            if (password.length < 6) {
                this.classList.add('invalid');
                this.classList.remove('valid');
                feedback.textContent = '✗ Password troppo corta (minimo 6 caratteri)';
                feedback.className = 'input-feedback error';
            } else if (password.length < 8) {
                this.classList.remove('invalid');
                this.classList.add('valid');
                feedback.textContent = '⚠ Password accettabile (consigliati 8+ caratteri)';
                feedback.className = 'input-feedback warning';
            } else {
                this.classList.add('valid');
                this.classList.remove('invalid');
                feedback.textContent = '✓ Password forte';
                feedback.className = 'input-feedback success';
            }
        });

        // Toggle visibilità password
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.className = 'bi bi-eye-slash';
                this.title = 'Nascondi password';
            } else {
                passwordInput.type = 'password';
                icon.className = 'bi bi-eye';
                this.title = 'Mostra password';
            }
        });

        // Validazione form completa
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');

            // Verifica se account è bloccato
            if (CONFIG.currentAttempts >= CONFIG.maxAttempts) {
                const timeSinceLastAttempt = Date.now() - CONFIG.lastAttemptTime;
                if (timeSinceLastAttempt < CONFIG.lockoutDuration) {
                    e.preventDefault();
                    const remainingTime = Math.ceil((CONFIG.lockoutDuration - timeSinceLastAttempt) / 60000);
                    showLoginAlert(
                        `Account temporaneamente bloccato per sicurezza.`,
                        'security',
                        8000,
                        `Riprova tra ${remainingTime} minuti.`
                    );
                    return false;
                }
            }

            // Validazione campi vuoti
            if (!email || !password) {
                e.preventDefault();
                showLoginAlert('Inserisci email e password per continuare.', 'warning');

                // Evidenzia campi vuoti
                if (!email) document.getElementById('email').classList.add('invalid');
                if (!password) document.getElementById('password').classList.add('invalid');

                return false;
            }

            // Validazione formato email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                showLoginAlert('Inserisci un indirizzo email valido.', 'warning');
                document.getElementById('email').classList.add('invalid');
                document.getElementById('email').focus();
                return false;
            }

            // Validazione lunghezza password
            if (password.length < 6) {
                e.preventDefault();
                showLoginAlert('La password deve essere lunga almeno 6 caratteri.', 'warning');
                document.getElementById('password').classList.add('invalid');
                document.getElementById('password').focus();
                return false;
            }

            // Validazione captcha se presente
            const captchaInput = document.getElementById('captcha');
            if (captchaInput) {
                const captchaValue = parseInt(captchaInput.value);
                const captchaAnswer = parseInt(document.querySelector('input[name="captcha_answer"]').value);

                if (isNaN(captchaValue) || captchaValue !== captchaAnswer) {
                    e.preventDefault();
                    showLoginAlert('Risolvi correttamente l\'operazione matematica.', 'warning');
                    captchaInput.classList.add('invalid');
                    captchaInput.focus();
                    return false;
                }
            }

            // Feedback visivo di caricamento
            submitBtn.disabled = true;
            btnText.innerHTML = '<i class="spinner-border spinner-border-sm"></i> Verifica credenziali...';

            // Aggiungi overlay di caricamento al form
            const loadingOverlay = document.createElement('div');
            loadingOverlay.className = 'loading-overlay';
            loadingOverlay.innerHTML = '<div class="spinner-border text-primary"></div>';
            this.style.position = 'relative';
            this.appendChild(loadingOverlay);

            // Mostra feedback
            showLoginAlert('Verifica delle credenziali in corso...', 'info', 3000);

            // Incrementa contatore tentativi (sarà gestito dal server)
            CONFIG.currentAttempts++;
        });

        // Funzione per mostrare info recupero password
        function showPasswordRecoveryInfo() {
            showLoginAlert(
                'Contatta l\'amministratore per il recupero della password.',
                'info',
                0,
                'In futuro implementeremo il recupero automatico via email.'
            );
        }

        // Gestione errori dal server (se la pagina viene ricaricata con errori)
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const error = urlParams.get('error');

            if (error === 'invalid_credentials') {
                // Scuoti il form per errore credenziali
                document.querySelector('.login-container').classList.add('shake');
                setTimeout(() => {
                    document.querySelector('.login-container').classList.remove('shake');
                }, 500);

                // Aggiorna indicatore di sicurezza
                if (CONFIG.currentAttempts >= 3) {
                    const securityIndicator = document.getElementById('securityIndicator');
                    securityIndicator.innerHTML = `
                        <small class="text-warning">
                            <i class="bi bi-shield-exclamation text-warning"></i>
                            Multipli tentativi rilevati
                        </small>
                    `;
                    securityIndicator.className = 'security-indicator mt-3';
                    securityIndicator.style.background = 'rgba(255, 193, 7, 0.1)';
                    securityIndicator.style.borderColor = 'rgba(255, 193, 7, 0.2)';
                }
            }

            // Auto-focus sul campo appropriato basato sugli errori
            const emailField = document.getElementById('email');
            const passwordField = document.getElementById('password');

            if (emailField.value && !passwordField.value) {
                passwordField.focus();
            } else if (!emailField.value) {
                emailField.focus();
            }

            // Rimuovi parametri URL per pulizia
            if (error) {
                const url = new URL(window.location);
                url.searchParams.delete('error');
                window.history.replaceState({}, document.title, url.pathname);
            }
        });

        // Prevenzione brute force lato client
        let formSubmissionCount = 0;
        const maxSubmissionsPerMinute = 3;

        setInterval(() => {
            formSubmissionCount = 0;
        }, 60000); // Reset ogni minuto

        // Monitora tentativi multipli
        document.getElementById('loginForm').addEventListener('submit', function() {
            formSubmissionCount++;

            if (formSubmissionCount > maxSubmissionsPerMinute) {
                showLoginAlert(
                    'Troppi tentativi di accesso ravvicinati.',
                    'security',
                    0,
                    'Attendere un minuto prima di riprovare.'
                );
                return false;
            }
        });
    </script>

<?php
include __DIR__ . '/../../pages/footer.php';
ob_end_flush();
?>