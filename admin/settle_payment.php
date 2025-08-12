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

// Récupérer les données JSON
$input = json_decode(file_get_contents('php://input'), true);
$affiliate_id = $input['payment_id'] ?? 0; // Dans notre cas, payment_id est l'affiliate_id

if (!$affiliate_id) {
    echo json_encode(['success' => false, 'message' => 'ID de l\'affilié manquant']);
    exit();
}

try {
    // Récupérer les informations de l'affilié
    $affiliate_query = "SELECT username, full_name FROM users WHERE id = ?";
    $affiliate_stmt = $pdo->prepare($affiliate_query);
    $affiliate_stmt->execute([$affiliate_id]);
    $affiliate = $affiliate_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$affiliate) {
        echo json_encode(['success' => false, 'message' => 'Affilié non trouvé']);
        exit();
    }

    // Récupérer tous les paiements de cet affilié avec statut 'delivered'
    $payments_query = "SELECT 
                        o.id,
                        o.affiliate_margin as total_amount,
                        o.created_at,
                        o.payment_reason,
                        COUNT(oi.id) as packages
                      FROM orders o
                      LEFT JOIN order_items oi ON o.id = oi.order_id
                      WHERE o.affiliate_id = ? AND o.status = 'delivered'
                      GROUP BY o.id";

    $payments_stmt = $pdo->prepare($payments_query);
    $payments_stmt->execute([$affiliate_id]);
    $payments = $payments_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($payments)) {
        echo json_encode(['success' => false, 'message' => 'Aucun paiement à régler pour cet affilié']);
        exit();
    }

    // Calculer le montant total
    $total_amount = 0;
    $total_packages = 0;
    $payment_reasons = [];

    foreach ($payments as $payment) {
        $total_amount += $payment['total_amount'];
        $total_packages += $payment['packages'];
        if (!empty($payment['payment_reason'])) {
            $payment_reasons[] = $payment['payment_reason'];
        }
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

    // Insérer le paiement dans la table affiliate_payments
    $insert_query = "INSERT INTO affiliate_payments (affiliate_id, montant, date_paiement, statut, raison, colis) 
                     VALUES (?, ?, NOW(), 'réglé', ?, ?)";
    
    $reason_text = !empty($payment_reasons) ? implode(', ', array_unique($payment_reasons)) : 'Paiement des commandes livrées';
    
    $insert_stmt = $pdo->prepare($insert_query);
    $insert_stmt->execute([
        $affiliate_id,
        $total_amount,
        $reason_text,
        $total_packages
    ]);

    $payment_id = $pdo->lastInsertId();

    // Mettre à jour seulement commission_paid_at pour indiquer que la commission a été payée
    // Le statut des commandes reste 'delivered' comme demandé
    $update_orders = "UPDATE orders 
                     SET commission_paid_at = NOW() 
                     WHERE affiliate_id = ? AND status = 'delivered'";
    
    $update_stmt = $pdo->prepare($update_orders);
    $update_stmt->execute([$affiliate_id]);

    // Réponse de succès
    echo json_encode([
        'success' => true, 
        'message' => 'Paiement réglé avec succès !',
        'payment_id' => $payment_id,
        'affiliate_name' => $affiliate['username'],
        'amount' => $total_amount,
        'packages' => $total_packages
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur lors du règlement : ' . $e->getMessage()]);
}
?> 