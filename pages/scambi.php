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
                   u2.nome as nome_ultimo_proponente,
                   GROUP_CONCAT(DISTINCT CONCAT(o.nome_oggetto, ' (', sr.quantita_richiesta, ')') SEPARATOR ', ') as richieste,
                   GROUP_CONCAT(DISTINCT CONCAT(o2.nome_oggetto, ' (', so.quantita_offerta, ')') SEPARATOR ', ') as offerte
            FROM scambi s
            JOIN utente u ON s.fk_utente_richiedente = u.id_utente
            LEFT JOIN utente u2 ON COALESCE(s.fk_utente_offerente, s.fk_utente_richiedente) = u2.id_utente
            LEFT JOIN scambio_richieste sr ON s.id_scambio = sr.fk_scambio
            LEFT JOIN oggetto o ON sr.fk_oggetto = o.id_oggetto
            LEFT JOIN scambio_offerte so ON s.id_scambio = so.fk_scambio
            LEFT JOIN oggetto o2 ON so.fk_oggetto = o2.id_oggetto
            WHERE s.stato_scambio = 'in_corso'
            AND (
                -- Caso 1: L'utente corrente è il richiedente originale E qualcun altro ha fatto una controproposta
                (s.fk_utente_richiedente = ? AND s.fk_utente_offerente IS NOT NULL AND s.fk_utente_offerente != ?)
                OR
                -- Caso 2: L'utente corrente NON è il richiedente originale E il richiedente è l'ultimo proponente
                (s.fk_utente_richiedente != ? AND (s.fk_utente_offerente IS NULL OR s.fk_utente_offerente = s.fk_utente_richiedente))
            )
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
    // user_id viene usato 4 volte nella query
    $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Aggiungi questa nuova funzione per distinguere meglio i tipi di scambi
function getScambiConStato($conn, $user_id) {
    $sql = "SELECT DISTINCT s.*, 
                   u_richiedente.nome as nome_richiedente,
                   u_richiedente.email as email_richiedente, 
                   u_richiedente.telefono as telefono_richiedente,
                   u_offerente.nome as nome_offerente,
                   u_offerente.email as email_offerente,
                   u_offerente.telefono as telefono_offerente,
                   CASE 
                       WHEN s.fk_utente_richiedente = ? THEN 'mio_scambio'
                       WHEN s.fk_utente_offerente = ? THEN 'mia_controproposta'
                       WHEN s.fk_utente_offerente IS NULL THEN 'posso_controproporre'
                       WHEN s.fk_utente_offerente = s.fk_utente_richiedente THEN 'posso_accettare'
                       ELSE 'non_mio'
                   END as tipo_azione,
                   GROUP_CONCAT(DISTINCT CONCAT(o.nome_oggetto, ' (', sr.quantita_richiesta, ')') SEPARATOR ', ') as richieste,
                   GROUP_CONCAT(DISTINCT CONCAT(o2.nome_oggetto, ' (', so.quantita_offerta, ')') SEPARATOR ', ') as offerte
            FROM scambi s
            LEFT JOIN utente u_richiedente ON s.fk_utente_richiedente = u_richiedente.id_utente
            LEFT JOIN utente u_offerente ON s.fk_utente_offerente = u_offerente.id_utente
            LEFT JOIN scambio_richieste sr ON s.id_scambio = sr.fk_scambio
            LEFT JOIN oggetto o ON sr.fk_oggetto = o.id_oggetto
            LEFT JOIN scambio_offerte so ON s.id_scambio = so.fk_scambio
            LEFT JOIN oggetto o2 ON so.fk_oggetto = o2.id_oggetto
            WHERE s.stato_scambio = 'in_corso'
            AND (s.fk_utente_richiedente = ? OR s.fk_utente_offerente = ? OR 
                 (s.fk_utente_richiedente != ? AND 
                  NOT EXISTS (
                      SELECT 1 FROM scambio_richieste sr2
                      LEFT JOIN oggetto_utente ou ON sr2.fk_oggetto = ou.fk_oggetto AND ou.fk_utente = ?
                      WHERE sr2.fk_scambio = s.id_scambio
                      AND (ou.quantita_ogg IS NULL OR ou.quantita_ogg < sr2.quantita_richiesta)
                  )))
            GROUP BY s.id_scambio
            ORDER BY s.data_creazione DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}
