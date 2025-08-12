<?php
require_once 'config/database.php';

$database = new Database();
$conn = $database->getConnection();

echo "=== DÉBOGAGE PROBLÈME PROFIT ===\n\n";

// Récupérer une commande récente
$stmt = $conn->query("SELECT id, order_number, total_amount, final_sale_price, commission_amount, affiliate_margin, delivery_fee FROM orders ORDER BY created_at DESC LIMIT 1");
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if ($order) {
    echo "Commande ID: " . $order['id'] . "\n";
    echo "Numéro: " . $order['order_number'] . "\n";
    echo "Total amount (prix admin): " . $order['total_amount'] . " DH\n";
    echo "Final sale price (prix client): " . ($order['final_sale_price'] ?? 'NULL') . " DH\n";
    echo "Commission amount: " . $order['commission_amount'] . " DH\n";
    echo "Affiliate margin: " . ($order['affiliate_margin'] ?? 'NULL') . " DH\n";
    echo "Delivery fee: " . ($order['delivery_fee'] ?? 'NULL') . " DH\n";
    
    echo "\n=== CALCULS ===\n";
    
    $final_sale_price = $order['final_sale_price'] ?? 0;
    $total_cost = $order['total_amount'];
    $delivery_fee = $order['delivery_fee'] ?? 0;
    
    $calculated_profit = $final_sale_price - $total_cost - $delivery_fee;
    
    echo "Profit calculé: " . $calculated_profit . " DH\n";
    echo "Profit stocké: " . ($order['affiliate_margin'] ?? 'NULL') . " DH\n";
    
    if ($calculated_profit != ($order['affiliate_margin'] ?? 0)) {
        echo "\n❌ PROBLÈME: Différence de " . abs($calculated_profit - ($order['affiliate_margin'] ?? 0)) . " DH\n";
    } else {
        echo "\n✅ Les profits correspondent\n";
    }
}
?> 