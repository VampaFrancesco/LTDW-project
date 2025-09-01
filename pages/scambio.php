<?php
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../include/ScambioManager.php';

// Controllo autenticazione
SessionManager::requireLogin();
$utente_id = SessionManager::getUserId();
$nome_utente = SessionManager::get('user_nome', 'Utente') . ' ' . SessionManager::get('user_cognome', '');

// Inizializza ScambioManager tramite factory (usa include/config.inc.php)
try {
    $scambio_manager = ScambioManagerFactory::create();
} catch (Exception $e) {
    SessionManager::setFlashMessage("Errore di connessione: " . $e->getMessage(), 'error');
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/pages/errors.php');
    exit();
}

$messaggio = "";
$messaggio_tipo = "info";

// Gestione POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['crea_scambio'])) {
            $carte_offerte   = json_decode($_POST['carte_offerte']   ?? '[]', true) ?: [];
            $carte_richieste = json_decode($_POST['carte_richieste'] ?? '[]', true) ?: [];
            $carte_cartacee  = json_decode($_POST['carte_cartacee']  ?? '[]', true) ?: [];

            if ((empty($carte_offerte) && empty($carte_cartacee)) || empty($carte_richieste)) {
                throw new Exception("Devi selezionare almeno una carta da offrire (digitale o cartacea) e almeno una da richiedere");
            }

            $id_scambio = $scambio_manager->creaProposta($utente_id, $carte_offerte, $carte_richieste, null, $carte_cartacee);
            $messaggio = "✅ Proposta di scambio creata con successo! ID: #$id_scambio";
            $messaggio_tipo = "success";
        }

        if (isset($_POST['accetta'])) {
            $scambio_manager->accettaScambio($_POST['id_scambio'], $utente_id);
            $messaggio = "✅ Scambio accettato con successo!";
            $messaggio_tipo = "success";
        }

        if (isset($_POST['rifiuta'])) {
            $scambio_manager->rifiutaScambio($_POST['id_scambio'], $utente_id);
            $messaggio = "❌ Scambio rifiutato!";
            $messaggio_tipo = "warning";
        }
    } catch (Exception $e) {
        $messaggio = "Errore: " . $e->getMessage();
        $messaggio_tipo = "danger";
    }
}

// Dati pagina
try {
    $scambi_disponibili = $scambio_manager->getScambiDisponibili($utente_id);
    $miei_scambi        = $scambio_manager->getScambiUtente($utente_id);
    // opzionali: $collezione e $categorie per la parte digitale
    $collezione = $scambio_manager->getCollezioneUtente($utente_id);
    $categorie  = $scambio_manager->getCategorie();
} catch (Exception $e) {
    $scambi_disponibili = [];
    $miei_scambi = [];
    $collezione = [];
    $categorie = [];
    $messaggio = "Errore nel recupero dati: " . $e->getMessage();
    $messaggio_tipo = "danger";
}
?>

