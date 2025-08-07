<?php
// crea_admin.php
// Pagina per creare nuovi amministratori

// Controllo accesso admin
require_once __DIR__ . '/admin_check.php';
require_once __DIR__ . '/../../include/config.inc.php';

// Recupera dati admin corrente
$adminName = SessionManager::get('user_nome', 'Admin');
$currentUserId = SessionManager::get('user_id');

// Recupera messaggi flash
$flash_message = SessionManager::getFlashMessage();
$form_data = SessionManager::get('admin_form_data', []);
SessionManager::remove('admin_form_data');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Crea Amministratore - Box Omnia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">

<!-- Header / Navbar -->
<header class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">
            <i class="bi bi-speedometer2"></i> Dashboard Admin Box Omnia
        </a>
        <div class="navbar-nav ms-auto">
            <span class="nav-link">
                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($adminName); ?>
            </span>
            <a class="nav-link" href="<?php echo BASE_URL; ?>/pages/auth/logout.php">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>
</header>

<div class="container-fluid flex-grow-1">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-12 col-md-3 col-lg-2 d-md-block bg-light sidebar">
            <div class="position-sticky pt-3">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="gestione_utenti.php">
                            <i class="bi bi-people"></i> Gestione Utenti
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="gestione_prodotti.php">
                            <i class="bi bi-box"></i> Gestione Prodotti
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="gestione_ordini.php">
                            <i class="bi bi-cart"></i> Gestione Ordini
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="report.php">
                            <i class="bi bi-graph-up"></i> Report
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="#">
                            <i class="bi bi-shield-plus"></i> Crea Admin
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="col-12 col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="bi bi-shield-plus"></i> Crea Nuovo Amministratore</h1>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Torna alla Dashboard
                </a>
            </div>

            <!-- Mostra messaggi flash -->
            <?php if ($flash_message): ?>
                <div class="alert alert-<?php echo htmlspecialchars($flash_message['type']); ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($flash_message['content']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Informazioni Amministratore</h5>
                        </div>
                        <div class="card-body">
                            <form action="<?php echo BASE_URL; ?>/pages/dashboard/crea_admin_action.php" method="POST">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="nome" class="form-label">Nome *</label>
                                        <input type="text"
                                               class="form-control"
                                               id="nome"
                                               name="nome"
                                               value="<?php echo htmlspecialchars($form_data['nome'] ?? ''); ?>"
                                               required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="cognome" class="form-label">Cognome *</label>
                                        <input type="text"
                                               class="form-control"
                                               id="cognome"
                                               name="cognome"
                                               value="<?php echo htmlspecialchars($form_data['cognome'] ?? ''); ?>"
                                               required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email"
                                           class="form-control"
                                           id="email"
                                           name="email"
                                           value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>"
                                           placeholder="admin@boxomnia.it"
                                           required>
                                    <div class="form-text">
                                        <i class="bi bi-info-circle"></i> L'email deve terminare con @boxomnia.it
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="password" class="form-label">Password *</label>
                                    <input type="password"
                                           class="form-control"
                                           id="password"
                                           name="password"
                                           required>
                                    <div class="form-text">Minimo 8 caratteri, deve contenere maiuscole, minuscole e numeri</div>
                                </div>

                                <div class="mb-3">
                                    <label for="conferma_password" class="form-label">Conferma Password *</label>
                                    <input type="password"
                                           class="form-control"
                                           id="conferma_password"
                                           name="conferma_password"
                                           required>
                                </div>

                                <div class="mb-4">
                                    <label for="livello_admin" class="form-label">Livello Amministratore</label>
                                    <select class="form-select" id="livello_admin" name="livello_admin">
                                        <option value="admin" <?php echo ($form_data['livello_admin'] ?? '') === 'admin' ? 'selected' : ''; ?>>
                                            Admin Standard
                                        </option>
                                        <option value="super_admin" <?php echo ($form_data['livello_admin'] ?? '') === 'super_admin' ? 'selected' : ''; ?>>
                                            Super Admin
                                        </option>
                                    </select>
                                    <div class="form-text">
                                        Admin Standard: accesso limitato | Super Admin: accesso completo
                                    </div>
                                </div>

                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <button type="reset" class="btn btn-outline-secondary me-md-2">
                                        <i class="bi bi-arrow-clockwise"></i> Reset
                                    </button>
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-person-plus"></i> Crea Amministratore
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-info-circle"></i> Informazioni</h6>
                        </div>
                        <div class="card-body">
                            <h6>Requisiti per Admin:</h6>
                            <ul class="small">
                                <li>Email con dominio @boxomnia.it</li>
                                <li>Password sicura (min 8 caratteri)</li>
                                <li>Tutti i campi obbligatori compilati</li>
                            </ul>

                            <hr>

                            <h6>Livelli Admin:</h6>
                            <ul class="small">
                                <li><strong>Admin Standard:</strong> Gestione prodotti, ordini, utenti</li>
                                <li><strong>Super Admin:</strong> Tutte le funzioni + creazione admin</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Lista admin esistenti -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-people"></i> Admin Esistenti</h6>
                        </div>
                        <div class="card-body">
                            <?php
                            // Connetti al database per mostrare admin esistenti
                            $db_config = $config['dbms']['localhost'];
                            $conn = new mysqli($db_config['host'], $db_config['user'], $db_config['passwd'], $db_config['dbname']);

                            $result = $conn->query("
                                SELECT u.nome, u.cognome, u.email, a.livello_admin, a.data_creazione
                                FROM admin a 
                                JOIN utente u ON a.fk_utente = u.id_utente 
                                ORDER BY a.data_creazione DESC
                            ");

                            if ($result && $result->num_rows > 0):
                                ?>
                                <div class="small">
                                    <?php while ($admin = $result->fetch_assoc()): ?>
                                        <div class="mb-2 p-2 border rounded">
                                            <strong><?php echo htmlspecialchars($admin['nome'] . ' ' . $admin['cognome']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($admin['email']); ?></small><br>
                                            <small>
                                                <span class="badge bg-<?php echo $admin['livello_admin'] === 'super_admin' ? 'danger' : 'primary'; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $admin['livello_admin'])); ?>
                                                </span>
                                            </small>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <p class="small text-muted">Nessun altro amministratore trovato.</p>
                            <?php endif;
                            $conn->close();
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Footer -->
<footer class="footer mt-auto py-3 bg-light">
    <div class="container-fluid">
        <span class="text-muted">&copy; <?php echo date('Y'); ?> BOX OMNIA - Dashboard Amministrativa</span>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Validazione client-side -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        const email = document.getElementById('email');
        const password = document.getElementById('password');
        const confermaPassword = document.getElementById('conferma_password');

        // Validazione email dominio
        email.addEventListener('blur', function() {
            if (this.value && !this.value.toLowerCase().endsWith('@boxomnia.it')) {
                this.setCustomValidity('L\'email deve terminare con @boxomnia.it');
            } else {
                this.setCustomValidity('');
            }
        });

        // Validazione password match
        function validatePasswordMatch() {
            if (password.value !== confermaPassword.value) {
                confermaPassword.setCustomValidity('Le password non corrispondono');
            } else {
                confermaPassword.setCustomValidity('');
            }
        }

        password.addEventListener('change', validatePasswordMatch);
        confermaPassword.addEventListener('keyup', validatePasswordMatch);

        // Validazione forza password
        password.addEventListener('input', function() {
            const pwd = this.value;
            const hasUpper = /[A-Z]/.test(pwd);
            const hasLower = /[a-z]/.test(pwd);
            const hasNumber = /\d/.test(pwd);
            const minLength = pwd.length >= 8;

            if (!minLength || !hasUpper || !hasLower || !hasNumber) {
                this.setCustomValidity('La password deve contenere almeno 8 caratteri, una maiuscola, una minuscola e un numero');
            } else {
                this.setCustomValidity('');
            }
        });
    });
</script>
</body>
</html>