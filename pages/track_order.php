<?php
// 1. Includi la logica e i file di configurazione
require_once __DIR__ . '/../include/session_manager.php';
require_once __DIR__ . '/../include/config.inc.php';

// 2. Richiedi login
SessionManager::requireLogin();

// 3. Richiedi il file di logica per il tracking
$trackingData = include __DIR__ . '/../action/get_tracking_data.php';

// Se i dati non sono validi o l'ordine non è trovato, reindirizza
if (!$trackingData || !$trackingData['order']) {
    header('Location: ' . BASE_URL . '/ordini.php');
    exit();
}

// 4. Includi l'header della pagina
$hideNav = false;
include __DIR__ . '/header.php';
?>

<link rel="stylesheet" href="styles.css">

<main class="background-custom">
    <div class="container py-5">
        <h1 class="fashion_taital mb-5">Traccia il tuo ordine</h1>

        <div class="card p-4 shadow-sm mb-4">
            <div class="row">
                <div class="col-md-6">
                    <h5 class="mb-3">Dettagli Ordine</h5>
                    <p><strong>Numero Ordine:</strong> #<?php echo htmlspecialchars($trackingData['order']['id_ordine']); ?></p>
                    <p><strong>Tracking:</strong> <code><?php echo htmlspecialchars($trackingData['order']['tracking']); ?></code></p>
                    <p><strong>Data Ordine:</strong> <?php echo htmlspecialchars($trackingData['order']['data_ordine']); ?></p>
                    <p><strong>Stato Attuale:</strong> <span class="badge bg-<?php echo getStatusColor($trackingData['order']['stato_ordine']); ?>">
                        <?php echo getStatusLabel($trackingData['order']['stato_ordine']); ?>
                    </span></p>
                </div>
                <div class="col-md-6">
                    <h5 class="mb-3">Indirizzo di Spedizione</h5>
                    <p><strong>Cliente:</strong> <?php echo htmlspecialchars($trackingData['client']['nome']); ?></p>
                    <p><strong>Indirizzo:</strong> <?php echo htmlspecialchars($trackingData['address']['via']); ?>, <?php echo htmlspecialchars($trackingData['address']['cap']); ?> <?php echo htmlspecialchars($trackingData['address']['citta']); ?> - <?php echo htmlspecialchars($trackingData['address']['provincia']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($trackingData['client']['email']); ?></p>
                </div>
            </div>
        </div>

        <div class="card p-4 shadow-sm">
            <h5 class="mb-4">Cronologia Spedizione</h5>
            <div class="timeline">
                <?php
                // Definisci gli stati e le loro icone
                $steps = [
                    0 => ['label' => 'In Elaborazione', 'icon' => 'bi-gear-fill', 'class' => 'bg-warning'],
                    2 => ['label' => 'Spedito', 'icon' => 'bi-truck', 'class' => 'bg-info'],
                    3 => ['label' => 'In Consegna', 'icon' => 'bi-box-seam', 'class' => 'bg-primary'],
                    1 => ['label' => 'Consegnato', 'icon' => 'bi-check-circle-fill', 'class' => 'bg-success']
                ];

                // Cicla gli stati per creare la timeline
                foreach ($steps as $rawStatus => $step) {
                    $isCompleted = false;
                    $isCurrent = false;

                    // Controlla se lo stato è stato raggiunto
                    foreach ($trackingData['history'] as $historyItem) {
                        if ($historyItem['stato_nuovo'] == $rawStatus) {
                            $isCompleted = true;
                            break;
                        }
                    }

                    // Se lo stato è il passo attuale
                    if ($rawStatus == $trackingData['order']['stato_ordine']) {
                        $isCurrent = true;
                    }

                    echo '<div class="timeline-item ' . ($isCompleted ? 'completed' : '') . ' ' . ($isCurrent ? 'current' : '') . '">';
                    echo '<div class="timeline-icon ' . $step['class'] . '"><i class="bi ' . $step['icon'] . '"></i></div>';
                    echo '<div class="timeline-content">';
                    echo '<h6>' . $step['label'] . '</h6>';
                    
                    // Mostra la data se lo stato è completato
                    if ($isCompleted) {
                        $historyDate = '';
                        foreach ($trackingData['history'] as $historyItem) {
                            if ($historyItem['stato_nuovo'] == $rawStatus) {
                                $historyDate = new DateTime($historyItem['data_modifica']);
                                echo '<small>' . $historyDate->format('d/m/Y H:i') . '</small>';
                                break;
                            }
                        }
                    } else {
                        echo '<small>In attesa</small>';
                    }
                    
                    echo '</div>';
                    echo '</div>';
                }
                ?>
            </div>
            
        </div>
        <div class="text-center mt-4">
            <a href="ordini.php" class="btn btn-primary"><i class="bi bi-arrow-left"></i> Torna agli ordini</a>
        </div>
    </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>

<?php
// Funzioni di utilità per lo stato
function getStatusLabel($rawStatus) {
    switch ($rawStatus) {
        case 0: return 'In Elaborazione';
        case 1: return 'Consegnato';
        case 2: return 'Spedito';
        case 3: return 'In Consegna'; // Aggiunto per coerenza con la timeline
        case 4: return 'Annullato';
        default: return 'Sconosciuto';
    }
}

function getStatusColor($rawStatus) {
    switch ($rawStatus) {
        case 0: return 'warning';
        case 1: return 'success';
        case 2: return 'info';
        case 3: return 'primary'; // Aggiunto per coerenza con la timeline
        case 4: return 'danger';
        default: return 'secondary';
    }
}
?>