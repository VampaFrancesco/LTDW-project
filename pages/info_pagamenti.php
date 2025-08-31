<?php 
include __DIR__ . '/header.php';
?>


<!-- Metodi di Pagamento Disponibili -->
 <main class="section-box">
        <div >
    <h2 class="section-title">
        💰 Metodi di Pagamento Disponibili
    </h2>
    
    <div class="payment-methods-grid">
        <!-- Carte di Credito/Debito -->
        <div class="payment-card credit-card">
            <div class="payment-header">
                <div class="payment-icon">💳</div>
                <div class="payment-name">Carte di Credito/Debito</div>
            </div>
            <div class="payment-description">
                Accettiamo tutte le principali carte di credito e debito per pagamenti immediati e sicuri.
            </div>
            <ul class="payment-features">
                <li>Visa, Mastercard, American Express</li>
                <li>Pagamento immediato</li>
                <li>Protezione 3D Secure</li>
                <li>Nessuna commissione aggiuntiva</li>
            </ul>
        </div>

        <!-- PayPal -->
        <div class="payment-card paypal">
            <div class="payment-header">
                <div class="payment-icon"> <img src="https://www.paypalobjects.com/webstatic/mktg/logo/pp_cc_mark_37x23.jpg"> </div>
                <div class="payment-name">PayPal</div>
            </div>
            <div class="payment-description">
                Paga rapidamente con il tuo account PayPal senza condividere i dati della tua carta.
            </div>
            <ul class="payment-features">
                <li>Login rapido con PayPal</li>
                <li>Protezione acquirente</li>
                <li>Nessun dato sensibile condiviso</li>
                <li>Pagamento in 1 click</li>
            </ul>
        </div>

        <!-- Bonifico Bancario -->
        <div class="payment-card bank-transfer">
            <div class="payment-header">
                <div class="payment-icon">🏦</div>
                <div class="payment-name">Bonifico Bancario</div>
            </div>
            <div class="payment-description">
                Per ordini di importo elevato, accettiamo pagamenti tramite bonifico bancario.
            </div>
            <ul class="payment-features">
                <li>Per ordini superiori a €100</li>
                <li>Nessuna commissione</li>
                <li>Spedizione dopo accredito</li>
                <li>Coordinate bancarie fornite via email</li>
            </ul>
        </div>
    </div>
</div>
</main>
<?php 
include __DIR__ . '/footer.php';
?>

