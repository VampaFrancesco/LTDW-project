<?php
require_once __DIR__.'/../include/session_manager.php';
require_once __DIR__.'/../include/config.inc.php';

// Richiedi autenticazione
SessionManager::requireLogin();

// Recupera i dati del checkout dalla sessione
$checkout_data = SessionManager::get('checkout_data');

// Se non ci sono dati di checkout, torna al carrello
if (!$checkout_data || !isset($checkout_data['items'])) {
    SessionManager::setFlashMessage('Per procedere al pagamento devi prima selezionare dei prodotti dal carrello.', 'info');
    header('Location: ' . BASE_URL . '/pages/cart.php');
    exit();
}

// Verifica che i dati non siano troppo vecchi (max 60 minuti invece di 30)
if (isset($checkout_data['timestamp']) && time() - $checkout_data['timestamp'] > 3600) {
    SessionManager::remove('checkout_data');
    SessionManager::setFlashMessage('La sessione di checkout è scaduta. I prezzi potrebbero essere cambiati, ricontrolla il carrello.', 'warning');
    header('Location: ' . BASE_URL . '/pages/cart.php');
    exit();
}

// Verifica che l'utente corrente sia lo stesso che ha creato il checkout
$user_id = SessionManager::get('user_id');
if (isset($checkout_data['user_id']) && $checkout_data['user_id'] != $user_id) {
    SessionManager::remove('checkout_data');
    SessionManager::setFlashMessage('Sessione non valida. Riprova dal carrello.', 'danger');
    header('Location: ' . BASE_URL . '/pages/cart.php');
    exit();
}

// Recupera gli indirizzi dell'utente
$db_config = $config['dbms']['localhost'];
$conn = new mysqli(
        $db_config['host'],
        $db_config['user'],
        $db_config['passwd'],
        $db_config['dbname']
);

if ($conn->connect_error) {
    SessionManager::setFlashMessage('Errore di connessione al database. Riprova più tardi.', 'danger');
    header('Location: ' . BASE_URL . '/pages/cart.php');
    exit();
}

