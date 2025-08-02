<?php
$hideNav = false;
include __DIR__ . '/../header.php';

$error_message = '';
if (isset($_GET['error'])) {
    $error_message = htmlspecialchars($_GET['error']);
}
?>

<div class="background-custom">

    <div class="login-container">

        <h2>Accedi al tuo account</h2>

        <?php if (!empty($error_message)): ?>
            <p style="color: red;"><?php echo $error_message; ?></p>
        <?php endif; ?>

        <form action="../../action/login_action.php" method="POST">
            <div class="form-group" >
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
</div>

<?php include __DIR__ . '/../footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const alert = document.querySelector('.floating-alert');
        if (!alert) return;
        alert.addEventListener('animationend', () => {
            alert.remove();
        });
    });
</script>