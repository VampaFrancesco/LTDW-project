<?php
// pages/supporto_utente.php
require_once __DIR__ . '/../include/session_manager.php';
require_once __DIR__ . '/../include/config.inc.php';

SessionManager::requireLogin();
include __DIR__ . '/header.php';

$user_id = SessionManager::getUserId();

// Connessione database
$db_config = $config['dbms']['localhost'];
$conn = new mysqli(
    $db_config['host'],
    $db_config['user'],
    $db_config['passwd'],
    $db_config['dbname']
);

if ($conn->connect_error) {
    die("Errore di connessione: " . $conn->connect_error);
}

// Segna le notifiche come lette quando l'utente visita questa pagina
$stmt = $conn->prepare("UPDATE notifiche_utente SET letta = TRUE WHERE fk_utente = ? AND letta = FALSE");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->close();

// Recupera le richieste dell'utente con le relative risposte
$richieste = [];
$sql = "
    SELECT 
        rs.id_richiesta,
        rs.oggetto,
        rs.messaggio,
        rs.stato,
        rs.priorita,
        rs.data_creazione,
        rs.data_aggiornamento,
        COUNT(resp.id_risposta) as num_risposte,
        MAX(resp.data_risposta) as ultima_risposta,
        -- Controlla se ci sono notifiche non lette per questa richiesta
        CASE WHEN EXISTS (
            SELECT 1 FROM notifiche_utente n 
            WHERE n.fk_richiesta = rs.id_richiesta 
            AND n.fk_utente = ? 
            AND n.letta = FALSE
        ) THEN 1 ELSE 0 END as ha_notifiche_nuove
    FROM richieste_supporto rs
    LEFT JOIN risposte_supporto resp ON rs.id_richiesta = resp.fk_richiesta
    WHERE rs.fk_utente = ?
    GROUP BY rs.id_richiesta, rs.oggetto, rs.messaggio, rs.stato, rs.priorita, 
             rs.data_creazione, rs.data_aggiornamento
    ORDER BY rs.data_aggiornamento DESC, rs.data_creazione DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $richieste[] = $row;
    }
}
$stmt->close();

