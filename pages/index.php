<?php
include 'header.php';
include '../include/config.inc.php';
?>

    <main class="background-custom"> <!-- Aggiunta classe specifica -->
        <div class="container">
            <div class="section">
                <!-- Contenuto della prima sezione -->
                <?php include 'sections/slider_prodotti.php'; ?>
            </div>

            <div class="section">
                <!-- Contenuto della seconda sezione -->
                <?php include 'sections/piu_venduti.php'; ?>
            </div>

            <div class="section">
                <!-- Contenuto della terza sezione -->
                <?php include 'sections/scopri_anche.php'; ?>
            </div>
        </div>
    </main>

<?php include 'footer.php'; ?>