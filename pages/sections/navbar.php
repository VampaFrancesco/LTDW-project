<?php
// include/navbar.php
<<<<<<< Updated upstream

require_once __DIR__.'/../../include/session_config.php';
require_once __DIR__.'/../../include/session_manager.php';
require_once __DIR__.'/../../include/config.inc.php';

=======
>>>>>>> Stashed changes
?>
<nav class="navbar-custom">
    <ul class="nav-list">
        <li class="nav-item">
<<<<<<< Updated upstream
            <a href="<?php echo BASE_URL; ?>/pages/home_utente.php">
=======
            <a href="/LTDW-project/pages/home_utente.php">
>>>>>>> Stashed changes
                <i class="bi bi-house-fill"></i><span>Homepage</span>
            </a>
        </li>
        <li class="nav-item">
<<<<<<< Updated upstream
            <a href="<?php echo BASE_URL; ?>/pages/novita.php">
=======
            <a href="/novita">
>>>>>>> Stashed changes
                <i class="bi bi-stars"></i><span>Novità</span>
            </a>
        </li>
        <li class="nav-item">
<<<<<<< Updated upstream
            <a href="<?php echo BASE_URL; ?>/pages/prevendita.php">
=======
            <a href="/prevendita">
>>>>>>> Stashed changes
                <i class="bi bi-tags-fill"></i><span>Prevendita</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="/LTDW-project/pages/accessori.php">
                <i class="bi bi-shop"></i><span>Accessori</span>
            </a>
        </li>
        <li class="nav-item">
<<<<<<< Updated upstream
            <a href="<?php echo SessionManager::isLoggedIn() ? BASE_URL . '/pages/collezione.php' : BASE_URL . '/pages/auth/login.php'; ?>">
=======
            <a href="/LTDW-project/pages/collezione.php">
>>>>>>> Stashed changes
                <i class="bi bi-collection-fill"></i><span>Collezioni</span>
            </a>
        </li>
        <li class="nav-item">
<<<<<<< Updated upstream
            <a href="<?php echo BASE_URL; ?>/pages/catalogo.php">
                <i class="bi bi-box-seam"></i><span>Catalogo</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?php echo BASE_URL; ?>/pages/ordini.php">
=======
            <a href="/LTDW-project/pages/catalogo.php" class="pokeball-link">
                <div class="pokeball">
                    <div class="pokeball-top"></div>
                    <div class="pokeball-middle">
                        <div class="pokeball-button"></div>
                    </div>
                    <div class="pokeball-bottom"></div>
                </div>
                <span>Pokémon</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="/LTDW-project/pages/ordini.php">
>>>>>>> Stashed changes
                <i class="bi bi-clock-history"></i><span>Ordini</span>
            </a>
        </li>
    </ul>
<<<<<<< Updated upstream
</nav>
=======
</nav>
>>>>>>> Stashed changes
