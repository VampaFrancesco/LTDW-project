<?php
// 1. PRIMA di qualsiasi output, includi il Session Manager e config
require_once __DIR__.'/../include/session_manager.php';
require_once __DIR__.'/../include/config.inc.php';

// 2. Richiedi autenticazione (questo fa il redirect automaticamente se non loggato)
SessionManager::requireLogin();

// 3. ORA è sicuro includere l'header (dopo tutti i controlli)
include 'header.php';

// 4. Recupera i dati utente in modo sicuro
$nome_utente = SessionManager::get('user_nome', 'Utente');
$cognome_utente = SessionManager::get('user_cognome', '');

// Costruisci il nome completo (nome + cognome se esiste)
$nome_completo = trim($nome_utente . ' ' . $cognome_utente) ?: 'Utente';

// Calcola il tempo rimanente della sessione (5 minuti = 300 secondi)
$session_timeout = 300; // 5 minuti in secondi
$last_activity = SessionManager::get('last_activity', time());
$time_remaining = $session_timeout - (time() - $last_activity);
$session_expires_at = ($last_activity + $session_timeout) * 1000; // JavaScript usa millisecondi
?>

    <main class="background-custom">
        <div class="container">
            <!-- Timer della sessione -->
            <div class="session-timer-container mb-4">
                <div class="alert alert-info d-flex align-items-center justify-content-between">
                    <div>
                        <i class="bi bi-clock-fill me-2"></i>
                        <strong>Sessione scade tra:</strong>
                    </div>
                    <div>
                    <span id="session-timer" class="badge bg-primary fs-6">
                        <span id="minutes">--</span>:<span id="seconds">--</span>
                    </span>
                    </div>
                </div>
            </div>

            <!-- 5. Mostra il nome utente con escape XSS -->
            <h1 class="fashion_taital mb-5">Bentornato, <?php echo htmlspecialchars($nome_completo); ?>!</h1>
            <p class="section-intro-text text-center">Esplora le ultime novità, la tua collezione e le offerte esclusive pensate per te.</p>

            <div class="section">
                <?php include 'sections/slider_prodotti.php'; ?>
            </div>

            <div class="section">
                <?php include 'sections/piu_venduti.php'; ?>
            </div>

            <div class="section">
                <?php include 'sections/scopri_anche.php'; ?>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tempo di scadenza della sessione dal PHP (in millisecondi)
            let currentSessionExpiresAt = <?php echo $session_expires_at; ?>;

            function updateTimer() {
                const now = new Date().getTime();
                const timeLeft = currentSessionExpiresAt - now;

                if (timeLeft <= 0) {
                    // Sessione scaduta
                    document.getElementById('minutes').textContent = '00';
                    document.getElementById('seconds').textContent = '00';

                    // Cambia colore del timer
                    const timerBadge = document.getElementById('session-timer');
                    timerBadge.className = 'badge bg-danger fs-6';

                    // Mostra avviso e reindirizza
                    alert('La tua sessione è scaduta. Verrai reindirizzato alla pagina di login.');
                    window.location.href = '<?php echo BASE_URL; ?>/pages/auth/login.php';
                    return;
                }

                // Calcola minuti e secondi rimanenti
                const minutes = Math.floor(timeLeft / 60000);
                const seconds = Math.floor((timeLeft % 60000) / 1000);

                // Aggiorna il display
                document.getElementById('minutes').textContent = minutes.toString().padStart(2, '0');
                document.getElementById('seconds').textContent = seconds.toString().padStart(2, '0');

                // Cambia colore quando rimane meno di 1 minuto
                const timerBadge = document.getElementById('session-timer');
                if (timeLeft <= 60000) { // Meno di 1 minuto
                    timerBadge.className = 'badge bg-warning fs-6';
                } else if (timeLeft <= 30000) { // Meno di 30 secondi
                    timerBadge.className = 'badge bg-danger fs-6';
                }

                // Avviso quando rimangono 30 secondi
                if (timeLeft <= 30000 && timeLeft > 29000) {
                    const alertDiv = document.querySelector('.session-timer-container .alert');
                    alertDiv.className = 'alert alert-warning d-flex align-items-center justify-content-between';

                    // Mostra notifica
                    if (Notification.permission === 'granted') {
                        new Notification('BoxOmnia - Sessione in scadenza', {
                            body: 'La tua sessione scadrà tra 30 secondi',
                            icon: '<?php echo BASE_URL; ?>/images/favicon.ico'
                        });
                    }
                }
            }

            // Richiedi permesso per le notifiche
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }

            // Aggiorna il timer ogni secondo
            updateTimer(); // Prima chiamata immediata
            const timerInterval = setInterval(updateTimer, 1000);

            // Estendi automaticamente la sessione su attività dell'utente
            let activityTimer;

            function resetActivityTimer() {
                clearTimeout(activityTimer);
                activityTimer = setTimeout(function() {
                    // Invia richiesta AJAX per estendere la sessione
                    fetch('<?php echo BASE_URL; ?>/api/session_extended.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({action: 'extend'})
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Aggiorna il tempo di scadenza
                                currentSessionExpiresAt = data.new_expiry_time;
                                console.log('Sessione estesa automaticamente');
                            }
                        })
                        .catch(error => console.error('Errore estensione sessione:', error));
                }, 30000); // Estendi dopo 30 secondi di inattività
            }

            // Rileva attività dell'utente
            ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'].forEach(function(event) {
                document.addEventListener(event, resetActivityTimer, true);
            });

            resetActivityTimer(); // Avvia il timer di attività
        });
    </script>

<?php include 'footer.php'; ?>