<?php
require_once __DIR__.'/../include/session_manager.php';
require_once __DIR__.'/../include/config.inc.php';

// ✅ Controllo login senza loop
if (!SessionManager::isLoggedIn()) {
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit;
}

$userId = SessionManager::getUserId();

// ✅ CONNESSIONE DATABASE
$db_config = $config['dbms']['localhost'];
$conn = new mysqli(
    $db_config['host'],
    $db_config['user'],
    $db_config['passwd'],
    $db_config['dbname']
);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    SessionManager::setFlashMessage('Servizio temporaneamente non disponibile. Riprova più tardi.', 'danger');
    // Redirect a una pagina di errore interna, non login
    header('Location: ' . BASE_URL . '/pages/errors.php');
    exit;
}

// ✅ Recupera dati utente
$stmt = $conn->prepare(" SELECT u.nome, u.email, i.via, i.citta, i.cap, i.provincia 
    FROM utente u 
    LEFT JOIN indirizzo_spedizione i ON u.id = i.id_utente
    WHERE u.id = ?
");
if (!$stmt) {
    error_log("Query preparation failed: " . $conn->error);
    SessionManager::setFlashMessage('Errore interno. Riprova più tardi.', 'danger');
    $conn->close();
    header('Location: ' . BASE_URL . '/pages/errors.php');
    exit;
}

$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$utente = $result->fetch_assoc();

// ✅ Se form inviato → aggiorna dati
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome      = trim($_POST['nome']);
    $email     = trim($_POST['email']);
    $via       = trim($_POST['via']);
    $citta     = trim($_POST['citta']);
    $cap       = trim($_POST['cap']);
    $provincia = trim($_POST['provincia']);

    // Aggiorna utente
    $stmtUser = $conn->prepare("UPDATE utente SET nome = ?, email = ? WHERE id = ?");
    $stmtUser->bind_param("ssi", $nome, $email, $userId);
    $stmtUser->execute();

    // Controlla indirizzo
    $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM indirizzo_spedizione WHERE id_utente = ?");
    $stmtCheck->bind_param("i", $userId);
    $stmtCheck->execute();
    $stmtCheck->bind_result($count);
    $stmtCheck->fetch();
    $stmtCheck->close();

    if ($count > 0) {
        $sqlAddr = "UPDATE indirizzo_spedizione 
                    SET via = ?, citta = ?, cap = ?, provincia = ?
                    WHERE id_utente = ?";
    } else {
        $sqlAddr = "INSERT INTO indirizzo_spedizione (via, citta, cap, provincia, id_utente)
                    VALUES (?, ?, ?, ?, ?)";
    }

    $stmtAddr = $conn->prepare($sqlAddr);
    $stmtAddr->bind_param("ssssi", $via, $citta, $cap, $provincia, $userId);
    $stmtAddr->execute();

    // Aggiorna sessione
    SessionManager::set('user_nome', $nome);
    SessionManager::set('user_email', $email);

    SessionManager::setFlashMessage('Profilo aggiornato con successo!', 'success');

    // Redirect dopo salvataggio
    header("Location: profilo.php");
    exit;
}

// ✅ Ora posso includere l'header (nessun output prima)
include __DIR__ . '/header.php';
?>
<main class="background-custom">
    <div class="container section">
        <h1 class="fashion_taital">Modifica Profilo</h1>
        <div class="register-container" style="max-width: 500px;">
            <form method="POST">

                <div class="form-group">
                    <label for="nome">Nome completo</label>
                    <input type="text" id="nome" name="nome" class="form-control" 
                           value="<?php echo htmlspecialchars($utente['nome'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Indirizzo Email</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?php echo htmlspecialchars($utente['email'] ?? ''); ?>" required>
                </div>

                <hr>
                <h3 style="margin-bottom: 15px;">Indirizzo di Spedizione</h3>

                <div class="form-group">
                    <label for="via">Via</label>
                    <input type="text" id="via" name="via" class="form-control" 
                           value="<?php echo htmlspecialchars($utente['via'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="citta">Città</label>
                    <input type="text" id="citta" name="citta" class="form-control" 
                           value="<?php echo htmlspecialchars($utente['citta'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="cap">CAP</label>
                    <input type="text" id="cap" name="cap" class="form-control" 
                           value="<?php echo htmlspecialchars($utente['cap'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="provincia">Provincia</label>
                    <input type="text" id="provincia" name="provincia" class="form-control" 
                           value="<?php echo htmlspecialchars($utente['provincia'] ?? ''); ?>">
                </div>

                <button type="submit" class="btn">Salva Modifiche</button>
            </form>
        </div>
    </div>
</main>

<?php
include __DIR__ . '/footer.php';
?>
