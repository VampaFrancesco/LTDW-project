<?php 
 // 1. PRIMA di qualsiasi output 
 require_once __DIR__.'/../include/session_manager.php'; 
 require_once __DIR__.'/../include/config.inc.php'; 

 // 2. Richiedi login 
 SessionManager::requireLogin(); 

 // 3. Includi la logica per recuperare gli ordini 
 $orders = include '../action/get_orders.php'; 

 // 4. SOLO DOPO includi header 
 $hideNav = false; 
 include __DIR__ . '/header.php'; 
 ?> 

    <main class="background-custom"> 
        <div class="container py-5"> 
            <h1 class="fashion_taital mb-5">Storico ordini</h1> 

            <?php if (empty($orders)): ?> 
                <div class="alert alert-info text-center" role="alert"> 
                    <i class="bi bi-info-circle me-2"></i> 
                    Non hai ancora effettuato nessun ordine 
                </div> 
            <?php else: ?> 
                <div class="orders-list"> 
                    <?php foreach ($orders as $order): ?> 
                        <div class="order-card"> 
                            <div class="order-image-container"> 
                                <img src="<?php echo htmlspecialchars($order['image']); ?>" 
                                    alt="Immagine Ordine <?php echo htmlspecialchars($order['id']); ?>" 
                                    class="order-image" onerror="this.src='<?php echo BASE_URL; ?>/images/default_order.png'"> 
                            </div> 
                            <div class="order-details"> 
                                <div class="order-header"> 
                                    <h3><?php echo htmlspecialchars($order['product_name']); ?></h3> 
                                    <span class="order-status <?php echo strtolower(str_replace(' ', '-', $order['status'])); ?>"> 
                                    <?php echo htmlspecialchars($order['status']); ?> 
                                  </span> 
                                </div> 

                                <p><strong>Ordine:</strong> #<?php echo htmlspecialchars($order['id']); ?></p> 
                                <p><strong>Data Ordine:</strong> <?php echo htmlspecialchars($order['date']); ?></p> 
                                <p><strong>Indirizzo:</strong> <?php echo htmlspecialchars($order['address']); ?></p> 
                                <p><strong>Quantità Articoli:</strong> <?php echo htmlspecialchars($order['quantity']); ?></p> 

                                <?php if (!empty($order['tracking'])): ?> 
                                    <p><strong>Tracking:</strong> 
                                        <code><?php echo htmlspecialchars($order['tracking']); ?></code> 
                                    </p> 
                                <?php endif; ?> 

                                <p class="order-total"> 
                                    <strong>Totale Pagato:</strong> €<?php echo $order['total']; ?> 
                                </p> 

                                <button type="button" class="btn-view-details" 
                                        onclick="showOrderDetails('<?php echo $order['id']; ?>')"> 
                                    <i class="bi bi-eye"></i> Vedi Dettagli 
                                </button> 

                                <?php if ($order['raw_status'] == 2 && !empty($order['tracking'])): ?> 
                                    <a href="track_order.php?id=<?php echo htmlspecialchars($order['id']); ?>" class="btn btn-outline-primary ms-2">
                                        <i class="bi bi-truck"></i> Traccia Spedizione
                                    </a>
                                <?php endif; ?> 
                            </div> 
                        </div> 
                    <?php endforeach; ?> 
                </div> 
            <?php endif; ?> 
        </div> 
    </main> 

    <div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-labelledby="orderDetailsModalLabel" aria-hidden="true"> 
        <div class="modal-dialog modal-lg"> 
            <div class="modal-content"> 
                <div class="modal-header"> 
                    <h5 class="modal-title" id="orderDetailsModalLabel"> 
                        <i class="bi bi-receipt"></i> Dettagli Ordine 
                    </h5> 
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button> 
                </div> 
                <div class="modal-body" id="orderDetailsContent"> 
                    <div class="text-center"> 
                        <div class="spinner-border text-primary" role="status"> 
                            <span class="visually-hidden">Caricamento...</span> 
                        </div> 
                    </div> 
                </div> 
                <div class="modal-footer"> 
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button> 
                    <button type="button" class="btn btn-primary" onclick="printOrder()"> 
                        <i class="bi bi-printer"></i> Stampa 
                    </button> 
                </div> 
            </div> 
        </div> 
    </div> 

    <script> 
        // Dati degli ordini per JavaScript 
        const ordersData = <?php echo json_encode($orders); ?>; 

        function showOrderDetails(orderId) { 
            // Trova l'ordine nei dati 
            const order = ordersData.find(o => o.id === orderId); 

            if (!order) { 
                alert('Ordine non trovato'); 
                return; 
            } 

            // Genera il contenuto HTML per i dettagli 
            const detailsHTML = ` 
            <div class="order-details-container"> 
                <div class="row"> 
                    <div class="col-md-4 text-center"> 
                        <img src="${order.image}" alt="Prodotto" class="img-fluid rounded mb-3" style="max-height: 200px;" onerror="this.src='<?php echo BASE_URL; ?>/images/default_order.png'"> 
                        <h5 class="text-primary">${order.product_name}</h5> 
                    </div> 
                    <div class="col-md-8"> 
                        <div class="order-info-grid"> 
                            <div class="info-item"> 
                                <strong><i class="bi bi-hash"></i> Numero Ordine:</strong> 
                                <span>${order.id}</span> 
                            </div> 
                            <div class="info-item"> 
                                <strong><i class="bi bi-calendar"></i> Data Ordine:</strong> 
                                <span>${order.date}</span> 
                            </div> 
                            <div class="info-item"> 
                                <strong><i class="bi bi-flag"></i> Stato:</strong> 
                                <span class="badge bg-${getStatusColor(order.status)}">${order.status}</span> 
                            </div> 
                            <div class="info-item"> 
                                <strong><i class="bi bi-geo-alt"></i> Indirizzo di Spedizione:</strong> 
                                <span>${order.address}</span> 
                            </div> 
                            <div class="info-item"> 
                                <strong><i class="bi bi-box"></i> Quantità:</strong> 
                                <span>${order.quantity} articolo${order.quantity > 1 ? 'i' : ''}</span> 
                            </div> 
                            ${order.tracking ? ` 
                                <div class="info-item"> 
                                    <strong><i class="bi bi-truck"></i> Codice Tracking:</strong> 
                                    <span><code>${order.tracking}</code></span> 
                                </div> 
                            ` : ''} 
                            <div class="info-item total-item"> 
                                <strong><i class="bi bi-currency-euro"></i> Totale Pagato:</strong> 
                                <span class="text-success fs-5 fw-bold">€${order.total}</span> 
                            </div> 
                        </div> 
                    </div> 
                </div> 

                ${order.raw_status == 2 && order.tracking ? ` 
                    <div class="mt-4 p-3 bg-light rounded"> 
                        <h6><i class="bi bi-info-circle"></i> Informazioni Spedizione</h6> 
                        <p class="mb-2">Il tuo ordine è stato spedito con codice tracking: <strong>${order.tracking}</strong></p> 
                        <a href="track_order.php?id=${order.id}" class="btn btn-outline-primary">
                            <i class="bi bi-truck"></i> Traccia Spedizione
                        </a>
                    </div> 
                ` : ''} 

                ${order.raw_status == 1 ? ` 
                    <div class="mt-4 p-3 bg-success bg-opacity-10 rounded"> 
                        <h6 class="text-success"><i class="bi bi-check-circle"></i> Ordine Completato</h6> 
                        <p class="mb-0">Il tuo ordine è stato completato con successo. Grazie per aver acquistato da noi!</p> 
                    </div> 
                ` : ''} 
            </div> 
            `; 

            // Inserisce il contenuto nel modal 
            document.getElementById('orderDetailsContent').innerHTML = detailsHTML; 

            // Mostra il modal 
            const modal = new bootstrap.Modal(document.getElementById('orderDetailsModal')); 
            modal.show(); 
        } 

        function getStatusColor(status) { 
            switch(status.toLowerCase()) { 
                case 'completato': return 'success'; 
                case 'in elaborazione': return 'warning'; 
                case 'spedito': return 'info'; 
                case 'annullato': return 'danger'; 
                default: return 'secondary'; 
            } 
        } 

        function printOrder() {
    const orderDetailsContent = document.getElementById('orderDetailsContent').innerHTML;

    const printContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Dettagli Ordine - BoxOmnia</title>
            <link rel="stylesheet" href="../assets/css/print_styles.css">
        </head>
        <body>
            <div class="print-container">
                <div class="print-header">
                    <h1>BoxOmnia</h1>
                    <div class="print-company-info">
                        <p>Email: info@mysterybox.it</p>
                        <p>Telefono: +39 000 000 000</p>
                        <p>Indirizzo: Via delle Sorprese, 123 - Roma</p>
                        <p>Orari: Lun - Ven: 9:00 - 18:00</p>
                    </div>
                </div>
                <div class="print-order-content">
                    ${orderDetailsContent}
                </div>
                <div class="print-footer">
                    <p>Grazie per il tuo acquisto su BoxOmnia!</p>
                </div>
            </div>
        </body>
        </html>
    `;

    const printWindow = window.open('', '_blank');
    printWindow.document.write(printContent);
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
    printWindow.close();
}
    </script> 

 <?php include __DIR__ . '/footer.php'; ?>