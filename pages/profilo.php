<?php
require_once __DIR__.'/../include/session_manager.php';
$config = require_once __DIR__.'/../include/config.inc.php';

// Connessione al database usando la configurazione
try {
    $pdo = new PDO(
        "mysql:host=" . $config['dbms']['localhost']['host'] . ";dbname=" . $config['dbms']['localhost']['dbname'] . ";charset=utf8mb4", 
        $config['dbms']['localhost']['user'], 
        $config['dbms']['localhost']['passwd']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Errore di connessione al database: " . $e->getMessage());
}

include __DIR__ . '/header.php';

// Richiede login, reindirizza se non loggato
SessionManager::requireLogin();

// Recupera l'ID utente dalla sessione
$userId = SessionManager::get('user_id');

// Recupera i dati completi dell'utente dal database
try {
    // Dati utente principali
    $stmt = $pdo->prepare("SELECT * FROM utente WHERE id_utente = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Indirizzi di spedizione
    $stmt = $pdo->prepare("SELECT * FROM indirizzo_spedizione WHERE fk_utente = ? ORDER BY id_indirizzo DESC");
    $stmt->execute([$userId]);
    $indirizzi = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistiche ordini
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as totale_ordini,
            COUNT(CASE WHEN stato_ordine = 2 THEN 1 END) as ordini_completati,
            COUNT(CASE WHEN stato_ordine = 0 THEN 1 END) as ordini_in_elaborazione,
            IFNULL(SUM(c.totale), 0) as totale_speso
        FROM ordine o 
        LEFT JOIN carrello c ON o.fk_carrello = c.id_carrello 
        WHERE o.fk_utente = ?
    ");
    $stmt->execute([$userId]);
    $statistiche = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Oggetti posseduti
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as oggetti_totali,
            SUM(quantita_ogg) as quantita_totale
        FROM oggetto_utente 
        WHERE fk_utente = ?
    ");
    $stmt->execute([$userId]);
    $collezione = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Punti utente (se esistono)
    $stmt = $pdo->prepare("SELECT * FROM punti_utente WHERE fk_utente = ?");
    $stmt->execute([$userId]);
    $punti = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verifica se è admin
    $stmt = $pdo->prepare("SELECT livello_admin FROM admin WHERE fk_utente = ?");
    $stmt->execute([$userId]);
    $adminInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $userData = [];
    $indirizzi = [];
    $statistiche = ['totale_ordini' => 0, 'ordini_completati' => 0, 'ordini_in_elaborazione' => 0, 'totale_speso' => 0];
    $collezione = ['oggetti_totali' => 0, 'quantita_totale' => 0];
    $punti = null;
    $adminInfo = null;
}

// Fallback ai dati di sessione se il database non restituisce dati
$nome = $userData['nome'] ?? SessionManager::get('user_nome', 'Utente');
$cognome = $userData['cognome'] ?? SessionManager::get('user_cognome', '');
$email = $userData['email'] ?? SessionManager::get('user_email', '');
$telefono = $userData['telefono'] ?? '';

?>

<main class="background-custom">
    <div class="container section">
        
        <h1 class="fashion_taital">Profilo Utente</h1>

        <div style="max-width: 1000px; margin: 0 auto;">
            
            <!-- Informazioni Personali -->
            <div style="background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); margin-bottom: 20px;">
                
                <h2 style="color: #333; margin-bottom: 20px; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">
                    👤 Informazioni Personali
                    <?php if ($adminInfo): ?>
                        <span style="background: #dc3545; color: white; padding: 5px 10px; border-radius: 15px; font-size: 12px; margin-left: 10px;">
                            <?php echo strtoupper($adminInfo['livello_admin']); ?>
                        </span>
                    <?php endif; ?>
                </h2>
                
                <div class="order-info-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                    <div class="info-item" style="padding: 10px; background: #f8f9fa; border-radius: 5px;">
                        <strong>Nome:</strong>
                        <span><?php echo htmlspecialchars($nome); ?></span>
                    </div>
                    <div class="info-item" style="padding: 10px; background: #f8f9fa; border-radius: 5px;">
                        <strong>Cognome:</strong>
                        <span><?php echo htmlspecialchars($cognome); ?></span>
                    </div>
                    <div class="info-item" style="padding: 10px; background: #f8f9fa; border-radius: 5px;">
                        <strong>Email:</strong>
                        <span><?php echo htmlspecialchars($email); ?></span>
                    </div>
                    <div class="info-item" style="padding: 10px; background: #f8f9fa; border-radius: 5px;">
                        <strong>Telefono:</strong>
                        <span><?php echo !empty($telefono) ? htmlspecialchars($telefono) : '<em style="color: #6c757d;">Non inserito</em>'; ?></span>
                    </div>
                </div>
            </div>

            <!-- Indirizzi di Spedizione -->
            <div style="background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); margin-bottom: 20px;">
                <h2 style="color: #333; margin-bottom: 20px; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">
                    🏠 Indirizzi di Spedizione
                </h2>
                
                <?php if (!empty($indirizzi)): ?>
                    <div style="display: grid; gap: 15px;">
                        <?php foreach ($indirizzi as $indirizzo): ?>
                            <div style="padding: 15px; border: 1px solid #ddd; border-radius: 8px; background: #f8f9fa;">
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
                                    <div><strong>Via:</strong> <?php echo htmlspecialchars($indirizzo['via'] . ' ' . $indirizzo['civico']); ?></div>
                                    <div><strong>CAP:</strong> <?php echo htmlspecialchars($indirizzo['cap']); ?></div>
                                    <div><strong>Città:</strong> <?php echo htmlspecialchars($indirizzo['citta']); ?></div>
                                    <div><strong>Provincia:</strong> <?php echo htmlspecialchars($indirizzo['provincia']); ?></div>
                                    <div><strong>Nazione:</strong> <?php echo htmlspecialchars($indirizzo['nazione']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color: #6c757d; font-style: italic; text-align: center; padding: 20px;">
                        📍 Nessun indirizzo di spedizione salvato
                    </p>
                <?php endif; ?>

                <!-- Bottone aggiungi indirizzo -->
                <div style="margin-top:20px; text-align:center;">
                    <a href="aggiungi_indirizzo.php" class="btn-add-to-cart" style="text-decoration: none; background-color: #17a2b8; display: inline-block; padding: 12px 20px; border-radius: 8px;">
                        🏠 Aggiungi indirizzo
                    </a>
                </div>
            </div>
            <!-- Statistiche Account -->
            <div style="background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); margin-bottom: 20px;">
                <h2 style="color: #333; margin-bottom: 20px; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">
                    📊 Statistiche Account
                </h2>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <!-- Statistiche Ordini -->
                    <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 10px;">
                        <div style="font-size: 24px; font-weight: bold;"><?php echo $statistiche['totale_ordini']; ?></div>
                        <div style="font-size: 14px; opacity: 0.9;">Ordini Totali</div>
                    </div>
                    
                    <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; border-radius: 10px;">
                        <div style="font-size: 24px; font-weight: bold;"><?php echo $statistiche['ordini_completati']; ?></div>
                        <div style="font-size: 14px; opacity: 0.9;">Ordini Completati</div>
                    </div>
                    
                    <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; border-radius: 10px;">
                        <div style="font-size: 24px; font-weight: bold;"><?php echo $statistiche['ordini_in_elaborazione']; ?></div>
                        <div style="font-size: 14px; opacity: 0.9;">In Elaborazione</div>
                    </div>
                    
                    <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; border-radius: 10px;">
                        <div style="font-size: 24px; font-weight: bold;">€<?php echo number_format($statistiche['totale_speso'], 2); ?></div>
                        <div style="font-size: 14px; opacity: 0.9;">Totale Speso</div>
                    </div>
                </div>
            </div>

            <!-- Collezione -->
            <div style="background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); margin-bottom: 20px;">
                <h2 style="color: #333; margin-bottom: 20px; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">
                    🎴 La Mia Collezione
                </h2>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                    <div style="text-align: center; padding: 25px; background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); border-radius: 10px;">
                        <div style="font-size: 28px; font-weight: bold; color: #8b4513;"><?php echo $collezione['oggetti_totali'] ?? 0; ?></div>
                        <div style="color: #8b4513; font-weight: 500;">Oggetti Unici</div>
                    </div>
                    
                    <div style="text-align: center; padding: 25px; background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); border-radius: 10px;">
                        <div style="font-size: 28px; font-weight: bold; color: #2c3e50;"><?php echo $collezione['quantita_totale'] ?? 0; ?></div>
                        <div style="color: #2c3e50; font-weight: 500;">Quantità Totale</div>
                    </div>
                </div>

                <!-- Bottone la mia collezione -->
                <div style="margin-top:20px; text-align:center;">
                    <a href="collezione.php" class="btn-add-to-cart" style="text-decoration: none; background-color: #28a745; display: inline-block; padding: 12px 20px; border-radius: 8px;">
                        🎴 La mia collezione
                    </a>
                </div>
            </div>

            <!-- Sistema Punti (se presente) -->
            <?php if ($punti): ?>
            <div style="background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); margin-bottom: 20px;">
                <h2 style="color: #333; margin-bottom: 20px; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">
                    🏆 Sistema Punti
                </h2>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div style="text-align: center; padding: 25px; background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%); border-radius: 10px;">
                        <div style="font-size: 32px; font-weight: bold; color: #8b008b;"><?php echo $punti['punti']; ?></div>
                        <div style="color: #8b008b; font-weight: 500;">Punti</div>
                    </div>
                    
                    <div style="text-align: center; padding: 25px; background: linear-gradient(135deg, #a8caba 0%, #5d4e75 100%); border-radius: 10px; color: white;">
                        <div style="font-size: 32px; font-weight: bold;"><?php echo $punti['livello']; ?></div>
                        <div style="opacity: 0.9; font-weight: 500;">Livello</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Pulsanti Azione -->
            <div style="background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
                <h2 style="color: #333; margin-bottom: 20px; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px;">
                    ⚙️ Azioni Account
                </h2>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <a href="ordini.php" class="btn-add-to-cart" style="text-decoration: none; text-align: center; display: block; padding: 15px;">
                        📦 I miei ordini
                    </a>
                    <a href="<?php echo (defined('BASE_URL') ? BASE_URL : ''); ?>/pages/modifica_profilo.php" class="btn-add-to-cart" style="text-decoration: none; background-color: #24B1D9; text-align: center; display: block; padding: 15px;">
                        ✏️ Modifica profilo
                    </a>
                    <?php if ($adminInfo): ?>
                    <a href="<?php echo (defined('BASE_URL') ? BASE_URL : ''); ?>/admin/" class="btn-add-to-cart" style="text-decoration: none; background-color: #6f42c1; text-align: center; display: block; padding: 15px;">
                        🔧 Pannello Admin
                    </a>
                    <?php endif; ?>
                    <a href="<?php echo (defined('BASE_URL') ? BASE_URL : ''); ?>/pages/auth/logout.php" class="btn-add-to-cart" style="text-decoration: none; background-color: #dc3545; text-align: center; display: block; padding: 15px;">
                        🚪 Logout
                    </a>
                </div>
            </div>

                    </div>
                </div>
            </main>

<style>
@media (max-width: 768px) {
    .order-info-grid {
        grid-template-columns: 1fr !important;
    }
    
    .container > div > div {
        padding: 20px !important;
    }
    
    .fashion_taital {
        font-size: 24px !important;
    }
}

.info-item {
    transition: transform 0.2s ease;
}

.info-item:hover {
    transform: translateY(-2px);
}

.btn-add-to-cart:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
}
</style>

<?php
include __DIR__ . '/footer.php';
?>