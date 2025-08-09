<?php
require_once __DIR__.'/../include/session_manager.php';
require_once __DIR__.'/../include/config.inc.php';

// Richiedi autenticazione
SessionManager::requireLogin();

// Recupera i dati dell'ultimo ordine
$ultimo_ordine = SessionManager::get('ultimo_ordine');

// Se non ci sono dati ordine, redirect al carrello
if (!$ultimo_ordine) {
    header('Location: ' . BASE_URL . '/pages/carrello.php');
    exit();
}

// Rimuovi i dati dell'ordine dalla sessione dopo averli recuperati
SessionManager::remove('ultimo_ordine');

$user_email = SessionManager::get('user_email');
$user_nome = SessionManager::get('user_nome');

include __DIR__.'/header.php';
?>

    <main class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Card di conferma -->
                <div class="card shadow-lg">
                    <div class="card-body text-center py-5">
                        <!-- Icona di successo animata -->
                        <div class="success-animation mb-4">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
                        </div>

                        <!-- Titolo -->
                        <h2 class="mb-3">Ordine Confermato!</h2>
                        <p class="lead text-muted mb-4">
                            Grazie per il tuo ordine, <?php echo htmlspecialchars($user_nome); ?>!
                        </p>

                        <!-- Dettagli ordine -->
                        <div class="order-details bg-light rounded p-4 mb-4">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <h5 class="text-muted mb-1">Numero Ordine</h5>
                                    <p class="fs-4 fw-bold mb-0" style="color: #ED308C;">
                                        #<?php echo str_pad($ultimo_ordine['id'], 6, '0', STR_PAD_LEFT); ?>
                                    </p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <h5 class="text-muted mb-1">Totale Pagato</h5>
                                    <p class="fs-4 fw-bold mb-0" style="color: #24B1D9;">
                                        €<?php echo number_format($ultimo_ordine['totale'], 2, ',', '.'); ?>
                                    </p>
                                </div>
                            </div>

                            <hr class="my-3">

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <h5 class="text-muted mb-1">Metodo di Pagamento</h5>
                                    <p class="mb-0">
                                        <?php
                                        $metodi = [
                                                'carta_credito' => '<i class="bi bi-credit-card"></i> Carta di Credito',
                                                'paypal' => '<i class="bi bi-paypal"></i> PayPal',
                                                'bonifico' => '<i class="bi bi-bank"></i> Bonifico Bancario'
                                        ];
                                        echo $metodi[$ultimo_ordine['metodo_pagamento']] ?? 'Carta di Credito';
                                        ?>
                                    </p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <h5 class="text-muted mb-1">Stato Ordine</h5>
                                    <p class="mb-0">
                                    <span class="badge bg-warning text-dark fs-6">
                                        <i class="bi bi-clock"></i> In elaborazione
                                    </span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Informazioni aggiuntive -->
                        <div class="alert alert-info text-start" role="alert">
                            <h6 class="alert-heading">
                                <i class="bi bi-info-circle"></i> Cosa succede ora?
                            </h6>
                            <ul class="mb-0">
                                <li>Riceverai una email di conferma all'indirizzo <strong><?php echo htmlspecialchars($user_email); ?></strong></li>
                                <li>Il nostro team preparerà il tuo ordine entro 24-48 ore</li>
                                <li>Riceverai il numero di tracking appena l'ordine sarà spedito</li>
                                <li>Puoi monitorare lo stato del tuo ordine nella sezione "I miei ordini"</li>
                            </ul>
                        </div>

                        <!-- Timeline dello stato ordine -->
                        <div class="order-timeline mt-4 mb-4">
                            <div class="timeline-step active">
                                <div class="timeline-icon">
                                    <i class="bi bi-check"></i>
                                </div>
                                <div class="timeline-label">Ordine Ricevuto</div>
                            </div>
                            <div class="timeline-step">
                                <div class="timeline-icon">
                                    <i class="bi bi-box"></i>
                                </div>
                                <div class="timeline-label">In Preparazione</div>
                            </div>
                            <div class="timeline-step">
                                <div class="timeline-icon">
                                    <i class="bi bi-truck"></i>
                                </div>
                                <div class="timeline-label">Spedito</div>
                            </div>
                            <div class="timeline-step">
                                <div class="timeline-icon">
                                    <i class="bi bi-house-check"></i>
                                </div>
                                <div class="timeline-label">Consegnato</div>
                            </div>
                        </div>

                        <!-- Pulsanti azione -->
                        <div class="d-grid gap-2 d-md-block">
                            <a href="<?php echo BASE_URL; ?>/pages/ordini.php" class="btn btn-primary btn-lg">
                                <i class="bi bi-list-check"></i> Visualizza Ordini
                            </a>
                            <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-outline-secondary btn-lg">
                                <i class="bi bi-house"></i> Torna alla Home
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Card promozionale -->
                <div class="card mt-4 border-0 bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white text-center py-4">
                        <h4 class="mb-3">
                            <i class="bi bi-gift"></i> Ottieni il 10% di sconto!
                        </h4>
                        <p class="mb-3">
                            Sul tuo prossimo ordine usa il codice:
                        </p>
                        <div class="coupon-code bg-white text-dark rounded p-2 d-inline-block">
                            <h3 class="mb-0 font-monospace">BOXOMNIA10</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <style>
        /* Animazione successo */
        @keyframes checkmark {
            0% {
                transform: scale(0);
                opacity: 0;
            }
            50% {
                transform: scale(1.2);
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .success-animation i {
            animation: checkmark 0.8s ease-out;
        }

        /* Timeline ordine */
        .order-timeline {
            display: flex;
            justify-content: space-between;
            position: relative;
            padding: 0 20px;
        }

        .order-timeline::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 10%;
            right: 10%;
            height: 2px;
            background-color: #e9ecef;
            z-index: 0;
        }

        .timeline-step {
            position: relative;
            text-align: center;
            flex: 1;
            z-index: 1;
        }

        .timeline-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #e9ecef;
            color: #6c757d;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            border: 3px solid #fff;
        }

        .timeline-step.active .timeline-icon {
            background-color: #28a745;
            color: white;
        }

        .timeline-label {
            font-size: 0.875rem;
            color: #6c757d;
        }

        .timeline-step.active .timeline-label {
            color: #28a745;
            font-weight: 600;
        }

        /* Effetto hover sui pulsanti */
        .btn {
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        /* Coupon code */
        .coupon-code {
            border: 2px dashed #667eea;
            position: relative;
            overflow: hidden;
        }

        .coupon-code::before {
            content: '📋 Clicca per copiare';
            position: absolute;
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
            background-color: #333;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.75rem;
            opacity: 0;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .coupon-code:hover {
            cursor: pointer;
            background-color: #f8f9fa !important;
        }

        .coupon-code:hover::before {
            opacity: 1;
            top: -35px;
        }
    </style>

    <script>
        // Copia codice sconto al click
        document.querySelector('.coupon-code').addEventListener('click', function() {
            const code = 'BOXOMNIA10';
            navigator.clipboard.writeText(code).then(function() {
                // Mostra notifica di successo
                const originalContent = document.querySelector('.coupon-code h3').innerHTML;
                document.querySelector('.coupon-code h3').innerHTML = '✓ Copiato!';
                setTimeout(function() {
                    document.querySelector('.coupon-code h3').innerHTML = originalContent;
                }, 2000);
            });
        });

        // Confetti animation (opzionale)
        function createConfetti() {
            const colors = ['#667eea', '#764ba2', '#28a745', '#ffc107', '#dc3545'];
            const confettiCount = 50;
            const container = document.querySelector('.success-animation');

            for (let i = 0; i < confettiCount; i++) {
                const confetti = document.createElement('div');
                confetti.style.position = 'absolute';
                confetti.style.width = '10px';
                confetti.style.height = '10px';
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.left = Math.random() * 100 + '%';
                confetti.style.top = '-10px';
                confetti.style.opacity = Math.random();
                confetti.style.transform = 'rotate(' + Math.random() * 360 + 'deg)';
                confetti.style.transition = 'all 1s ease-out';
                container.appendChild(confetti);

                setTimeout(() => {
                    confetti.style.top = '100%';
                    confetti.style.opacity = '0';
                    confetti.style.transform = 'rotate(' + Math.random() * 720 + 'deg)';
                }, 10);

                setTimeout(() => {
                    confetti.remove();
                }, 1000);
            }
        }

        // Attiva confetti all'apertura della pagina
        window.addEventListener('load', createConfetti);
    </script>

<?php include __DIR__.'/footer.php'; ?>