// Funzione per recuperare conversazione completa
function getConversazioneCompleta($conn, $richiesta_id, $user_id) {
    $conversazione = [];
    
    // Messaggio originale
    $stmt = $conn->prepare("
        SELECT 'utente' as tipo, rs.messaggio, rs.data_creazione as data, 
               CONCAT(u.nome, ' ', u.cognome) as autore
        FROM richieste_supporto rs
        JOIN utente u ON rs.fk_utente = u.id_utente
        WHERE rs.id_richiesta = ? AND rs.fk_utente = ?
    ");
    $stmt->bind_param("ii", $richiesta_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $conversazione[] = $row;
    }
    $stmt->close();
    
    // Risposte admin
    $stmt = $conn->prepare("
        SELECT 'admin' as tipo, r.messaggio, r.data_risposta as data,
               CONCAT(u.nome, ' ', u.cognome) as autore
        FROM risposte_supporto r
        JOIN utente u ON r.fk_admin = u.id_utente
        WHERE r.fk_richiesta = ?
        ORDER BY r.data_risposta ASC
    ");
    $stmt->bind_param("i", $richiesta_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $conversazione[] = $row;
    }
    $stmt->close();
    
    // Ordina tutta la conversazione per data
    usort($conversazione, function($a, $b) {
        return strtotime($a['data']) - strtotime($b['data']);
    });
    
    return $conversazione;
}
?>

<main class="background-custom">
    <div class="container">
        <div class="user-support-header">
            <h1 class="fashion_taital mb-5">Le mie Richieste di Supporto</h1>
            <div class="support-actions">
                <a href="<?php echo BASE_URL; ?>/pages/contatti.php" class="btn btn-filter-nav">
                    <i class="bi bi-plus-circle"></i> Nuova Richiesta
                </a>
            </div>
        </div>

        <?php if (empty($richieste)): ?>
            <div class="empty-support-state">
                <div class="empty-icon">
                    <i class="bi bi-headset"></i>
                </div>
                <h3>Nessuna richiesta di supporto</h3>
                <p>Non hai ancora inviato alcuna richiesta di supporto. Quando ne invierai una, apparirà qui.</p>
                <a href="<?php echo BASE_URL; ?>/pages/contatti.php" class="btn btn-filter-nav">
                    Invia la tua prima richiesta
                </a>
            </div>
        <?php else: ?>
            <div class="user-support-requests">
                <?php foreach ($richieste as $richiesta): ?>
                    <div class="user-request-card" data-request-id="<?php echo $richiesta['id_richiesta']; ?>">
                        <div class="request-card-header">
                            <div class="request-title-section">
                                <h4 class="request-title">
                                    <?php echo htmlspecialchars($richiesta['oggetto']); ?>
                                    <?php if ($richiesta['ha_notifiche_nuove']): ?>
                                        <span class="new-response-badge">
                                            <i class="bi bi-bell-fill"></i> Nuova risposta
                                        </span>
                                    <?php endif; ?>
                                </h4>
                                <div class="request-meta-info">
                                    <span class="request-date">
                                        <i class="bi bi-calendar3"></i>
                                        Creata il <?php echo date('d/m/Y H:i', strtotime($richiesta['data_creazione'])); ?>
                                    </span>
                                    <?php if ($richiesta['ultima_risposta']): ?>
                                        <span class="last-response">
                                            <i class="bi bi-clock-history"></i>
                                            Ultima risposta: <?php echo date('d/m/Y H:i', strtotime($richiesta['ultima_risposta'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="request-status-section">
                                <?php
                                $status_class = match($richiesta['stato']) {
                                    'aperta' => 'status-open',
                                    'in_corso' => 'status-progress',
                                    'chiusa' => 'status-closed',
                                };
                                $status_text = match($richiesta['stato']) {
                                    'aperta' => 'Aperta',
                                    'in_corso' => 'In Corso',
                                    'chiusa' => 'Chiusa',
                                };
                                ?>
                                <div class="status-badge <?php echo $status_class; ?>">
                                    <i class="bi bi-circle-fill"></i>
                                    <?php echo $status_text; ?>
                                </div>
                                <div class="response-count">
                                    <i class="bi bi-chat-left-text"></i>
                                    <?php echo $richiesta['num_risposte']; ?> risposte
                                </div>
                            </div>
                        </div>

                        <div class="request-conversation">
                            <?php 
                            $conversazione = getConversazioneCompleta($conn, $richiesta['id_richiesta'], $user_id);
                            foreach ($conversazione as $messaggio):
                            ?>
                                <div class="message-item <?php echo $messaggio['tipo'] === 'utente' ? 'user-message' : 'admin-message'; ?>">
                                    <div class="message-header">
                                        <div class="message-author">
                                            <?php if ($messaggio['tipo'] === 'utente'): ?>
                                                <i class="bi bi-person-circle user-icon"></i>
                                                <strong>Tu</strong>
                                            <?php else: ?>
                                                <i class="bi bi-shield-check admin-icon"></i>
                                                <strong><?php echo htmlspecialchars($messaggio['autore']); ?></strong>
                                                <span class="admin-label">Staff</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="message-date">
                                            <?php echo date('d/m/Y H:i', strtotime($messaggio['data'])); ?>
                                        </div>
                                    </div>
                                    <div class="message-content">
                                        <?php echo nl2br(htmlspecialchars($messaggio['messaggio'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($richiesta['stato'] !== 'chiusa'): ?>
                            <div class="request-status-info">
                                <div class="status-waiting">
                                    <i class="bi bi-clock"></i>
                                    In attesa di risposta dal nostro team di supporto...
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="request-closed-actions">
                                <div class="closed-message">
                                    <i class="bi bi-check-circle-fill text-success"></i>
                                    Richiesta chiusa. Se hai bisogno di ulteriore assistenza, puoi aprire una nuova richiesta.
                                </div>
                                <a href="<?php echo BASE_URL; ?>/pages/contatti.php" class="btn btn-support-secondary">
                                    <i class="bi bi-plus"></i> Nuova Richiesta
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Sezione FAQ/Aiuto rapido -->
        <div class="quick-help-section">
            <h3>Hai bisogno di aiuto immediato?</h3>
            <div class="help-cards">
                <div class="help-card">
    <i class="bi bi-question-circle"></i>
    <h5>FAQ</h5>
    <p>Consulta le domande più frequenti</p>
    <a href="<?php echo BASE_URL; ?>/pages/domande_frequenti.php" class="btn btn-support-outline">Vai alle FAQ</a>
</div>
                <div class="help-card">
                    <i class="bi bi-telephone"></i>
                    <h5>Chiamaci</h5>
                    <p>+39 000 000 000<br>Lun-Ven 8:30-16:30</p>
                </div>
                <div class="help-card">
                    <i class="bi bi-envelope"></i>
                    <h5>Scrivici</h5>
                    <p>info@boxomnia.it<br>Ti risponderemo entro 24h</p>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animazione apparizione card
    const cards = document.querySelectorAll('.user-request-card');
    cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
    });
    
    // Aggiorna il contatore delle notifiche nell'header (resetta a 0)
    const notificationBadge = document.querySelector('.nav-link[title="Notifiche"] .badge');
    if (notificationBadge) {
        notificationBadge.style.display = 'none';
    }
    
    // Smooth scroll per nuove richieste
    const newRequestBadges = document.querySelectorAll('.new-response-badge');
    if (newRequestBadges.length > 0) {
        newRequestBadges[0].closest('.user-request-card').scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });
    }
});
</script>

<?php $conn->close(); ?>