<?php
// 1. PRIMA di qualsiasi output: include SessionManager e controlli
require_once __DIR__ . '/../include/session_manager.php';
require_once __DIR__ . '/../include/config.inc.php';

// 2. Richiedi autenticazione (fa il redirect automaticamente se non loggato)
SessionManager::requireLogin();

// 3. ORA è sicuro includere l'header
include __DIR__ . '/header.php';

// 4. Recupera i dati utente
$user_id = SessionManager::getUserId();

// Accedi alle credenziali dal global $config array
if (!isset(
    $config['dbms']['localhost']['host'],
    $config['dbms']['localhost']['user'],
    $config['dbms']['localhost']['passwd'],
    $config['dbms']['localhost']['dbname']
)) {
    die("Errore: Credenziali database incomplete nel file di configurazione.");
}

$db_host   = $config['dbms']['localhost']['host'];
$db_user   = $config['dbms']['localhost']['user'];
$db_passwd = $config['dbms']['localhost']['passwd'];
$db_name   = $config['dbms']['localhost']['dbname'];

// Connessione al database
$conn = new mysqli($db_host, $db_user, $db_passwd, $db_name);

if ($conn->connect_error) {
    die("Connessione al database fallita: " . $conn->connect_error);
}

// Abilita la reportistica degli errori MySQLi (utile per il debug)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Query per ottenere la classifica degli utenti con i loro punteggi
$sql = "
        SELECT 
            u.id_utente,
            u.nome,
            u.cognome,
            u.email,
            COALESCE(SUM(o.punto * ou.quantita_ogg), 0) AS punteggio_totale,
            COUNT(DISTINCT ou.fk_oggetto) AS oggetti_posseduti
        FROM utente u
        LEFT JOIN oggetto_utente ou ON u.id_utente = ou.fk_utente
        LEFT JOIN oggetto o ON ou.fk_oggetto = o.id_oggetto
        WHERE u.email NOT LIKE '%@boxomnia.it'
        GROUP BY u.id_utente, u.nome, u.cognome, u.email
        ORDER BY punteggio_totale DESC, oggetti_posseduti DESC, u.nome ASC;

";

$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
$classifica = $result->fetch_all(MYSQLI_ASSOC);

$stmt->close();
$conn->close();
?>

