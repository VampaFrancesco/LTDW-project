<?php
// 1. Includi il Session Manager all'inizio e header
include 'header.php';
require_once __DIR__.'/../include/session_manager.php';

// 3. Verifica autenticazione usando il Session Manager
if (!SessionManager::get('user_logged_in')) {
    $redirectUrl =  '/LTDW-project/pages/auth/login.php';
    header('Location: ' . $redirectUrl);
    exit();
}

// 4. Recupera i dati utente in modo sicuro
$nome_utente = SessionManager::get('user_name', 'Utente');
$cognome_utente = SessionManager::get('user_surname', '');

// Costruisci il nome completo (nome + cognome se esiste)
$nome_completo = trim($nome_utente . ' ' . $cognome_utente) ?: 'Utente';


?>

    <main class="background-custom">
        <div class="container">
            <!-- 5. Mostra il nome utente con escape XSS -->
            <h1 class="fashion_taital mb-5">Bentornato, <?php echo htmlspecialchars($nome_completo) ?>!</h1>
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