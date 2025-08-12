<?php
require_once 'config/database.php';

$database = new Database();
$conn = $database->getConnection();

echo "=== VÉRIFICATION PRODUITS COMMANDE ===\n\n";

// Récupérer une commande récente
$stmt = $conn->query("SELECT id, order_number, total_amount, final_sale_price, commission_amount, affiliate_margin, delivery_fee FROM orders ORDER BY created_at DESC LIMIT 1");
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if ($order) {
    echo "Commande ID: " . $order['id'] . "\n";
    echo "Numéro: " . $order['order_number'] . "\n";
    echo "Total amount (prix admin): " . $order['total_amount'] . " DH\n";
    echo "Final sale price (prix client): " . $order['final_sale_price'] . " DH\n";
    echo "Affiliate margin stocké: " . $order['affiliate_margin'] . " DH\n";
    echo "Delivery fee: " . $order['delivery_fee'] . " DH\n";
    
    echo "\n=== PRODUITS DE LA COMMANDE ===\n";
    
    // Récupérer les produits de cette commande
    $stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $stmt->execute([$order['id']]);
    $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_calculated = 0;
    foreach ($order_items as $item) {
        echo "Produit: " . $item['product_name'] . "\n";
        echo "  - Prix: " . $item['price'] . " DH\n";
        echo "  - Quantité: " . $item['quantity'] . "\n";
        echo "  - Sous-total: " . ($item['price'] * $item['quantity']) . " DH\n";
        $total_calculated += $item['price'] * $item['quantity'];
    }
    
    echo "\nTotal calculé: " . $total_calculated . " DH\n";
    echo "Total stocké: " . $order['total_amount'] . " DH\n";
    
    echo "\n=== CALCULS PROFIT ===\n";
    
    // Calcul selon la logique du frontend (tous les produits)
    $profit_correct = $order['final_sale_price'] - $total_calculated - $order['delivery_fee'];
    echo "Profit correct (tous produits): " . $profit_correct . " DH\n";
    
    // Calcul selon la logique actuelle (premier produit seulement)
    $first_product_cost = $order_items[0]['price'] * $order_items[0]['quantity'];
    $profit_incorrect = $order['final_sale_price'] - $first_product_cost - $order['delivery_fee'];
    echo "Profit incorrect (premier produit): " . $profit_incorrect . " DH\n";
    
    echo "Profit stocké: " . $order['affiliate_margin'] . " DH\n";
    
    echo "\n=== DIFFÉRENCES ===\n";
    echo "Différence avec profit correct: " . abs($profit_correct - $order['affiliate_margin']) . " DH\n";
    echo "Différence avec profit incorrect: " . abs($profit_incorrect - $order['affiliate_margin']) . " DH\n";
}
?> 