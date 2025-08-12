<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "<h2>Débogage de la commande #105</h2>";
    
    // Voir la structure complète de la commande
    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([105]);
    $order = $stmt->fetch();
    
    if ($order) {
        echo "<h3>Structure de la commande :</h3>";
        echo "<pre>";
        print_r($order);
        echo "</pre>";
        
        // Voir les articles de la commande
        $stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmt->execute([105]);
        $items = $stmt->fetchAll();
        
        echo "<h3>Articles de la commande :</h3>";
        echo "<pre>";
        print_r($items);
        echo "</pre>";
        
        // Tester la requête avec le bon affiliate_id
        $stmt = $conn->prepare("
            SELECT o.*, 
                   GROUP_CONCAT(CONCAT(oi.product_name, ' (x', oi.quantity, ')') SEPARATOR ', ') as products_detail,
                   GROUP_CONCAT(CONCAT(oi.product_name, ':', oi.quantity, ':', oi.price) SEPARATOR '|') as products_data
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE o.id = ? AND o.affiliate_id = ?
            GROUP BY o.id
        ");
        $stmt->execute([105, $order['affiliate_id']]);
        $order_details = $stmt->fetch();
        
        if ($order_details) {
            echo "<h3>✅ Requête réussie avec affiliate_id = " . $order['affiliate_id'] . "</h3>";
            echo "<p><a href='order_details.php?id=105'>Voir la page de détails</a></p>";
        } else {
            echo "<h3>❌ Requête échouée même avec le bon affiliate_id</h3>";
        }
        
    } else {
        echo "<p>Commande #105 non trouvée</p>";
    }
    
} catch (Exception $e) {
    echo "<p><strong>❌ Erreur :</strong> " . $e->getMessage() . "</p>";
}
?> 