<?php
// pages/pagamento.php - VERSIONE CORRETTA
require_once __DIR__.'/../include/session_manager.php';
require_once __DIR__.'/../include/config.inc.php';

SessionManager::requireLogin();
$user_id = SessionManager::get('user_id');

// Recupera dati checkout
$checkout_data = SessionManager::get('checkout_data');

if (!$checkout_data || !isset($checkout_data['items'])) {
    SessionManager::setFlashMessage('Sessione checkout scaduta.', 'warning');
    header('Location: ' . BASE_URL . '/pages/cart.php');
    exit();
}

// Verifica scadenza (60 minuti)
if (time() - $checkout_data['timestamp'] > 3600) {
    SessionManager::remove('checkout_data');
    SessionManager::setFlashMessage('Sessione scaduta, riprova.', 'warning');
    header('Location: ' . BASE_URL . '/pages/cart.php');
    exit();
}

// Database
$db_config = $config['dbms']['localhost'];
$conn = new mysqli(
        $db_config['host'],
        $db_config['user'],
        $db_config['passwd'],
        $db_config['dbname']
);

// Recupera indirizzi
$stmt = $conn->prepare("
    SELECT id_indirizzo, via, civico, cap, citta, provincia, nazione 
    FROM indirizzo_spedizione 
    WHERE fk_utente = ? 
    ORDER BY id_indirizzo DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$indirizzi_result = $stmt->get_result();

$indirizzi = [];
while ($row = $indirizzi_result->fetch_assoc()) {
    $indirizzi[] = $row;
}

$stmt->close();
$conn->close();

// Calcolo spedizione
$spedizione = $checkout_data['totale'] >= 50 ? 0 : 5.00;
$totale_finale = $checkout_data['totale'] + $spedizione;

include __DIR__ . '/header.php';
?>

    <style>
        /* ===== CSS PAGAMENTO.PHP - OTTIMIZZATO ===== */

        /* Container principale */
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .container.py-4 {
            padding: 2rem 0.75rem !important;
        }

        /* Titolo pagina */
        h2 {
            color: #333;
            font-weight: 700;
            margin-bottom: 2rem;
            text-align: center;
            padding: 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.3);
        }

        /* Sezioni principali */
        h4 {
            color: #495057;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            font-size: 1.2rem;
        }

        h4::before {
            content: '';
            width: 4px;
            height: 24px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            margin-right: 0.75rem;
            border-radius: 2px;
        }

        /* Form styling */
        #payment-form {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            animation: slideInUp 0.6s ease-out;
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Opzioni indirizzi */
        .form-check.border {
            border: 2px solid #e9ecef !important;
            border-radius: 12px !important;
            padding: 1.5rem !important;
            margin-bottom: 1rem !important;
            background: linear-gradient(135deg, #fafbfc 0%, #f8f9fa 100%);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .form-check.border::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 0;
            height: 100%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            transition: width 0.3s ease;
            opacity: 0.05;
            z-index: 1;
        }

        .form-check.border:hover {
            border-color: #667eea !important;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
        }

        .form-check.border:hover::before {
            width: 100%;
        }

        .form-check.border:has(input:checked) {
            border-color: #28a745 !important;
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            box-shadow: 0 5px 20px rgba(40, 167, 69, 0.2);
        }

        .form-check-input {
            transform: scale(1.3) !important;
            accent-color: #28a745;
            position: relative;
            z-index: 2;
        }

        .form-check-label {
            cursor: pointer;
            position: relative;
            z-index: 2;
            width: 100%;
        }

        .form-check-label strong {
            color: #495057;
            font-size: 1.05rem;
        }

        .form-check-label i {
            font-size: 1.2rem;
            margin-right: 0.5rem;
            color: #667eea;
        }

        /* Card riepilogo ordine */
        .card.mb-4 {
            border: none !important;
            border-radius: 15px !important;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08) !important;
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
            border-bottom: 1px solid #dee2e6 !important;
            padding: 1.5rem !important;
        }

        .card-header h5 {
            margin: 0 !important;
            color: #495057;
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        .card-header h5::before {
            content: '🛒';
            margin-right: 0.75rem;
            font-size: 1.2rem;
        }

        .card-body {
            padding: 2rem !important;
        }

        .card-body .d-flex {
            padding: 0.75rem 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .card-body .d-flex:last-child {
            border-bottom: none;
            font-weight: 700;
            font-size: 1.1rem;
            color: #28a745;
            background: linear-gradient(135deg, #f8fff9 0%, #f1f8f2 100%);
            margin: 1rem -2rem 0 -2rem;
            padding: 1.5rem 2rem;
            border-radius: 0 0 15px 15px;
        }

        .card-body hr {
            margin: 1rem 0;
            border-color: #dee2e6;
            opacity: 0.5;
        }

        /* Note ordine */
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.75rem;
        }

        .form-control {
            border: 2px solid #e9ecef !important;
            border-radius: 12px !important;
            padding: 1rem !important;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-control:focus {
            border-color: #667eea !important;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1) !important;
        }

        /* Pulsanti */
        .d-flex.justify-content-between:last-child {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 2px solid #f0f0f0;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .btn {
            border-radius: 25px !important;
            padding: 0.75rem 2rem !important;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-secondary {
            background: transparent !important;
            color: #6c757d !important;
            border: 2px solid #dee2e6 !important;
        }

        .btn-secondary:hover {
            background: #f8f9fa !important;
            color: #495057 !important;
            border-color: #adb5bd !important;
            transform: translateY(-2px);
        }

        .btn-success.btn-lg {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%) !important;
            border: none !important;
            padding: 1rem 3rem !important;
            font-size: 1.1rem !important;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .btn-success.btn-lg:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4) !important;
        }

        .btn-success.btn-lg:disabled {
            background: #6c757d !important;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .container.py-4 {
                padding: 1rem 0.5rem !important;
            }

            #payment-form {
                padding: 1.5rem;
                margin: 0 -0.5rem;
            }

            .d-flex.justify-content-between:last-child {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            h2 {
                font-size: 1.5rem;
                padding: 1rem;
            }

            .form-check.border {
                padding: 1rem !important;
            }
        }

        /* Stati focus per accessibilità */
        .form-check.border:focus-within {
            outline: 3px solid rgba(102, 126, 234, 0.3);
            outline-offset: 2px;
        }

        .btn:focus {
            outline: 3px solid rgba(102, 126, 234, 0.3) !important;
            outline-offset: 2px;
        }

        /* Animazioni staggered per elementi */
        .mb-4:nth-child(1) { animation-delay: 0.1s; }
        .mb-4:nth-child(2) { animation-delay: 0.2s; }
        .mb-4:nth-child(3) { animation-delay: 0.3s; }
        .mb-4:nth-child(4) { animation-delay: 0.4s; }

        .mb-4 {
            animation: slideInUp 0.6s ease-out both;
        }

        /* Loading state per il pulsante */
        .btn-success.btn-lg.loading {
            position: relative;
        }

        .btn-success.btn-lg.loading::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            margin: auto;
            border: 2px solid transparent;
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>

    <div class="container py-4">
        <h2><i class="bi bi-credit-card"></i> Pagamento</h2>

        <!-- ✅ FORM CORRETTO CHE INVIA A process_payment_action.php -->
        <form method="POST" action="<?php echo BASE_URL; ?>/action/process_payment_action.php" id="payment-form">

            <!-- Selezione Indirizzo -->
            <div class="mb-4">
                <h4>Indirizzo di Spedizione</h4>
                <?php foreach ($indirizzi as $indirizzo): ?>
                    <div class="form-check border p-3 mb-2">
                        <input class="form-check-input" type="radio" name="indirizzo_id"
                               value="<?php echo $indirizzo['id_indirizzo']; ?>" required>
                        <label class="form-check-label">
                            <strong><?php echo htmlspecialchars($indirizzo['via'] . ' ' . $indirizzo['civico']); ?></strong><br>
                            <?php echo htmlspecialchars($indirizzo['cap'] . ' ' . $indirizzo['citta'] . ' (' . $indirizzo['provincia'] . ')'); ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Metodo di Pagamento -->
            <div class="mb-4">
                <h4>Metodo di Pagamento</h4>
                <div class="form-check border p-3 mb-2">
                    <input class="form-check-input" type="radio" name="payment_method"
                           value="carta_credito" checked required>
                    <label class="form-check-label">
                        <i class="bi bi-credit-card"></i> Carta di Credito
                    </label>
                </div>
                <div class="form-check border p-3 mb-2">
                    <input class="form-check-input" type="radio" name="payment_method"
                           value="paypal" required>
                    <label class="form-check-label">
                        <i class="bi bi-paypal"></i> PayPal
                    </label>
                </div>
            </div>

            <!-- Riepilogo Ordine -->
            <div class="card mb-4">
                <div class="card-header"><h5>Riepilogo Ordine</h5></div>
                <div class="card-body">
                    <?php foreach ($checkout_data['items'] as $item): ?>
                        <div class="d-flex justify-content-between">
                            <span><?php echo htmlspecialchars($item['nome_prodotto']); ?> (x<?php echo $item['quantita']; ?>)</span>
                            <span>€<?php echo number_format($item['totale'], 2); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <span>Subtotale:</span>
                        <span>€<?php echo number_format($checkout_data['totale'], 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Spedizione:</span>
                        <span><?php echo $spedizione == 0 ? 'GRATUITA' : '€' . number_format($spedizione, 2); ?></span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between fw-bold">
                        <span>TOTALE:</span>
                        <span>€<?php echo number_format($totale_finale, 2); ?></span>
                    </div>
                </div>
            </div>

            <!-- Note Ordine -->
            <div class="mb-4">
                <label for="note_ordine" class="form-label">Note aggiuntive (opzionale)</label>
                <textarea class="form-control" name="note_ordine" id="note_ordine" rows="3"></textarea>
            </div>

            <!-- Pulsanti -->
            <div class="d-flex justify-content-between">
                <a href="<?php echo BASE_URL; ?>/pages/cart.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Torna al Carrello
                </a>
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="bi bi-check-circle"></i> Conferma e Paga €<?php echo number_format($totale_finale, 2); ?>
                </button>
            </div>
        </form>
    </div>

    <script>
        // Validazione form
        document.getElementById('payment-form').addEventListener('submit', function(e) {
            const addressSelected = document.querySelector('input[name="indirizzo_id"]:checked');
            const paymentSelected = document.querySelector('input[name="payment_method"]:checked');

            if (!addressSelected) {
                e.preventDefault();
                alert('Seleziona un indirizzo di spedizione');
                return false;
            }

            if (!paymentSelected) {
                e.preventDefault();
                alert('Seleziona un metodo di pagamento');
                return false;
            }

            // Disabilita pulsante per evitare doppi click
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Elaborazione...';
        });
    </script>

<?php include __DIR__ . '/footer.php'; ?>