<?php

class ScambioManager {
    private $pdo;
    
    public function __construct($database_connection) {
        $this->pdo = $database_connection;
    }
    
    /**
     * Crea una nuova proposta di scambio
     */
    public function creaProposta($utente_proponente, $carte_offerte, $carte_richieste, $utente_destinatario = null) {
        try {
            $this->pdo->beginTransaction();
            
            // Verifica che l'utente abbia le carte che vuole offrire
            if (!$this->verificaPossessoOggetti($utente_proponente, $carte_offerte)) {
                throw new Exception("Non possiedi tutte le carte che vuoi offrire");
            }
            
            // Crea il record dello scambio
            $stmt = $this->pdo->prepare("
                INSERT INTO scambio (fk_utente, stato_scambio) 
                VALUES (?, 'proposto')
            ");
            $stmt->execute([$utente_proponente]);
            $id_scambio = $this->pdo->lastInsertId();
            
            // Inserisce gli oggetti offerti dall'utente proponente
            foreach ($carte_offerte as $carta) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO scambio_oggetto (fk_scambio, fk_oggetto, da_utente, quantita_scambio) 
                    VALUES (?, ?, 1, ?)
                ");
                $stmt->execute([$id_scambio, $carta['id_oggetto'], $carta['quantita']]);
            }
            
            // Inserisce gli oggetti richiesti
            foreach ($carte_richieste as $carta) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO scambio_oggetto (fk_scambio, fk_oggetto, da_utente, quantita_scambio) 
                    VALUES (?, ?, 0, ?)
                ");
                $stmt->execute([$id_scambio, $carta['id_oggetto'], $carta['quantita']]);
            }
            
            $this->pdo->commit();
            return $id_scambio;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Accetta uno scambio proposto
     */
    public function accettaScambio($id_scambio, $utente_accettante) {
        try {
            $this->pdo->beginTransaction();
            
            // Verifica che lo scambio esista e sia in stato "proposto"
            $scambio = $this->getScambio($id_scambio);
            if (!$scambio || $scambio['stato_scambio'] !== 'proposto') {
                throw new Exception("Scambio non valido o già processato");
            }
            
            // Non puoi accettare il tuo stesso scambio
            if ($scambio['fk_utente'] == $utente_accettante) {
                throw new Exception("Non puoi accettare il tuo stesso scambio");
            }
            
            // Verifica che l'utente accettante abbia le carte richieste
            $carte_richieste = $this->getCarteRichieste($id_scambio);
            if (!$this->verificaPossessoOggetti($utente_accettante, $carte_richieste)) {
                throw new Exception("Non possiedi tutte le carte richieste per questo scambio");
            }
            
            // Esegue lo scambio
            $this->eseguiScambio($id_scambio, $utente_accettante);
            
            // Aggiorna lo stato del scambio
            $stmt = $this->pdo->prepare("
                UPDATE scambio 
                SET stato_scambio = 'completato', data_scambio = NOW() 
                WHERE id_scambio = ?
            ");
            $stmt->execute([$id_scambio]);
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Rifiuta uno scambio
     */
    public function rifiutaScambio($id_scambio, $utente) {
        try {
            $scambio = $this->getScambio($id_scambio);
            if (!$scambio || $scambio['stato_scambio'] !== 'proposto') {
                throw new Exception("Scambio non valido o già processato");
            }
            
            // Solo il proponente può cancellare il suo scambio
            if ($scambio['fk_utente'] != $utente) {
                throw new Exception("Non puoi rifiutare questo scambio");
            }
            
            $stmt = $this->pdo->prepare("
                UPDATE scambio 
                SET stato_scambio = 'rifiutato' 
                WHERE id_scambio = ?
            ");
            $stmt->execute([$id_scambio]);
            
            return true;
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Esegue effettivamente lo scambio degli oggetti tra utenti
     */
    private function eseguiScambio($id_scambio, $utente_accettante) {
        $scambio = $this->getScambio($id_scambio);
        $utente_proponente = $scambio['fk_utente'];
        
        // Ottieni le carte offerte e richieste
        $carte_offerte = $this->getCarteOfferte($id_scambio);
        $carte_richieste = $this->getCarteRichieste($id_scambio);
        
        // Trasferisce le carte offerte dal proponente all'accettante
        foreach ($carte_offerte as $carta) {
            $this->trasferisciOggetto(
                $utente_proponente, 
                $utente_accettante, 
                $carta['fk_oggetto'], 
                $carta['quantita_scambio']
            );
        }
        
        // Trasferisce le carte richieste dall'accettante al proponente
        foreach ($carte_richieste as $carta) {
            $this->trasferisciOggetto(
                $utente_accettante, 
                $utente_proponente, 
                $carta['fk_oggetto'], 
                $carta['quantita_scambio']
            );
        }
    }
    
    /**
     * Trasferisce un oggetto da un utente all'altro
     */
    private function trasferisciOggetto($da_utente, $a_utente, $id_oggetto, $quantita) {
        // Rimuovi dall'utente che cede
        $stmt = $this->pdo->prepare("
            UPDATE oggetto_utente 
            SET quantita_ogg = quantita_ogg - ? 
            WHERE fk_utente = ? AND fk_oggetto = ?
        ");
        $stmt->execute([$quantita, $da_utente, $id_oggetto]);
        
        // Rimuovi il record se la quantità diventa 0
        $stmt = $this->pdo->prepare("
            DELETE FROM oggetto_utente 
            WHERE fk_utente = ? AND fk_oggetto = ? AND quantita_ogg <= 0
        ");
        $stmt->execute([$da_utente, $id_oggetto]);
        
        // Aggiungi all'utente che riceve
        $stmt = $this->pdo->prepare("
            INSERT INTO oggetto_utente (fk_utente, fk_oggetto, quantita_ogg) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE quantita_ogg = quantita_ogg + ?
        ");
        $stmt->execute([$a_utente, $id_oggetto, $quantita, $quantita]);
    }
    
    /**
     * Verifica che un utente possieda gli oggetti specificati
     */
    private function verificaPossessoOggetti($id_utente, $oggetti) {
        foreach ($oggetti as $oggetto) {
            $stmt = $this->pdo->prepare("
                SELECT quantita_ogg 
                FROM oggetto_utente 
                WHERE fk_utente = ? AND fk_oggetto = ?
            ");
            $stmt->execute([$id_utente, $oggetto['id_oggetto']]);
            $possesso = $stmt->fetch();
            
            if (!$possesso || $possesso['quantita_ogg'] < $oggetto['quantita']) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Ottiene i dettagli di uno scambio
     */
    public function getScambio($id_scambio) {
        $stmt = $this->pdo->prepare("
            SELECT s.*, u.nome, u.cognome, u.email 
            FROM scambio s 
            JOIN utente u ON s.fk_utente = u.id_utente 
            WHERE s.id_scambio = ?
        ");
        $stmt->execute([$id_scambio]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Ottiene le carte offerte in uno scambio
     */
    public function getCarteOfferte($id_scambio) {
        $stmt = $this->pdo->prepare("
            SELECT so.*, o.nome_oggetto, o.desc_oggetto, r.nome_rarita, r.colore
            FROM scambio_oggetto so
            JOIN oggetto o ON so.fk_oggetto = o.id_oggetto
            LEFT JOIN rarita r ON o.fk_rarita = r.id_rarita
            WHERE so.fk_scambio = ? AND so.da_utente = 1
        ");
        $stmt->execute([$id_scambio]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Ottiene le carte richieste in uno scambio
     */
    public function getCarteRichieste($id_scambio) {
        $stmt = $this->pdo->prepare("
            SELECT so.*, o.nome_oggetto, o.desc_oggetto, r.nome_rarita, r.colore
            FROM scambio_oggetto so
            JOIN oggetto o ON so.fk_oggetto = o.id_oggetto
            LEFT JOIN rarita r ON o.fk_rarita = r.id_rarita
            WHERE so.fk_scambio = ? AND so.da_utente = 0
        ");
        $stmt->execute([$id_scambio]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Ottiene tutti gli scambi disponibili
     */
    public function getScambiDisponibili($utente_id = null) {
        $where_clause = "WHERE s.stato_scambio = 'proposto'";
        $params = [];
        
        if ($utente_id) {
            $where_clause .= " AND s.fk_utente != ?";
            $params[] = $utente_id;
        }
        
        $stmt = $this->pdo->prepare("
            SELECT s.*, u.nome, u.cognome,
                   COUNT(CASE WHEN so.da_utente = 1 THEN 1 END) as carte_offerte,
                   COUNT(CASE WHEN so.da_utente = 0 THEN 1 END) as carte_richieste
            FROM scambio s 
            JOIN utente u ON s.fk_utente = u.id_utente
            LEFT JOIN scambio_oggetto so ON s.id_scambio = so.fk_scambio
            $where_clause
            GROUP BY s.id_scambio
            ORDER BY s.data_scambio DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Ottiene la collezione di un utente per la selezione delle carte
     */
    public function getCollezioneUtente($id_utente, $categoria_id = null) {
        $where_clause = "";
        $params = [$id_utente];
        
        if ($categoria_id) {
            $where_clause = " AND o.fk_categoria_oggetto = ?";
            $params[] = $categoria_id;
        }
        
        $stmt = $this->pdo->prepare("
            SELECT ou.*, o.nome_oggetto, o.desc_oggetto, 
                   r.nome_rarita, r.colore, co.nome_categoria,
                   oc.valore_stimato
            FROM oggetto_utente ou
            JOIN oggetto o ON ou.fk_oggetto = o.id_oggetto
            JOIN categoria_oggetto co ON o.fk_categoria_oggetto = co.id_categoria
            LEFT JOIN rarita r ON o.fk_rarita = r.id_rarita
            LEFT JOIN oggetto_collezione oc ON o.id_oggetto = oc.fk_oggetto
            WHERE ou.fk_utente = ? AND ou.quantita_ogg > 0 $where_clause
            ORDER BY r.ordine DESC, o.nome_oggetto
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Calcola il valore stimato di una lista di carte
     */
    public function calcolaValoreStimato($carte) {
        $valore_totale = 0;
        
        foreach ($carte as $carta) {
            $stmt = $this->pdo->prepare("
                SELECT valore_stimato 
                FROM oggetto_collezione 
                WHERE fk_oggetto = ?
            ");
            $stmt->execute([$carta['id_oggetto']]);
            $valore = $stmt->fetch();
            
            if ($valore) {
                $valore_totale += $valore['valore_stimato'] * $carta['quantita'];
            }
        }
        
        return $valore_totale;
    }
}

// Esempio di utilizzo
try {
    // Connessione al database
    $pdo = new PDO("mysql:host=localhost;dbname=boxomnia;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $scambio_manager = new ScambioManager($pdo);
    
    // Esempio: Creare una proposta di scambio
    $carte_offerte = [
        ['id_oggetto' => 1, 'quantita' => 1], // Mago Nero
        ['id_oggetto' => 48, 'quantita' => 2] // Drago Bebè
    ];
    
    $carte_richieste = [
        ['id_oggetto' => 2, 'quantita' => 1], // Pikachu
        ['id_oggetto' => 76, 'quantita' => 1] // Charizard
    ];
    
    // Crea la proposta (utente 1 propone)
    $id_scambio = $scambio_manager->creaProposta(1, $carte_offerte, $carte_richieste);
    echo "Proposta di scambio creata con ID: $id_scambio\n";
    
    // Visualizza gli scambi disponibili
    $scambi = $scambio_manager->getScambiDisponibili(2); // Utente 2 guarda gli scambi
    foreach ($scambi as $scambio) {
        echo "Scambio #{$scambio['id_scambio']} da {$scambio['nome']} {$scambio['cognome']}\n";
        echo "Offre {$scambio['carte_offerte']} carte, chiede {$scambio['carte_richieste']} carte\n";
    }
    
    // Accetta lo scambio (utente 2 accetta)
    $scambio_manager->accettaScambio($id_scambio, 2);
    echo "Scambio completato!\n";
    
} catch (Exception $e) {
    echo "Errore: " . $e->getMessage() . "\n";
}

?>