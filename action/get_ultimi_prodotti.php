<?php

$config = include __DIR__ . '/../../include/config.inc.php';

// connessione
$conn = new mysqli(
    $config['host'],
    $config['user'],
    $config['passwd'],
    $config['dbname']
);
if ($conn->connect_error) {
    throw new RuntimeException("DB connection failed: " . $conn->connect_error);
}

// esegui la query
$sql    = "SELECT id_box, nome, prezzo FROM mystery_box ORDER BY id_box DESC LIMIT 10";
$result = $conn->query($sql);

// raccogli in array
$products = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    $result->free();
}

$conn->close();

// restituisci l’array dei prodotti
return $products;
