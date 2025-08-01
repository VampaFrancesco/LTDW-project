<?php
// pages/auth/login.php
$hideNav = false;
include __DIR__ . '/../header.php';

// Cattura l'array di configurazione restituito da config.inc.php
$db_config = require_once __DIR__ . '/../../include/config.inc.php';

/* $error_message = '';

// Controlla se il modulo è stato inviato
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error_message = "Per favore, inserisci sia l'email che la password.";
    } else {
        // Connessione al database usando i valori dell'array $db_config
        $conn = new mysqli($db_config['host'], $db_config['user'], $db_config['passwd'], $db_config['dbname']);

        // Controlla la connessione
        if ($conn->connect_error) {
            die("Connessione al database fallita: " . $conn->connect_error);
        }

        // Prepara la query SQL per prevenire SQL injection
        $stmt = $conn->prepare("SELECT id_utente, password FROM utente WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($id_utente, $hashed_password);
            $stmt->fetch();

            // Verifica la password
            if (password_verify($password, $hashed_password)) {
                // Password corretta, imposta le variabili di sessione e reindirizza
                $_SESSION['user_id'] = $id_utente;
                $_SESSION['user_email'] = $email;

                // Reindirizza alla pagina principale o alla dashboard
                header('Location: ../../index.php');
                exit();
            } else {
                $error_message = "Email o password non validi.";
            }
        } else {
            $error_message = "Email o password non validi.";
        }

        $stmt->close();
        $conn->close();
    }
}*/
?>


<main class="background-custom">


    <div class="login-container">

        <h2>Accedi al tuo account</h2>

        <?php if (!empty($error_message)): ?>
            <p style="color: red;"><?php echo $error_message; ?></p>
        <?php endif; ?>

        <form action="#" method="POST">
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary">Accedi</button>
        </form>
        <?php if (isset($_GET['registered'])): ?>
            <div class="floating-alert background-custom">
                Registrazione avvenuta con successo!
            </div>
        <?php endif; ?>
        <p>Non hai un account? <a href="register.php">Registrati qui</a></p>
    </div>
</main>


<?php include __DIR__ . '/../footer.php';
?>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const alert = document.querySelector('.floating-alert');
        if (!alert) return;
        alert.addEventListener('animationend', () => {
            alert.remove();
        });
    });
</script>

