<?php
require_once __DIR__.'/../include/session_manager.php';
$config = require_once __DIR__.'/../include/config.inc.php';

// Connessione al database usando la configurazione
try {
    $pdo = new PDO(
        "mysql:host=" . $config['dbms']['localhost']['host'] . ";dbname=" . $config['dbms']['localhost']['dbname'] . ";charset=utf8mb4", 
        $config['dbms']['localhost']['user'], 
        $config['dbms']['localhost']['passwd']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Errore di connessione al database: " . $e->getMessage());
}

include __DIR__ . '/header.php';

// Richiede login, reindirizza se non loggato
SessionManager::requireLogin();

$message = '';
$messageType = '';

// Recupera l'ID utente dalla sessione
$userId = SessionManager::get('user_id');

// Recupera i dati attuali dell'utente dal database
try {
    $stmt = $pdo->prepare("SELECT nome, cognome, email, telefono FROM utente WHERE id_utente = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData) {
        throw new Exception("Utente non trovato");
    }
} catch (Exception $e) {
    $message = "Errore nel caricamento dei dati: " . $e->getMessage();
    $messageType = 'error';
    $userData = [
        'nome' => '',
        'cognome' => '',
        'email' => '',
        'telefono' => ''
    ];
}

// Gestione del form di modifica
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $cognome = trim($_POST['cognome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $nuovaPassword = trim($_POST['nuova_password'] ?? '');
    $confermaPassword = trim($_POST['conferma_password'] ?? '');
    $passwordAttuale = trim($_POST['password_attuale'] ?? '');
    
    $errors = [];
    
    // Validazioni
    if (empty($nome)) {
        $errors[] = "Il nome è obbligatorio";
    }
    
    if (empty($cognome)) {
        $errors[] = "Il cognome è obbligatorio";
    }
    
    if (empty($email)) {
        $errors[] = "L'email è obbligatoria";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'email non è valida";
    }
    
    // Verifica se l'email è già in uso da un altro utente
    if (!empty($email) && $email !== $userData['email']) {
        try {
            $stmt = $pdo->prepare("SELECT id_utente FROM utente WHERE email = ? AND id_utente != ?");
            $stmt->execute([$email, $userId]);
            if ($stmt->rowCount() > 0) {
                $errors[] = "L'email è già utilizzata da un altro utente";
            }
        } catch (Exception $e) {
            $errors[] = "Errore nella verifica dell'email";
        }
    }
    
    // Validazione telefono (opzionale)
    if (!empty($telefono) && !preg_match('/^[0-9\+\-\s\(\)]+$/', $telefono)) {
        $errors[] = "Il numero di telefono non è valido";
    }
    
    // Se viene inserita una nuova password, validarla
    $cambiaPassword = false;
    if (!empty($nuovaPassword) || !empty($confermaPassword)) {
        if (empty($passwordAttuale)) {
            $errors[] = "Per cambiare la password, inserisci la password attuale";
        } else {
            // Verifica la password attuale
            try {
                $stmt = $pdo->prepare("SELECT password FROM utente WHERE id_utente = ?");
                $stmt->execute([$userId]);
                $currentHash = $stmt->fetchColumn();
                
                if (!password_verify($passwordAttuale, $currentHash)) {
                    $errors[] = "La password attuale non è corretta";
                }
            } catch (Exception $e) {
                $errors[] = "Errore nella verifica della password";
            }
        }
        
        if (strlen($nuovaPassword) < 6) {
            $errors[] = "La nuova password deve essere di almeno 6 caratteri";
        }
        
        if ($nuovaPassword !== $confermaPassword) {
            $errors[] = "Le password non corrispondono";
        }
        
        $cambiaPassword = true;
    }
    
    // Se non ci sono errori, aggiorna i dati
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            if ($cambiaPassword) {
                // Aggiorna con la nuova password
                $hashedPassword = password_hash($nuovaPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE utente SET nome = ?, cognome = ?, email = ?, telefono = ?, password = ? WHERE id_utente = ?");
                $stmt->execute([$nome, $cognome, $email, $telefono ?: null, $hashedPassword, $userId]);
            } else {
                // Aggiorna senza cambiare la password
                $stmt = $pdo->prepare("UPDATE utente SET nome = ?, cognome = ?, email = ?, telefono = ? WHERE id_utente = ?");
                $stmt->execute([$nome, $cognome, $email, $telefono ?: null, $userId]);
            }
            
            $pdo->commit();
            
            // Aggiorna i dati in sessione
            SessionManager::set('user_nome', $nome);
            SessionManager::set('user_cognome', $cognome);
            SessionManager::set('user_email', $email);
            
            $message = "Profilo aggiornato con successo!";
            $messageType = 'success';
            
            // Aggiorna i dati per la visualizzazione
            $userData = [
                'nome' => $nome,
                'cognome' => $cognome,
                'email' => $email,
                'telefono' => $telefono
            ];
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Errore nell'aggiornamento: " . $e->getMessage();
            $messageType = 'error';
        }
    } else {
        $message = implode('<br>', $errors);
        $messageType = 'error';
    }
}

?>

<main class="background-custom">
    <div class="container section">
        
        <h1 class="fashion_taital">Modifica Profilo</h1>

        <div style="max-width: 600px; margin: 0 auto;">
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?>" 
                     style="margin-bottom: 20px; padding: 15px; border-radius: 5px; 
                            background-color: <?php echo $messageType === 'success' ? '#d4edda' : '#f8d7da'; ?>; 
                            color: <?php echo $messageType === 'success' ? '#155724' : '#721c24'; ?>; 
                            border: 1px solid <?php echo $messageType === 'success' ? '#c3e6cb' : '#f5c6cb'; ?>;">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div style="background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
                
                <form method="POST" action="">
                    
                    <!-- Dati personali -->
                    <h3 style="margin-bottom: 20px; color: #333;">Dati Personali</h3>
                    
                    <div style="margin-bottom: 20px;">
                        <label for="nome" style="display: block; margin-bottom: 5px; font-weight: bold; color: #333;">Nome *</label>
                        <input type="text" 
                               id="nome" 
                               name="nome" 
                               value="<?php echo htmlspecialchars($userData['nome']); ?>" 
                               required
                               style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 5px; font-size: 16px;">
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label for="cognome" style="display: block; margin-bottom: 5px; font-weight: bold; color: #333;">Cognome *</label>
                        <input type="text" 
                               id="cognome" 
                               name="cognome" 
                               value="<?php echo htmlspecialchars($userData['cognome']); ?>" 
                               required
                               style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 5px; font-size: 16px;">
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label for="email" 
                            style="display: block; margin-bottom: 5px; font-weight: bold; color: #333;">
                            Email *
                        </label>
                        <input type="email" 
                            id="email" 
                            name="email" 
                            value="<?php echo htmlspecialchars($userData['email']); ?>" 
                            required
                            style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 5px; font-size: 16px; box-sizing: border-box; overflow: hidden; text-overflow: ellipsis;">
                    </div>

                    
                    <div style="margin-bottom: 30px;">
                        <label for="telefono" style="display: block; margin-bottom: 5px; font-weight: bold; color: #333;">Telefono</label>
                        <input type="tel" 
                               id="telefono" 
                               name="telefono" 
                               value="<?php echo htmlspecialchars($userData['telefono'] ?? ''); ?>" 
                               placeholder="Opzionale"
                               style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 5px; font-size: 16px;">
                    </div>
                    
                    <!-- Cambio password -->
                    <hr style="margin: 30px 0; border: none; border-top: 1px solid #eee;">
                    
                    <h3 style="margin-bottom: 20px; color: #333;">Cambio Password (opzionale)</h3>
                    <p style="color: #666; margin-bottom: 20px; font-size: 14px;">Lascia vuoto se non vuoi cambiare la password</p>
                    
                    <div style="margin-bottom: 20px;">
                        <label for="password_attuale" style="display: block; margin-bottom: 5px; font-weight: bold; color: #333;">Password Attuale</label>
                        <input type="password" 
                               id="password_attuale" 
                               name="password_attuale" 
                               style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 5px; font-size: 16px;">
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label for="nuova_password" style="display: block; margin-bottom: 5px; font-weight: bold; color: #333;">Nuova Password</label>
                        <input type="password" 
                               id="nuova_password" 
                               name="nuova_password" 
                               minlength="6"
                               style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 5px; font-size: 16px;">
                        <small style="color: #666;">Minimo 6 caratteri</small>
                    </div>
                    
                    <div style="margin-bottom: 30px;">
                        <label for="conferma_password" style="display: block; margin-bottom: 5px; font-weight: bold; color: #333;">Conferma Nuova Password</label>
                        <input type="password" 
                               id="conferma_password" 
                               name="conferma_password" 
                               minlength="6"
                               style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 5px; font-size: 16px;">
                    </div>
                    
                    <!-- Pulsanti -->
                    <div style="display: flex; flex-wrap: wrap; gap: 15px; justify-content: center;">
                        <button type="submit" class="btn-add-to-cart" style="border: none; cursor: pointer;">
                            💾 Salva Modifiche
                        </button>
                        <a href="profilo.php" class="btn-add-to-cart" style="text-decoration: none; background-color: #6c757d;">
                            ← Torna al Profilo
                        </a>
                    </div>
                    
                </form>
                
            </div>
        </div>

    </div>
</main>

<script>
// Validazione lato client per le password
document.addEventListener('DOMContentLoaded', function() {
    const nuovaPassword = document.getElementById('nuova_password');
    const confermaPassword = document.getElementById('conferma_password');
    const passwordAttuale = document.getElementById('password_attuale');
    
    function checkPasswordsMatch() {
        if (nuovaPassword.value !== confermaPassword.value) {
            confermaPassword.setCustomValidity('Le password non corrispondono');
        } else {
            confermaPassword.setCustomValidity('');
        }
    }
    
    function checkPasswordRequired() {
        const hasNewPassword = nuovaPassword.value.length > 0 || confermaPassword.value.length > 0;
        
        if (hasNewPassword) {
            passwordAttuale.setAttribute('required', 'required');
            nuovaPassword.setAttribute('required', 'required');
            confermaPassword.setAttribute('required', 'required');
        } else {
            passwordAttuale.removeAttribute('required');
            nuovaPassword.removeAttribute('required');
            confermaPassword.removeAttribute('required');
        }
    }
    
    nuovaPassword.addEventListener('input', function() {
        checkPasswordsMatch();
        checkPasswordRequired();
    });
    
    confermaPassword.addEventListener('input', function() {
        checkPasswordsMatch();
        checkPasswordRequired();
    });
    
    passwordAttuale.addEventListener('input', checkPasswordRequired);
});
</script>

<?php
include __DIR__ . '/footer.php';
?>