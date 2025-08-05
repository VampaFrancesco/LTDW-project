<?php
// include/navbar.php
require_once __DIR__.'/../../include/session_config.php';
require_once __DIR__.'/../../include/session_manager.php';
require_once __DIR__.'/../../include/config.inc.php';
?>
<nav class="navbar-custom">
    <ul class="nav-list">
        <li class="nav-item">
            <a href="<?php echo BASE_URL; ?>/pages/index.php">
                <i class="bi bi-house-fill"></i><span>Homepage</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?php echo BASE_URL; ?>/pages/accessori.php">
                <i class="bi bi-shop"></i><span>Accessori</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?php echo SessionManager::isLoggedIn() ? BASE_URL . '/pages/collezione.php' : BASE_URL . '/pages/auth/login.php'; ?>">
                <i class="bi bi-collection-fill"></i><span>Collezioni</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?php echo BASE_URL; ?>/pages/ordini.php">
                <i class="bi bi-clock-history"></i><span>Ordini</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?php echo BASE_URL; ?>/pages/ordini.php">
                <i class="bi bi-peace"></i><span>Ordini</span>
            </a>
        </li>
    </ul>
</nav>