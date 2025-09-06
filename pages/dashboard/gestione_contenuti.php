<?php
// Configurazione percorsi
$base_path = '/Applications/MAMP/htdocs/LTDW-project';
$include_path = $base_path . '/include';

// Include le configurazioni necessarie
require_once $include_path . '/session_manager.php';
require_once $include_path . '/config.inc.php';

// Verifica che sia un admin
SessionManager::requireLogin();

// Determina quale sezione gestire
$sezione_corrente = $_GET['sezione'] ?? 'homepage';
$sezioni_disponibili = ['homepage', 'about', 'faq'];

if (!in_array($sezione_corrente, $sezioni_disponibili)) {
    $sezione_corrente = 'homepage';
}

// Gestione dell'aggiornamento del contenuto
$messaggio = '';
$tipo_messaggio = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aggiorna_contenuti'])) {
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=boxomnia;charset=utf8mb4', 'admin', 'admin');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Lista di tutti i contenuti da aggiornare in base alla sezione
        $contenuti_da_aggiornare = [];

        switch ($sezione_corrente) {
            case 'homepage':
                $contenuti_da_aggiornare = [
                        'testo_benvenuto', 'titolo_mystery_box', 'titolo_funko_pop',
                        'titolo_community', 'avviso_promozioni', 'community_classifica_titolo',
                        'community_scambi_titolo', 'community_collezione_titolo',
                        'community_classifica_desc', 'community_scambi_desc', 'community_collezione_desc'
                ];
                break;
            case 'about':
                $contenuti_da_aggiornare = [
                        'about_titolo_principale', 'about_paragrafo_intro', 'about_storia_titolo',
                        'about_storia_testo', 'about_cosa_troverai_titolo', 'about_cosa_troverai_testo',
                        'about_valori_titolo', 'about_valori_testo', 'about_community_titolo', 'about_community_testo'
                ];
                break;
            case 'faq':
                $contenuti_da_aggiornare = [
                        'faq_titolo_pagina', 'faq_sottotitolo_pagina', 'faq_intro_titolo', 'faq_intro_testo',
                        'faq_q1', 'faq_a1', 'faq_q2', 'faq_a2', 'faq_q3', 'faq_a3', 'faq_q4', 'faq_a4',
                        'faq_q5', 'faq_a5', 'faq_q6', 'faq_a6', 'faq_q7', 'faq_a7', 'faq_q8', 'faq_a8',
                        'faq_contatti_titolo', 'faq_contatti_testo'
                ];
                break;
        }

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
        $messaggio = "Tutti i contenuti della sezione " . strtoupper($sezione_corrente) . " sono stati aggiornati con successo!";
        $tipo_messaggio = "success";

    } catch (Exception $e) {
        $pdo->rollBack();
        $messaggio = "Errore durante l'aggiornamento: " . $e->getMessage();
        $tipo_messaggio = "error";
    }
}

