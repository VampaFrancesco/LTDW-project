<?php
$hideNav = false;
include __DIR__ . '/../header.php';
$db_config = require_once __DIR__ . '/../../include/config.inc.php';
?>

<main class="background-custom">
        <div class="register-container">
            <h2> Registra il tuo account </h2>

            <?php if (!empty($error_message)): ?>
                <p style="color: red;"><?php echo $error_message; ?></p>
            <?php endif; ?>

            <form action="../../action/register_action.php" method="POST" id="registerForm">
                <div class="form-group">
                    <label for="nome">Nome:</label>
                    <input type="text" id="nome" name="nome" required>
                </div>
                <div class="form-group">
                    <label for="cognome">Cognome:</label>
                    <input type="text" id="cognome" name="cognome" required>
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary">Registrati</button>
            </form>
            <p>Hai già un account? <a href="login.php">Accedi qui</a></p>
        </div>
</main>
</>
<?php include __DIR__ . '/../footer.php';
?>