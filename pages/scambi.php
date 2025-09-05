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

// Gestione delle azioni POST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'crea_scambio':
                creaScambio($conn, $user_id, $_POST);
                break;

            case 'modifica_scambio':
                modificaScambio($conn, $user_id, $_POST);
                break;

            case 'accetta_scambio':
                accettaScambio($conn, (int)$_POST['id_scambio'], $user_id);
                break;
        }
    } catch (Throwable $e) {
        echo "<div class='alert alert-danger'>Errore: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Funzioni per la gestione degli scambi
function creaScambio($conn, $user_id, $data) {
    try {
        $conn->autocommit(FALSE);
        
        // Inserisci nuovo scambio
        $stmt = $conn->prepare("INSERT INTO scambi (fk_utente_richiedente) VALUES (?)");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $scambio_id = $conn->insert_id;
        
        // Inserisci carte richieste
        if (!empty($data['carte_richieste'])) {
            foreach ($data['carte_richieste'] as $carta_id => $quantita) {
                if ($quantita > 0) {
                    $stmt = $conn->prepare("INSERT INTO scambio_richieste (fk_scambio, fk_oggetto, quantita_richiesta) VALUES (?, ?, ?)");
                    $stmt->bind_param("iii", $scambio_id, $carta_id, $quantita);
                    $stmt->execute();
                }
            }
        }
        
        // Inserisci carte offerte
        if (!empty($data['carte_offerte'])) {
            foreach ($data['carte_offerte'] as $carta_id => $quantita) {
                if ($quantita > 0) {
                    $stmt = $conn->prepare("INSERT INTO scambio_offerte (fk_scambio, fk_oggetto, quantita_offerta, fk_utente_offerente) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("iiii", $scambio_id, $carta_id, $quantita, $user_id);
                    $stmt->execute();
                }
            }
        }
        
        $conn->commit();
        echo "<div class='alert alert-success'>Scambio creato con successo!</div>";
    } catch (Exception $e) {
        $conn->rollback();
        echo "<div class='alert alert-danger'>Errore nella creazione dello scambio: " . $e->getMessage() . "</div>";
    }
}

function modificaScambio(mysqli $conn, int $user_id, array $data) {
    if (empty($data['id_scambio'])) {
        throw new Exception("Scambio non specificato.");
    }
    $scambio_id = (int)$data['id_scambio'];

    // Carico lo scambio
    $stmt = $conn->prepare("SELECT fk_utente_richiedente, stato_scambio FROM scambi WHERE id_scambio = ?");
    $stmt->bind_param("i", $scambio_id);
    $stmt->execute();
    $scambio = $stmt->get_result()->fetch_assoc();
    if (!$scambio) throw new Exception("Scambio inesistente.");
    if ($scambio['stato_scambio'] !== 'in_corso') throw new Exception("Lo scambio non è modificabile nello stato attuale.");

    // Solo chi NON è il creatore può controproporre
    if ((int)$scambio['fk_utente_richiedente'] === $user_id) {
        throw new Exception("Il creatore non può controproporre a se stesso.");
    }

    $conn->begin_transaction();
    try {
        // Svuoto le liste precedenti
        $stmt = $conn->prepare("DELETE FROM scambio_offerte WHERE fk_scambio = ?");
        $stmt->bind_param("i", $scambio_id);
        $stmt->execute();

        $stmt = $conn->prepare("DELETE FROM scambio_richieste WHERE fk_scambio = ?");
        $stmt->bind_param("i", $scambio_id);
        $stmt->execute();

        // Inserisco nuove liste
        if (!empty($data['carte_offerte'])) {
            $stmt = $conn->prepare("INSERT INTO scambio_offerte (fk_scambio, fk_oggetto, quantita_offerta, fk_utente_offerente) VALUES (?, ?, ?, ?)");
            foreach ($data['carte_offerte'] as $oggetto_id => $qta) {
                $qta = (int)$qta;
                if ($qta > 0) {
                    $oggetto_id = (int)$oggetto_id;
                    validaCartaSingola($conn, $oggetto_id);
                    $stmt->bind_param("iiii", $scambio_id, $oggetto_id, $qta, $user_id);
                    $stmt->execute();
                }
            }
        }

        if (!empty($data['carte_richieste'])) {
            $stmtR = $conn->prepare("INSERT INTO scambio_richieste (fk_scambio, fk_oggetto, quantita_richiesta) VALUES (?, ?, ?)");
            foreach ($data['carte_richieste'] as $oggetto_id => $qta) {
                $qta = (int)$qta;
                if ($qta > 0) {
                    $oggetto_id = (int)$oggetto_id;
                    validaCartaSingola($conn, $oggetto_id);
                    $stmtR->bind_param("iii", $scambio_id, $oggetto_id, $qta);
                    $stmtR->execute();
                }
            }
        }

        // Segno chi è l'ultimo proponente e mantengo lo scambio in corso
        $stmt = $conn->prepare("UPDATE scambi SET fk_utente_offerente = ?, stato_scambio = 'in_corso' WHERE id_scambio = ?");
        $stmt->bind_param("ii", $user_id, $scambio_id);
        $stmt->execute();

        $conn->commit();
        echo "<div class='alert alert-success'>Controproposta inviata! Il creatore ora può accettare o modificare.</div>";
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function accettaScambio(mysqli $conn, int $scambio_id, int $user_id) {
    $conn->begin_transaction();
    try {
        // Lock riga scambio
        $stmt = $conn->prepare("SELECT id_scambio, fk_utente_richiedente, COALESCE(fk_utente_offerente, fk_utente_richiedente) AS ultimo_proponente, stato_scambio
                                FROM scambi WHERE id_scambio = ? FOR UPDATE");
        $stmt->bind_param("i", $scambio_id);
        $stmt->execute();
        $scambio = $stmt->get_result()->fetch_assoc();
        if (!$scambio) throw new Exception("Scambio inesistente.");
        if ($scambio['stato_scambio'] !== 'in_corso') throw new Exception("Scambio non accettabile nello stato attuale.");

        $richiedente = (int)$scambio['fk_utente_richiedente'];
        $ultimo_proponente = (int)$scambio['ultimo_proponente'];
        
        // Determina chi è il proponente e chi la controparte
        if ($ultimo_proponente === $richiedente) {
            // Il richiedente originale è l'ultimo proponente
            $proponente = $richiedente;
            $controparte = $user_id; // Chi accetta
        } else {
            // Qualcun altro è diventato proponente
            $proponente = $ultimo_proponente;
            $controparte = ($user_id === $richiedente) ? $richiedente : $ultimo_proponente;
        }

        // Carico liste
        $offerte = fetchOfferte($conn, $scambio_id);   
        $richieste = fetchRichieste($conn, $scambio_id); 

        if (empty($offerte) && empty($richieste)) {
            throw new Exception("Lo scambio non contiene elementi.");
        }

        // Validazioni: solo Carte Singole + disponibilità sufficiente
        foreach ($offerte as $o) {
            validaCartaSingola($conn, (int)$o['fk_oggetto']);
            assertDisponibilita($conn, $proponente, (int)$o['fk_oggetto'], (int)$o['quantita_offerta'], 
                "Il proponente non possiede abbastanza copie dell'oggetto offerto.");
        }
        foreach ($richieste as $r) {
            validaCartaSingola($conn, (int)$r['fk_oggetto']);
            assertDisponibilita($conn, $controparte, (int)$r['fk_oggetto'], (int)$r['quantita_richiesta'], 
                "La controparte non possiede abbastanza copie dell'oggetto richiesto.");
        }

        // Aggiorno inventari: 
        // PROPONENTE: perde le offerte, riceve le richieste
        foreach ($offerte as $o) {
            deltaOggettoUtente($conn, $proponente, (int)$o['fk_oggetto'], -(int)$o['quantita_offerta']);
        }
        foreach ($richieste as $r) {
            deltaOggettoUtente($conn, $proponente, (int)$r['fk_oggetto'], +(int)$r['quantita_richiesta']);
        }
        
        // CONTROPARTE: riceve le offerte, perde le richieste
        foreach ($offerte as $o) {
            deltaOggettoUtente($conn, $controparte, (int)$o['fk_oggetto'], +(int)$o['quantita_offerta']);
        }
        foreach ($richieste as $r) {
            deltaOggettoUtente($conn, $controparte, (int)$r['fk_oggetto'], -(int)$r['quantita_richiesta']);
        }

        // Concludo scambio
        $stmt = $conn->prepare("UPDATE scambi SET stato_scambio = 'concluso' WHERE id_scambio = ?");
        $stmt->bind_param("i", $scambio_id);
        $stmt->execute();

        $conn->commit();
        echo "<div class='alert alert-success'>Scambio concluso con successo!</div>";
    } catch (Throwable $e) {
        $conn->rollback();
        echo "<div class='alert alert-danger'>Errore nell'accettazione dello scambio: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

function fetchOfferte(mysqli $conn, int $scambio_id): array {
    $stmt = $conn->prepare("SELECT fk_oggetto, quantita_offerta FROM scambio_offerte WHERE fk_scambio = ?");
    $stmt->bind_param("i", $scambio_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function fetchRichieste(mysqli $conn, int $scambio_id): array {
    $stmt = $conn->prepare("SELECT fk_oggetto, quantita_richiesta FROM scambio_richieste WHERE fk_scambio = ?");
    $stmt->bind_param("i", $scambio_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function validaCartaSingola(mysqli $conn, int $oggetto_id): void {
    $sql = "SELECT 1
            FROM oggetto o
            JOIN categoria_oggetto c ON c.id_categoria = o.fk_categoria_oggetto
            WHERE o.id_oggetto = ? AND c.tipo_oggetto = 'Carta Singola'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $oggetto_id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_row()) {
        throw new Exception("L'oggetto $oggetto_id non è una Carta Singola.");
    }
}

function assertDisponibilita(mysqli $conn, int $utente_id, int $oggetto_id, int $qta, string $msgIfFail): void {
    $stmt = $conn->prepare("SELECT quantita_ogg FROM oggetto_utente WHERE fk_utente = ? AND fk_oggetto = ? FOR UPDATE");
    $stmt->bind_param("ii", $utente_id, $oggetto_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $possesso = (int)($row['quantita_ogg'] ?? 0);
    if ($possesso < $qta) {
        throw new Exception($msgIfFail);
    }
}

function deltaOggettoUtente(mysqli $conn, int $utente_id, int $oggetto_id, int $delta): void {
    // Prima verifica lo stato attuale
    $stmt = $conn->prepare("SELECT quantita_ogg FROM oggetto_utente WHERE fk_utente = ? AND fk_oggetto = ?");
    $stmt->bind_param("ii", $utente_id, $oggetto_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $quantita_precedente = $result ? (int)$result['quantita_ogg'] : 0;
    
    if ($quantita_precedente === 0 && $delta > 0) {
        // Caso: utente non ha l'oggetto, ma ne riceve
        $stmt = $conn->prepare("INSERT INTO oggetto_utente (fk_utente, fk_oggetto, quantita_ogg) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $utente_id, $oggetto_id, $delta);
        $stmt->execute();
    } else if ($quantita_precedente > 0) {
        // Caso: utente ha già l'oggetto, aggiorna la quantità
        $nuova_quantita = max(0, $quantita_precedente + $delta);
        
        if ($nuova_quantita > 0) {
            $stmt = $conn->prepare("UPDATE oggetto_utente SET quantita_ogg = ? WHERE fk_utente = ? AND fk_oggetto = ?");
            $stmt->bind_param("iii", $nuova_quantita, $utente_id, $oggetto_id);
            $stmt->execute();
        } else {
            // Se la quantità va a 0, elimina la riga
            $stmt = $conn->prepare("DELETE FROM oggetto_utente WHERE fk_utente = ? AND fk_oggetto = ?");
            $stmt->bind_param("ii", $utente_id, $oggetto_id);
            $stmt->execute();
        }
    } else if ($delta < 0) {
        // Caso problematico: si sta tentando di sottrarre da un utente che non ha l'oggetto
        throw new Exception("Tentativo di sottrarre oggetto $oggetto_id da utente $utente_id che non lo possiede");
    }
}

// Query per ottenere le carte dell'utente per tipo
function getCarteUtente($conn, $user_id, $tipo) {
    $sql = "SELECT o.id_oggetto, o.nome_oggetto, ou.quantita_ogg 
            FROM oggetto o 
            JOIN oggetto_utente ou ON o.id_oggetto = ou.fk_oggetto 
            JOIN categoria_oggetto co ON o.fk_categoria_oggetto = co.id_categoria 
            WHERE ou.fk_utente = ? AND co.tipo_oggetto = 'Carta Singola' AND co.nome_categoria LIKE ?
            ORDER BY o.nome_oggetto";
    
    $stmt = $conn->prepare($sql);
    $tipo_param = '%' . $tipo . '%';
    $stmt->bind_param("is", $user_id, $tipo_param);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Query per ottenere tutte le carte disponibili per tipo
function getTutteCarteTipo($conn, $tipo) {
    $sql = "SELECT o.id_oggetto, o.nome_oggetto 
            FROM oggetto o 
            JOIN categoria_oggetto co ON o.fk_categoria_oggetto = co.id_categoria 
            WHERE co.tipo_oggetto = 'Carta Singola' AND co.nome_categoria LIKE ?
            ORDER BY o.nome_oggetto";
    
    $stmt = $conn->prepare($sql);
    $tipo_param = '%' . $tipo . '%';
    $stmt->bind_param("s", $tipo_param);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Query per ottenere scambi disponibili - solo quelli che l'utente può accettare
function getScambiDisponibili($conn, $user_id) {
    $sql = "SELECT DISTINCT s.*, u.nome, u.email, u.telefono,
                   GROUP_CONCAT(DISTINCT CONCAT(o.nome_oggetto, ' (', sr.quantita_richiesta, ')') SEPARATOR ', ') as richieste,
                   GROUP_CONCAT(DISTINCT CONCAT(o2.nome_oggetto, ' (', so.quantita_offerta, ')') SEPARATOR ', ') as offerte
            FROM scambi s
            JOIN utente u ON s.fk_utente_richiedente = u.id_utente
            LEFT JOIN scambio_richieste sr ON s.id_scambio = sr.fk_scambio
            LEFT JOIN oggetto o ON sr.fk_oggetto = o.id_oggetto
            LEFT JOIN scambio_offerte so ON s.id_scambio = so.fk_scambio
            LEFT JOIN oggetto o2 ON so.fk_oggetto = o2.id_oggetto
            WHERE s.fk_utente_richiedente != ? 
            AND s.stato_scambio = 'in_corso'
            -- Solo scambi dove l'utente corrente ha tutte le carte richieste
            AND NOT EXISTS (
                SELECT 1 FROM scambio_richieste sr2
                LEFT JOIN oggetto_utente ou ON sr2.fk_oggetto = ou.fk_oggetto AND ou.fk_utente = ?
                WHERE sr2.fk_scambio = s.id_scambio
                AND (ou.quantita_ogg IS NULL OR ou.quantita_ogg < sr2.quantita_richiesta)
            )
            GROUP BY s.id_scambio
            ORDER BY s.data_creazione DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Query per ottenere i propri scambi
function getMieiScambi($conn, $user_id) {
    $sql = "SELECT s.*, 
                   GROUP_CONCAT(CONCAT(o.nome_oggetto, ' (', sr.quantita_richiesta, ')') SEPARATOR ', ') as richieste,
                   GROUP_CONCAT(CONCAT(o2.nome_oggetto, ' (', so.quantita_offerta, ')') SEPARATOR ', ') as offerte
            FROM scambi s
            LEFT JOIN scambio_richieste sr ON s.id_scambio = sr.fk_scambio
            LEFT JOIN oggetto o ON sr.fk_oggetto = o.id_oggetto
            LEFT JOIN scambio_offerte so ON s.id_scambio = so.fk_scambio
            LEFT JOIN oggetto o2 ON so.fk_oggetto = o2.id_oggetto
            WHERE s.fk_utente_richiedente = ? OR s.fk_utente_offerente = ?
            GROUP BY s.id_scambio
            ORDER BY s.data_creazione DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Recupera i dati per la pagina
$carte_yugioh_utente = getCarteUtente($conn, $user_id, 'Yu-Gi-Oh');
$carte_pokemon_utente = getCarteUtente($conn, $user_id, 'Pokemon');
$tutte_carte_yugioh = getTutteCarteTipo($conn, 'Yu-Gi-Oh');
$tutte_carte_pokemon = getTutteCarteTipo($conn, 'Pokemon');
$scambi_disponibili = getScambiDisponibili($conn, $user_id);
$miei_scambi = getMieiScambi($conn, $user_id);

$conn->close();
?>

<link rel="stylesheet" href="scambi.css">

<main class="background-custom">
    <div>
        <div class="container">
            <div class="scambi-collection-header">
                <h1 class="fashion_taverage">Centro Scambi</h1>
            </div>

            <?php if (isset($_GET['add_status'])): ?>
                <div class="alert mt-3 <?php echo $_GET['add_status'] == 'success' ? 'alert-success' : 'alert-danger'; ?>">
                    <?php echo htmlspecialchars($_GET['add_message']); ?>
                </div>
            <?php endif; ?>

            <!-- Sezione Crea Scambio -->
            <div class="scambi-section-card mb-5">
                <div class="scambi-section-header">
                    <h2><i class="fas fa-plus-circle me-2"></i>Crea Scambio</h2>
                </div>
                
                <form method="POST" class="scambi-form">
                    <input type="hidden" name="action" value="crea_scambio">
                    
                    <div class="row">
                        <!-- Sezione carte da offrire -->
                        <div class="col-lg-6 mb-4">
                            <h4 class="scambi-subsection-title">Le Mie Carte da Offrire</h4>
                            
                            <!-- Tabs per Yu-Gi-Oh e Pokemon -->
                            <ul class="nav nav-tabs scambi-custom-tabs" id="offrireTab" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="offrire-yugioh-tab" data-bs-toggle="tab" data-bs-target="#offrire-yugioh" type="button">Yu-Gi-Oh!</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="offrire-pokemon-tab" data-bs-toggle="tab" data-bs-target="#offrire-pokemon" type="button">Pokémon</button>
                                </li>
                            </ul>
                            
                            <div class="tab-content mt-3" id="offrireTabContent">
                                <div class="tab-pane fade show active" id="offrire-yugioh">
                                    <input type="text" class="form-control scambi-search-input mb-3" placeholder="Cerca carte Yu-Gi-Oh..." onkeyup="filtraCarteOffrire('yugioh', this.value)">
                                    <div class="scambi-carte-grid" id="carte-offrire-yugioh">
                                        <?php foreach ($carte_yugioh_utente as $carta): ?>
                                            <div class="scambi-carta-item" data-nome="<?= strtolower($carta['nome_oggetto']) ?>">
                                                <div class="scambi-carta-nome"><?= htmlspecialchars($carta['nome_oggetto']) ?></div>
                                                <div class="scambi-carta-quantita">Possedute: <?= $carta['quantita_ogg'] ?></div>
                                                <div class="input-group mt-2">
                                                    <input type="number" class="form-control scambi-carta-input" name="carte_offerte[<?= $carta['id_oggetto'] ?>]" min="0" max="<?= $carta['quantita_ogg'] ?>" value="0">
                                                    <span class="input-group-text">pz</span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <div class="tab-pane fade" id="offrire-pokemon">
                                    <input type="text" class="form-control scambi-search-input mb-3" placeholder="Cerca carte Pokémon..." onkeyup="filtraCarteOffrire('pokemon', this.value)">
                                    <div class="scambi-carte-grid" id="carte-offrire-pokemon">
                                        <?php foreach ($carte_pokemon_utente as $carta): ?>
                                            <div class="scambi-carta-item" data-nome="<?= strtolower($carta['nome_oggetto']) ?>">
                                                <div class="scambi-carta-nome"><?= htmlspecialchars($carta['nome_oggetto']) ?></div>
                                                <div class="scambi-carta-quantita">Possedute: <?= $carta['quantita_ogg'] ?></div>
                                                <div class="input-group mt-2">
                                                    <input type="number" class="form-control scambi-carta-input" name="carte_offerte[<?= $carta['id_oggetto'] ?>]" min="0" max="<?= $carta['quantita_ogg'] ?>" value="0">
                                                    <span class="input-group-text">pz</span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Sezione carte da ricevere -->
                        <div class="col-lg-6 mb-4">
                            <h4 class="scambi-subsection-title">Carte che Voglio Ricevere</h4>
                            
                            <!-- Tabs per Yu-Gi-Oh e Pokemon -->
                            <ul class="nav nav-tabs scambi-custom-tabs" id="ricevereTab" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="ricevere-yugioh-tab" data-bs-toggle="tab" data-bs-target="#ricevere-yugioh" type="button">Yu-Gi-Oh!</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="ricevere-pokemon-tab" data-bs-toggle="tab" data-bs-target="#ricevere-pokemon" type="button">Pokémon</button>
                                </li>
                            </ul>
                            
                            <div class="tab-content mt-3" id="ricevereTabContent">
                                <div class="tab-pane fade show active" id="ricevere-yugioh">
                                    <input type="text" class="form-control scambi-search-input mb-3" placeholder="Cerca carte Yu-Gi-Oh..." onkeyup="filtraCarteRicevere('yugioh', this.value)">
                                    <div class="scambi-carte-grid" id="carte-ricevere-yugioh">
                                        <?php foreach ($tutte_carte_yugioh as $carta): ?>
                                            <div class="scambi-carta-item" data-nome="<?= strtolower($carta['nome_oggetto']) ?>">
                                                <div class="scambi-carta-nome"><?= htmlspecialchars($carta['nome_oggetto']) ?></div>
                                                <div class="input-group mt-2">
                                                    <input type="number" class="form-control scambi-carta-input" name="carte_richieste[<?= $carta['id_oggetto'] ?>]" min="0" value="0">
                                                    <span class="input-group-text">pz</span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <div class="tab-pane fade" id="ricevere-pokemon">
                                    <input type="text" class="form-control scambi-search-input mb-3" placeholder="Cerca carte Pokémon..." onkeyup="filtraCarteRicevere('pokemon', this.value)">
                                    <div class="scambi-carte-grid" id="carte-ricevere-pokemon">
                                        <?php foreach ($tutte_carte_pokemon as $carta): ?>
                                            <div class="scambi-carta-item" data-nome="<?= strtolower($carta['nome_oggetto']) ?>">
                                                <div class="scambi-carta-nome"><?= htmlspecialchars($carta['nome_oggetto']) ?></div>
                                                <div class="input-group mt-2">
                                                    <input type="number" class="form-control scambi-carta-input" name="carte_richieste[<?= $carta['id_oggetto'] ?>]" min="0" value="0">
                                                    <span class="input-group-text">pz</span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary btn-lg scambi-custom-btn">
                            <i class="fas fa-handshake me-2"></i>Crea Scambio
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Sezione Scambi Disponibili -->
            <div class="scambi-section-card mb-5">
                <div class="scambi-section-header">
                    <h2><i class="fas fa-store me-2"></i>Scambi Disponibili</h2>
                </div>
                
                <div class="scambi-grid">
                    <?php foreach ($scambi_disponibili as $scambio): ?>
                        <div class="scambi-card">
                            <div class="scambi-header">
                                <h5><?= htmlspecialchars($scambio['nome']) ?></h5>
                                <div class="scambi-contatti">
                                    <div>📧 <?= htmlspecialchars($scambio['email']) ?></div>
                                    <?php if (!empty($scambio['telefono'])): ?>
                                        <div>📞 <?= htmlspecialchars($scambio['telefono']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="scambi-dettagli">
                                <?php if (!empty($scambio['richieste'])): ?>
                                    <div class="mb-2">
                                        <strong>Vuole:</strong> <?= htmlspecialchars($scambio['richieste']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($scambio['offerte'])): ?>
                                    <div class="mb-2">
                                        <strong>Offre:</strong> <?= htmlspecialchars($scambio['offerte']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="scambi-actions">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="accetta_scambio">
                                    <input type="hidden" name="id_scambio" value="<?= $scambio['id_scambio'] ?>">
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="fas fa-check me-1"></i>Accetta
                                    </button>
                                </form>
                                <button class="btn btn-outline-primary btn-sm" onclick="modificaScambioModal(<?= $scambio['id_scambio'] ?>)">
                                    <i class="fas fa-edit me-1"></i>Modifica
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Sezione I Miei Scambi -->
            <div class="scambi-section-card">
                <div class="scambi-section-header">
                    <h2><i class="fas fa-user-tie me-2"></i>I Miei Scambi</h2>
                </div>
                
                <div class="scambi-grid">
                    <?php foreach ($miei_scambi as $scambio): ?>
                        <div class="scambi-card scambi-mio-scambio">
                            <div class="scambi-header">
                                <div class="scambi-stato-badge scambi-stato-<?= $scambio['stato_scambio'] ?>">
                                    <?= ucfirst(str_replace('_', ' ', $scambio['stato_scambio'])) ?>
                                </div>
                                <div class="scambi-data-scambio">
                                    <?= date('d/m/Y H:i', strtotime($scambio['data_creazione'])) ?>
                                </div>
                            </div>
                            
                            <div class="scambi-dettagli">
                                <?php if (!empty($scambio['richieste'])): ?>
                                    <div class="mb-2">
                                        <strong>Richieste:</strong> <?= htmlspecialchars($scambio['richieste']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($scambio['offerte'])): ?>
                                    <div class="mb-2">
                                        <strong>Offerte:</strong> <?= htmlspecialchars($scambio['offerte']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>

<!-- Bootstrap JavaScript necessario per i modal -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Serializzo in JS le liste lato server
    const MIE_CARTE_YGO = <?php echo json_encode($carte_yugioh_utente, JSON_UNESCAPED_UNICODE); ?>;
    const MIE_CARTE_PKM = <?php echo json_encode($carte_pokemon_utente, JSON_UNESCAPED_UNICODE); ?>;
    const TUTTE_YGO = <?php echo json_encode($tutte_carte_yugioh, JSON_UNESCAPED_UNICODE); ?>;
    const TUTTE_PKM = <?php echo json_encode($tutte_carte_pokemon, JSON_UNESCAPED_UNICODE); ?>;

    // helper per escape HTML
    function escHtml(s) {
        return String(s === undefined ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // Funzioni per filtrare le carte
    function filtraCarteOffrire(tipo, searchTerm) {
        const container = document.getElementById(`carte-offrire-${tipo}`);
        if (!container) return;
        const cards = container.querySelectorAll('.scambi-carta-item');
        cards.forEach(card => {
            const nomeCard = card.dataset.nome || '';
            if (nomeCard.includes(searchTerm.toLowerCase())) {
                card.style.display = 'block';
                card.classList.add('scambi-fade-in');
            } else {
                card.style.display = 'none';
                card.classList.remove('scambi-fade-in');
            }
        });
        container.style.opacity = '0.7';
        setTimeout(() => container.style.opacity = '1', 200);
    }

    function filtraCarteRicevere(tipo, searchTerm) {
        const container = document.getElementById(`carte-ricevere-${tipo}`);
        if (!container) return;
        const cards = container.querySelectorAll('.scambi-carta-item');
        cards.forEach(card => {
            const nomeCard = card.dataset.nome || '';
            if (nomeCard.includes(searchTerm.toLowerCase())) {
                card.style.display = 'block';
                card.classList.add('scambi-fade-in');
            } else {
                card.style.display = 'none';
                card.classList.remove('scambi-fade-in');
            }
        });
        container.style.opacity = '0.7';
        setTimeout(() => container.style.opacity = '1', 200);
    }

    // Funzione per evidenziare carte selezionate
    function evidenziaCarteSelezionate(root = document) {
        const inputs = root.querySelectorAll('.scambi-carta-input:not([data-scambi-bound])');
        inputs.forEach(input => {
            input.setAttribute('data-scambi-bound', '1');
            input.addEventListener('input', function() {
                const cartaItem = this.closest('.scambi-carta-item');
                const value = parseInt(this.value) || 0;
                if (value > 0) {
                    cartaItem.classList.add('scambi-carta-selezionata');
                    cartaItem.style.borderColor = 'var(--secondary-color)';
                    cartaItem.style.transform = 'scale(1.02)';
                } else {
                    cartaItem.classList.remove('scambi-carta-selezionata');
                    cartaItem.style.borderColor = 'transparent';
                    cartaItem.style.transform = 'scale(1)';
                }
            });
        });
    }

    // Funzione per validare il form principale
    function validaFormScambio() {
        const form = document.querySelector('.scambi-form');
        if (!form) return;
        form.addEventListener('submit', function(e) {
            const carteRichieste = form.querySelectorAll('input[name^="carte_richieste"]');
            const carteOfferte = form.querySelectorAll('input[name^="carte_offerte"]');
            let hasRichieste = false;
            let hasOfferte = false;
            carteRichieste.forEach(input => { if (parseInt(input.value) > 0) hasRichieste = true; });
            carteOfferte.forEach(input => { if (parseInt(input.value) > 0) hasOfferte = true; });
            if (!hasRichieste) {
                e.preventDefault();
                mostrarAlert('Devi selezionare almeno una carta da ricevere!', 'warning');
                return false;
            }
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.classList.add('scambi-loading');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creando scambio...';
            }
        });
    }

    // Funzione per mostrare alert personalizzati
    function mostrarAlert(messaggio, tipo = 'info') {
        const alertPrecedente = document.querySelector('.scambi-alert-custom');
        if (alertPrecedente) alertPrecedente.remove();
        const alert = document.createElement('div');
        alert.className = `alert alert-${tipo} scambi-alert-custom fade show`;
        alert.innerHTML = `
            <i class="fas fa-${getIconaAlert(tipo)} me-2"></i>
            ${escHtml(messaggio)}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        alert.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1055;
            min-width: 300px;
            border-radius: 15px;
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(10px);
            border: 2px solid #3f51b5;
        `;
        document.body.appendChild(alert);
        setTimeout(() => {
            if (alert.parentNode) {
                alert.classList.remove('show');
                setTimeout(() => alert.remove(), 300);
            }
        }, 5000);
    }

    function getIconaAlert(tipo) {
        const icone = {
            'success': 'check-circle',
            'danger': 'exclamation-triangle',
            'warning': 'exclamation-circle',
            'info': 'info-circle'
        };
        return icone[tipo] || 'info-circle';
    }

    // Funzioni per il modal di modifica
    function buildGridCarte(titolo, cards, namePrefix, showPossedute=false) {
        const searchId = 'search-' + Math.random().toString(36).slice(2);
        const grid = `
            <h5 class="scambi-subsection-title">${escHtml(titolo)}</h5>
            <input type="text" id="${searchId}" class="form-control scambi-search-input mb-3" placeholder="Cerca..." data-search-id="${searchId}">
            <div class="scambi-carte-grid" data-search-for="${searchId}">
                ${cards.map(c => {
                    const id = escHtml(c.id_oggetto || c.id || '');
                    const nome = escHtml(c.nome_oggetto || c.nome || '');
                    const poss = escHtml(c.quantita_ogg || 0);
                    return `
                        <div class="scambi-carta-item" data-nome="${(nome).toLowerCase()}">
                            <div class="scambi-carta-nome">${nome}</div>
                            ${showPossedute ? `<div class="scambi-carta-quantita">Possedute: ${poss}</div>` : ''}
                            <div class="input-group mt-2">
                                <input type="number" class="form-control scambi-carta-input" name="${namePrefix}[${id}]" min="0" ${showPossedute ? `max="${poss}"` : ''} value="0">
                                <span class="input-group-text">pz</span>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
        return grid;
    }

    function initModalSearchHandlers(modalRoot) {
        const inputs = modalRoot.querySelectorAll('.scambi-search-input');
        inputs.forEach(inp => {
            const sid = inp.getAttribute('data-search-id') || inp.id;
            const grid = modalRoot.querySelector(`[data-search-for="${sid}"]`);
            if (!grid) return;
            const items = grid.querySelectorAll('.scambi-carta-item');
            inp.addEventListener('input', function() {
                const s = (this.value || '').toLowerCase();
                items.forEach(it => {
                    it.style.display = it.dataset.nome && it.dataset.nome.includes(s) ? 'block' : 'none';
                });
            });
        });
    }

    function modificaScambioModal(idScambio) {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-xl">
                <div class="modal-content" style="border-radius: 15px; border: 2px solid #3f51b5;">
                    <form method="POST" id="form-modifica-${idScambio}">
                        <input type="hidden" name="action" value="modifica_scambio">
                        <input type="hidden" name="id_scambio" value="${escHtml(idScambio)}">
                        <div class="modal-header" style="background: linear-gradient(135deg, #3f51b5, #00bcd4); color: white;">
                            <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Controproposta per Scambio #${escHtml(idScambio)}</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted mb-3">
                                <i class="fas fa-info-circle me-2"></i>
                                Seleziona le carte che <b>offri</b> (tra le tue) e quelle che <b>richiedi</b>.
                            </p>

                            <div class="row">
                                <div class="col-lg-6 mb-4">
                                    <ul class="nav nav-tabs scambi-custom-tabs">
                                        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#offro-ygo-${escHtml(idScambio)}">Yu-Gi-Oh!</button></li>
                                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#offro-pkm-${escHtml(idScambio)}">Pokémon</button></li>
                                    </ul>
                                    <div class="tab-content mt-3">
                                        <div class="tab-pane fade show active" id="offro-ygo-${escHtml(idScambio)}">
                                            ${buildGridCarte("Le mie carte da offrire (YGO)", MIE_CARTE_YGO, "carte_offerte", true)}
                                        </div>
                                        <div class="tab-pane fade" id="offro-pkm-${escHtml(idScambio)}">
                                            ${buildGridCarte("Le mie carte da offrire (PKM)", MIE_CARTE_PKM, "carte_offerte", true)}
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-6 mb-4">
                                    <ul class="nav nav-tabs scambi-custom-tabs">
                                        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#voglio-ygo-${escHtml(idScambio)}">Yu-Gi-Oh!</button></li>
                                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#voglio-pkm-${escHtml(idScambio)}">Pokémon</button></li>
                                    </ul>
                                    <div class="tab-content mt-3">
                                        <div class="tab-pane fade show active" id="voglio-ygo-${escHtml(idScambio)}">
                                            ${buildGridCarte("Carte che voglio ricevere (YGO)", TUTTE_YGO, "carte_richieste", false)}
                                        </div>
                                        <div class="tab-pane fade" id="voglio-pkm-${escHtml(idScambio)}">
                                            ${buildGridCarte("Carte che voglio ricevere (PKM)", TUTTE_PKM, "carte_richieste", false)}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                            <button type="submit" class="btn btn-primary scambi-custom-btn">
                                <i class="fas fa-paper-plane me-2"></i>Invia controproposta
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        // Show modal
        const bs = new bootstrap.Modal(modal);
        bs.show();

        // Inizializza comportamenti del modal
        initModalSearchHandlers(modal);
        evidenziaCarteSelezionate(modal);

        // Validazione submit
        const form = modal.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const inputs = this.querySelectorAll('.scambi-carta-input');
                let hasSomething = false;
                inputs.forEach(i => { if (parseInt(i.value || '0') > 0) hasSomething = true; });
                if (!hasSomething) {
                    e.preventDefault();
                    mostrarAlert('Seleziona almeno una carta da offrire o ricevere.', 'warning');
                } else {
                    const btn = this.querySelector('button[type="submit"]');
                    if (btn) {
                        btn.classList.add('scambi-loading');
                        btn.disabled = true;
                        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Inviando...';
                    }
                }
            });
        }

        // Fix per i tab nel modal
        modal.addEventListener('click', function(e) {
            if (e.target.matches('[data-bs-toggle="tab"]')) {
                e.preventDefault();
                const target = e.target.getAttribute('data-bs-target');
                if (target) {
                    modal.querySelectorAll('.tab-pane').forEach(pane => {
                        pane.classList.remove('show', 'active');
                    });
                    modal.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
                        tab.classList.remove('active');
                    });
                    
                    const targetPane = modal.querySelector(target);
                    if (targetPane) {
                        targetPane.classList.add('show', 'active');
                    }
                    e.target.classList.add('active');
                }
            }
        });

        // Rimuovi dal DOM quando chiuso
        modal.addEventListener('hidden.bs.modal', () => modal.remove());
    }

    // Funzioni di utilità
    function animaContatori() {
        const contatori = document.querySelectorAll('.scambi-carta-quantita');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const elemento = entry.target;
                    const testo = elemento.textContent || '';
                    const numero = testo.match(/\d+/);
                    if (numero) {
                        animaNumero(elemento, 0, parseInt(numero[0]), 1000);
                    }
                    observer.unobserve(elemento);
                }
            });
        });
        contatori.forEach(contatore => observer.observe(contatore));
    }

    function animaNumero(elemento, inizio, fine, durata) {
        const incremento = (fine - inizio) / (durata / 16);
        let corrente = inizio;
        const timer = setInterval(() => {
            corrente += incremento;
            if (corrente >= fine) {
                corrente = fine;
                clearInterval(timer);
            }
            const testoOriginale = elemento.textContent;
            elemento.textContent = testoOriginale.replace(/\d+/, Math.floor(corrente));
        }, 16);
    }

    function aggiungiEffettiHover() {
        const cards = document.querySelectorAll('.scambi-carta-item, .scambi-card');
        cards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
            });
            card.addEventListener('mouseleave', function() {
                this.style.transition = 'all 0.4s ease';
            });
        });
    }

    function salvataggiAutomatico() {
        const inputs = document.querySelectorAll('.scambi-carta-input');
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                const formData = {};
                inputs.forEach(inp => {
                    if (parseInt(inp.value) > 0) {
                        formData[inp.name] = inp.value;
                    }
                });
            });
        });
    }

    function gestisciTabResponsive() {
        const tabs = document.querySelectorAll('.scambi-custom-tabs');
        function checkResponsive() {
            tabs.forEach(tabContainer => {
                const tabLinks = tabContainer.querySelectorAll('.nav-link');
                const containerWidth = tabContainer.offsetWidth;
                const totalTabsWidth = Array.from(tabLinks).reduce((total, tab) => total + tab.offsetWidth, 0);
                if (totalTabsWidth > containerWidth && window.innerWidth < 768) {
                    tabContainer.classList.add('scambi-vertical-tabs');
                } else {
                    tabContainer.classList.remove('scambi-vertical-tabs');
                }
            });
        }
        window.addEventListener('resize', checkResponsive);
        checkResponsive();
    }

    // Inizializzazione
    document.addEventListener('DOMContentLoaded', function() {
        evidenziaCarteSelezionate();
        validaFormScambio();
        aggiungiEffettiHover();
        salvataggiAutomatico();
        gestisciTabResponsive();
        setTimeout(animaContatori, 500);
        
        const sezioni = document.querySelectorAll('.scambi-section-card');
        sezioni.forEach((sezione, index) => sezione.style.animationDelay = `${index * 0.2}s`);
        
        const alertElements = document.querySelectorAll('.alert');
        alertElements.forEach(alert => setTimeout(() => alert.classList.add('scambi-fade-out'), 3000));
    });
</script>