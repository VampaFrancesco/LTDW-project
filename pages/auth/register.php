<?php
$hideNav = false;
include __DIR__ . '/../header.php';

?>
<div class="register">
    <label>
        Nome <br>
        <input name="Nome" type="text" placeholder="nome" maxlength="100"><br>
    </label>
    <label>
        Cognome <br>
        <input name="Cognome" type="text" placeholder="cognome" maxlength="100"><br>
    </label>
    <label>
        E-mail <br>
        <input name="Cognome" type="email" placeholder="email" maxlength="100"><br>
    </label>
    <label>
        Password <br>
        <input name="password" type="password" placeholder="password" maxlength="100"><br>
        <input name="password 2" type="password" placeholder="inserisci di nuovo la password" maxlength="100"><br>
    </label>

    <button name="register" onClick="onclick('index.php')">Registrati</button>
</div>

<?php
include '../footer.php'
?>
