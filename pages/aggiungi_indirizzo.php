<?php
// pages/aggiungi_indirizzo.php - VERSIONE CORRETTA
require_once __DIR__ . '/../include/session_manager.php';
$config = require_once __DIR__ . '/../include/config.inc.php';

// Richiede login
SessionManager::requireLogin();
$userId = SessionManager::get('user_id');

// Connessione PDO
try {
    $pdo = new PDO(
            "mysql:host=".$config['dbms']['localhost']['host'].";dbname=".$config['dbms']['localhost']['dbname'].";charset=utf8mb4",
            $config['dbms']['localhost']['user'],
            $config['dbms']['localhost']['passwd'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Errore di connessione al database: " . $e->getMessage());
}

// CSRF token (semplice)
if (!SessionManager::get('csrf_token')) {
    SessionManager::set('csrf_token', bin2hex(random_bytes(32)));
}
$csrfToken = SessionManager::get('csrf_token');

// Default values (per ripopolare in caso di errore)
$values = [
        'via' => '',
        'civico' => '',
        'cap' => '',
        'citta' => '',
        'provincia' => '',
        'nazione' => 'Italia'
];
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($csrfToken, $_POST['csrf_token'])) {
        $errors[] = "Token di sicurezza non valido. Riprova.";
    }

    // Raccogli input e trim
    $values['via']       = trim($_POST['via'] ?? '');
    $values['civico']    = trim($_POST['civico'] ?? '');
    $values['cap']       = trim($_POST['cap'] ?? '');
    $values['citta']     = trim($_POST['citta'] ?? '');
    $values['provincia'] = strtoupper(trim($_POST['provincia'] ?? '')); // es. MI, RM
    $values['nazione']   = trim($_POST['nazione'] ?? 'Italia');

    // Validazioni basilari
    if ($values['via'] === '')          $errors[] = "La via è obbligatoria.";
    if ($values['civico'] === '')       $errors[] = "Il numero civico è obbligatorio.";
    if ($values['citta'] === '')        $errors[] = "La città è obbligatoria.";
    if ($values['cap'] === '' || !preg_match('/^\d{5}$/', $values['cap'])) {
        $errors[] = "Il CAP deve contenere 5 cifre.";
    }
    if ($values['provincia'] === '' || !preg_match('/^[A-Z]{2}$/', $values['provincia'])) {
        $errors[] = "La provincia deve essere di 2 lettere (es. MI, RM).";
    }
    if ($values['nazione'] === '')      $errors[] = "La nazione è obbligatoria.";

    // Se tutto ok, inserisci
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO indirizzo_spedizione (fk_utente, via, civico, cap, citta, provincia, nazione)
                VALUES (:fk_utente, :via, :civico, :cap, :citta, :provincia, :nazione)
            ");
            $stmt->execute([
                    ':fk_utente' => $userId,
                    ':via'       => $values['via'],
                    ':civico'    => (int)$values['civico'], // ✅ CAST a intero
                    ':cap'       => (int)$values['cap'],    // ✅ CAST a intero
                    ':citta'     => $values['citta'],
                    ':provincia' => $values['provincia'],
                    ':nazione'   => $values['nazione'],
            ]);

            // ✅ CORREZIONE: Usa SessionManager per il messaggio flash
            SessionManager::setFlashMessage('Indirizzo aggiunto con successo!', 'success');

            // Successo: redirect al profilo (evita reinvio form)
            header("Location: ".(defined('BASE_URL') ? BASE_URL : '')."/pages/profilo.php");
            exit;
        } catch (Exception $e) {
            if ($e->getCode() == '23000') {
                $errors[] = "Questo indirizzo è già stato aggiunto.";
                error_log($errors[0]);
            } else {
                $errors[] = "Errore durante il salvataggio dell'indirizzo. Riprova.";
                error_log("Errore DB: " . $e->getMessage());
            }
        }
    }
}

