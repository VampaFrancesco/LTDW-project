<?php
/**
 * pages/pagamento.php - VERSIONE MIGLIORATA COMPLETA
 * Gestisce la pagina di pagamento con controlli di sicurezza avanzati
 */

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

// Verifica che i dati non siano troppo vecchi (max 60 minuti)
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

// ✅ CONNESSIONE DATABASE CON GESTIONE ERRORI MIGLIORATA
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

// ✅ RECUPERA INDIRIZZI CON GESTIONE SICURA DEI NULL
$stmt = $conn->prepare("
    SELECT id_indirizzo, via, civico, cap, citta, provincia, nazione 
    FROM indirizzo_spedizione 
    WHERE fk_utente = ?
    ORDER BY id_indirizzo DESC
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
    // ✅ SANIFICAZIONE DEI DATI PER EVITARE NULL
    $indirizzi[] = [
            'id_indirizzo' => $row['id_indirizzo'],
            'via' => $row['via'] ?? '',
            'civico' => $row['civico'] ?? '',
            'cap' => $row['cap'] ?? '',
            'citta' => $row['citta'] ?? '',
            'provincia' => $row['provincia'] ?? '',
            'nazione' => $row['nazione'] ?? 'Italia'
    ];
}
$stmt->close();
$conn->close();

