<?php
// test_update_cart.php - Metti questo file nella stessa cartella di update_cart.php
// Accedi a questo file direttamente dal browser per testare

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../include/session_manager.php';
require_once __DIR__ . '/../include/config.inc.php';

SessionManager::startSecureSession();

// Test 1: Verifica la configurazione
echo "<h2>Test Configurazione</h2>";
echo "Session ID: " . session_id() . "<br>";
echo "User logged in: " . (SessionManager::isLoggedIn() ? 'Yes' : 'No') . "<br>";

if (SessionManager::isLoggedIn()) {
    echo "User ID: " . SessionManager::get('user_id') . "<br>";
}

// Test 2: Verifica connessione database
echo "<h2>Test Database</h2>";
$db_config = $config['dbms']['localhost'];
$conn = new mysqli(
    $db_config['host'],
    $db_config['user'],
    $db_config['passwd'],
    $db_config['dbname']
);

if ($conn->connect_error) {
    echo "❌ Errore connessione: " . $conn->connect_error . "<br>";
} else {
    echo "✅ Connessione OK<br>";

    // Mostra contenuto carrello se loggato
    if (SessionManager::isLoggedIn()) {
        $id_utente = SessionManager::get('user_id');
        $query = "SELECT * FROM carrello WHERE fk_utente = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id_utente);
        $stmt->execute();
        $result = $stmt->get_result();

        echo "<h3>Contenuto Carrello Database:</h3>";
        if ($result->num_rows > 0) {
            echo "<table border='1'>";
            echo "<tr><th>ID</th><th>Mystery Box</th><th>Oggetto</th><th>Quantità</th><th>Totale</th></tr>";
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row['id_carrello'] . "</td>";
                echo "<td>" . ($row['fk_mystery_box'] ?? 'NULL') . "</td>";
                echo "<td>" . ($row['fk_oggetto'] ?? 'NULL') . "</td>";
                echo "<td>" . $row['quantita'] . "</td>";
                echo "<td>" . $row['totale'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "Carrello vuoto nel database<br>";
        }
        $stmt->close();
    }
}

// Test 3: Mostra carrello sessione
echo "<h3>Contenuto Carrello Sessione:</h3>";
$cart = SessionManager::get('cart', []);
if (!empty($cart)) {
    echo "<pre>";
    print_r($cart);
    echo "</pre>";
} else {
    echo "Carrello sessione vuoto<br>";
}

// Test 4: Test chiamata AJAX simulata
echo "<h2>Test Chiamata Update</h2>";
echo "<button onclick='testUpdate()'>Test Update Quantity</button>";
echo "<button onclick='testRemove()'>Test Remove Item</button>";
echo "<div id='result'></div>";
?>

<script>
    function testUpdate() {
        // Adatta questo in base a un item_key reale nel tuo carrello
        const itemKey = 'mystery_box_1'; // Cambia con un valore reale

        fetch('update_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=update&item_key=' + itemKey + '&quantity=2'
        })
            .then(response => response.text())
            .then(text => {
                document.getElementById('result').innerHTML = '<pre>Response: ' + text + '</pre>';
                console.log('Raw response:', text);
                try {
                    const data = JSON.parse(text);
                    console.log('Parsed data:', data);
                } catch (e) {
                    console.error('Parse error:', e);
                }
            })
            .catch(error => {
                document.getElementById('result').innerHTML = 'Error: ' + error;
                console.error('Error:', error);
            });
    }

    function testRemove() {
        const itemKey = 'mystery_box_1'; // Cambia con un valore reale

        fetch('update_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=remove&item_key=' + itemKey
        })
            .then(response => response.text())
            .then(text => {
                document.getElementById('result').innerHTML = '<pre>Response: ' + text + '</pre>';
                console.log('Raw response:', text);
            })
            .catch(error => {
                document.getElementById('result').innerHTML = 'Error: ' + error;
            });
    }
</script>