include __DIR__ . '/header.php';
?>

    <main class="background-custom">
        <div class="container section">

            <h1 class="fashion_taital">Aggiungi Indirizzo</h1>

            <div style="max-width: 900px; margin: 0 auto;">

                <div style="background:#fff; padding:30px; border-radius:10px; box-shadow:0 4px 20px rgba(0,0,0,0.1); margin-bottom:20px;">
                    <h2 style="color:#333; margin-bottom:20px; border-bottom:2px solid #f0f0f0; padding-bottom:10px;">
                        🏠 Nuovo Indirizzo di Spedizione
                    </h2>

                    <?php if (!empty($errors)): ?>
                        <div style="background:#fdecea; color:#b00020; border:1px solid #f5c6cb; padding:12px 16px; border-radius:8px; margin-bottom:16px;">
                            <strong>Attenzione:</strong>
                            <ul style="margin:8px 0 0 18px;">
                                <?php foreach ($errors as $err): ?>
                                    <li><?php echo htmlspecialchars($err); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <!-- ✅ CORREZIONE: Form corretto con submit button -->
                    <form method="post" action="" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:16px; margin-bottom: 24px;">
                            <div>
                                <label style="display:block; font-weight:600; margin-bottom:6px;">Via *</label>
                                <input type="text" name="via" required
                                       value="<?php echo htmlspecialchars($values['via']); ?>"
                                       autocomplete="address-line1"
                                       style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px;">
                            </div>

                            <div>
                                <label style="display:block; font-weight:600; margin-bottom:6px;">Civico *</label>
                                <input type="number" name="civico" required min="1" max="9999"
                                       value="<?php echo htmlspecialchars($values['civico']); ?>"
                                       autocomplete="address-line2"
                                       style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px;">
                            </div>

                            <div>
                                <label style="display:block; font-weight:600; margin-bottom:6px;">CAP</label>
                                <input type="text" name="cap" pattern="[0-9]{5}" required maxlength="5"
                                       value="<?php echo htmlspecialchars($values['cap']); ?>"
                                       autocomplete="postal-code"
                                       inputmode="numeric"
                                       placeholder="es. 00100"
                                       style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px;">
                            </div>

                            <div>
                                <label style="display:block; font-weight:600; margin-bottom:6px;">Città *</label>
                                <input type="text" name="citta" required
                                       value="<?php echo htmlspecialchars($values['citta']); ?>"
                                       autocomplete="address-level2"
                                       style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px;">
                            </div>

                            <div>
                                <label style="display:block; font-weight:600; margin-bottom:6px;">Provincia (2 lettere) *</label>
                                <input type="text" name="provincia" maxlength="2" required pattern="[A-Z]{2}"
                                       value="<?php echo htmlspecialchars($values['provincia']); ?>"
                                       placeholder="es. MI, RM, NA"
                                       style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; text-transform:uppercase;">
                            </div>

                            <div>
                                <label style="display:block; font-weight:600; margin-bottom:6px;">Nazione *</label>
                                <input type="text" name="nazione" required
                                       value="<?php echo htmlspecialchars($values['nazione']); ?>"
                                       autocomplete="country-name"
                                       style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px;">
                            </div>
                        </div>

                        <!-- ✅ CORREZIONE: Bottoni coretti -->
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                            <button type="submit" class="btn-add-to-cart" style="background-color:#28a745; color:#fff; border:none; padding:15px; border-radius:8px; cursor:pointer; text-align:center; font-size: 16px; font-weight: 600;">
                                💾 Salva indirizzo
                            </button>
                            <a href="<?php echo (defined('BASE_URL') ? BASE_URL : ''); ?>/pages/profilo.php" class="btn-add-to-cart" style="text-decoration:none; background-color:#6c757d; color:#fff; display:block; padding:15px; border-radius:8px; text-align:center; font-size: 16px; font-weight: 600; line-height: 1;">
                                ⬅️ Torna al profilo
                            </a>
                        </div>
                    </form>
                </div>

                <div style="background:#fff; padding:20px; border-radius:10px; box-shadow:0 4px 20px rgba(0,0,0,0.08); margin-bottom:50px;">
                    <p style="color:#6c757d; margin:0;">
                        I campi contrassegnati con * sono obbligatori. I dati verranno usati esclusivamente per la spedizione dei tuoi ordini.
                    </p>
                </div>

            </div>
        </div>
    </main>

    <style>
        /* Responsive come le altre card */
        @media (max-width: 768px) {
            .fashion_taital { font-size: 24px !important; }
            form input { font-size: 16px; } /* migliore tastiera su mobile */
        }

        /* Hover coerenti */
        input[type="text"]:focus,
        input[type="number"]:focus {
            outline: none;
            border-color: #24B1D9;
            box-shadow: 0 0 0 3px rgba(36,177,217,0.15);
        }

        .btn-add-to-cart:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }

        /* Stile per il bottone submit */
        button[type="submit"]:hover {
            background-color: #218838 !important;
        }

        /* Validazione form */
        input:invalid {
            border-color: #dc3545;
        }

        input:valid {
            border-color: #28a745;
        }

        /* Loading state per il bottone */
        button[type="submit"]:disabled {
            background-color: #6c757d !important;
            cursor: not-allowed;
            opacity: 0.6;
        }
    </style>

    <!-- ✅ AGGIUNTO: JavaScript per migliorare UX -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const submitBtn = form.querySelector('button[type="submit"]');
            const provinciaInput = form.querySelector('input[name="provincia"]');
            const capInput = form.querySelector('input[name="cap"]');

            // Auto-maiuscolo per provincia
            provinciaInput.addEventListener('input', function() {
                this.value = this.value.toUpperCase();
            });

            // Solo numeri per CAP
            capInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '').substring(0, 5);
            });

            // Prevent double submission
            form.addEventListener('submit', function() {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '⏳ Salvataggio...';

                // Re-enable dopo 3 secondi come fallback
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '💾 Salva indirizzo';
                }, 3000);
            });

            // Validazione in tempo reale
            form.querySelectorAll('input[required]').forEach(input => {
                input.addEventListener('blur', function() {
                    if (this.value.trim() === '') {
                        this.style.borderColor = '#dc3545';
                    } else {
                        this.style.borderColor = '#28a745';
                    }
                });
            });
        });
    </script>

<?php include __DIR__ . '/footer.php'; ?>