$stmt = $conn->prepare("
    SELECT id_indirizzo, via, civico, cap, citta, provincia, nazione 
    FROM indirizzo_spedizione 
    WHERE fk_utente = ?
");

if (!$stmt) {
    SessionManager::setFlashMessage('Errore nel recupero degli indirizzi. Riprova più tardi.', 'danger');
    header('Location: ' . BASE_URL . '/pages/cart.php');
    exit();
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$indirizzi_result = $stmt->get_result();

$indirizzi = [];
while ($row = $indirizzi_result->fetch_assoc()) {
    $indirizzi[] = $row;
}
$stmt->close();
$conn->close();

// Se non ci sono indirizzi, reindirizza a gestione indirizzi
if (empty($indirizzi)) {
    SessionManager::setFlashMessage('Devi aggiungere un indirizzo di spedizione prima di completare l\'ordine.', 'warning');
    SessionManager::set('redirect_after_address', BASE_URL . '/pages/pagamento.php');
    header('Location: ' . BASE_URL . '/pages/gestione_indirizzi.php');
    exit();
}

include __DIR__.'/header.php';
?>

    <main class="container my-5">
        <div class="row">
            <!-- Colonna sinistra: Form di pagamento -->
            <div class="col-lg-8">
                <h2 class="mb-4">Checkout</h2>

                <!-- Step indicator -->
                <div class="step-indicator mb-4">
                    <div class="d-flex justify-content-between">
                        <div class="step active">
                            <div class="step-number">1</div>
                            <div class="step-label">Carrello</div>
                        </div>
                        <div class="step active">
                            <div class="step-number">2</div>
                            <div class="step-label">Spedizione</div>
                        </div>
                        <div class="step active">
                            <div class="step-number">3</div>
                            <div class="step-label">Pagamento</div>
                        </div>
                        <div class="step">
                            <div class="step-number">4</div>
                            <div class="step-label">Conferma</div>
                        </div>
                    </div>
                </div>

                <!-- Info sessione -->
                <div class="alert alert-info mb-4">
                    <i class="bi bi-clock"></i>
                    <strong>Tempo rimanente:</strong>
                    <span id="timeRemaining">59:59</span> -
                    Completa l'ordine entro questo tempo per mantenere i prezzi correnti.
                </div>

                <form action="<?php echo BASE_URL; ?>/action/process_payment_action.php" method="POST" id="paymentForm">

                    <!-- Sezione Indirizzo di Spedizione -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-geo-alt"></i> Indirizzo di Spedizione</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($indirizzi) > 1): ?>
                                <div class="mb-3">
                                    <label for="indirizzo_spedizione" class="form-label">Seleziona indirizzo:</label>
                                    <select class="form-select" id="indirizzo_spedizione" name="indirizzo_id" required>
                                        <?php foreach ($indirizzi as $indirizzo): ?>
                                            <option value="<?php echo $indirizzo['id_indirizzo']; ?>">
                                                <?php echo htmlspecialchars($indirizzo['via'] . ' ' . $indirizzo['civico'] . ', ' .
                                                        $indirizzo['cap'] . ' ' . $indirizzo['citta'] . ' (' . $indirizzo['provincia'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="indirizzo_id" value="<?php echo $indirizzi[0]['id_indirizzo']; ?>">
                                <p class="mb-0">
                                    <strong><?php echo htmlspecialchars($indirizzi[0]['via'] . ' ' . $indirizzi[0]['civico']); ?></strong><br>
                                    <?php echo htmlspecialchars($indirizzi[0]['cap'] . ' ' . $indirizzi[0]['citta'] . ' (' . $indirizzi[0]['provincia'] . ')'); ?><br>
                                    <?php echo htmlspecialchars($indirizzi[0]['nazione']); ?>
                                </p>
                            <?php endif; ?>
                            <a href="<?php echo BASE_URL; ?>/pages/gestione_indirizzi.php" class="btn btn-sm btn-outline-primary mt-2">
                                <i class="bi bi-plus-circle"></i> Gestisci indirizzi
                            </a>
                        </div>
                    </div>

                    <!-- Sezione Metodo di Pagamento (Fittizio) -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-credit-card"></i> Metodo di Pagamento</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info mb-3">
                                <i class="bi bi-info-circle"></i>
                                <strong>Ambiente di test:</strong> Questo è un sistema di pagamento fittizio. Nessun pagamento reale verrà elaborato.
                            </div>

                            <!-- Opzioni di pagamento -->
                            <div class="payment-options">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="radio" name="payment_method" id="carta_credito" value="carta_credito" checked>
                                    <label class="form-check-label" for="carta_credito">
                                        <i class="bi bi-credit-card"></i> Carta di Credito/Debito
                                    </label>
                                </div>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="radio" name="payment_method" id="paypal" value="paypal">
                                    <label class="form-check-label" for="paypal">
                                        <i class="bi bi-paypal"></i> PayPal
                                    </label>
                                </div>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="radio" name="payment_method" id="bonifico" value="bonifico">
                                    <label class="form-check-label" for="bonifico">
                                        <i class="bi bi-bank"></i> Bonifico Bancario
                                    </label>
                                </div>
                            </div>

                            <!-- Form carta di credito (fittizio) -->
                            <div id="creditCardForm" class="mt-4">
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label for="card_number" class="form-label">Numero Carta</label>
                                        <input type="text" class="form-control" id="card_number" placeholder="1234 5678 9012 3456" maxlength="19">
                                        <small class="text-muted">Inserisci qualsiasi numero per il test</small>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="card_name" class="form-label">Nome Titolare</label>
                                        <input type="text" class="form-control" id="card_name" placeholder="Mario Rossi">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="card_expiry" class="form-label">Scadenza</label>
                                        <input type="text" class="form-control" id="card_expiry" placeholder="MM/YY" maxlength="5">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="card_cvv" class="form-label">CVV</label>
                                        <input type="text" class="form-control" id="card_cvv" placeholder="123" maxlength="3">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Note aggiuntive -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-chat-text"></i> Note per l'ordine (opzionale)</h5>
                        </div>
                        <div class="card-body">
                            <textarea class="form-control" name="note_ordine" rows="3" placeholder="Inserisci eventuali note per la spedizione..."></textarea>
                        </div>
                    </div>

                </form>
            </div>

            <!-- Colonna destra: Riepilogo ordine -->
            <div class="col-lg-4">
                <div class="card sticky-top" style="top: 20px;">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="bi bi-receipt"></i> Riepilogo Ordine</h5>
                    </div>
                    <div class="card-body">
                        <!-- Prodotti -->
                        <?php foreach ($checkout_data['items'] as $item): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <div>
                                    <small><?php echo htmlspecialchars($item['nome_prodotto']); ?></small><br>
                                    <small class="text-muted">Quantità: <?php echo $item['quantita']; ?></small>
                                </div>
                                <div class="text-end">
                                    <strong>€<?php echo number_format($item['totale'], 2, ',', '.'); ?></strong>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <hr>

                        <!-- Subtotale -->
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotale:</span>
                            <span>€<?php echo number_format($checkout_data['totale'], 2, ',', '.'); ?></span>
                        </div>

                        <!-- Spedizione -->
                        <div class="d-flex justify-content-between mb-2">
                            <span>Spedizione:</span>
                            <span class="text-success">GRATIS</span>
                        </div>

                        <hr>

                        <!-- Totale -->
                        <div class="d-flex justify-content-between mb-3">
                            <strong>Totale:</strong>
                            <strong class="text-primary fs-4">€<?php echo number_format($checkout_data['totale'], 2, ',', '.'); ?></strong>
                        </div>

                        <!-- Pulsante conferma -->
                        <button type="submit" form="paymentForm" class="btn btn-success btn-lg w-100">
                            <i class="bi bi-lock-fill"></i> Conferma e Paga
                        </button>

                        <!-- Sicurezza -->
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                <i class="bi bi-shield-check"></i> Pagamento sicuro e protetto
                            </small>
                        </div>

                        <!-- Link al carrello -->
                        <div class="text-center mt-2">
                            <a href="<?php echo BASE_URL; ?>/pages/cart.php" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Torna al carrello
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Info aggiuntive -->
                <div class="mt-3">
                    <div class="alert alert-secondary">
                        <h6><i class="bi bi-truck"></i> Spedizione Gratuita</h6>
                        <small>Su tutti gli ordini sopra i €25</small>
                    </div>
                    <div class="alert alert-secondary">
                        <h6><i class="bi bi-arrow-return-left"></i> Reso Facile</h6>
                        <small>30 giorni per il reso</small>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Timer per la sessione
        let sessionStart = <?php echo $checkout_data['timestamp']; ?>;
        let sessionTimeout = 3600; // 60 minuti in secondi

        function updateTimer() {
            let elapsed = Math.floor(Date.now() / 1000) - sessionStart;
            let remaining = sessionTimeout - elapsed;

            if (remaining <= 0) {
                alert('La sessione di checkout è scaduta. Verrai reindirizzato al carrello.');
                window.location.href = '<?php echo BASE_URL; ?>/pages/cart.php';
                return;
            }

            let minutes = Math.floor(remaining / 60);
            let seconds = remaining % 60;

            document.getElementById('timeRemaining').textContent =
                String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');

            // Cambia colore quando mancano 5 minuti
            let timerElement = document.getElementById('timeRemaining');
            if (remaining <= 300) {
                timerElement.style.color = '#dc3545';
                timerElement.style.fontWeight = 'bold';
            }
        }

        // Aggiorna timer ogni secondo
        setInterval(updateTimer, 1000);
        updateTimer(); // Prima chiamata immediata

        // Formattazione numero carta
        document.getElementById('card_number').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
            e.target.value = formattedValue;
        });

        // Formattazione scadenza
        document.getElementById('card_expiry').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.slice(0, 2) + '/' + value.slice(2, 4);
            }
            e.target.value = value;
        });

        // Solo numeri per CVV
        document.getElementById('card_cvv').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });

        // Mostra/nasconde form carta di credito
        document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const creditCardForm = document.getElementById('creditCardForm');
                if (this.value === 'carta_credito') {
                    creditCardForm.style.display = 'block';
                } else {
                    creditCardForm.style.display = 'none';
                }
            });
        });
    </script>

<?php include __DIR__.'/footer.php'; ?>