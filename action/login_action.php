<?php

try {
    require_once __DIR__ . '/../include/session_manager.php';
    require_once __DIR__ . '/../include/config.inc.php';

    if (!defined('BASE_URL')) {
        define('BASE_URL', '/LTDW-project');
    }

    SessionManager::startSecureSession();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ' . BASE_URL . '/pages/auth/login.php');
        exit();
    }

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        SessionManager::setFlashMessage('Inserisci email e password', 'danger');
        SessionManager::set('login_form_data', ['email' => $email]);
        header('Location: ' . BASE_URL . '/pages/auth/login.php');
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        SessionManager::setFlashMessage('Formato email non valido', 'danger');
        SessionManager::set('login_form_data', ['email' => $email]);
        header('Location: ' . BASE_URL . '/pages/auth/login.php');
        exit();
    }

    $db_config = $config['dbms']['localhost'];
    $conn = new mysqli(
        $db_config['host'],
        $db_config['user'],
        $db_config['passwd'],
        $db_config['dbname']
    );

    if ($conn->connect_error) {
        error_log("Login - Database connection failed: " . $conn->connect_error);
        SessionManager::setFlashMessage('Servizio temporaneamente non disponibile. Riprova più tardi.', 'danger');
        header('Location: ' . BASE_URL . '/pages/auth/login.php');
        exit();
    }

    $stmt = $conn->prepare("SELECT id_utente, password, nome, cognome, email FROM utente WHERE email = ?");
    if (!$stmt) {
        error_log("Login - Query preparation failed: " . $conn->error);
        SessionManager::setFlashMessage('Errore interno. Riprova più tardi.', 'danger');
        $conn->close();
        header('Location: ' . BASE_URL . '/pages/auth/login.php');
        exit();
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            error_log("Login successful for: " . $email . " (ID: " . $user['id_utente'] . ")");

            // IMPOSTA SESSIONE UTENTE
            SessionManager::set('user_logged_in', true);
            SessionManager::set('user_id', $user['id_utente']);
            SessionManager::set('user_nome', $user['nome']);
            SessionManager::set('user_cognome', $user['cognome']);
            SessionManager::set('user_email', $user['email']);

            // CONTROLLA SE È ADMIN
            $isAdmin = false;
            $adminLevel = null;

            if (strlen($email) >= 12 && substr(strtolower($email), -12) === '@boxomnia.it') {
                error_log("Admin email detected: " . $email);

                $adminStmt = $conn->prepare("SELECT id_admin, livello_admin FROM admin WHERE fk_utente = ?");
                if ($adminStmt) {
                    $adminStmt->bind_param("i", $user['id_utente']);
                    $adminStmt->execute();
                    $adminResult = $adminStmt->get_result();

                    if ($adminResult->num_rows === 1) {
                        $adminData = $adminResult->fetch_assoc();
                        $isAdmin = true;
                        $adminLevel = $adminData['livello_admin'];
                        error_log("User confirmed as admin with level: " . $adminLevel);
                    } else {
                        error_log("Email @boxomnia.it but not found in admin table for user: " . $user['id_utente']);
                    }
                    $adminStmt->close();
                } else {
                    error_log("Failed to prepare admin check query: " . $conn->error);
                }
            }

            // IMPOSTA STATO ADMIN
            SessionManager::set('user_is_admin', $isAdmin);
            if ($adminLevel) {
                SessionManager::set('admin_level', $adminLevel);
            }

            // ========================================
            // CONSOLIDAMENTO CARRELLO PER TUTTI GLI UTENTI (NON SOLO ADMIN!)
            // ========================================
            try {
                $session_cart = SessionManager::get('cart', []);

                if (!empty($session_cart)) {
                    error_log("Found session cart with " . count($session_cart) . " items. Consolidating...");

                    foreach ($session_cart as $item_key => $item) {
                        $product_id = $item['id_prodotto'] ?? 0;
                        $quantita = $item['quantita'] ?? 1;
                        $prezzo = $item['prezzo'] ?? 0;
                        $tipo = $item['tipo'] ?? '';

                        if ($product_id <= 0 || !in_array($tipo, ['mystery_box', 'oggetto'])) {
                            continue;
                        }

                        if ($tipo === 'mystery_box') {
                            $check_stmt = $conn->prepare("
                                SELECT id_carrello, quantita 
                                FROM carrello 
                                WHERE fk_utente = ? 
                                AND fk_mystery_box = ? 
                                AND stato = 'attivo'
                            ");
                            $check_stmt->bind_param("ii", $user['id_utente'], $product_id);
                        } else {
                            $check_stmt = $conn->prepare("
                                SELECT id_carrello, quantita 
                                FROM carrello 
                                WHERE fk_utente = ? 
                                AND fk_oggetto = ? 
                                AND stato = 'attivo'
                            ");
                            $check_stmt->bind_param("ii", $user['id_utente'], $product_id);
                        }

                        $check_stmt->execute();
                        $existing = $check_stmt->get_result()->fetch_assoc();
                        $check_stmt->close();

                        if ($existing) {
                            $nuova_quantita = $existing['quantita'] + $quantita;
                            $nuovo_totale = $nuova_quantita * $prezzo;

                            $update_stmt = $conn->prepare("
                                UPDATE carrello 
                                SET quantita = ?, 
                                    totale = ?, 
                                    data_ultima_modifica = NOW() 
                                WHERE id_carrello = ?
                            ");
                            $update_stmt->bind_param("idi", $nuova_quantita, $nuovo_totale, $existing['id_carrello']);
                            $update_stmt->execute();
                            $update_stmt->close();

                            error_log("Updated existing cart item: $tipo ID $product_id");
                        } else {
                            $totale = $quantita * $prezzo;

                            if ($tipo === 'mystery_box') {
                                $insert_stmt = $conn->prepare("
                                    INSERT INTO carrello 
                                    (fk_utente, fk_mystery_box, fk_oggetto, quantita, totale, stato, data_creazione, data_ultima_modifica) 
                                    VALUES (?, ?, NULL, ?, ?, 'attivo', NOW(), NOW())
                                ");
                                $insert_stmt->bind_param("iiid", $user['id_utente'], $product_id, $quantita, $totale);
                            } else {
                                $insert_stmt = $conn->prepare("
                                    INSERT INTO carrello 
                                    (fk_utente, fk_mystery_box, fk_oggetto, quantita, totale, stato, data_creazione, data_ultima_modifica) 
                                    VALUES (?, NULL, ?, ?, ?, 'attivo', NOW(), NOW())
                                ");
                                $insert_stmt->bind_param("iiid", $user['id_utente'], $product_id, $quantita, $totale);
                            }

                            $insert_stmt->execute();
                            $insert_stmt->close();

                            error_log("Added new cart item: $tipo ID $product_id");
                        }
                    }

                    SessionManager::remove('cart');
                    error_log("Session cart consolidated and cleared");
                }

                // Marca come completati i carrelli che hanno ordini associati
                $cleanup_stmt = $conn->prepare("
                    UPDATE carrello c
                    INNER JOIN ordine o ON c.id_carrello = o.fk_carrello
                    SET c.stato = 'completato'
                    WHERE c.fk_utente = ? 
                    AND c.stato = 'attivo'
                ");
                $cleanup_stmt->bind_param("i", $user['id_utente']);
                $cleanup_stmt->execute();
                $cleanup_stmt->close();

                // Pulisci vecchi carrelli abbandonati
                $cleanup_stmt = $conn->prepare("
                    UPDATE carrello 
                    SET stato = 'abbandonato' 
                    WHERE fk_utente = ? 
                    AND stato = 'attivo' 
                    AND data_ultima_modifica < DATE_SUB(NOW(), INTERVAL 7 DAY)
                    AND id_carrello NOT IN (
                        SELECT fk_carrello FROM ordine WHERE fk_carrello IS NOT NULL
                    )
                ");
                $cleanup_stmt->bind_param("i", $user['id_utente']);
                $cleanup_stmt->execute();
                $cleanup_stmt->close();

                // Aggiorna il contatore del carrello
                $count_stmt = $conn->prepare("
                    SELECT SUM(quantita) as total 
                    FROM carrello 
                    WHERE fk_utente = ? AND stato = 'attivo'
                ");
                $count_stmt->bind_param("i", $user['id_utente']);
                $count_stmt->execute();
                $count_result = $count_stmt->get_result()->fetch_assoc();
                $count_stmt->close();

                SessionManager::set('cart_items_count', $count_result['total'] ?? 0);

            } catch (Exception $cart_error) {
                error_log("Cart consolidation error: " . $cart_error->getMessage());
            }
            // ========================================
            // FINE CONSOLIDAMENTO CARRELLO
            // ========================================

            // REGENERA ID SESSIONE PER SICUREZZA
            session_regenerate_id(true);

            $stmt->close();
            $conn->close();

            // REDIRECT BASATO SUL RUOLO
            $redirect_url = SessionManager::get('redirect_after_login');

            if ($redirect_url) {
                SessionManager::remove('redirect_after_login');
                $final_redirect = $redirect_url;
                error_log("Redirecting to saved page: " . $final_redirect);
            } elseif ($isAdmin) {
                $final_redirect = BASE_URL . '/pages/dashboard/dashboard.php';
                error_log("Redirecting admin to dashboard");
            } else {
                $final_redirect = BASE_URL . '/pages/home_utente.php';
                error_log("Redirecting user to home");
            }

            ob_end_clean();
            header('Location: ' . $final_redirect);
            exit();

        } else {
            error_log("Login failed - wrong password for: " . $email);
        }
    } else {
        error_log("Login failed - user not found: " . $email);
    }

    // LOGIN FALLITO
    $stmt->close();
    $conn->close();

    SessionManager::setFlashMessage('Email o password non corretti', 'danger');
    SessionManager::set('login_form_data', ['email' => $email]);

    ob_end_clean();
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit();

} catch (Exception $e) {
    error_log("Login critical error: " . $e->getMessage());
    error_log("Login error trace: " . $e->getTraceAsString());

    if (isset($stmt) && $stmt) $stmt->close();
    if (isset($conn) && $conn) $conn->close();

    SessionManager::setFlashMessage('Si è verificato un errore imprevisto. Riprova più tardi.', 'danger');

    ob_end_clean();
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit();

} catch (Error $e) {
    error_log("Login fatal error: " . $e->getMessage());
    error_log("Login fatal error trace: " . $e->getTraceAsString());

    if (isset($stmt) && $stmt) $stmt->close();
    if (isset($conn) && $conn) $conn->close();

    SessionManager::setFlashMessage('Errore di sistema. Contatta l\'amministratore.', 'danger');

    ob_end_clean();
    header('Location: ' . BASE_URL . '/pages/auth/login.php');
    exit();
}