// Recupera tutti i contenuti attuali
try {
    $pdo = new PDO('mysql:host=localhost;dbname=boxomnia;charset=utf8mb4', 'admin', 'admin');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Recupera contenuti in base alla sezione corrente
    $contenuti_query = [];
    switch ($sezione_corrente) {
        case 'homepage':
            $contenuti_query = [
                    'testo_benvenuto', 'titolo_mystery_box', 'titolo_funko_pop',
                    'titolo_community', 'avviso_promozioni', 'community_classifica_titolo',
                    'community_scambi_titolo', 'community_collezione_titolo',
                    'community_classifica_desc', 'community_scambi_desc', 'community_collezione_desc'
            ];
            break;
        case 'about':
            $contenuti_query = [
                    'about_titolo_principale', 'about_paragrafo_intro', 'about_storia_titolo',
                    'about_storia_testo', 'about_cosa_troverai_titolo', 'about_cosa_troverai_testo',
                    'about_valori_titolo', 'about_valori_testo', 'about_community_titolo', 'about_community_testo'
            ];
            break;
        case 'faq':
            $contenuti_query = [
                    'faq_titolo_pagina', 'faq_sottotitolo_pagina', 'faq_intro_titolo', 'faq_intro_testo',
                    'faq_q1', 'faq_a1', 'faq_q2', 'faq_a2', 'faq_q3', 'faq_a3', 'faq_q4', 'faq_a4',
                    'faq_q5', 'faq_a5', 'faq_q6', 'faq_a6', 'faq_q7', 'faq_a7', 'faq_q8', 'faq_a8',
                    'faq_contatti_titolo', 'faq_contatti_testo'
            ];
            break;
    }

    $placeholders = str_repeat('?,', count($contenuti_query) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT id_contenuto, testo_contenuto, data_modifica 
        FROM contenuti_modificabili 
        WHERE id_contenuto IN ($placeholders)
        ORDER BY data_modifica DESC
    ");
    $stmt->execute($contenuti_query);
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
    $contenuti_default = [];
    switch ($sezione_corrente) {
        case 'homepage':
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
            break;
        case 'about':
            $contenuti_default = [
                    'about_titolo_principale' => 'Chi Siamo',
                    'about_paragrafo_intro' => 'Benvenuto nel mondo delle <strong>Mystery Box</strong>, degli accessori e dei <strong>Funko POP</strong> esclusivi! Siamo appassionati di sorprese, collezionismo e momenti indimenticabili. La nostra missione è trasformare ogni acquisto in un\'esperienza unica!',
                    'about_storia_titolo' => 'LA NOSTRA STORIA',
                    'about_storia_testo' => 'Tutto è iniziato con la passione per il collezionismo e la voglia di condividere la gioia della sorpresa e gli oggetti trovati. Da allora, abbiamo creato un assortimento di <em>Mystery Box</em>, <em>Funko POP</em> e accessori ispirati a fumetti, anime, videogiochi e cultura pop.',
                    'about_cosa_troverai_titolo' => 'COSA TROVERAI',
                    'about_cosa_troverai_testo' => '<em>Mystery Box</em> con oggetti esclusivi e limitati, accessori di alta qualità per fan e collezionisti, <em>Funko POP</em> ufficiali e selezionati con cura. Ogni box è una sorpresa che racconta una storia unica nel mondo del collezionismo. Inoltre non perdere la sezione <em>Community</em>: gestisci le tue carte, scambia e sfida gli altri collezionisti!',
                    'about_valori_titolo' => 'I NOSTRI VALORI',
                    'about_valori_testo' => 'Offriamo solo prodotti originali, con attenzione alla qualità, alla sicurezza e al servizio clienti. Crediamo che ogni box debba raccontare una storia… e che la <em>sorpresa</em> sia metà del divertimento!',
                    'about_community_titolo' => 'COMMUNITY',
                    'about_community_testo' => 'Non siamo solo un negozio, siamo una comunità di collezionisti. Crediamo che la passione per gli oggetti di collezionismo sia ancora più bella quando viene condivisa. Per questo, abbiamo creato una sezione dove puoi portare il tuo hobby a un livello completamente nuovo. E ricorda: crescere insieme è bello, ma farlo con <em>rispetto</em> è fondamentale!'
            ];
            break;
        case 'faq':
            $contenuti_default = [
                    'faq_titolo_pagina' => 'FAQ Pagamenti',
                    'faq_sottotitolo_pagina' => 'Domande Frequenti sui Metodi di Pagamento',
                    'faq_intro_titolo' => 'Centro Assistenza Pagamenti',
                    'faq_intro_testo' => 'Trova le risposte alle domande più comuni sui nostri metodi di pagamento. Se non trovi quello che cerchi, contatta il nostro supporto clienti.',
                    'faq_q1' => 'I miei dati di pagamento sono sicuri?',
                    'faq_a1' => '<strong>Assolutamente sì!</strong> La sicurezza dei tuoi dati è la nostra priorità assoluta. Non memorizziamo mai i dati delle tue carte di credito sui nostri server. Tutti i pagamenti vengono elaborati tramite gateway sicuri certificati PCI DSS con crittografia SSL 256-bit.',
                    'faq_q2' => 'Posso modificare il metodo di pagamento dopo aver effettuato l\'ordine?',
                    'faq_a2' => 'Una volta confermato l\'ordine e processato il pagamento, <strong>non è possibile modificare il metodo di pagamento</strong>. Tuttavia, se l\'ordine è ancora in fase di elaborazione (entro 30 minuti dall\'acquisto), puoi contattare immediatamente il nostro supporto clienti.',
                    'faq_q3' => 'Cosa succede se il pagamento non va a buon fine?',
                    'faq_a3' => 'In caso di pagamento non riuscito, <strong>riceverai una notifica immediata</strong> via email con i dettagli dell\'errore. Il tuo ordine rimarrà in sospeso per 24 ore, durante le quali potrai accedere al tuo account e riprovare il pagamento.',
                    'faq_q4' => 'Accettate pagamenti rateali?',
                    'faq_a4' => 'Attualmente <strong>non offriamo pagamenti rateali diretti</strong> sul nostro sito. Tuttavia, puoi utilizzare servizi esterni come <strong>PayPal Pay in 4</strong> (se disponibile al checkout).',
                    'faq_q5' => 'Quanto tempo ci vuole per elaborare il pagamento?',
                    'faq_a5' => 'I tempi di elaborazione variano per metodo: <strong>Carte di credito/debito:</strong> Immediato (max 2-3 minuti), <strong>PayPal:</strong> Immediato, <strong>Bonifico bancario:</strong> 1-3 giorni lavorativi.',
                    'faq_q6' => 'Ci sono commissioni aggiuntive sui pagamenti?',
                    'faq_a6' => '<strong>No, tutti i nostri metodi di pagamento sono gratuiti!</strong> Non applichiamo commissioni aggiuntive su nessun metodo di pagamento accettato.',
                    'faq_q7' => 'Posso pagare con carte prepagate?',
                    'faq_a7' => '<strong>Sì, accettiamo carte prepagate</strong> Visa, Mastercard e American Express, purché siano abilitate per acquisti online e abbiano credito sufficiente.',
                    'faq_q8' => 'Come posso richiedere un rimborso?',
                    'faq_a8' => 'Per richiedere un rimborso, contatta il nostro <strong>servizio clienti entro 14 giorni</strong> dall\'acquisto. I rimborsi vengono elaborati utilizzando lo stesso metodo di pagamento utilizzato per l\'acquisto.',
                    'faq_contatti_titolo' => 'Non hai trovato la risposta che cercavi?',
                    'faq_contatti_testo' => 'Il nostro team di supporto è disponibile 7 giorni su 7 per aiutarti con qualsiasi domanda sui pagamenti.'
            ];
            break;
    }

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

// Configurazione delle sezioni per l'organizzazione dell'interfaccia
function getSezioniConfig($sezione) {
    switch ($sezione) {
        case 'homepage':
            return [
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
        case 'about':
            return [
                    'Intestazione Pagina' => [
                            'about_titolo_principale' => ['Titolo Principale', 'text', 100],
                            'about_paragrafo_intro' => ['Paragrafo Introduttivo', 'textarea', 600]
                    ],
                    'La Nostra Storia' => [
                            'about_storia_titolo' => ['Titolo Sezione Storia', 'text', 100],
                            'about_storia_testo' => ['Testo Storia', 'textarea', 800]
                    ],
                    'Cosa Troverai' => [
                            'about_cosa_troverai_titolo' => ['Titolo Sezione', 'text', 100],
                            'about_cosa_troverai_testo' => ['Testo Descrizione', 'textarea', 800]
                    ],
                    'I Nostri Valori' => [
                            'about_valori_titolo' => ['Titolo Sezione', 'text', 100],
                            'about_valori_testo' => ['Testo Valori', 'textarea', 600]
                    ],
                    'Community' => [
                            'about_community_titolo' => ['Titolo Sezione Community', 'text', 100],
                            'about_community_testo' => ['Testo Community', 'textarea', 800]
                    ]
            ];
        case 'faq':
            return [
                    'Intestazione Pagina' => [
                            'faq_titolo_pagina' => ['Titolo Pagina', 'text', 100],
                            'faq_sottotitolo_pagina' => ['Sottotitolo Pagina', 'text', 200],
                            'faq_intro_titolo' => ['Titolo Introduzione', 'text', 100],
                            'faq_intro_testo' => ['Testo Introduzione', 'textarea', 400]
                    ],
                    'Domande e Risposte 1-4' => [
                            'faq_q1' => ['Domanda 1', 'text', 200],
                            'faq_a1' => ['Risposta 1', 'textarea', 1000],
                            'faq_q2' => ['Domanda 2', 'text', 200],
                            'faq_a2' => ['Risposta 2', 'textarea', 800],
                            'faq_q3' => ['Domanda 3', 'text', 200],
                            'faq_a3' => ['Risposta 3', 'textarea', 800],
                            'faq_q4' => ['Domanda 4', 'text', 200],
                            'faq_a4' => ['Risposta 4', 'textarea', 600]
                    ],
                    'Domande e Risposte 5-8' => [
                            'faq_q5' => ['Domanda 5', 'text', 200],
                            'faq_a5' => ['Risposta 5', 'textarea', 800],
                            'faq_q6' => ['Domanda 6', 'text', 200],
                            'faq_a6' => ['Risposta 6', 'textarea', 600],
                            'faq_q7' => ['Domanda 7', 'text', 200],
                            'faq_a7' => ['Risposta 7', 'textarea', 600],
                            'faq_q8' => ['Domanda 8', 'text', 200],
                            'faq_a8' => ['Risposta 8', 'textarea', 800]
                    ],
                    'Sezione Contatti' => [
                            'faq_contatti_titolo' => ['Titolo Contatti', 'text', 200],
                            'faq_contatti_testo' => ['Testo Contatti', 'textarea', 400]
                    ]
            ];
        default:
            return [];
    }
}

$sezioni = getSezioniConfig($sezione_corrente);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Contenuti <?= ucfirst($sezione_corrente); ?> - BoxOmnia Admin</title>
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

        .btn-secondary, .btn-outline-secondary, .btn-outline-warning {
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

        .btn-sm {
            padding: 8px 16px;
            font-size: 0.875rem;
        }

        .section-tabs {
            margin-bottom: 2rem;
        }

        .section-tabs .nav-link {
            border-radius: 25px;
            margin-right: 10px;
            padding: 10px 20px;
        }

        .section-tabs .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: #667eea;
        }

        .preview-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-left: 4px solid #667eea;
            padding: 1.5rem;
            margin: 1rem 0;
            border-radius: 8px;
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
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="./gestione_contenuti.php">Gestione Contenuti</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= ucfirst($sezione_corrente); ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="admin-container">
    <!-- Tab di selezione sezione -->
    <div class="section-tabs">
        <ul class="nav nav-pills">
            <li class="nav-item">
                <a class="nav-link <?= $sezione_corrente === 'homepage' ? 'active' : ''; ?>"
                   href="?sezione=homepage">
                    <i class="bi bi-house me-1"></i>Homepage
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $sezione_corrente === 'about' ? 'active' : ''; ?>"
                   href="?sezione=about">
                    <i class="bi bi-info-circle me-1"></i>About
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $sezione_corrente === 'faq' ? 'active' : ''; ?>"
                   href="?sezione=faq">
                    <i class="bi bi-question-circle me-1"></i>FAQ
                </a>
            </li>
        </ul>
    </div>

    <form method="POST" action="?sezione=<?= $sezione_corrente; ?>">
        <!-- Barra di salvataggio fissa -->
        <div class="sticky-save-bar">
            <div class="row align-items-center">
                <div class="col-md-7">
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
                <div class="col-md-5 text-end">
                    <button type="button" class="btn btn-outline-warning btn-sm me-1" onclick="resetToDefaults()" title="Ripristina ai valori di fabbrica">
                        <i class="bi bi-arrow-clockwise me-1"></i>Default
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm me-2" onclick="resetAllForms()" title="Ripristina ai valori del database">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Ripristina
                    </button>
                    <button type="submit" name="aggiorna_contenuti" class="btn btn-primary">
                        <i class="bi bi-floppy me-1"></i>Salva Modifiche
                    </button>
                </div>
            </div>
        </div>

        <!-- Anteprima in base alla sezione -->
        <?php if ($sezione_corrente === 'homepage'): ?>
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

                        <div class="row">
                            <div class="col-md-4">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <i class="bi bi-chart-line text-primary fs-1"></i>
                                        <h6 id="preview-community-classifica-titolo" class="mt-2"><?= htmlspecialchars($contenuti_attuali['community_classifica_titolo']); ?></h6>
                                        <p id="preview-community-classifica-desc" class="small text-muted"><?= htmlspecialchars($contenuti_attuali['community_classifica_desc']); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <i class="bi bi-exchange text-success fs-1"></i>
                                        <h6 id="preview-community-scambi-titolo" class="mt-2"><?= htmlspecialchars($contenuti_attuali['community_scambi_titolo']); ?></h6>
                                        <p id="preview-community-scambi-desc" class="small text-muted"><?= htmlspecialchars($contenuti_attuali['community_scambi_desc']); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <i class="bi bi-collection text-warning fs-1"></i>
                                        <h6 id="preview-community-collezione-titolo" class="mt-2"><?= htmlspecialchars($contenuti_attuali['community_collezione_titolo']); ?></h6>
                                        <p id="preview-community-collezione-desc" class="small text-muted"><?= htmlspecialchars($contenuti_attuali['community_collezione_desc']); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif ($sezione_corrente === 'about'): ?>
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">
                        <i class="bi bi-eye me-2"></i>
                        Anteprima Pagina About
                    </h4>
                </div>
                <div class="card-body">
                    <div class="preview-section">
                        <h1 id="preview-about-titolo-principale" class="mb-4"><?= htmlspecialchars($contenuti_attuali['about_titolo_principale']); ?></h1>
                        <p id="preview-about-paragrafo-intro" class="lead"><?= htmlspecialchars_decode($contenuti_attuali['about_paragrafo_intro']); ?></p>

                        <hr class="my-4">

                        <h2 id="preview-about-storia-titolo"><?= htmlspecialchars($contenuti_attuali['about_storia_titolo']); ?></h2>
                        <p id="preview-about-storia-testo"><?= htmlspecialchars_decode($contenuti_attuali['about_storia_testo']); ?></p>

                        <h2 id="preview-about-cosa-troverai-titolo"><?= htmlspecialchars($contenuti_attuali['about_cosa_troverai_titolo']); ?></h2>
                        <p id="preview-about-cosa-troverai-testo"><?= htmlspecialchars_decode($contenuti_attuali['about_cosa_troverai_testo']); ?></p>

                        <h2 id="preview-about-valori-titolo"><?= htmlspecialchars($contenuti_attuali['about_valori_titolo']); ?></h2>
                        <p id="preview-about-valori-testo"><?= htmlspecialchars_decode($contenuti_attuali['about_valori_testo']); ?></p>

                        <h2 id="preview-about-community-titolo"><?= htmlspecialchars($contenuti_attuali['about_community_titolo']); ?></h2>
                        <p id="preview-about-community-testo"><?= htmlspecialchars_decode($contenuti_attuali['about_community_testo']); ?></p>
                    </div>
                </div>
            </div>
        <?php elseif ($sezione_corrente === 'faq'): ?>
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">
                        <i class="bi bi-eye me-2"></i>
                        Anteprima Pagina FAQ
                    </h4>
                </div>
                <div class="card-body">
                    <div class="preview-section">
                        <div class="text-center mb-4">
                            <h1 id="preview-faq-titolo-pagina"><?= htmlspecialchars($contenuti_attuali['faq_titolo_pagina']); ?></h1>
                            <p id="preview-faq-sottotitolo-pagina" class="lead"><?= htmlspecialchars($contenuti_attuali['faq_sottotitolo_pagina']); ?></p>
                        </div>

                        <div class="alert alert-info">
                            <h4 id="preview-faq-intro-titolo"><?= htmlspecialchars($contenuti_attuali['faq_intro_titolo']); ?></h4>
                            <p id="preview-faq-intro-testo"><?= htmlspecialchars($contenuti_attuali['faq_intro_testo']); ?></p>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h6 id="preview-faq-q1"><?= htmlspecialchars($contenuti_attuali['faq_q1']); ?></h6>
                                    </div>
                                    <div class="card-body">
                                        <p id="preview-faq-a1" class="small"><?= htmlspecialchars_decode($contenuti_attuali['faq_a1']); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h6 id="preview-faq-q2"><?= htmlspecialchars($contenuti_attuali['faq_q2']); ?></h6>
                                    </div>
                                    <div class="card-body">
                                        <p id="preview-faq-a2" class="small"><?= htmlspecialchars_decode($contenuti_attuali['faq_a2']); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-success text-center">
                            <h5 id="preview-faq-contatti-titolo"><?= htmlspecialchars($contenuti_attuali['faq_contatti_titolo']); ?></h5>
                            <p id="preview-faq-contatti-testo"><?= htmlspecialchars($contenuti_attuali['faq_contatti_testo']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

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
                                            rows="<?= $max_length > 400 ? 5 : ($max_length > 200 ? 4 : 3); ?>"
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
                                <li><?= $sezione_corrente === 'homepage' ? 'Usa emoji per rendere più accattivanti i titoli' : 'Mantieni il tono coerente con il brand'; ?></li>
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
                                <li><?= $sezione_corrente === 'faq' ? 'Per le FAQ puoi usare HTML semplice' : 'Evita caratteri speciali non necessari'; ?></li>
                                <li><?= $sezione_corrente === 'about' ? 'Usa &lt;strong&gt; e &lt;em&gt; per enfasi' : 'Non inserire codice HTML'; ?></li>
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
                    <?php
                    $link_anteprima = '';
                    switch ($sezione_corrente) {
                        case 'homepage':
                            $link_anteprima = '../pages/home_utente.php';
                            break;
                        case 'about':
                            $link_anteprima = '../pages/about.php';
                            break;
                        case 'faq':
                            $link_anteprima = '../pages/domande_frequenti.php';
                            break;
                    }
                    ?>
                    <a href="<?= $link_anteprima; ?>" class="btn btn-outline-primary" target="_blank">
                        <i class="bi bi-eye me-1"></i>Visualizza Pagina Live
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
    // Contenuti originali per il ripristino (dal database)
    const contenutiOriginali = <?= json_encode($contenuti_attuali); ?>;

    // Contenuti di default (fallback) in base alla sezione
    const contenutiDefault = <?= json_encode($contenuti_default); ?>;

    // Aggiorna anteprima in tempo reale
    function updatePreview(chiave, valore) {
        const previewElement = document.getElementById('preview-' + chiave.replace(/_/g, '-'));
        if (previewElement) {
            // Per contenuti che supportano HTML (About e FAQ)
            if (['about', 'faq'].includes('<?= $sezione_corrente; ?>') && valore.includes('<')) {
                previewElement.innerHTML = valore || '[Testo mancante]';
            } else {
                previewElement.textContent = valore || '[Testo mancante]';
            }
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

    // Ripristina tutti i form ai valori originali (dal database)
    function resetAllForms() {
        if (confirm('Sei sicuro di voler ripristinare tutti i contenuti ai valori originali dal database? Le modifiche non salvate andranno perse.')) {
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

    // Ripristina tutti i form ai valori di default di fabbrica
    function resetToDefaults() {
        if (confirm('Sei sicuro di voler ripristinare tutti i contenuti ai valori di default di fabbrica? Questa azione sovrascriverà tutti i contenuti attuali.')) {
            Object.keys(contenutiDefault).forEach(function(chiave) {
                const elemento = document.getElementById(chiave);
                if (elemento) {
                    elemento.value = contenutiDefault[chiave];
                    updatePreview(chiave, contenutiDefault[chiave]);
                    updateCharCount(chiave, contenutiDefault[chiave], elemento.maxLength);
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
            if (alert && !alert.classList.contains('alert-info') && !alert.classList.contains('alert-success')) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 5000);
    });
</script>
</body>
</html>