<main class="background-custom">
    <div class="container py-4">
        <h1 class="fashion_taital mb-4">
            <i class="bi bi-arrow-left-right"></i>
            Sistema di Scambio
        </h1>

        <div class="row mb-3">
            <div class="col-md-12">
                <p class="lead">Benvenuto nel sistema di scambio, <?= htmlspecialchars($nome_utente) ?>!</p>
            </div>
        </div>

        <?php if ($messaggio): ?>
            <div class="alert alert-<?= $messaggio_tipo ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($messaggio) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Tabs -->
        <ul class="nav nav-tabs" id="scambioTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="scambi-disponibili-tab" data-bs-toggle="tab" data-bs-target="#scambi-disponibili" type="button" role="tab">
                    <i class="bi bi-shop"></i> Scambi Disponibili (<?= count($scambi_disponibili) ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="miei-scambi-tab" data-bs-toggle="tab" data-bs-target="#miei-scambi" type="button" role="tab">
                    <i class="bi bi-person-badge"></i> I Miei Scambi (<?= count($miei_scambi) ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="crea-scambio-tab" data-bs-toggle="tab" data-bs-target="#crea-scambio" type="button" role="tab">
                    <i class="bi bi-plus-circle"></i> Crea Scambio
                </button>
            </li>
        </ul>

        <div class="tab-content" id="scambioTabsContent">
            <!-- SCAMBI DISPONIBILI -->
            <div class="tab-pane fade show active" id="scambi-disponibili" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-header">
                        <h3><i class="bi bi-shop"></i> Scambi Proposti da Altri Utenti</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($scambi_disponibili): ?>
                            <div class="row">
                                <?php foreach ($scambi_disponibili as $scambio): ?>
                                    <div class="col-md-6 col-lg-4 mb-4">
                                        <div class="card h-100 border-primary">
                                            <div class="card-header bg-primary text-white">
                                                <h5 class="mb-0">
                                                    <i class="bi bi-person"></i>
                                                    <?= htmlspecialchars(($scambio['nome'] ?? 'Utente') . ' ' . ($scambio['cognome'] ?? '')) ?>
                                                </h5>
                                                <small>Scambio #<?= $scambio['id_scambio'] ?></small>
                                            </div>
                                            <div class="card-body">
                                                <div class="mb-3">
                                                    <h6 class="text-success"><i class="bi bi-gift"></i> Offre (<?= (int)($scambio['carte_offerte'] ?? 0) ?> carte digitali)</h6>
                                                    <?php foreach ($scambio_manager->getCarteOfferte($scambio['id_scambio']) as $carta): ?>
                                                        <span class="badge bg-success me-1 mb-1">
                                                            <?= htmlspecialchars($carta['nome_oggetto']) ?> (x<?= (int)$carta['quantita_scambio'] ?>)
                                                        </span>
                                                    <?php endforeach; ?>
                                                    <?php $cartacee = $scambio_manager->getCarteCartacee($scambio['id_scambio']); if ($cartacee): ?>
                                                        <div class="mt-2">
                                                            <h6 class="text-warning mb-1"><i class="bi bi-file-earmark-text"></i> Carte Cartacee Offerte</h6>
                                                            <?php foreach ($cartacee as $cc): ?>
                                                                <span class="badge bg-warning text-dark me-1 mb-1">
                                                                    <?= htmlspecialchars($cc['nome_carta']) ?> (x<?= (int)$cc['quantita'] ?>, <?= htmlspecialchars(ucfirst($cc['stato'])) ?>)
                                                                </span>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="mb-3">
                                                    <h6 class="text-info"><i class="bi bi-search"></i> Cerca (<?= (int)($scambio['carte_richieste'] ?? 0) ?> carte digitali)</h6>
                                                    <?php foreach ($scambio_manager->getCarteRichieste($scambio['id_scambio']) as $carta): ?>
                                                        <span class="badge bg-info me-1 mb-1">
                                                            <?= htmlspecialchars($carta['nome_oggetto']) ?> (x<?= (int)$carta['quantita_scambio'] ?>)
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>

                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted"><?= date('d/m/Y H:i', strtotime($scambio['data_scambio'])) ?></small>

                                                    <?php
                                                    $carte_richieste = $scambio_manager->getCarteRichieste($scambio['id_scambio']);
                                                    $puo_accettare = $scambio_manager->verificaPossessoOggetti($utente_id, $carte_richieste);
                                                    ?>
                                                    <?php if ($puo_accettare): ?>
                                                        <form method="post" style="display:inline">
                                                            <input type="hidden" name="id_scambio" value="<?= $scambio['id_scambio'] ?>">
                                                            <button type="submit" name="accetta" class="btn btn-success btn-sm">
                                                                <i class="bi bi-check-circle"></i> Accetta
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <button class="btn btn-secondary btn-sm" disabled title="Non hai le carte richieste">
                                                            <i class="bi bi-x-circle"></i> Non disponibile
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-inbox display-4 text-muted"></i>
                                <p class="text-muted mt-2">Nessuno scambio disponibile al momento.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- MIEI SCAMBI -->
            <div class="tab-pane fade" id="miei-scambi" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-header">
                        <h3><i class="bi bi-person-badge"></i> I Miei Scambi</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($miei_scambi): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Creato il</th>
                                            <th>Offre</th>
                                            <th>Cerca</th>
                                            <th>Azioni</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($miei_scambi as $scambio): ?>
                                            <tr>
                                                <td>#<?= $scambio['id_scambio'] ?></td>
                                                <td><?= date('d/m/Y H:i', strtotime($scambio['data_scambio'])) ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#modal-dettagli-<?= $scambio['id_scambio'] ?>">
                                                        <?= (int)($scambio['carte_offerte'] ?? 0) ?> digitali<?= $scambio_manager->getCarteCartacee($scambio['id_scambio']) ? ' + cartacee' : '' ?>
                                                    </button>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#modal-dettagli-<?= $scambio['id_scambio'] ?>">
                                                        <?= (int)($scambio['carte_richieste'] ?? 0) ?> digitali
                                                    </button>
                                                </td>
                                                <td>
                                                    <?php if ($scambio['stato_scambio'] === 'proposto'): ?>
                                                        <form method="post" style="display:inline">
                                                            <input type="hidden" name="id_scambio" value="<?= $scambio['id_scambio'] ?>">
                                                            <button type="submit" name="rifiuta" class="btn btn-danger btn-sm" onclick="return confirm('Sei sicuro di voler annullare questo scambio?')">
                                                                <i class="bi bi-x-circle"></i> Annulla
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#modal-dettagli-<?= $scambio['id_scambio'] ?>">
                                                        <i class="bi bi-eye"></i> Dettagli
                                                    </button>
                                                </td>
                                            </tr>

                                            <!-- Modal dettagli -->
                                            <?php $dettagli = $scambio_manager->getDettagliCompleti($scambio['id_scambio']); ?>
                                            <div class="modal fade" id="modal-dettagli-<?= $scambio['id_scambio'] ?>" tabindex="-1">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Dettagli Scambio #<?= $scambio['id_scambio'] ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <h6 class="text-success">Carte Digitali Offerte:</h6>
                                                                    <?php foreach ($dettagli['carte_offerte_dettagli'] as $carta): ?>
                                                                        <div class="mb-2 p-2 border rounded">
                                                                            <strong><?= htmlspecialchars($carta['nome_oggetto']) ?></strong>
                                                                            <span class="badge bg-secondary">x<?= (int)$carta['quantita_scambio'] ?></span>
                                                                        </div>
                                                                    <?php endforeach; ?>

                                                                    <?php if (!empty($dettagli['carte_cartacee'])): ?>
                                                                        <h6 class="text-warning mt-3">Carte Cartacee Offerte:</h6>
                                                                        <?php foreach ($dettagli['carte_cartacee'] as $cc): ?>
                                                                            <div class="mb-2 p-2 border rounded">
                                                                                <strong><?= htmlspecialchars($cc['nome_carta']) ?></strong>
                                                                                <span class="badge bg-secondary">x<?= (int)$cc['quantita'] ?></span>
                                                                                <small class="ms-2">Stato: <?= htmlspecialchars(ucfirst($cc['stato'])) ?></small>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <h6 class="text-info">Carte Digitali Richieste:</h6>
                                                                    <?php foreach ($dettagli['carte_richieste_dettagli'] as $carta): ?>
                                                                        <div class="mb-2 p-2 border rounded">
                                                                            <strong><?= htmlspecialchars($carta['nome_oggetto']) ?></strong>
                                                                            <span class="badge bg-secondary">x<?= (int)$carta['quantita_scambio'] ?></span>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            </div>
                                                            <hr>
                                                            <h6>Riepilogo Scambio</h6>
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <strong>Valore Stimato Offerto:</strong> <span>€<?= number_format($dettagli['valore_offerto'] ?? 0, 2) ?></span>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <strong>Valore Stimato Richiesto:</strong> <span>€<?= number_format($dettagli['valore_richiesto'] ?? 0, 2) ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-inbox display-4 text-muted"></i>
                                <p class="text-muted mt-2">Non hai ancora creato nessuno scambio.</p>
                                <button class="btn btn-primary" data-bs-toggle="tab" data-bs-target="#crea-scambio">
                                    <i class="bi bi-plus-circle"></i> Crea il tuo primo scambio
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- CREA SCAMBIO -->
            <div class="tab-pane fade" id="crea-scambio" role="tabpanel">
                <div class="card mt-3">
                    <div class="card-header">
                        <h3><i class="bi bi-plus-circle"></i> Proponi un Nuovo Scambio</h3>
                    </div>
                    <div class="card-body">
                        <form method="post" id="form-crea-scambio">
                            <div class="row g-4">
                                <!-- Sezione carte cartacee -->
                                <div class="col-md-6">
                                    <h5 class="text-warning"><i class="bi bi-file-earmark-text"></i> Carte Cartacee da Offrire</h5>
                                    <p class="text-muted small">Aggiungi una o più carte cartacee che vuoi scambiare: nome, quantità, stato (scarso, buono, eccellente).</p>
                                    <div id="cartacee-container"></div>
                                    <button type="button" class="btn btn-outline-warning btn-sm mt-2" id="btn-aggiungi-cartacea">
                                        <i class="bi bi-plus-circle"></i> Aggiungi carta cartacea
                                    </button>
                                </div>

                                <!-- Placeholder per la UI delle carte digitali (se già presente nel tuo progetto, rimane invariata) -->
                                <div class="col-md-6">
                                    <h5 class="text-success"><i class="bi bi-gift"></i> Carte Digitali</h5>
                                    <p class="text-muted small">(Opzionale) Seleziona carte digitali dalla tua collezione e quelle che cerchi. Se non usi il selettore esistente, puoi ignorare questa sezione.</p>
                                    <!-- Qui puoi integrare la tua UI esistente per popolare i due array JSON sotto -->
                                    <div class="alert alert-light border">Userai il selettore esistente per popolare <code>carte_offerte</code> e <code>carte_richieste</code>.</div>
                                </div>
                            </div>

                            <input type="hidden" name="carte_offerte" id="input_carte_offerte">
                            <input type="hidden" name="carte_richieste" id="input_carte_richieste">
                            <input type="hidden" name="carte_cartacee" id="input_carte_cartacee">

                            <div class="text-center mt-4">
                                <button type="submit" name="crea_scambio" class="btn btn-primary btn-lg">
                                    <i class="bi bi-plus-circle"></i> Crea Proposta di Scambio
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// ====== Gestione Carte Cartacee (front-end) ======
(function(){
    const btnAdd = document.getElementById('btn-aggiungi-cartacea');
    const container = document.getElementById('cartacee-container');
    const hidden = document.getElementById('input_carte_cartacee');

    let cartacee = [];

    function render(){
        container.innerHTML = '';
        if (cartacee.length === 0){
            container.innerHTML = '<p class="text-muted">Nessuna carta cartacea aggiunta.</p>';
        }
        cartacee.forEach((c, idx) => {
            const wrap = document.createElement('div');
            wrap.className = 'p-2 mb-2 border rounded';
            wrap.innerHTML = `
                <div class="row g-2 align-items-end">
                    <div class="col-6">
                        <label class="form-label">Nome carta</label>
                        <input type="text" class="form-control" value="${c.nome || ''}" data-field="nome" data-idx="${idx}" placeholder="Es. Charizard Holo" required>
                    </div>
                    <div class="col-3">
                        <label class="form-label">Quantità</label>
                        <input type="number" min="1" class="form-control" value="${c.quantita || 1}" data-field="quantita" data-idx="${idx}">
                    </div>
                    <div class="col-3">
                        <label class="form-label">Stato</label>
                        <select class="form-select" data-field="stato" data-idx="${idx}">
                            <option value="eccellente" ${c.stato==='eccellente'?'selected':''}>Eccellente</option>
                            <option value="buono" ${c.stato==='buono'?'selected':''}>Buono</option>
                            <option value="scarso" ${c.stato==='scarso'?'selected':''}>Scarso</option>
                        </select>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button type="button" class="btn btn-sm btn-outline-danger" data-action="remove" data-idx="${idx}"><i class="bi bi-trash"></i> Rimuovi</button>
                    </div>
                </div>`;
            container.appendChild(wrap);
        });
        syncHidden();
    }

    function syncHidden(){
        hidden.value = JSON.stringify(cartacee.filter(c => (c.nome||'').trim() !== ''));
    }

    function add(){
        cartacee.push({ nome: '', quantita: 1, stato: 'buono' });
        render();
    }

    container.addEventListener('input', (e)=>{
        const idx = +e.target.getAttribute('data-idx');
        const field = e.target.getAttribute('data-field');
        if (Number.isInteger(idx) && field){
            cartacee[idx][field] = field === 'quantita' ? Math.max(1, parseInt(e.target.value||'1',10)) : e.target.value;
            syncHidden();
        }
    });

    container.addEventListener('click', (e)=>{
        if (e.target.matches('[data-action="remove"], [data-action="remove"] *')){
            const btn = e.target.closest('[data-action="remove"]');
            const idx = +btn.getAttribute('data-idx');
            cartacee.splice(idx,1);
            render();
        }
    });

    if (btnAdd){ btnAdd.addEventListener('click', add); }

    // stato iniziale
    render();
})();
</script>
