<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "<h2>Débogage de la commande #106</h2>";
    
    // Voir la structure complète de la commande
    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([106]);
    $order = $stmt->fetch();
    
    if ($order) {
        echo "<h3>✅ Commande trouvée !</h3>";
        echo "<p><strong>Numéro :</strong> " . htmlspecialchars($order['order_number']) . "</p>";
        echo "<p><strong>Client :</strong> " . htmlspecialchars($order['customer_name']) . "</p>";
        echo "<p><strong>Affiliate ID :</strong> " . $order['affiliate_id'] . "</p>";
        
        // Voir les articles de la commande
        $stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmt->execute([106]);
        $items = $stmt->fetchAll();
        
        echo "<h3>Articles de la commande (" . count($items) . " articles) :</h3>";
        foreach ($items as $item) {
            echo "<p>- " . htmlspecialchars($item['product_name']) . " (x" . $item['quantity'] . ") - " . $item['price'] . " DH</p>";
        }
        
        // Tester la requête complète
        $stmt = $conn->prepare("
            SELECT o.*, 
                   GROUP_CONCAT(CONCAT(oi.product_name, ' (x', oi.quantity, ')') SEPARATOR ', ') as products_detail,
                   GROUP_CONCAT(CONCAT(oi.product_name, ':', oi.quantity, ':', oi.price) SEPARATOR '|') as products_data
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE o.id = ?
            GROUP BY o.id
        ");
        $stmt->execute([106]);
        $order_details = $stmt->fetch();
        
        if ($order_details) {
            echo "<h3>✅ Requête réussie !</h3>";
            echo "<p><strong>Produits :</strong> " . htmlspecialchars($order_details['products_detail'] ?? 'Aucun produit') . "</p>";
            echo "<p><a href='order_details.php?id=106' target='_blank'>Voir la page de détails</a></p>";
        } else {
            echo "<h3>❌ Requête échouée</h3>";
        }
        
    } else {
        echo "<p>❌ Commande #106 non trouvée</p>";
    }
    
} catch (Exception $e) {
    echo "<p><strong>❌ Erreur :</strong> " . $e->getMessage() . "</p>";
}
?> 