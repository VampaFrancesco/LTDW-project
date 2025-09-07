<?php
include __DIR__ . '/header.php';

// Configurazione connessione database
try {
    $pdo = new PDO('mysql:host=localhost;dbname=boxomnia;charset=utf8mb4', 'admin', 'admin');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Recupera tutti i contenuti della pagina About
    $stmt = $pdo->prepare("
        SELECT id_contenuto, testo_contenuto 
        FROM contenuti_modificabili 
        WHERE id_contenuto IN (
            'about_titolo_principale',
            'about_paragrafo_intro',
            'about_storia_titolo',
            'about_storia_testo',
            'about_cosa_troverai_titolo',
            'about_cosa_troverai_testo',
            'about_valori_titolo',
            'about_valori_testo',
            'about_community_titolo',
            'about_community_testo'
        )
    ");
    $stmt->execute();
    $contenuti_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organizza i contenuti
    $contenuti = [];
    foreach ($contenuti_raw as $contenuto) {
        $contenuti[$contenuto['id_contenuto']] = $contenuto['testo_contenuto'];
    }

} catch (PDOException $e) {
    // In caso di errore, usa valori di fallback
    $contenuti = [];
}

// Contenuti di default (fallback)
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

// Combina contenuti DB con fallback
foreach ($contenuti_default as $chiave => $valore_default) {
    if (!isset($contenuti[$chiave])) {
        $contenuti[$chiave] = $valore_default;
    }
}

function getContenuto($chiave, $contenuti) {
    return htmlspecialchars_decode($contenuti[$chiave] ?? '');
}
?>

    <main class="background-custom about-page">
        <div class="container section">
            <h1 class="fashion_taital h2-about"><?= getContenuto('about_titolo_principale', $contenuti); ?></h1>

            <p class="lead">
                <?= getContenuto('about_paragrafo_intro', $contenuti); ?>
            </p>

            <div class="about-content">

                <div class="about-image-section">
                    <img src="../images/about1.webp"
                         alt="Mystery Box">
                    <img src="../images/about2.jpg"
                         alt="Seconda immagine">
                </div>

                <!-- Testo a destra -->
                <div class="about-text-section">

                    <h2><?= getContenuto('about_storia_titolo', $contenuti); ?></h2>
                    <p>
                        <?= getContenuto('about_storia_testo', $contenuti); ?>
                    </p>

                    <h2><?= getContenuto('about_cosa_troverai_titolo', $contenuti); ?></h2>
                    <p>
                        <?= getContenuto('about_cosa_troverai_testo', $contenuti); ?>
                    </p>

                    <h2><?= getContenuto('about_valori_titolo', $contenuti); ?></h2>
                    <p>
                        <?= getContenuto('about_valori_testo', $contenuti); ?>
                    </p>

                    <h2><?= getContenuto('about_community_titolo', $contenuti); ?></h2>
                    <p>
                        <?= getContenuto('about_community_testo', $contenuti); ?>
                    </p>

                </div>
            </div>
        </div>
    </main>

<?php
include __DIR__ . '/footer.php';
?>