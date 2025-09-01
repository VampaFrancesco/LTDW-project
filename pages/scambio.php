<?php
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../include/ScambioManager.php';

// Controllo autenticazione
SessionManager::requireLogin();

// Recupero utente loggato
$utente_id = SessionManager::getUserId();
$nome_utente = SessionManager::get('user_nome', 'Utente') . ' ' . SessionManager::get('user_cognome', '');

// Inizializza ScambioManager
try {
    $scambio_manager = ScambioManagerFactory::create();
} catch (Exception $e) {
    SessionManager::setFlashMessage("Errore di connessione: " . $e->getMessage(), 'error');
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/pages/errors.php');
    exit();
}

$messaggio = "";
$messaggio_tipo = "info";

// Gestione form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['crea_scambio'])) {
            $carte_offerte = json_decode($_POST['carte_offerte'], true);
            $carte_richieste = json_decode($_POST['carte_richieste'], true);

            if (empty($carte_offerte) || empty($carte_richieste)) {
                throw new Exception("Devi selezionare almeno una carta da offrire e una da richiedere");
            }

            $id_scambio = $scambio_manager->creaProposta($utente_id, $carte_offerte, $carte_richieste);
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

// Recupera dati per la pagina
try {
    $scambi_disponibili = $scambio_manager->getScambiDisponibili($utente_id);
    $miei_scambi = $scambio_manager->getScambiUtente($utente_id);
    $collezione = $scambio_manager->getCollezioneUtente($utente_id);
    $categorie = $scambio_manager->getCategorie();
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
                    <p class="lead">Benvenuto nel sistema di scambio, <?= htmlspecialchars($nome_utente) ?>!
                        Qui puoi scambiare le tue carte con altri collezionisti.</p>
                </div>
            </div>

            <?php if ($messaggio): ?>
                <div class="alert alert-<?= $messaggio_tipo ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($messaggio) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Navigation Tabs -->
            <ul class="nav nav-tabs" id="scambioTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="scambi-disponibili-tab" data-bs-toggle="tab"
                            data-bs-target="#scambi-disponibili" type="button" role="tab">
                        <i class="bi bi-shop"></i> Scambi Disponibili (<?= count($scambi_disponibili) ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="crea-scambio-tab" data-bs-toggle="tab"
                            data-bs-target="#crea-scambio" type="button" role="tab">
                        <i class="bi bi-plus-circle"></i> Proponi Scambio
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="miei-scambi-tab" data-bs-toggle="tab"
                            data-bs-target="#miei-scambi" type="button" role="tab">
                        <i class="bi bi-person-badge"></i> I Miei Scambi (<?= count($miei_scambi) ?>)
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="scambioTabsContent">

                <!-- TAB SCAMBI DISPONIBILI -->
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
                                                        <?= htmlspecialchars($scambio['nome'] . ' ' . $scambio['cognome']) ?>
                                                    </h5>
                                                    <small>Scambio #<?= $scambio['id_scambio'] ?></small>
                                                </div>
                                                <div class="card-body">
                                                    <div class="mb-3">
                                                        <h6 class="text-success">
                                                            <i class="bi bi-gift"></i> Offre (<?= $scambio['carte_offerte'] ?> carte)
                                                        </h6>
                                                        <?php
                                                        $carte_offerte = $scambio_manager->getCarteOfferte($scambio['id_scambio']);
                                                        foreach ($carte_offerte as $carta): ?>
                                                            <span class="badge bg-success me-1 mb-1">
                                                            <?= htmlspecialchars($carta['nome_oggetto']) ?> (x<?= $carta['quantita_scambio'] ?>)
                                                        </span>
                                                        <?php endforeach; ?>
                                                    </div>

                                                    <div class="mb-3">
                                                        <h6 class="text-info">
                                                            <i class="bi bi-search"></i> Cerca (<?= $scambio['carte_richieste'] ?> carte)
                                                        </h6>
                                                        <?php
                                                        $carte_richieste = $scambio_manager->getCarteRichieste($scambio['id_scambio']);
                                                        foreach ($carte_richieste as $carta): ?>
                                                            <span class="badge bg-info me-1 mb-1">
                                                            <?= htmlspecialchars($carta['nome_oggetto']) ?> (x<?= $carta['quantita_scambio'] ?>)
                                                        </span>
                                                        <?php endforeach; ?>
                                                    </div>

                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <small class="text-muted">
                                                            <?= date('d/m/Y H:i', strtotime($scambio['data_scambio'])) ?>
                                                        </small>

                                                        <?php
                                                        // Verifica se l'utente ha le carte richieste
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

                <!-- TAB CREA SCAMBIO -->
                <div class="tab-pane fade" id="crea-scambio" role="tabpanel">
                    <div class="card mt-3">
                        <div class="card-header">
                            <h3><i class="bi bi-plus-circle"></i> Proponi un Nuovo Scambio</h3>
                        </div>
                        <div class="card-body">
                            <form method="post" id="form-crea-scambio">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5 class="text-success"><i class="bi bi-gift"></i> Le Tue Carte da Offrire</h5>
                                        <div class="mb-3">
                                            <label class="form-label">Filtra per categoria:</label>
                                            <select class="form-select" id="filtro-categoria-offerte">
                                                <option value="">Tutte le categorie</option>
                                                <?php foreach ($categorie as $categoria): ?>
                                                    <option value="<?= $categoria['id_categoria'] ?>">
                                                        <?= htmlspecialchars($categoria['nome_categoria']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="mb-3" style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">
                                            <?php if ($collezione): ?>
                                                <?php foreach ($collezione as $carta): ?>
                                                    <div class="carta-item mb-2 p-2 border rounded"
                                                         data-categoria="<?= $carta['fk_categoria_oggetto'] ?>"
                                                         data-id="<?= $carta['fk_oggetto'] ?>"
                                                         style="cursor: pointer;">
                                                        <div class="form-check">
                                                            <input class="form-check-input carta-offerta" type="checkbox"
                                                                   value="<?= $carta['fk_oggetto'] ?>"
                                                                   id="offerta_<?= $carta['fk_oggetto'] ?>">
                                                            <label class="form-check-label w-100" for="offerta_<?= $carta['fk_oggetto'] ?>">
                                                                <div class="d-flex justify-content-between align-items-center">
                                                                    <div>
                                                                        <strong><?= htmlspecialchars($carta['nome_oggetto']) ?></strong>
                                                                        <?php if ($carta['nome_rarita']): ?>
                                                                            <span class="badge" style="background-color: <?= $carta['colore'] ?>">
                                                                            <?= htmlspecialchars($carta['nome_rarita']) ?>
                                                                        </span>
                                                                        <?php endif; ?>
                                                                        <br>
                                                                        <small class="text-muted"><?= htmlspecialchars($carta['nome_categoria']) ?></small>
                                                                        <?php if ($carta['valore_stimato']): ?>
                                                                            <small class="text-success"> - €<?= number_format($carta['valore_stimato'], 2) ?></small>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <div class="text-end">
                                                                        <span class="badge bg-secondary">x<?= $carta['quantita_ogg'] ?></span>
                                                                        <div class="mt-1">
                                                                            <input type="number" class="form-control form-control-sm quantita-offerta"
                                                                                   min="1" max="<?= $carta['quantita_ogg'] ?>"
                                                                                   value="1" style="width: 60px; display: none;"
                                                                                   data-card-id="<?= $carta['fk_oggetto'] ?>">
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </label>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <p class="text-muted">Non hai carte nella tua collezione.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <h5 class="text-info"><i class="bi bi-search"></i> Carte che Desideri</h5>
                                        <div class="mb-3">
                                            <label class="form-label">Cerca carte:</label>
                                            <input type="text" class="form-control" id="cerca-carte"
                                                   placeholder="Digita il nome della carta...">
                                        </div>

                                        <div id="risultati-ricerca" class="mb-3" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">
                                            <p class="text-muted text-center">Usa la barra di ricerca per trovare le carte che desideri.</p>
                                        </div>

                                        <div id="carte-richieste-selezionate">
                                            <h6>Carte Selezionate:</h6>
                                            <div id="lista-carte-richieste">
                                                <p class="text-muted">Nessuna carta selezionata</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mt-4">
                                    <div class="col-md-12">
                                        <div class="alert alert-info">
                                            <h6><i class="bi bi-info-circle"></i> Riepilogo Scambio</h6>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <strong>Valore Stimato Offerto:</strong>
                                                    <span id="valore-offerto">€0.00</span>
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>Valore Stimato Richiesto:</strong>
                                                    <span id="valore-richiesto">€0.00</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <input type="hidden" name="carte_offerte" id="input_carte_offerte">
                                <input type="hidden" name="carte_richieste" id="input_carte_richieste">

                                <div class="text-center">
                                    <button type="submit" name="crea_scambio" class="btn btn-primary btn-lg">
                                        <i class="bi bi-plus-circle"></i> Crea Proposta di Scambio
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- TAB MIEI SCAMBI -->
                <div class="tab-pane fade" id="miei-scambi" role="tabpanel">
                    <div class="card mt-3">
                        <div class="card-header">
                            <h3><i class="bi bi-person-badge"></i> I Miei Scambi</h3>
                        </div>
                        <div class="card-body">
                            <?php if ($miei_scambi): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Data</th>
                                            <th>Stato</th>
                                            <th>Carte Offerte</th>
                                            <th>Carte Richieste</th>
                                            <th>Azioni</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($miei_scambi as $scambio): ?>
                                            <tr>
                                                <td><strong>#<?= $scambio['id_scambio'] ?></strong></td>
                                                <td><?= date('d/m/Y H:i', strtotime($scambio['data_scambio'])) ?></td>
                                                <td>
                                                    <?php
                                                    $badge_class = match($scambio['stato_scambio']) {
                                                        'proposto' => 'bg-warning',
                                                        'completato' => 'bg-success',
                                                        'rifiutato' => 'bg-danger',
                                                        default => 'bg-secondary'
                                                    };
                                                    ?>
                                                    <span class="badge <?= $badge_class ?>">
                                                        <?= ucfirst($scambio['stato_scambio']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-success"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#modal-dettagli-<?= $scambio['id_scambio'] ?>">
                                                        <?= $scambio['carte_offerte'] ?> carte
                                                    </button>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-info"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#modal-dettagli-<?= $scambio['id_scambio'] ?>">
                                                        <?= $scambio['carte_richieste'] ?> carte
                                                    </button>
                                                </td>
                                                <td>
                                                    <?php if ($scambio['stato_scambio'] === 'proposto'): ?>
                                                        <form method="post" style="display:inline">
                                                            <input type="hidden" name="id_scambio" value="<?= $scambio['id_scambio'] ?>">
                                                            <button type="submit" name="rifiuta" class="btn btn-danger btn-sm"
                                                                    onclick="return confirm('Sei sicuro di voler annullare questo scambio?')">
                                                                <i class="bi bi-x-circle"></i> Annulla
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <button class="btn btn-info btn-sm"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#modal-dettagli-<?= $scambio['id_scambio'] ?>">
                                                        <i class="bi bi-eye"></i> Dettagli
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Modal per dettagli scambi -->
                                <?php foreach ($miei_scambi as $scambio): ?>
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
                                                            <h6 class="text-success">Carte Offerte:</h6>
                                                            <?php foreach ($dettagli['carte_offerte_dettagli'] as $carta): ?>
                                                                <div class="mb-2 p-2 border rounded">
                                                                    <strong><?= htmlspecialchars($carta['nome_oggetto']) ?></strong>
                                                                    <span class="badge bg-secondary">x<?= $carta['quantita_scambio'] ?></span>
                                                                    <?php if ($carta['nome_rarita']): ?>
                                                                        <br><small style="color: <?= $carta['colore'] ?>"><?= htmlspecialchars($carta['nome_rarita']) ?></small>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endforeach; ?>
                                                            <p><strong>Valore Totale: €<?= number_format($dettagli['valore_offerto'], 2) ?></strong></p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <h6 class="text-info">Carte Richieste:</h6>
                                                            <?php foreach ($dettagli['carte_richieste_dettagli'] as $carta): ?>
                                                                <div class="mb-2 p-2 border rounded">
                                                                    <strong><?= htmlspecialchars($carta['nome_oggetto']) ?></strong>
                                                                    <span class="badge bg-secondary">x<?= $carta['quantita_scambio'] ?></span>
                                                                    <?php if ($carta['nome_rarita']): ?>
                                                                        <br><small style="color: <?= $carta['colore'] ?>"><?= htmlspecialchars($carta['nome_rarita']) ?></small>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endforeach; ?>
                                                            <p><strong>Valore Totale: €<?= number_format($dettagli['valore_richiesto'], 2) ?></strong></p>
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
            </div>
        </div>
    </main>

    <script src="<?php echo (defined('BASE_URL') ? BASE_URL : ''); ?>/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let carteOfferte = [];
            let carteRichieste = [];

            // Gestione filtro categoria per carte offerte
            document.getElementById('filtro-categoria-offerte').addEventListener('change', function() {
                const categoriaId = this.value;
                const carteItems = document.querySelectorAll('.carta-item');

                carteItems.forEach(item => {
                    if (!categoriaId || item.dataset.categoria === categoriaId) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });

            // Gestione selezione carte offerte
            document.querySelectorAll('.carta-offerta').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const cardId = parseInt(this.value);
                    const quantitaInput = document.querySelector(`input.quantita-offerta[data-card-id="${cardId}"]`);

                    if (this.checked) {
                        quantitaInput.style.display = 'inline-block';
                        aggiungiCartaOfferta(cardId);
                    } else {
                        quantitaInput.style.display = 'none';
                        rimuoviCartaOfferta(cardId);
                    }
                    aggiornaValoreOfferto();
                });
            });

            // Gestione quantità carte offerte
            document.querySelectorAll('.quantita-offerta').forEach(input => {
                input.addEventListener('change', function() {
                    const cardId = parseInt(this.dataset.cardId);
                    aggiornaQuantitaOfferta(cardId, parseInt(this.value));
                    aggiornaValoreOfferto();
                });
            });

            // Ricerca carte richieste
            let searchTimeout;
            document.getElementById('cerca-carte').addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const termine = this.value.trim();

                if (termine.length < 2) {
                    document.getElementById('risultati-ricerca').innerHTML =
                        '<p class="text-muted text-center">Digita almeno 2 caratteri per cercare.</p>';
                    return;
                }

                searchTimeout = setTimeout(() => {
                    cercaCarte(termine);
                }, 300);
            });

            // Funzioni helper
            function aggiungiCartaOfferta(cardId) {
                const carta = trovaCartaNellaCollezione(cardId);
                if (carta) {
                    carteOfferte.push({
                        fk_oggetto: cardId,
                        quantita: 1,
                        nome: carta.nome,
                        valore: carta.valore
                    });
                }
            }

            function rimuoviCartaOfferta(cardId) {
                carteOfferte = carteOfferte.filter(carta => carta.fk_oggetto !== cardId);
            }

            function aggiornaQuantitaOfferta(cardId, quantita) {
                const carta = carteOfferte.find(c => c.fk_oggetto === cardId);
                if (carta) {
                    carta.quantita = quantita;
                }
            }

            function trovaCartaNellaCollezione(cardId) {
                const item = document.querySelector(`[data-id="${cardId}"]`);
                if (item) {
                    const nome = item.querySelector('strong').textContent;
                    const valoreEl = item.querySelector('.text-success');
                    const valore = valoreEl ? parseFloat(valoreEl.textContent.replace('€', '').replace(',', '.')) : 0;
                    return { nome, valore };
                }
                return null;
            }

            function aggiornaValoreOfferto() {
                const totale = carteOfferte.reduce((sum, carta) => {
                    return sum + (carta.valore * carta.quantita);
                }, 0);
                document.getElementById('valore-offerto').textContent = '€' + totale.toFixed(2);
            }

            function aggiornaValoreRichiesto() {
                const totale = carteRichieste.reduce((sum, carta) => {
                    return sum + (carta.valore * carta.quantita);
                }, 0);
                document.getElementById('valore-richiesto').textContent = '€' + totale.toFixed(2);
            }

            async function cercaCarte(termine) {
                try {
                    const response = await fetch('<?php echo (defined('BASE_URL') ? BASE_URL : ''); ?>/api/cerca_carte.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ termine: termine })
                    });

                    const risultati = await response.json();
                    mostraRisultatiRicerca(risultati);
                } catch (error) {
                    console.error('Errore ricerca:', error);
                    document.getElementById('risultati-ricerca').innerHTML =
                        '<p class="text-danger">Errore durante la ricerca.</p>';
                }
            }

            function mostraRisultatiRicerca(risultati) {
                const container = document.getElementById('risultati-ricerca');

                if (risultati.length === 0) {
                    container.innerHTML = '<p class="text-muted text-center">Nessuna carta trovata.</p>';
                    return;
                }

                let html = '';
                risultati.forEach(carta => {
                    html += `
                <div class="carta-ricerca mb-2 p-2 border rounded" style="cursor: pointer;"
                     onclick="selezionaCartaRichiesta(${carta.id_oggetto}, '${carta.nome_oggetto}', ${carta.valore_stimato || 0})">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${carta.nome_oggetto}</strong>
                            ${carta.nome_rarita ? `<span class="badge" style="background-color: ${carta.colore}">${carta.nome_rarita}</span>` : ''}
                            <br><small class="text-muted">${carta.nome_categoria}</small>
                            ${carta.valore_stimato ? `<small class="text-success"> - €${parseFloat(carta.valore_stimato).toFixed(2)}</small>` : ''}
                        </div>
                        <button class="btn btn-sm btn-primary">
                            <i class="bi bi-plus"></i> Aggiungi
                        </button>
                    </div>
                </div>
            `;
                });

                container.innerHTML = html;
            }

            function selezionaCartaRichiesta(id, nome, valore) {
                // Verifica se già selezionata
                if (carteRichieste.some(carta => carta.fk_oggetto === id)) {
                    alert('Carta già selezionata!');
                    return;
                }

                const quantita = prompt(`Quante copie di "${nome}" desideri?`, '1');
                if (quantita && parseInt(quantita) > 0) {
                    carteRichieste.push({
                        fk_oggetto: id,
                        nome: nome,
                        quantita: parseInt(quantita),
                        valore: valore || 0
                    });

                    aggiornaListaCarteRichieste();
                    aggiornaValoreRichiesto();
                }
            }

            function rimuoviCartaRichiesta(index) {
                carteRichieste.splice(index, 1);
                aggiornaListaCarteRichieste();
                aggiornaValoreRichiesto();
            }

            function aggiornaListaCarteRichieste() {
                const container = document.getElementById('lista-carte-richieste');

                if (carteRichieste.length === 0) {
                    container.innerHTML = '<p class="text-muted">Nessuna carta selezionata</p>';
                    return;
                }

                let html = '';
                carteRichieste.forEach((carta, index) => {
                    html += `
                <div class="mb-2 p-2 border rounded d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${carta.nome}</strong>
                        <span class="badge bg-secondary">x${carta.quantita}</span>
                        ${carta.valore > 0 ? `<small class="text-success">€${(carta.valore * carta.quantita).toFixed(2)}</small>` : ''}
                    </div>
                    <button class="btn btn-sm btn-danger" onclick="rimuoviCartaRichiesta(${index})">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            `;
                });

                container.innerHTML = html;
            }

            // Submit form
            document.getElementById('form-crea-scambio').addEventListener('submit', function(e) {
                if (carteOfferte.length === 0 || carteRichieste.length === 0) {
                    e.preventDefault();
                    alert('Devi selezionare almeno una carta da offrire e una da richiedere!');
                    return;
                }

                document.getElementById('input_carte_offerte').value = JSON.stringify(carteOfferte);
                document.getElementById('input_carte_richieste').value = JSON.stringify(carteRichieste);
            });

            // Rendi le funzioni globali per onclick
            window.selezionaCartaRichiesta = selezionaCartaRichiesta;
            window.rimuoviCartaRichiesta = rimuoviCartaRichiesta;
        });
    </script>

<?php include 'footer.php'; ?>