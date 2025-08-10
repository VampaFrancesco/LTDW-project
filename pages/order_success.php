<?php
// pages/order_success.php
require_once __DIR__ . '/../include/session_manager.php';
require_once __DIR__ . '/../include/config.inc.php';

// Richiedi autenticazione
SessionManager::requireLogin();

$flash_message = SessionManager::getFlashMessage();
$total = $_GET['total'] ?? '0.00';
$order_number = 'ORD' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);

include __DIR__ . '/header.php';
?>

    <style>
        .success-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 0;
        }

        .success-card {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 600px;
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

        .success-icon {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #28a745, #20c997);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.4);
            }
            70% {
                box-shadow: 0 0 0 30px rgba(40, 167, 69, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(40, 167, 69, 0);
            }
        }

        .success-icon i {
            font-size: 4rem;
            color: white;
        }

        .success-title {
            color: #28a745;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .success-subtitle {
            color: #6c757d;
            font-size: 1.2rem;
            margin-bottom: 2rem;
        }

        .order-details {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 2rem 0;
            border-left: 4px solid #28a745;
        }

        .order-detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e9ecef;
        }

        .order-detail-item:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 1.1rem;
            color: #28a745;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 2rem;
        }

        .btn-primary-gradient {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary-gradient:hover {
            background: linear-gradient(135deg, #0056b3, #004085);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 123, 255, 0.3);
            color: white;
            text-decoration: none;
        }

        .btn-outline-gradient {
            border: 2px solid #28a745;
            color: #28a745;
            background: transparent;
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-outline-gradient:hover {
            background: #28a745;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(40, 167, 69, 0.3);
            text-decoration: none;
        }

        .info-box {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 2rem;
            text-align: left;
        }

        .info-box h6 {
            color: #1976d2;
            margin-bottom: 0.5rem;
        }

        .info-box ul {
            margin: 0;
            padding-left: 1.2rem;
        }

        .info-box li {
            color: #424242;
            margin-bottom: 0.25rem;
        }

        @media (max-width: 768px) {
            .success-card {
                margin: 1rem;
                padding: 2rem 1.5rem;
            }

            .success-title {
                font-size: 2rem;
            }

            .action-buttons {
                flex-direction: column;
                align-items: center;
            }

            .btn-primary-gradient,
            .btn-outline-gradient {
                width: 100%;
                justify-content: center;
            }
        }
    </style>

    <main class="success-container">
        <div class="success-card">
            <div class="success-icon">
                <i class="bi bi-check-lg"></i>
            </div>

            <h1 class="success-title">Ordine Completato!</h1>
            <p class="success-subtitle">
                Grazie per aver scelto Box Omnia! Il tuo ordine è stato ricevuto e sarà processato a breve.
            </p>

            <div class="order-details">
                <h5 class="text-center mb-3">
                    <i class="bi bi-receipt me-2"></i>Dettagli Ordine
                </h5>

                <div class="order-detail-item">
                    <span><i class="bi bi-hash me-2"></i>Numero Ordine:</span>
                    <span class="fw-bold"><?php echo htmlspecialchars($order_number); ?></span>
                </div>

                <div class="order-detail-item">
                    <span><i class="bi bi-calendar me-2"></i>Data:</span>
                    <span><?php echo date('d/m/Y H:i'); ?></span>
                </div>

                <div class="order-detail-item">
                    <span><i class="bi bi-person me-2"></i>Cliente:</span>
                    <span><?php echo htmlspecialchars(SessionManager::get('user_nome') . ' ' . SessionManager::get('user_cognome')); ?></span>
                </div>

                <div class="order-detail-item">
                    <span><i class="bi bi-currency-euro me-2"></i>Totale Pagato:</span>
                    <span>€<?php echo htmlspecialchars($total); ?></span>
                </div>
            </div>

            <div class="info-box">
                <h6><i class="bi bi-info-circle me-2"></i>Cosa succede ora?</h6>
                <ul>
                    <li>Riceverai una email di conferma entro pochi minuti</li>
                    <li>Il tuo ordine sarà processato entro 24-48 ore</li>
                    <li>Ti invieremo il codice di tracking quando l'ordine sarà spedito</li>
                    <li>Puoi monitorare lo stato del tuo ordine nella sezione "I miei ordini"</li>
                </ul>
            </div>

            <div class="action-buttons">
                <a href="<?php echo BASE_URL; ?>/pages/ordini.php" class="btn-primary-gradient">
                    <i class="bi bi-bag-check"></i>Visualizza i miei ordini
                </a>

                <a href="<?php echo BASE_URL; ?>/pages/pokémon.php" class="btn-outline-gradient">
                    <i class="bi bi-arrow-left"></i>Continua lo shopping
                </a>
            </div>

            <div class="text-center mt-4">
                <p class="text-muted mb-0">
                    <i class="bi bi-headset me-2"></i>
                    Hai domande? Contatta il nostro
                    <a href="mailto:info@boxomnia.it" class="text-decoration-none">servizio clienti</a>
                </p>
            </div>
        </div>
    </main>

    <script>
        // Aggiungi un po' di interattività
        document.addEventListener('DOMContentLoaded', function() {
            // Confetti effect (opzionale)
            setTimeout(function() {
                // Qui potresti aggiungere un effetto confetti se hai una libreria
                console.log('Ordine completato con successo!');
            }, 500);

            // Scroll smooth verso l'alto se necessario
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        // Prevenire il back button per evitare doppi ordini
        history.replaceState(null, null, location.href);
        window.addEventListener('popstate', function() {
            history.pushState(null, null, location.href);
        });
    </script>

<?php include __DIR__ . '/footer.php'; ?>