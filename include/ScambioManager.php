<?php
/**
 * ScambioManager rigenerato con supporto alle carte cartacee.
 * - Usa include/config.inc.php (ritorna $config) per connettersi al DB.
 * - Aggiunge tabella scambio_cartaceo (se non esiste).
 * - Estende creaProposta per accettare $carte_cartacee.
 */

require_once __DIR__ . '/session_manager.php';

class ScambioManagerFactory {
    public static function create(): ScambioManager {
        $config = require __DIR__ . '/config.inc.php';
        if (!isset($config['dbms']['localhost'])) {
            throw new Exception('Configurazione DB non trovata.');
        }
        $db = $config['dbms']['localhost'];
        $dsn = "mysql:host={$db['host']};dbname={$db['dbname']};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        $pdo = new PDO($dsn, $db['user'], $db['passwd'], $options);
        return new ScambioManager($pdo);
    }
}

class ScambioManager {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->ensureSchema();
    }

    private function ensureSchema(): void {
        // Crea la tabella per le carte cartacee se non esiste
        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS scambio_cartaceo (
                id INT AUTO_INCREMENT PRIMARY KEY,
                fk_scambio INT NOT NULL,
                nome_carta VARCHAR(255) NOT NULL,
                quantita INT NOT NULL DEFAULT 1,
                stato ENUM('scarso','buono','eccellente') NOT NULL DEFAULT 'buono',
                CONSTRAINT fk_scambio_cartaceo_scambio
                    FOREIGN KEY (fk_scambio) REFERENCES scambio(id_scambio)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }

    /**
     * Crea una proposta di scambio.
     * @param int $utente_proponente
     * @param array $carte_offerte      Array di oggetti digitali: [ ["id_oggetto"=>int, "quantita"=>int], ... ]
     * @param array $carte_richieste    Array di oggetti digitali: [ ["id_oggetto"=>int, "quantita"=>int], ... ]
     * @param int|null $utente_destinatario
     * @param array $carte_cartacee     Array di carte cartacee: [ ["nome"=>string, "quantita"=>int, "stato"=>'scarso|buono|eccellente'], ... ]
     * @return int id_scambio
     */
    public function creaProposta(int $utente_proponente, array $carte_offerte = [], array $carte_richieste = [], ?int $utente_destinatario = null, array $carte_cartacee = []): int {
        $this->pdo->beginTransaction();
        try {
            // Inserisci scambio
            $stmt = $this->pdo->prepare("INSERT INTO scambio (stato_scambio, fk_utente) VALUES (?, ?)");
            $stmt->execute(['proposto', $utente_proponente]);
            $id_scambio = (int)$this->pdo->lastInsertId();

            // Inserisci offerte digitali
            if (!empty($carte_offerte)) {
                foreach ($carte_offerte as $c) {
                    $id_oggetto = (int)($c['id_oggetto'] ?? 0);
                    $qta = max(1, (int)($c['quantita'] ?? 1));
                    $this->aggiungiRigaScambioOggetto($id_scambio, $id_oggetto, $qta, true);
                }
            }

            // Inserisci richieste digitali
            if (!empty($carte_richieste)) {
                foreach ($carte_richieste as $c) {
                    $id_oggetto = (int)($c['id_oggetto'] ?? 0);
                    $qta = max(1, (int)($c['quantita'] ?? 1));
                    $this->aggiungiRigaScambioOggetto($id_scambio, $id_oggetto, $qta, false);
                }
            }

            // Inserisci carte cartacee offerte
            if (!empty($carte_cartacee)) {
                $stmtC = $this->pdo->prepare("INSERT INTO scambio_cartaceo (fk_scambio, nome_carta, quantita, stato) VALUES (?,?,?,?)");
                foreach ($carte_cartacee as $cc) {
                    $nome = trim((string)($cc['nome'] ?? ''));
                    if ($nome === '') { continue; }
                    $qta = max(1, (int)($cc['quantita'] ?? 1));
                    $stato = in_array(($cc['stato'] ?? 'buono'), ['scarso','buono','eccellente'], true) ? $cc['stato'] : 'buono';
                    $stmtC->execute([$id_scambio, $nome, $qta, $stato]);
                }
            }

            $this->pdo->commit();
            return $id_scambio;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** Inserisce una riga in scambio_oggetto (tabella già esistente nel progetto). */
    private function aggiungiRigaScambioOggetto(int $id_scambio, int $id_oggetto, int $quantita, bool $da_proponente): void {
        // da_proponente=true => offerta; false => richiesta
        $stmt = $this->pdo->prepare("INSERT INTO scambio_oggetto (fk_scambio, fk_oggetto, quantita_scambio, da_proponente) VALUES (?,?,?,?)");
        $stmt->execute([$id_scambio, $id_oggetto, $quantita, $da_proponente ? 1 : 0]);
    }

    public function accettaScambio(int $id_scambio, int $utente_accettante): void {
        // Esegue controlli basilari e completa lo scambio (solo componenti digitali).
        $scambio = $this->getScambio($id_scambio);
        if (!$scambio || $scambio['stato_scambio'] !== 'proposto') {
            throw new Exception('Scambio non valido o non più disponibile');
        }
        if ((int)$scambio['fk_utente'] === $utente_accettante) {
            throw new Exception('Non puoi accettare uno scambio creato da te');
        }

        $richieste = $this->getCarteRichieste($id_scambio);
        if (!$this->verificaPossessoOggetti($utente_accettante, $richieste)) {
            throw new Exception('Non possiedi tutte le carte richieste');
        }

        $this->pdo->beginTransaction();
        try {
            // Trasferisci oggetti digitali
            $offerte = $this->getCarteOfferte($id_scambio);
            $this->eseguiTrasferimenti($scambio['fk_utente'], $utente_accettante, $offerte);
            $this->eseguiTrasferimenti($utente_accettante, $scambio['fk_utente'], $richieste);

            // Aggiorna stato
            $stmt = $this->pdo->prepare("UPDATE scambio SET stato_scambio = ? WHERE id_scambio = ?");
            $stmt->execute(['concluso', $id_scambio]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function rifiutaScambio(int $id_scambio, int $utente): void {
        $scambio = $this->getScambio($id_scambio);
        if (!$scambio) { throw new Exception('Scambio inesistente'); }
        if ((int)$scambio['fk_utente'] !== $utente) { /* opzionale: permetti annullo solo al proponente */ }
        $stmt = $this->pdo->prepare("UPDATE scambio SET stato_scambio = ? WHERE id_scambio = ?");
        $stmt->execute(['annullato', $id_scambio]);
    }

    public function getScambio(int $id_scambio): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM scambio WHERE id_scambio = ?");
        $stmt->execute([$id_scambio]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getCarteOfferte(int $id_scambio): array {
        $sql = "SELECT so.*, o.nome_oggetto FROM scambio_oggetto so JOIN oggetto o ON o.id_oggetto = so.fk_oggetto WHERE so.fk_scambio = ? AND so.da_proponente = 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id_scambio]);
        return $stmt->fetchAll();
    }

    public function getCarteRichieste(int $id_scambio): array {
        $sql = "SELECT so.*, o.nome_oggetto FROM scambio_oggetto so JOIN oggetto o ON o.id_oggetto = so.fk_oggetto WHERE so.fk_scambio = ? AND so.da_proponente = 0";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id_scambio]);
        return $stmt->fetchAll();
    }

    public function getCarteCartacee(int $id_scambio): array {
        $stmt = $this->pdo->prepare("SELECT * FROM scambio_cartaceo WHERE fk_scambio = ?");
        $stmt->execute([$id_scambio]);
        return $stmt->fetchAll();
    }

    public function getDettagliCompleti(int $id_scambio): array {
        return [
            'carte_offerte_dettagli'  => $this->getCarteOfferte($id_scambio),
            'carte_richieste_dettagli'=> $this->getCarteRichieste($id_scambio),
            'carte_cartacee'          => $this->getCarteCartacee($id_scambio),
            'valore_offerto'          => 0.0,
            'valore_richiesto'        => 0.0,
        ];
    }

    public function getScambiDisponibili(?int $escludi_utente_id = null): array {
        $params = [];
        $sql = "SELECT s.*, u.nome, u.cognome,
                       SUM(CASE WHEN so.da_proponente=1 THEN so.quantita_scambio ELSE 0 END) AS carte_offerte,
                       SUM(CASE WHEN so.da_proponente=0 THEN so.quantita_scambio ELSE 0 END) AS carte_richieste
                FROM scambio s
                LEFT JOIN scambio_oggetto so ON so.fk_scambio = s.id_scambio
                LEFT JOIN utente u ON u.id_utente = s.fk_utente
                WHERE s.stato_scambio = 'proposto'";
        if ($escludi_utente_id) {
            $sql .= " AND s.fk_utente <> ?";
            $params[] = $escludi_utente_id;
        }
        $sql .= " GROUP BY s.id_scambio ORDER BY s.data_scambio DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getScambiUtente(int $utente_id): array {
        $sql = "SELECT s.*,
                       SUM(CASE WHEN so.da_proponente=1 THEN so.quantita_scambio ELSE 0 END) AS carte_offerte,
                       SUM(CASE WHEN so.da_proponente=0 THEN so.quantita_scambio ELSE 0 END) AS carte_richieste
                FROM scambio s
                LEFT JOIN scambio_oggetto so ON so.fk_scambio = s.id_scambio
                WHERE s.fk_utente = ?
                GROUP BY s.id_scambio
                ORDER BY s.data_scambio DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$utente_id]);
        return $stmt->fetchAll();
    }

    // ====== Utilità specifiche del tuo progetto (semplificate) ======
    public function getCollezioneUtente(int $utente_id): array {
        // Restituisce oggetti posseduti dall'utente
        $sql = "SELECT ou.*, o.nome_oggetto
                FROM oggetto_utente ou
                JOIN oggetto o ON o.id_oggetto = ou.fk_oggetto
                WHERE ou.fk_utente = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$utente_id]);
        return $stmt->fetchAll();
    }

    public function getCategorie(): array {
        $stmt = $this->pdo->query("SELECT * FROM categoria ORDER BY nome_categoria");
        return $stmt->fetchAll();
    }

    public function verificaPossessoOggetti(int $utente_id, array $righe_scambio): bool {
        if (empty($righe_scambio)) return true;
        $ok = true;
        foreach ($righe_scambio as $r) {
            $id_oggetto = (int)$r['fk_oggetto'];
            $qta = (int)$r['quantita_scambio'];
            $stmt = $this->pdo->prepare("SELECT quantita FROM oggetto_utente WHERE fk_utente=? AND fk_oggetto=?");
            $stmt->execute([$utente_id, $id_oggetto]);
            $row = $stmt->fetch();
            if (!$row || (int)$row['quantita'] < $qta) { $ok = false; break; }
        }
        return $ok;
    }

    private function eseguiTrasferimenti(int $da_utente, int $a_utente, array $righe_scambio): void {
        foreach ($righe_scambio as $r) {
            $this->trasferisciOggetto($da_utente, $a_utente, (int)$r['fk_oggetto'], (int)$r['quantita_scambio']);
        }
    }

    private function trasferisciOggetto(int $da_utente, int $a_utente, int $id_oggetto, int $quantita): void {
        // Scala dal mittente
        $stmt = $this->pdo->prepare("UPDATE oggetto_utente SET quantita = quantita - ? WHERE fk_utente = ? AND fk_oggetto = ?");
        $stmt->execute([$quantita, $da_utente, $id_oggetto]);
        // Aggiungi al destinatario (upsert semplice)
        $stmt = $this->pdo->prepare("INSERT INTO oggetto_utente (fk_utente, fk_oggetto, quantita) VALUES (?,?,?)
                                     ON DUPLICATE KEY UPDATE quantita = quantita + VALUES(quantita)");
        $stmt->execute([$a_utente, $id_oggetto, $quantita]);
    }
}
