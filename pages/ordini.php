<?php
$hideNav = false;
include __DIR__ . '/header.php';
SessionManager::checkAuth();

$orders = [
    [
        'id' => 'ORD001',
        'date' => '2023-10-26',
        'status' => 'Completato',
        'address' => 'Via Roma 10, 20121 Milano (MI)',
        'quantity' => 2,
        'total' => '59.99',
        'image' => '/LTDW-project/images/mystery_box_fantasy.png'
    ],
    [
        'id' => 'ORD002',
        'date' => '2023-11-15',
        'status' => 'In Elaborazione',
        'address' => 'Piazza Duomo 1, 20122 Milano (MI)',
        'quantity' => 1,
        'total' => '29.99',
        'image' => '/LTDW-project/images/mystery_box_gaming.png'
    ],
    [
        'id' => 'ORD003',
        'date' => '2023-12-01',
        'status' => 'Annullato',
        'address' => 'Corso Buenos Aires 50, 20124 Milano (MI)',
        'quantity' => 3,
        'total' => '89.97',
        'image' => '/LTDW-project/images/mystery_box_classic.png'
    ]
];

?>

<main class="background-custom">
    <div class="container py-5">
        <h1 class="fashion_taital mb-5">Storico ordini</h1>

        <?php if (empty($orders)): ?>
            <div class="alert alert-info text-center" role="alert">
                Non hai ancora effettuato nessun ordine
            </div>
        <?php else: ?>
            <div class="orders-list">
                <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div class="order-image-container">
                            <img src="<?php echo htmlspecialchars($order['image']); ?>" alt="Immagine Ordine <?php echo htmlspecialchars($order['id']); ?>" class="order-image">
                        </div>
                        <div class="order-details">
                            <div class="order-header">
                                <h3>Ordine #<?php echo htmlspecialchars($order['id']); ?></h3>
                                <span class="order-status <?php echo strtolower(str_replace(' ', '-', $order['status'])); ?>">
                                    <?php echo htmlspecialchars($order['status']); ?>
                                </span>
                            </div>
                            <p><strong>Data Ordine:</strong> <?php echo htmlspecialchars($order['date']); ?></p>
                            <p><strong>Indirizzo:</strong> <?php echo htmlspecialchars($order['address']); ?></p>
                            <p><strong>Quantità Articoli:</strong> <?php echo htmlspecialchars($order['quantity']); ?></p>
                            <p class="order-total"><strong>Totale Pagato:</strong> €<?php echo htmlspecialchars(number_format($order['total'], 2)); ?></p>
                            <a href="#" class="btn btn-view-details">Vedi Dettagli</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>