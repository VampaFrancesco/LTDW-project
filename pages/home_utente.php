<?php


include 'header.php';

// Verifica se l'utente è loggato
// Se non c'è user_id nella sessione, reindirizza alla pagina di login
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/pages/auth/login.php');
    exit();
}

// Recupera il nome dell'utente dalla sessione.
$nome_utente = $_SESSION['username'] ?? 'Utente';

?>

<main class="background-custom">
    <div class="container">
        <h1 class="fashion_taital mb-5">Bentornato, <?php echo htmlspecialchars($nome_utente); ?>!</h1>
        <p class="section-intro-text text-center">Esplora le ultime novità, la tua collezione e le offerte esclusive pensate per te.</p>

        <div class="section">
            <?php include 'sections/slider_prodotti.php'; ?>
        </div>

        <div class="section">
            <?php include 'sections/piu_venduti.php'; ?>
        </div>

        <div class="section">
            <?php include 'sections/scopri_anche.php'; ?>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>