<main class="background-custom">
    <div class="container layout_padding">
        <div class="row">
            <div class="col-md-12">
                <!-- Page Header -->
                <div class="classifica-header text-center mb-5">
                    <h1 class="fashion_taital">
                        <i class="fas fa-trophy trophy-gold"></i>
                        Classifica Utenti
                        <i class="fas fa-medal medal-silver"></i>
                    </h1>
                    <p class="lead classifica-description">
                        Scopri chi sono i collezionisti più esperti della community! La classifica è basata sui punti totali degli oggetti posseduti.
                    </p>
                </div>

                <!-- Stats Overview -->
                <div class="row mb-5">
                    <div class="col-md-4">
                        <div class="stat-card">
                            <i class="fas fa-users stat-icon"></i>
                            <h3><?php echo count($classifica); ?></h3>
                            <p>Utenti Totali</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <i class="fas fa-star stat-icon-gold"></i>
                            <h3><?php echo !empty($classifica) ? number_format($classifica[0]['punteggio_totale']) : '0'; ?></h3>
                            <p>Punteggio Massimo</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <i class="fas fa-gem stat-icon-secondary"></i>
                            <h3><?php echo !empty($classifica) ? $classifica[0]['oggetti_posseduti'] : '0'; ?></h3>
                            <p>Oggetti del Leader</p>
                        </div>
                    </div>
                </div>

                <!-- Classifica Container -->
                <div class="classifica-container">
                    
                    <?php if (!empty($classifica)): ?>
                        <!-- Top 3 Podium -->
                        <div class="podium-section">
                            <h2 class="podium-title">🏆 TOP 3 🏆</h2>
                            <div class="row">
                                <?php 
                                $medals = ['🥇', '🥈', '🥉'];
                                $colors = ['gold', 'silver', 'bronze'];
                                for ($i = 0; $i < min(3, count($classifica)); $i++): 
                                    $utente = $classifica[$i];
                                ?>
                                <div class="col-md-4 mb-3">
                                    <div class="podium-card podium-card-<?php echo $colors[$i]; ?>">
                                        <div class="podium-medal"><?php echo $medals[$i]; ?></div>
                                        <h4 class="podium-name"><?php echo htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']); ?></h4>
                                        <div class="podium-score podium-score-<?php echo $colors[$i]; ?>">
                                            <?php echo number_format($utente['punteggio_totale']); ?> punti
                                        </div>
                                        <small class="podium-objects">
                                            <i class="fas fa-box"></i> <?php echo $utente['oggetti_posseduti']; ?> oggetti
                                        </small>
                                    </div>
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <!-- Full Rankings Table -->
                        <div class="table-responsive classifica-table-container">
                            <table class="table table-hover classifica-table">
                                <thead>
                                    <tr class="classifica-header-row">
                                        <th class="classifica-th">
                                            <i class="fas fa-hashtag"></i> Posizione
                                        </th>
                                        <th class="classifica-th">
                                            <i class="fas fa-user"></i> Utente
                                        </th>
                                        <th class="classifica-th">
                                            <i class="fas fa-star"></i> Punteggio
                                        </th>
                                        <th class="classifica-th">
                                            <i class="fas fa-box"></i> Oggetti
                                        </th>
                                        <th class="classifica-th">
                                            <i class="fas fa-chart-line"></i> Media
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($classifica as $posizione => $utente): ?>
                                        <tr class="classifica-row <?php echo ($posizione < 3) ? 'top-three' : ''; ?>">
                                            
                                            <td class="classifica-td position-cell">
                                                <?php if ($posizione == 0): ?>
                                                    <span class="position-medal gold">🥇</span> #1
                                                <?php elseif ($posizione == 1): ?>
                                                    <span class="position-medal silver">🥈</span> #2
                                                <?php elseif ($posizione == 2): ?>
                                                    <span class="position-medal bronze">🥉</span> #3
                                                <?php else: ?>
                                                    <span class="position-number">#<?php echo $posizione + 1; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <td class="classifica-td">
                                                <div class="user-info">
                                                    <div class="user-avatar">
                                                        <?php echo strtoupper(substr($utente['nome'], 0, 1) . substr($utente['cognome'], 0, 1)); ?>
                                                    </div>
                                                    <div class="user-details">
                                                        <div class="user-name">
                                                            <?php echo htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']); ?>
                                                        </div>
                                                        <small class="user-email">
                                                            <?php echo htmlspecialchars($utente['email']); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            
                                            <td class="classifica-td">
                                                <div class="score-badge">
                                                    <i class="fas fa-star"></i> <?php echo number_format($utente['punteggio_totale']); ?>
                                                </div>
                                            </td>
                                            
                                            <td class="classifica-td">
                                                <div class="objects-badge">
                                                    <i class="fas fa-box"></i> <?php echo $utente['oggetti_posseduti']; ?>
                                                </div>
                                            </td>
                                            
                                            <td class="classifica-td">
                                                <div class="average-score">
                                                    <?php echo $utente['oggetti_posseduti'] > 0 ? number_format($utente['punteggio_totale'] / $utente['oggetti_posseduti'], 1) : '0'; ?> pt/obj
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                    <?php else: ?>
                        <!-- Empty State -->
                        <div class="empty-classifica">
                            <i class="fas fa-trophy empty-trophy"></i>
                            <h3 class="empty-title">Classifica in Costruzione</h3>
                            <p class="empty-description">Non ci sono ancora utenti con oggetti nella collezione.</p>
                            <a href="mystery_box.php" class="btn-shop">
                                <i class="fas fa-box"></i> Inizia a Collezionare
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Achievement Info -->
                <div class="achievement-info mt-5">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-card">
                                <h4 class="info-title">
                                    <i class="fas fa-info-circle"></i> Come Funziona
                                </h4>
                                <ul class="info-list">
                                    <li class="info-item primary">
                                        <strong>Punteggio:</strong> Somma dei punti di tutti gli oggetti posseduti
                                    </li>
                                    <li class="info-item secondary">
                                        <strong>Quantità:</strong> Gli oggetti multipli moltiplicano i punti
                                    </li>
                                    <li class="info-item accent">
                                        <strong>Classifica:</strong> Ordinata per punteggio totale
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-card">
                                <h4 class="info-title secondary">
                                    <i class="fas fa-trophy"></i> Livelli Collezione
                                </h4>
                                <div class="level-item">
                                    <span class="level-badge legend">LEGGENDA</span>
                                    <span class="level-points">500+ punti</span>
                                </div>
                                <div class="level-item">
                                    <span class="level-badge master">MASTER</span>
                                    <span class="level-points">200+ punti</span>
                                </div>
                                <div class="level-item">
                                    <span class="level-badge expert">EXPERT</span>
                                    <span class="level-points">100+ punti</span>
                                </div>
                                <div class="level-item">
                                    <span class="level-badge novice">NOVICE</span>
                                    <span class="level-points">1+ punti</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>

<script>
    // Animazione contatori
    function animateCounters() {
        const counters = document.querySelectorAll('.stat-card h3');
        counters.forEach(counter => {
            const target = parseInt(counter.innerText.replace(/,/g, ''));
            const increment = target / 100;
            let current = 0;
            
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    counter.innerText = target.toLocaleString();
                    clearInterval(timer);
                } else {
                    counter.innerText = Math.floor(current).toLocaleString();
                }
            }, 20);
        });
    }

    // Avvia animazioni al caricamento
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(animateCounters, 500);
    });

    // Effetto hover per le righe della tabella
    document.addEventListener('DOMContentLoaded', function() {
        const rows = document.querySelectorAll('tbody tr');
        rows.forEach((row, index) => {
            row.style.animationDelay = (index * 0.1) + 's';
            row.classList.add('fade-in');
        });
    });
</script>


