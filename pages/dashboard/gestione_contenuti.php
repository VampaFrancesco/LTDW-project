<?php
// Configurazione percorsi
$base_path = '/Applications/MAMP/htdocs/LTDW-project';
$include_path = $base_path . '/include';

// Include le configurazioni necessarie
require_once $include_path . '/session_manager.php';
require_once $include_path . '/config.inc.php';

// Verifica che sia un admin
SessionManager::requireLogin();

// Gestione dell'aggiornamento del contenuto
$messaggio = '';
$tipo_messaggio = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aggiorna_contenuti'])) {
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=boxomnia', 'admin', 'admin');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Lista di tutti i contenuti da aggiornare
        $contenuti_da_aggiornare = [
                'testo_benvenuto', 'titolo_mystery_box', 'titolo_funko_pop',
                'titolo_community', 'avviso_promozioni', 'community_classifica_titolo',
                'community_scambi_titolo', 'community_collezione_titolo',
                'community_classifica_desc', 'community_scambi_desc', 'community_collezione_desc'
        ];

        $pdo->beginTransaction();

        foreach ($contenuti_da_aggiornare as $chiave) {
            if (isset($_POST[$chiave])) {
                $nuovo_testo = trim($_POST[$chiave]);

                if (empty($nuovo_testo)) {
                    throw new Exception("Il campo '{$chiave}' non può essere vuoto!");
                }

                $stmt = $pdo->prepare("
                    INSERT INTO contenuti_modificabili (id_contenuto, testo_contenuto, data_modifica) 
                    VALUES (?, ?, NOW()) 
                    ON DUPLICATE KEY UPDATE 
                        testo_contenuto = VALUES(testo_contenuto),
                        data_modifica = NOW()
                ");
                $stmt->execute([$chiave, $nuovo_testo]);
            }
        }

        $pdo->commit();
        $messaggio = "Tutti i contenuti sono stati aggiornati con successo!";
        $tipo_messaggio = "success";

    } catch (Exception $e) {
        $pdo->rollBack();
        $messaggio = "Errore durante l'aggiornamento: " . $e->getMessage();
        $tipo_messaggio = "error";
    }
}

