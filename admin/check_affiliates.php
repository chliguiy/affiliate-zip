<?php
require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

echo "=== VÉRIFICATION DES AFFILIÉS ===\n";

// Afficher tous les affiliés
echo "1. Tous les affiliés:\n";
$affiliates = $pdo->query("SELECT id, username, full_name, type, status FROM users WHERE type = 'affiliate' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($affiliates as $affiliate) {
    echo "- ID: {$affiliate['id']}, Username: {$affiliate['username']}, Nom: {$affiliate['full_name']}, Status: {$affiliate['status']}\n";
}

// Afficher les commandes par affilié
echo "\n2. Commandes par affilié:\n";
$orders_by_affiliate = $pdo->query("
    SELECT 
        o.affiliate_id,
        u.username as affiliate_name,
        COUNT(o.id) as total_orders,
        SUM(CASE WHEN o.status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
        SUM(CASE WHEN o.status = 'delivered' THEN o.total_amount ELSE 0 END) as total_amount
    FROM orders o
    LEFT JOIN users u ON o.affiliate_id = u.id
    GROUP BY o.affiliate_id, u.username
    ORDER BY delivered_orders DESC
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($orders_by_affiliate as $order) {
    echo "- Affiliate ID: {$order['affiliate_id']}, Nom: {$order['affiliate_name']}, Total: {$order['total_orders']}, Livrées: {$order['delivered_orders']}, Montant: {$order['total_amount']} MAD\n";
}

// Trouver un affilié avec des commandes livrées
echo "\n3. Affiliés avec des commandes livrées:\n";
$affiliates_with_delivered = $pdo->query("
    SELECT DISTINCT 
        u.id, u.username, u.full_name
    FROM users u
    JOIN orders o ON u.id = o.affiliate_id
    WHERE u.type = 'affiliate' AND o.status = 'delivered'
    ORDER BY u.id
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($affiliates_with_delivered)) {
    echo "❌ Aucun affilié avec des commandes livrées\n";
} else {
    foreach ($affiliates_with_delivered as $affiliate) {
        echo "- ID: {$affiliate['id']}, Username: {$affiliate['username']}, Nom: {$affiliate['full_name']}\n";
        
        // Afficher les commandes livrées de cet affilié
        $delivered_orders = $pdo->prepare("
            SELECT id, total_amount, created_at 
            FROM orders 
            WHERE affiliate_id = ? AND status = 'delivered'
            ORDER BY created_at DESC
        ");
        $delivered_orders->execute([$affiliate['id']]);
        $orders = $delivered_orders->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($orders as $order) {
            echo "  * Commande {$order['id']}: {$order['total_amount']} MAD ({$order['created_at']})\n";
        }
    }
}

echo "\n=== FIN VÉRIFICATION ===\n";
?> 