?>

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
$scambi_disponibili = getScambiConStato($conn, $user_id);
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
        <h2><i class="fas fa-store me-2"></i>Scambi Disponibili</h2>
    </div>
    
    <div class="scambi-grid">
        <?php 
        // Usa la nuova funzione invece di getScambiDisponibili
        $scambi_con_stato = getScambiConStato($conn, $user_id);
        foreach ($scambi_con_stato as $scambio): 
            // Salta i propri scambi (verranno mostrati nella sezione "I Miei Scambi")
            if ($scambio['tipo_azione'] == 'mio_scambio') continue;
            
            // Determina quale utente mostrare in base al tipo di azione
            $nome_contatto = '';
            $email_contatto = '';
            $telefono_contatto = '';
            
            if ($scambio['tipo_azione'] == 'mia_controproposta' || $scambio['tipo_azione'] == 'posso_accettare') {
                // Mostra i dati del richiedente originale
                $nome_contatto = $scambio['nome_richiedente'];
                $email_contatto = $scambio['email_richiedente'];
                $telefono_contatto = $scambio['telefono_richiedente'];
            } else {
                // Mostra i dati dell'ultimo proponente
                $nome_contatto = $scambio['nome_offerente'] ?: $scambio['nome_richiedente'];
                $email_contatto = $scambio['email_offerente'] ?: $scambio['email_richiedente'];
                $telefono_contatto = $scambio['telefono_offerente'] ?: $scambio['telefono_richiedente'];
            }
        ?>
            <div class="scambi-card">
                <div class="scambi-header">
                    <h5><?= htmlspecialchars($nome_contatto) ?></h5>
                    <div class="scambi-stato-azione">
                        <?php if ($scambio['tipo_azione'] == 'mia_controproposta'): ?>
                            <span class="badge bg-warning">In attesa di risposta</span>
                        <?php elseif ($scambio['tipo_azione'] == 'posso_accettare'): ?>
                            <span class="badge bg-success">Puoi accettare</span>
                        <?php else: ?>
                            <span class="badge bg-info">Nuovo scambio</span>
                        <?php endif; ?>
                    </div>
                    <div class="scambi-contatti">
                        <div>📧 <?= htmlspecialchars($email_contatto) ?></div>
                        <?php if (!empty($telefono_contatto)): ?>
                            <div>📞 <?= htmlspecialchars($telefono_contatto) ?></div>
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
                    <?php if ($scambio['tipo_azione'] == 'posso_accettare'): ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="accetta_scambio">
                            <input type="hidden" name="id_scambio" value="<?= $scambio['id_scambio'] ?>">
                            <button type="submit" class="btn btn-success btn-sm">
                                <i class="fas fa-check me-1"></i>Accetta
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <?php if ($scambio['tipo_azione'] == 'posso_controproporre' || $scambio['tipo_azione'] == 'posso_accettare'): ?>
                        <button class="btn btn-outline-primary btn-sm" onclick="modificaScambioModal(<?= $scambio['id_scambio'] ?>)">
                            <i class="fas fa-edit me-1"></i><?= $scambio['tipo_azione'] == 'posso_controproporre' ? 'Controproponi' : 'Modifica' ?>
                        </button>
                    <?php endif; ?>
                    
                    <?php if ($scambio['tipo_azione'] == 'mia_controproposta'): ?>
                        <span class="text-muted">
                            <i class="fas fa-clock me-1"></i>In attesa di risposta
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
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
// Aggiungi questo JavaScript per risolvere il problema dei tasti bloccati nell'header

// Funzione per gestire il z-index dei modals
function fixModalZIndex() {
    // Trova tutti i modals aperti
    const openModals = document.querySelectorAll('.modal.show');
    openModals.forEach((modal, index) => {
        modal.style.zIndex = 1050 + (index * 10);
        const backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) {
            backdrop.style.zIndex = 1040 + (index * 10);
        }
    });
}

// Funzione per prevenire il blocco degli eventi
function preventEventBlocking() {
    // Rimuovi eventuali overlay nascosti che potrebbero bloccare i click
    const overlays = document.querySelectorAll('[style*="position: fixed"], [style*="position: absolute"]');
    overlays.forEach(overlay => {
        const style = window.getComputedStyle(overlay);
        if (style.zIndex > 1055 && !overlay.classList.contains('modal') && !overlay.classList.contains('modal-backdrop')) {
            overlay.style.pointerEvents = 'none';
        }
    });
}

// Modifica la funzione modificaScambioModal esistente per gestire meglio il z-index
function modificaScambioModalFixed(idScambio) {
    // Chiudi eventuali modals aperti prima di aprire il nuovo
    const existingModals = document.querySelectorAll('.modal.show');
    existingModals.forEach(modal => {
        const bsModal = bootstrap.Modal.getInstance(modal);
        if (bsModal) bsModal.hide();
    });
    
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.style.zIndex = '1060'; // Z-index più alto
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
                                    <li class="nav-item"><button class="nav-link active" type="button" data-bs-toggle="tab" data-bs-target="#offro-ygo-${escHtml(idScambio)}">Yu-Gi-Oh!</button></li>
                                    <li class="nav-item"><button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#offro-pkm-${escHtml(idScambio)}">Pokémon</button></li>
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
                                    <li class="nav-item"><button class="nav-link active" type="button" data-bs-toggle="tab" data-bs-target="#voglio-ygo-${escHtml(idScambio)}">Yu-Gi-Oh!</button></li>
                                    <li class="nav-item"><button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#voglio-pkm-${escHtml(idScambio)}">Pokémon</button></li>
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

    // Configura il backdrop con z-index corretto
    const modalBackdrop = document.createElement('div');
    modalBackdrop.className = 'modal-backdrop fade show';
    modalBackdrop.style.zIndex = '1055';

    // Show modal
    const bs = new bootstrap.Modal(modal, {
        backdrop: 'static',
        keyboard: true
    });
    
    bs.show();

    // Fix z-index dopo che il modal è mostrato
    modal.addEventListener('shown.bs.modal', function() {
        fixModalZIndex();
        preventEventBlocking();
    });

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
    modal.addEventListener('hidden.bs.modal', function() {
        modal.remove();
        preventEventBlocking(); // Ripulisci dopo la chiusura
    });
}

// Sostituisci la chiamata alla funzione originale
function modificaScambioModal(idScambio) {
    modificaScambioModalFixed(idScambio);
}

// Aggiungi event listener per prevenire il blocco degli eventi
document.addEventListener('DOMContentLoaded', function() {
    // ... codice esistente ...
    
    // Aggiungi questo nuovo codice:
    preventEventBlocking();
    
    // Monitora i cambiamenti nel DOM per overlay problematici
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                setTimeout(preventEventBlocking, 100);
            }
        });
    });
    
    observer.observe(document.body, { childList: true, subtree: true });
});
</script>