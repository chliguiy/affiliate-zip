<?php
session_start();
require_once 'includes/auth.php';
require_once '../config/database.php';

// Vérifier l'authentification admin
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Vérifier que c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

$database = new Database();
$pdo = $database->getConnection();

try {
    // Récupérer tous les affiliés avec des commandes livrées
    $affiliates_query = "SELECT DISTINCT 
                          u.id as affiliate_id,
                          u.username,
                          u.full_name,
                          SUM(o.total_amount) as total_amount,
                          SUM(oi.id IS NOT NULL) as total_packages
                        FROM users u
                        JOIN orders o ON u.id = o.affiliate_id
                        LEFT JOIN order_items oi ON o.id = oi.order_id
                        WHERE o.status = 'delivered' AND u.type = 'affiliate'
                        GROUP BY u.id, u.username, u.full_name
                        HAVING total_amount > 0";

    $affiliates_stmt = $pdo->prepare($affiliates_query);
    $affiliates_stmt->execute();
    $affiliates = $affiliates_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($affiliates)) {
        echo json_encode(['success' => false, 'message' => 'Aucun paiement à régler']);
        exit();
    }

    // Vérifier si la table affiliate_payments existe, sinon la créer
    $check_table = "SHOW TABLES LIKE 'affiliate_payments'";
    $table_exists = $pdo->query($check_table)->rowCount() > 0;

    if (!$table_exists) {
        // Créer la table affiliate_payments
        $create_table = "CREATE TABLE affiliate_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            affiliate_id INT NOT NULL,
            montant DECIMAL(10,2) NOT NULL,
            date_paiement DATETIME NOT NULL,
            statut ENUM('en_attente', 'payé', 'réglé') DEFAULT 'réglé',
            raison TEXT,
            colis INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (affiliate_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        $pdo->exec($create_table);
    }

    $settled_count = 0;
    $total_amount = 0;

    foreach ($affiliates as $affiliate) {
        // Récupérer les raisons de paiement pour cet affilié
        $reasons_query = "SELECT DISTINCT payment_reason 
                          FROM orders 
                          WHERE affiliate_id = ? AND status = 'delivered' 
                          AND payment_reason IS NOT NULL AND payment_reason != ''";
        $reasons_stmt = $pdo->prepare($reasons_query);
        $reasons_stmt->execute([$affiliate['affiliate_id']]);
        $reasons = $reasons_stmt->fetchAll(PDO::FETCH_COLUMN);

        $reason_text = !empty($reasons) ? implode(', ', array_unique($reasons)) : 'Paiement des commandes livrées';

        // Insérer le paiement dans la table affiliate_payments
        $insert_query = "INSERT INTO affiliate_payments (affiliate_id, montant, date_paiement, statut, raison, colis) 
                         VALUES (?, ?, NOW(), 'réglé', ?, ?)";
        
        $insert_stmt = $pdo->prepare($insert_query);
        $insert_stmt->execute([
            $affiliate['affiliate_id'],
            $affiliate['total_amount'],
            $reason_text,
            $affiliate['total_packages']
        ]);

        // Mettre à jour seulement commission_paid_at pour indiquer que la commission a été payée
        // Le statut des commandes reste 'delivered' comme demandé
        $update_orders = "UPDATE orders 
                         SET commission_paid_at = NOW() 
                         WHERE affiliate_id = ? AND status = 'delivered'";
        
        $update_stmt = $pdo->prepare($update_orders);
        $update_stmt->execute([$affiliate['affiliate_id']]);

        $settled_count++;
        $total_amount += $affiliate['total_amount'];
    }

    // Réponse de succès
    echo json_encode([
        'success' => true, 
        'message' => "Tous les paiements ont été réglés avec succès ! ($settled_count affiliés, " . number_format($total_amount, 0) . " MAD)",
        'settled_count' => $settled_count,
        'total_amount' => $total_amount
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur lors du règlement : ' . $e->getMessage()]);
}
?> 