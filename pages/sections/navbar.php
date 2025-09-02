<?php
// include/navbar.php
require_once __DIR__.'/../../include/session_config.php';
require_once __DIR__.'/../../include/session_manager.php';
require_once __DIR__.'/../../include/config.inc.php';
?>
<nav class="navbar-custom">
    <ul class="nav-list">
        <li class="nav-item">
            <a href="<?php echo BASE_URL; ?>/pages/accessori.php">
<img src="<?php echo BASE_URL; ?>/images/shield.png"><span>Accessori</span>
            </a>
        </li>
        <li class="nav-item dropdown">
            <a href="#" class="pokeball-link" role="button" aria-expanded="false">
                <div class="pokeball">
                    <div class="pokeball-top"></div>
                    <div class="pokeball-middle">
                        <div class="pokeball-button"></div>
                    </div>
                    <div class="pokeball-bottom"></div>
                </div>
                <span>Pokémon</span>
            </a>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/pages/pokemon_mystery_boxes.php">Mystery Box</a></li>
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/pages/pokemon_funko_pops.php">Funko Pop</a></li>
            </ul>
        </li>
        <li class="nav-item dropdown">
            <a href="#" class="yugioh-link" role="button" aria-expanded="false">
                <div class="yugioh-icon-container">
            <span class="yugioh-card-icon">
            <span class="yugioh-card-element"></span>
            </span>
                </div>
            <span>Yu-Gi-Oh!</span>
            </a>
            <ul class="dropdown-menu">
               <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/pages/yugioh_mystery_boxes.php">Mystery Box</a></li>
               <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/pages/yugioh_funko_pops.php">Funko Pop</a></li>
    </ul>
        <li class="nav-item">
            <a href="<?php echo BASE_URL; ?>/pages/index.php">
                <i class="bi bi-house-fill"></i><span>Homepage</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?php echo BASE_URL; ?>/pages/classifica.php">
                <i class="bi bi-clock-history"></i><span>Classifica</span>
            </a>
        </li>
        <li class="nav-item">
            <a  href="<?php echo  BASE_URL; ?>/pages/scambio.php">
                <i class="bi bi-arrow-left-right"></i> Scambio
           </a>
        </li>
        <li class="nav-item">
            <a href="<?php echo SessionManager::isLoggedIn() ? BASE_URL . '/pages/collezione.php' : BASE_URL . '/pages/auth/login.php'; ?>">
                <i class="bi bi-collection-fill"></i><span>Collezioni</span>
            </a>
        </li>
</li>
        </li>
    </ul>
</nav>