// Se non ci sono indirizzi, reindirizza a gestione indirizzi
if (empty($indirizzi)) {
    SessionManager::setFlashMessage('Devi aggiungere un indirizzo di spedizione prima di completare l\'ordine.', 'warning');
    SessionManager::set('redirect_after_address', BASE_URL . '/pages/pagamento.php');
    header('Location: ' . BASE_URL . '/pages/aggiungi_indirizzo.php');
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

            <!-- ✅ MESSAGGI FLASH -->
            <?php
            $flash_message = SessionManager::getFlashMessage();
            if ($flash_message):
                ?>
                <div class="alert alert-<?php echo htmlspecialchars($flash_message['type'] ?? 'info'); ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($flash_message['content'] ?? ''); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

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
                                        <option value="<?php echo htmlspecialchars($indirizzo['id_indirizzo']); ?>">
                                            <?php echo htmlspecialchars($indirizzo['via'] . ' ' . $indirizzo['civico'] . ', ' .
                                                    $indirizzo['cap'] . ' ' . $indirizzo['citta'] . ' (' . $indirizzo['provincia'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="indirizzo_id" value="<?php echo htmlspecialchars($indirizzi[0]['id_indirizzo']); ?>">
                            <p class="mb-0">
                                <strong><?php echo htmlspecialchars($indirizzi[0]['via'] . ' ' . $indirizzi[0]['civico']); ?></strong><br>
                                <?php echo htmlspecialchars($indirizzi[0]['cap'] . ' ' . $indirizzi[0]['citta'] . ' (' . $indirizzi[0]['provincia'] . ')'); ?><br>
                                <?php echo htmlspecialchars($indirizzi[0]['nazione']); ?>
                            </p>
                        <?php endif; ?>
                        <a href="<?php echo BASE_URL; ?>/pages/aggiungi_indirizzo.php" class="btn btn-sm btn-outline-primary mt-2">
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
                                <div class="col-md-8 mb-3">
                                    <label for="card_number" class="form-label">Numero Carta</label>
                                    <input type="text" class="form-control" id="card_number" name="card_number"
                                           placeholder="1234 5678 9012 3456" maxlength="19">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="card_cvv" class="form-label">CVV</label>
                                    <input type="text" class="form-control" id="card_cvv" name="card_cvv"
                                           placeholder="123" maxlength="4">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="card_name" class="form-label">Nome sulla Carta</label>
                                    <input type="text" class="form-control" id="card_name" name="card_name"
                                           placeholder="Mario Rossi">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="card_month" class="form-label">Mese</label>
                                    <select class="form-select" id="card_month" name="card_month">
                                        <option value="">MM</option>
                                        <?php for($i = 1; $i <= 12; $i++): ?>
                                            <option value="<?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>">
                                                <?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="card_year" class="form-label">Anno</label>
                                    <select class="form-select" id="card_year" name="card_year">
                                        <option value="">YYYY</option>
                                        <?php
                                        $current_year = date('Y');
                                        for($i = $current_year; $i <= $current_year + 10; $i++):
                                            ?>
                                            <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Form PayPal (nascosto inizialmente) -->
                        <div id="paypalForm" class="mt-4" style="display: none;">
                            <div class="alert alert-info">
                                <i class="bi bi-paypal"></i>
                                Verrai reindirizzato su PayPal per completare il pagamento.
                            </div>
                        </div>

                        <!-- Form Bonifico (nascosto inizialmente) -->
                        <div id="bonificoForm" class="mt-4" style="display: none;">
                            <div class="alert alert-warning">
                                <h6><i class="bi bi-bank"></i> Istruzioni per il Bonifico</h6>
                                <p class="mb-1"><strong>IBAN:</strong> IT60 X054 2811 1010 0000 0123456</p>
                                <p class="mb-1"><strong>Intestatario:</strong> Box Omnia S.r.l.</p>
                                <p class="mb-0"><strong>Causale:</strong> Ordine #[numero_ordine]</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Note aggiuntive -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-chat-text"></i> Note Aggiuntive (Opzionale)</h6>
                    </div>
                    <div class="card-body">
                            <textarea class="form-control" name="note_ordine" rows="3"
                                      placeholder="Inserisci eventuali note per il tuo ordine..."></textarea>
                    </div>
                </div>

                <!-- Termini e Condizioni -->
                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" id="accept_terms" name="accept_terms" required>
                    <label class="form-check-label" for="accept_terms">
                        Accetto i <a href="#" target="_blank">Termini e Condizioni</a> e
                        l'<a href="#" target="_blank">Informativa sulla Privacy</a>
                    </label>
                </div>

                <!-- Pulsante Conferma -->
                <button type="submit" class="btn btn-success btn-lg w-100" id="confirmPaymentBtn">
                    <i class="bi bi-lock-fill"></i>
                    <span id="btnText">Conferma e Paga €<?php echo number_format($checkout_data['totale'], 2); ?></span>
                    <span id="btnSpinner" class="d-none">
                            <span class="spinner-border spinner-border-sm" role="status"></span>
                            Elaborazione...
                        </span>
                </button>

            </form>
        </div>

        <!-- Colonna destra: Riepilogo Ordine -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-cart3"></i> Riepilogo Ordine</h5>
                </div>
                <div class="card-body">
                    <!-- Items del carrello -->
                    <?php
                    $subtotale = 0;
                    foreach ($checkout_data['items'] as $item):
                        $subtotale += $item['totale'];
                        ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="flex-grow-1">
                                <h6 class="mb-0"><?php echo htmlspecialchars($item['nome_prodotto']); ?></h6>
                                <small class="text-muted">Qtà: <?php echo $item['quantita']; ?></small>
                            </div>
                            <span class="fw-bold">€<?php echo number_format($item['totale'], 2); ?></span>
                        </div>
                    <?php endforeach; ?>

                    <hr>

                    <!-- Calcoli -->
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotale:</span>
                        <span>€<?php echo number_format($subtotale, 2); ?></span>
                    </div>

                    <?php
                    $spese_spedizione = $subtotale >= 50 ? 0 : 5.00;
                    $totale_finale = $subtotale + $spese_spedizione;
                    ?>

                    <div class="d-flex justify-content-between mb-2">
                        <span>Spedizione:</span>
                        <span class="<?php echo $spese_spedizione == 0 ? 'text-success' : ''; ?>">
                                <?php echo $spese_spedizione == 0 ? 'GRATIS' : '€' . number_format($spese_spedizione, 2); ?>
                            </span>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between mb-3">
                        <strong>Totale:</strong>
                        <strong class="text-primary">€<?php echo number_format($totale_finale, 2); ?></strong>
                    </div>

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
                <div class="alert alert-info">
                    <h6><i class="bi bi-truck"></i> Spedizione Gratuita</h6>
                    <small>Su tutti gli ordini sopra i €50</small>
                </div>
                <div class="alert alert-info">
                    <h6><i class="bi bi-arrow-return-left"></i> Reso Facile</h6>
                    <small>30 giorni per il reso</small>
                </div>
            </div>
        </div>
    </div>
</main>

    <script>
        // ✅ GESTIONE FORM E VALIDAZIONE
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const acceptTerms = document.getElementById('accept_terms');

            if (!acceptTerms.checked) {
                e.preventDefault();
                alert('Devi accettare i Termini e Condizioni per continuare.');
                acceptTerms.focus();
                return false;
            }

            // Mostra spinner
            const btn = document.getElementById('confirmPaymentBtn');
            const btnText = document.getElementById('btnText');
            const btnSpinner = document.getElementById('btnSpinner');

            btn.disabled = true;
            btnText.classList.add('d-none');
            btnSpinner.classList.remove('d-none');

            return true;
        });

        // ✅ GESTIONE OPZIONI PAGAMENTO
        document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
            radio.addEventListener('change', function() {
                // Nasconde tutti i form
                document.getElementById('creditCardForm').style.display = 'none';
                document.getElementById('paypalForm').style.display = 'none';
                document.getElementById('bonificoForm').style.display = 'none';

                // Mostra il form corrispondente
                if (this.value === 'carta_credito') {
                    document.getElementById('creditCardForm').style.display = 'block';
                } else if (this.value === 'paypal') {
                    document.getElementById('paypalForm').style.display = 'block';
                } else if (this.value === 'bonifico') {
                    document.getElementById('bonificoForm').style.display = 'block';
                }
            });
        });

        // ✅ TIMER SESSIONE
        let sessionStart = <?php echo $checkout_data['timestamp']; ?>;
        let sessionTimeout = 3600; // 60 minuti

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
        }

        // Aggiorna timer ogni secondo
        setInterval(updateTimer, 1000);
        updateTimer(); // Prima chiamata immediata
    </script>

<?php include __DIR__ . '/footer.php'; ?>