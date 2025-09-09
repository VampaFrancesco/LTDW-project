<?php
include __DIR__ . '/header.php';

// Configurazione connessione database
try {
    $pdo = new PDO('mysql:host=localhost;dbname=boxomnia;charset=utf8mb4', 'admin', 'admin');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Recupera tutti i contenuti della pagina FAQ
    $stmt = $pdo->prepare("
        SELECT id_contenuto, testo_contenuto 
        FROM contenuti_modificabili 
        WHERE id_contenuto LIKE 'faq_%'
        ORDER BY id_contenuto
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
        'faq_titolo_pagina' => 'FAQ Pagamenti',
        'faq_intro_titolo' => 'Centro Assistenza Pagamenti',
        'faq_intro_testo' => 'Trova le risposte alle domande più comuni sui nostri metodi di pagamento. Se non trovi quello che cerchi, contatta il nostro supporto clienti.',
        'faq_q1' => 'I miei dati di pagamento sono sicuri?',
        'faq_a1' => '<strong>Assolutamente sì!</strong> La sicurezza dei tuoi dati è la nostra priorità assoluta. Non memorizziamo mai i dati delle tue carte di credito sui nostri server. Tutti i pagamenti vengono elaborati tramite gateway sicuri certificati PCI DSS con crittografia SSL 256-bit. Inoltre, utilizziamo tecnologie di protezione antifrode e monitoraggio 24/7 per garantire la massima sicurezza delle transazioni.',
        'faq_q2' => 'Posso modificare il metodo di pagamento dopo aver effettuato l\'ordine?',
        'faq_a2' => 'Una volta confermato l\'ordine e processato il pagamento, <strong>non è possibile modificare il metodo di pagamento</strong>. Tuttavia, se l\'ordine è ancora in fase di elaborazione (entro 30 minuti dall\'acquisto), puoi contattare immediatamente il nostro supporto clienti che valuterà la possibilità di assistenza. Per ordini futuri, assicurati di selezionare il metodo di pagamento preferito prima di confermare l\'acquisto.',
        'faq_q3' => 'Cosa succede se il pagamento non va a buon fine?',
        'faq_a3' => 'In caso di pagamento non riuscito, <strong>riceverai una notifica immediata</strong> via email con i dettagli dell\'errore. Il tuo ordine rimarrà in sospeso per 24 ore, durante le quali potrai accedere al tuo account e riprovare il pagamento con lo stesso o un diverso metodo. Se il problema persiste, verifica i dati della carta, il saldo disponibile o contatta la tua banca. Il nostro supporto è sempre disponibile per assistenza.',
        'faq_q4' => 'Accettate pagamenti rateali?',
        'faq_a4' => 'Attualmente <strong>non offriamo pagamenti rateali diretti</strong> sul nostro sito. Tuttavia, puoi utilizzare servizi esterni come <strong>PayPal Pay in 4</strong> (se disponibile al checkout), oppure le funzioni di rateizzazione della tua carta di credito offerte dalla tua banca. Molte banche permettono di convertire gli acquisti in rate direttamente dall\'app o dal sito della banca dopo l\'acquisto.',
        'faq_q5' => 'Quanto tempo ci vuole per elaborare il pagamento?',
        'faq_a5' => 'I tempi di elaborazione variano per metodo:<ul style="margin: 10px 0; padding-left: 20px;"><li><strong>Carte di credito/debito:</strong> Immediato (max 2-3 minuti)</li><li><strong>PayPal:</strong> Immediato</li><li><strong>Stripe:</strong> Immediato</li><li><strong>Bonifico bancario:</strong> 1-3 giorni lavorativi</li></ul><p>Una volta elaborato il pagamento, riceverai immediatamente una conferma via email e l\'ordine entrerà in preparazione.</p>',
        'faq_q6' => 'Ci sono commissioni aggiuntive sui pagamenti?',
        'faq_a6' => '<strong>No, tutti i nostri metodi di pagamento sono gratuiti!</strong> Non applichiamo commissioni aggiuntive su nessun metodo di pagamento accettato. Il prezzo che vedi al checkout è quello finale che pagherai. Eventuali commissioni potrebbero essere applicate dalla tua banca o dal fornitore della carta, ma non dipendono da noi.',
        'faq_q7' => 'Posso pagare con carte prepagate?',
        'faq_a7' => '<strong>Sì, accettiamo carte prepagate</strong> Visa, Mastercard e American Express, purché siano abilitate per acquisti online e abbiano credito sufficiente per coprire l\'importo dell\'ordine. Assicurati che la carta prepagata sia registrata con i tuoi dati corretti e che sia abilitata per transazioni internazionali se necessario.',
        'faq_q8' => 'Come posso richiedere un rimborso?',
        'faq_a8' => 'Per richiedere un rimborso, contatta il nostro <strong>servizio clienti entro 14 giorni</strong> dall\'acquisto. I rimborsi vengono elaborati utilizzando lo stesso metodo di pagamento utilizzato per l\'acquisto:<ul style="margin: 10px 0; padding-left: 20px;"><li><strong>Carte di credito/debito:</strong> 3-5 giorni lavorativi</li><li><strong>PayPal:</strong> 1-2 giorni lavorativi</li><li><strong>Bonifico bancario:</strong> 3-7 giorni lavorativi</li></ul><p>Riceverai una conferma via email quando il rimborso sarà stato elaborato.</p>',
        'faq_contatti_titolo' => 'Non hai trovato la risposta che cercavi?',
        'faq_contatti_testo' => 'Il nostro team di supporto è disponibile 7 giorni su 7 per aiutarti con qualsiasi domanda sui pagamenti.'
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

    <main class="background-custom">
        <div class="container">

            <!-- Header -->
            <div class="page-header">
                <h1 class="fashion_taital mb-5"><?= getContenuto('faq_titolo_pagina', $contenuti); ?></h1>
            </div>

            <!-- FAQ Container -->
            <div class="faq-container">

                <!-- Intro -->
                <div class="faq-intro">
                    <div class="faq-intro-icon">❓</div>
                    <h1 class="fashion-taital mb-5"><?= getContenuto('faq_intro_titolo', $contenuti); ?></h1>
                    <p class="faq-intro-text">
                        <?= getContenuto('faq_intro_testo', $contenuti); ?>
                    </p>
                </div>

                <!-- FAQ Items -->
                <div class="faq-list">

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <h4><?= getContenuto('faq_q1', $contenuti); ?></h4>
                            <span class="faq-toggle">+</span>
                        </div>
                        <div class="faq-answer">
                            <p><?= getContenuto('faq_a1', $contenuti); ?></p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <h4><?= getContenuto('faq_q2', $contenuti); ?></h4>
                            <span class="faq-toggle">+</span>
                        </div>
                        <div class="faq-answer">
                            <p><?= getContenuto('faq_a2', $contenuti); ?></p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <h4><?= getContenuto('faq_q3', $contenuti); ?></h4>
                            <span class="faq-toggle">+</span>
                        </div>
                        <div class="faq-answer">
                            <p><?= getContenuto('faq_a3', $contenuti); ?></p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <h4><?= getContenuto('faq_q4', $contenuti); ?></h4>
                            <span class="faq-toggle">+</span>
                        </div>
                        <div class="faq-answer">
                            <p><?= getContenuto('faq_a4', $contenuti); ?></p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <h4><?= getContenuto('faq_q5', $contenuti); ?></h4>
                            <span class="faq-toggle">+</span>
                        </div>
                        <div class="faq-answer">
                            <p><?= getContenuto('faq_a5', $contenuti); ?></p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <h4><?= getContenuto('faq_q6', $contenuti); ?></h4>
                            <span class="faq-toggle">+</span>
                        </div>
                        <div class="faq-answer">
                            <p><?= getContenuto('faq_a6', $contenuti); ?></p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <h4><?= getContenuto('faq_q7', $contenuti); ?></h4>
                            <span class="faq-toggle">+</span>
                        </div>
                        <div class="faq-answer">
                            <p><?= getContenuto('faq_a7', $contenuti); ?></p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <h4><?= getContenuto('faq_q8', $contenuti); ?></h4>
                            <span class="faq-toggle">+</span>
                        </div>
                        <div class="faq-answer">
                            <p><?= getContenuto('faq_a8', $contenuti); ?></p>
                        </div>
                    </div>

                </div>

                <!-- Contact Section -->
                <div class="contact-section">
                    <h3 class="contact-title"><?= getContenuto('faq_contatti_titolo', $contenuti); ?></h3>
                    <p class="contact-text">
                        <?= getContenuto('faq_contatti_testo', $contenuti); ?>
                    </p>
                    <a href="contatti.php" class="btn btn-filter-nav">💬 Contattaci</a>
                </div>

                <!-- Back Link -->
                <div class="back-link">
                    <a href="home_utente.php" class="btn btn-filter-nav">← Torna alla home</a>
                </div>

            </div>

        </div>
    </main>

    <script>
        function toggleFAQ(element) {
            const faqItem = element.parentNode;
            const answer = faqItem.querySelector('.faq-answer');
            const toggle = element.querySelector('.faq-toggle');

            // Chiudi tutti gli altri FAQ aperti
            document.querySelectorAll('.faq-item.active').forEach(item => {
                if (item !== faqItem) {
                    item.classList.remove('active');
                    item.querySelector('.faq-answer').classList.remove('active');
                    item.querySelector('.faq-toggle').textContent = '+';
                }
            });

            // Toggle dell'elemento corrente
            faqItem.classList.toggle('active');
            answer.classList.toggle('active');

            if (faqItem.classList.contains('active')) {
                toggle.textContent = '+';
                toggle.style.transform = 'rotate(45deg)';
            } else {
                toggle.textContent = '+';
                toggle.style.transform = 'rotate(0deg)';
            }
        }

        // Chiudi FAQ cliccando fuori
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.faq-item')) {
                document.querySelectorAll('.faq-item.active').forEach(item => {
                    item.classList.remove('active');
                    item.querySelector('.faq-answer').classList.remove('active');
                    item.querySelector('.faq-toggle').textContent = '+';
                    item.querySelector('.faq-toggle').style.transform = 'rotate(0deg)';
                });
            }
        });
    </script>


<?php
include __DIR__ . '/footer.php';
?>