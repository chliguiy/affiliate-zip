<?php
require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

// Test avec l'affilié ID 49 qui est affiché dans le tableau
$test_affiliate_id = 49;

echo "=== TEST AVEC AFFILIÉ ID 49 ===\n";
echo "Affilié ID: $test_affiliate_id\n";

// Vérifier si l'affilié existe
$affiliate_query = "SELECT id, username, full_name, type, status FROM users WHERE id = ?";
$affiliate_stmt = $pdo->prepare($affiliate_query);
$affiliate_stmt->execute([$test_affiliate_id]);
$affiliate = $affiliate_stmt->fetch(PDO::FETCH_ASSOC);

if (!$affiliate) {
    echo "❌ Affilié non trouvé avec l'ID: $test_affiliate_id\n";
    exit();
}

echo "✅ Affilié trouvé : {$affiliate['username']} (ID: {$affiliate['id']}, Type: {$affiliate['type']})\n";

// Vérifier les commandes livrées
$delivered_query = "SELECT 
                     o.id,
                     o.total_amount,
                     o.status,
                     COUNT(oi.id) as packages
                   FROM orders o
                   LEFT JOIN order_items oi ON o.id = oi.order_id
                   WHERE o.affiliate_id = ? AND o.status = 'delivered'
                   GROUP BY o.id";

$delivered_stmt = $pdo->prepare($delivered_query);
$delivered_stmt->execute([$test_affiliate_id]);
$delivered_orders = $delivered_stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Commandes livrées:\n";
if (empty($delivered_orders)) {
    echo "❌ Aucune commande livrée trouvée\n";
} else {
    $total_amount = 0;
    $total_packages = 0;
    foreach ($delivered_orders as $order) {
        echo "- Commande {$order['id']}: {$order['total_amount']} MAD ({$order['packages']} colis)\n";
        $total_amount += $order['total_amount'];
        $total_packages += $order['packages'];
    }
    echo "Total: $total_amount MAD, $total_packages colis\n";
    
    // Simuler le règlement
    echo "\n=== SIMULATION DE RÈGLEMENT ===\n";
    
    // Insérer dans affiliate_payments
    $insert_query = "INSERT INTO affiliate_payments (affiliate_id, montant, date_paiement, statut, raison, colis) 
                     VALUES (?, ?, NOW(), 'réglé', ?, ?)";
    
    $reason_text = "Paiement des commandes livrées";
    
    $insert_stmt = $pdo->prepare($insert_query);
    $result = $insert_stmt->execute([
        $test_affiliate_id,
        $total_amount,
        $reason_text,
        $total_packages
    ]);
    
    if ($result) {
        $payment_id = $pdo->lastInsertId();
        echo "✅ Paiement créé avec l'ID: $payment_id\n";
        
        // Mettre à jour les commandes
        $update_orders = "UPDATE orders 
                         SET status = 'paid', commission_paid_at = NOW() 
                         WHERE affiliate_id = ? AND status = 'delivered'";
        
        $update_stmt = $pdo->prepare($update_orders);
        $update_result = $update_stmt->execute([$test_affiliate_id]);
        
        if ($update_result) {
            $affected = $update_stmt->rowCount();
            echo "✅ $affected commandes mises à jour\n";
        } else {
            echo "❌ Erreur lors de la mise à jour des commandes\n";
        }
    } else {
        echo "❌ Erreur lors de la création du paiement\n";
    }
}

echo "\n=== FIN TEST ===\n";
?> 