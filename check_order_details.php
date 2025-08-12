<?php
require_once 'config/database.php';

// Créer une instance de la base de données
$database = new Database();
$conn = $database->getConnection();

echo "=== Vérification des détails d'une commande ===\n\n";

// Vérifier une commande récente avec tous les champs
try {
    $stmt = $conn->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 1");
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order) {
        echo "Commande ID: " . $order['id'] . "\n";
        echo "Numéro: " . $order['order_number'] . "\n";
        echo "Total amount (prix admin): " . number_format($order['total_amount'], 2) . " DH\n";
        echo "Final sale price (prix client): " . number_format($order['final_sale_price'] ?? 0, 2) . " DH\n";
        echo "Commission amount: " . number_format($order['commission_amount'], 2) . " DH\n";
        echo "Affiliate margin: " . number_format($order['affiliate_margin'] ?? 0, 2) . " DH\n";
        echo "Delivery fee: " . number_format($order['delivery_fee'] ?? 0, 2) . " DH\n";
        
        echo "\n=== Calculs ===\n";
        
        // Calcul du profit selon la logique du frontend
        $final_sale_price = $order['final_sale_price'] ?? 0;
        $total_cost = $order['total_amount'];
        $delivery_fee = $order['delivery_fee'] ?? 0;
        $calculated_profit = $final_sale_price - $total_cost - $delivery_fee;
        
        echo "Profit calculé (final_sale_price - total_amount - delivery_fee): " . number_format($calculated_profit, 2) . " DH\n";
        echo "Profit stocké (affiliate_margin): " . number_format($order['affiliate_margin'] ?? 0, 2) . " DH\n";
        echo "Commission stockée (commission_amount): " . number_format($order['commission_amount'], 2) . " DH\n";
        
        echo "\n=== Vérifications ===\n";
        echo "Profit calculé = Profit stocké: " . ($calculated_profit == ($order['affiliate_margin'] ?? 0) ? "✅ OUI" : "❌ NON") . "\n";
        echo "Commission = Affiliate margin: " . ($order['commission_amount'] == ($order['affiliate_margin'] ?? 0) ? "✅ OUI" : "❌ NON") . "\n";
        
        if ($calculated_profit != ($order['affiliate_margin'] ?? 0)) {
            echo "\n❌ PROBLÈME DÉTECTÉ: Le profit calculé ne correspond pas au profit stocké!\n";
            echo "Différence: " . number_format(abs($calculated_profit - ($order['affiliate_margin'] ?? 0)), 2) . " DH\n";
        }
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}

echo "\n=== Fin de la vérification ===\n";
?> 