// Recupera tutti i contenuti attuali
try {
    $pdo = new PDO('mysql:host=localhost;dbname=boxomnia', 'admin', 'admin');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("
        SELECT id_contenuto, testo_contenuto, data_modifica 
        FROM contenuti_modificabili 
        WHERE id_contenuto IN (
            'testo_benvenuto', 'titolo_mystery_box', 'titolo_funko_pop', 
            'titolo_community', 'avviso_promozioni', 'community_classifica_titolo',
            'community_scambi_titolo', 'community_collezione_titolo',
            'community_classifica_desc', 'community_scambi_desc', 'community_collezione_desc'
        )
        ORDER BY data_modifica DESC
    ");
    $stmt->execute();
    $contenuti_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organizza i contenuti
    $contenuti_attuali = [];
    $ultima_modifica_generale = null;

    foreach ($contenuti_raw as $contenuto) {
        $contenuti_attuali[$contenuto['id_contenuto']] = $contenuto['testo_contenuto'];
        if ($ultima_modifica_generale === null || $contenuto['data_modifica'] > $ultima_modifica_generale) {
            $ultima_modifica_generale = $contenuto['data_modifica'];
        }
    }

    // Contenuti di default
    $contenuti_default = [
            'testo_benvenuto' => "Esplora l'universo delle Mystery Box, Funko Pop e Carte da Collezione. Trova il tuo prossimo tesoro e unisciti alla community più appassionata del web.",
            'titolo_mystery_box' => '✨ Nuove Mystery Box',
            'titolo_funko_pop' => '🎉 Novità Funko POP',
            'titolo_community' => '🤝 La Community di BoxOmnia',
            'avviso_promozioni' => 'È possibile visionare e approfittare degli sconti solamente nelle novità essendo promozioni a tempo limitate',
            'community_classifica_titolo' => 'Classifica',
            'community_scambi_titolo' => 'Scambi di Carte',
            'community_collezione_titolo' => 'La Mia Collezione',
            'community_classifica_desc' => 'Sfida gli altri collezionisti e scala la classifica per diventare il numero uno!',
            'community_scambi_desc' => 'Scambia le tue carte doppie e completa la tua collezione con altri appassionati.',
            'community_collezione_desc' => 'Gestisci e mostra le tue carte Pokémon e Yu-Gi-Oh! alla community.'
    ];

    // Unisce contenuti DB con default
    foreach ($contenuti_default as $chiave => $valore_default) {
        if (!isset($contenuti_attuali[$chiave])) {
            $contenuti_attuali[$chiave] = $valore_default;
        }
    }

    $ultima_modifica_text = $ultima_modifica_generale ?
            date('d/m/Y H:i:s', strtotime($ultima_modifica_generale)) :
            'Mai modificato';

} catch (PDOException $e) {
    $contenuti_attuali = $contenuti_default;
    $ultima_modifica_text = "Errore nel recupero";
}

// Definizione delle sezioni per l'organizzazione dell'interfaccia
$sezioni = [
        'Testo di Benvenuto' => [
                'testo_benvenuto' => ['Messaggio di benvenuto', 'textarea', 500]
        ],
        'Titoli delle Sezioni' => [
                'titolo_mystery_box' => ['Titolo Mystery Box', 'text', 100],
                'titolo_funko_pop' => ['Titolo Funko POP', 'text', 100],
                'titolo_community' => ['Titolo Community', 'text', 100]
        ],
        'Avvisi e Note' => [
                'avviso_promozioni' => ['Avviso Promozioni', 'textarea', 300]
        ],
        'Community - Titoli Card' => [
                'community_classifica_titolo' => ['Titolo Card Classifica', 'text', 50],
                'community_scambi_titolo' => ['Titolo Card Scambi', 'text', 50],
                'community_collezione_titolo' => ['Titolo Card Collezione', 'text', 50]
        ],
        'Community - Descrizioni Card' => [
                'community_classifica_desc' => ['Descrizione Classifica', 'textarea', 200],
                'community_scambi_desc' => ['Descrizione Scambi', 'textarea', 200],
                'community_collezione_desc' => ['Descrizione Collezione', 'textarea', 200]
        ]
];
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Contenuti Homepage - BoxOmnia Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .admin-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 2rem;
        }

        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            border: none;
        }

        .section-card .card-header {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
        }

        .btn-secondary, .btn-outline-secondary {
            border-radius: 25px;
            padding: 10px 20px;
        }

        .admin-header {
            background: white;
            padding: 1.5rem 0;
            margin-bottom: 2rem;
            border-bottom: 2px solid #e9ecef;
        }

        .breadcrumb {
            background: none;
            margin: 0;
        }

        .textarea-count {
            font-size: 0.875rem;
            color: #6c757d;
            text-align: right;
            margin-top: 5px;
        }

        .preview-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-left: 4px solid #667eea;
            padding: 1.5rem;
            margin: 1rem 0;
            border-radius: 8px;
        }

        .community-preview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .community-card-preview {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            transition: transform 0.2s;
        }

        .community-card-preview:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .sticky-save-bar {
            position: sticky;
            top: 20px;
            z-index: 1000;
            background: white;
            border-radius: 15px;
            padding: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
<!-- Header Admin -->
<div class="admin-header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h3 class="mb-0">
                    <i class="bi bi-gear-fill text-primary me-2"></i>
                    Pannello Amministrazione BoxOmnia
                </h3>
            </div>
            <div class="col-md-6 text-end">
                    <span class="text-muted">
                        <i class="bi bi-person-circle me-1"></i>
                        Utente: <strong><?= htmlspecialchars(SessionManager::get('user_nome', 'Admin')); ?></strong>
                    </span>
            </div>
        </div>

        <nav aria-label="breadcrumb" class="mt-2">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard/">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="./gestione_contenuti.php">Gestione Contenuti</a></li>
                <li class="breadcrumb-item active" aria-current="page">Homepage Completa</li>
            </ol>
        </nav>
    </div>
</div>

<div class="admin-container">
    <form method="POST" action="">
        <!-- Barra di salvataggio fissa -->
        <div class="sticky-save-bar">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <?php if (!empty($messaggio)): ?>
                        <div class="alert <?= $tipo_messaggio === 'success' ? 'alert-success' : 'alert-danger'; ?> mb-0" role="alert">
                            <i class="bi <?= $tipo_messaggio === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'; ?> me-2"></i>
                            <?= htmlspecialchars($messaggio); ?>
                        </div>
                    <?php else: ?>
                        <div class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            <strong>Ultima modifica:</strong> <?= htmlspecialchars($ultima_modifica_text); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 text-end">
                    <button type="button" class="btn btn-outline-secondary me-2" onclick="resetAllForms()">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Ripristina Tutto
                    </button>
                    <button type="submit" name="aggiorna_contenuti" class="btn btn-primary">
                        <i class="bi bi-floppy me-1"></i>Salva Tutte le Modifiche
                    </button>
                </div>
            </div>
        </div>

        <!-- Anteprima Homepage -->
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">
                    <i class="bi bi-eye me-2"></i>
                    Anteprima Homepage
                </h4>
            </div>
            <div class="card-body">
                <div class="preview-section">
                    <div class="text-center mb-4">
                        <h3 class="text-primary mb-2">
                            <i class="bi bi-person-check me-2"></i>
                            Bentornato, [Nome Utente]!
                        </h3>
                        <p id="preview-testo-benvenuto" class="lead"><?= htmlspecialchars($contenuti_attuali['testo_benvenuto']); ?></p>
                    </div>

                    <hr class="my-4">

                    <div class="row">
                        <div class="col-md-6">
                            <h5 id="preview-titolo-mystery-box" class="text-secondary">
                                <?= htmlspecialchars($contenuti_attuali['titolo_mystery_box']); ?>
                            </h5>
                            <div class="alert alert-warning small">
                                <strong>Attenzione:</strong> <span id="preview-avviso-promozioni"><?= htmlspecialchars($contenuti_attuali['avviso_promozioni']); ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h5 id="preview-titolo-funko-pop" class="text-secondary">
                                <?= htmlspecialchars($contenuti_attuali['titolo_funko_pop']); ?>
                            </h5>
                        </div>
                    </div>

                    <hr class="my-4">

                    <h5 id="preview-titolo-community" class="mb-3">
                        <?= htmlspecialchars($contenuti_attuali['titolo_community']); ?>
                    </h5>

                    <div class="community-preview">
                        <div class="community-card-preview">
                            <i class="bi bi-chart-line text-primary fs-1"></i>
                            <h6 id="preview-community-classifica-titolo" class="mt-2"><?= htmlspecialchars($contenuti_attuali['community_classifica_titolo']); ?></h6>
                            <p id="preview-community-classifica-desc" class="small text-muted"><?= htmlspecialchars($contenuti_attuali['community_classifica_desc']); ?></p>
                        </div>
                        <div class="community-card-preview">
                            <i class="bi bi-exchange text-success fs-1"></i>
                            <h6 id="preview-community-scambi-titolo" class="mt-2"><?= htmlspecialchars($contenuti_attuali['community_scambi_titolo']); ?></h6>
                            <p id="preview-community-scambi-desc" class="small text-muted"><?= htmlspecialchars($contenuti_attuali['community_scambi_desc']); ?></p>
                        </div>
                        <div class="community-card-preview">
                            <i class="bi bi-collection text-warning fs-1"></i>
                            <h6 id="preview-community-collezione-titolo" class="mt-2"><?= htmlspecialchars($contenuti_attuali['community_collezione_titolo']); ?></h6>
                            <p id="preview-community-collezione-desc" class="small text-muted"><?= htmlspecialchars($contenuti_attuali['community_collezione_desc']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sezioni di Modifica -->
        <?php foreach ($sezioni as $nome_sezione => $campi): ?>
            <div class="card section-card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-pencil-square me-2"></i>
                        <?= htmlspecialchars($nome_sezione); ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($campi as $chiave => $config): ?>
                            <?php
                            list($label, $tipo, $max_length) = $config;
                            $valore_attuale = $contenuti_attuali[$chiave] ?? '';
                            $col_class = count($campi) > 2 ? 'col-md-6' : 'col-12';
                            ?>
                            <div class="<?= $col_class; ?> mb-3">
                                <label for="<?= $chiave; ?>" class="form-label">
                                    <strong><?= htmlspecialchars($label); ?>:</strong>
                                </label>

                                <?php if ($tipo === 'textarea'): ?>
                                    <textarea
                                            class="form-control"
                                            id="<?= $chiave; ?>"
                                            name="<?= $chiave; ?>"
                                            rows="<?= $max_length > 200 ? 4 : 3; ?>"
                                            maxlength="<?= $max_length; ?>"
                                            oninput="updatePreview('<?= $chiave; ?>', this.value); updateCharCount('<?= $chiave; ?>', this.value, <?= $max_length; ?>)"
                                            required><?= htmlspecialchars($valore_attuale); ?></textarea>
                                <?php else: ?>
                                    <input
                                            type="text"
                                            class="form-control"
                                            id="<?= $chiave; ?>"
                                            name="<?= $chiave; ?>"
                                            maxlength="<?= $max_length; ?>"
                                            value="<?= htmlspecialchars($valore_attuale); ?>"
                                            oninput="updatePreview('<?= $chiave; ?>', this.value); updateCharCount('<?= $chiave; ?>', this.value, <?= $max_length; ?>)"
                                            required>
                                <?php endif; ?>

                                <div class="textarea-count">
                                    <span id="count-<?= $chiave; ?>">0</span>/<?= $max_length; ?> caratteri
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Azioni finali -->
        <div class="card">
            <div class="card-body text-center">
                <div class="row">
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <i class="bi bi-info-circle text-info fs-2"></i>
                            <h6 class="mt-2">Informazioni</h6>
                            <ul class="small text-muted text-start">
                                <li>Le modifiche sono immediate</li>
                                <li>Tutti i campi sono obbligatori</li>
                                <li>Rispetta i limiti di caratteri</li>
                                <li>L'anteprima è in tempo reale</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <i class="bi bi-lightbulb text-warning fs-2"></i>
                            <h6 class="mt-2">Suggerimenti</h6>
                            <ul class="small text-muted text-start">
                                <li>Usa emoji per rendere più accattivanti i titoli</li>
                                <li>Mantieni messaggi concisi e chiari</li>
                                <li>Testa sempre l'anteprima prima di salvare</li>
                                <li>Considera l'esperienza mobile</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <i class="bi bi-shield-check text-success fs-2"></i>
                            <h6 class="mt-2">Sicurezza</h6>
                            <ul class="small text-muted text-start">
                                <li>Evita caratteri speciali non necessari</li>
                                <li>Non inserire codice HTML</li>
                                <li>Mantieni backup dei contenuti</li>
                                <li>Verifica sempre le modifiche</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-center gap-3">
                    <a href="./gestione_contenuti.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Torna alla Gestione
                    </a>
                    <a href="../home_utente.php" class="btn btn-outline-primary" target="_blank">
                        <i class="bi bi-eye me-1"></i>Visualizza Homepage Live
                    </a>
                    <button type="submit" name="aggiorna_contenuti" class="btn btn-primary">
                        <i class="bi bi-floppy me-1"></i>Salva Tutte le Modifiche
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Contenuti originali per il ripristino
    const contenutiOriginali = <?= json_encode($contenuti_attuali); ?>;

    // Aggiorna anteprima in tempo reale
    function updatePreview(chiave, valore) {
        const previewElement = document.getElementById('preview-' + chiave.replace('_', '-'));
        if (previewElement) {
            previewElement.textContent = valore || '[Testo mancante]';
        }
    }

    // Aggiorna contatore caratteri
    function updateCharCount(chiave, valore, maxLength) {
        const countElement = document.getElementById('count-' + chiave);
        if (countElement) {
            const currentLength = valore.length;
            countElement.textContent = currentLength;

            // Cambia colore in base al limite
            if (currentLength > maxLength * 0.9) {
                countElement.style.color = '#dc3545'; // Rosso
                countElement.innerHTML = `<i class="bi bi-exclamation-triangle me-1"></i>${currentLength}`;
            } else if (currentLength > maxLength * 0.75) {
                countElement.style.color = '#ffc107'; // Giallo
                countElement.innerHTML = `<i class="bi bi-exclamation-circle me-1"></i>${currentLength}`;
            } else {
                countElement.style.color = '#6c757d'; // Grigio
                countElement.textContent = currentLength;
            }
        }
    }

    // Ripristina tutti i form
    function resetAllForms() {
        if (confirm('Sei sicuro di voler ripristinare tutti i contenuti ai valori originali? Le modifiche non salvate andranno perse.')) {
            Object.keys(contenutiOriginali).forEach(function(chiave) {
                const elemento = document.getElementById(chiave);
                if (elemento) {
                    elemento.value = contenutiOriginali[chiave];
                    updatePreview(chiave, contenutiOriginali[chiave]);
                    updateCharCount(chiave, contenutiOriginali[chiave], elemento.maxLength);
                }
            });
        }
    }

    // Conferma prima di lasciare la pagina
    let hasChanges = false;
    function checkForChanges() {
        hasChanges = false;
        Object.keys(contenutiOriginali).forEach(function(chiave) {
            const elemento = document.getElementById(chiave);
            if (elemento && elemento.value !== contenutiOriginali[chiave]) {
                hasChanges = true;
            }
        });
    }

    window.addEventListener('beforeunload', function(e) {
        checkForChanges();
        if (hasChanges) {
            e.preventDefault();
            e.returnValue = 'Hai modifiche non salvate. Sei sicuro di voler uscire?';
            return e.returnValue;
        }
    });

    // Inizializzazione al caricamento della pagina
    document.addEventListener('DOMContentLoaded', function() {
        // Inizializza contatori
        Object.keys(contenutiOriginali).forEach(function(chiave) {
            const elemento = document.getElementById(chiave);
            if (elemento) {
                updateCharCount(chiave, elemento.value, elemento.maxLength || 500);
            }
        });

        // Rimuovi flag modifiche dopo salvataggio
        const form = document.querySelector('form');
        form.addEventListener('submit', function() {
            hasChanges = false;
        });

        // Controlla modifiche ad ogni input
        document.querySelectorAll('input, textarea').forEach(function(elemento) {
            elemento.addEventListener('input', checkForChanges);
        });

        // Auto-dismissione alert
        setTimeout(function() {
            const alert = document.querySelector('.alert');
            if (alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 5000);
    });
</script>
</body>
</html>