<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "<h2>Test de la base de données - Commandes</h2>";
    
    // Vérifier le nombre de commandes
    $stmt = $conn->query("SELECT COUNT(*) as count FROM orders");
    $result = $stmt->fetch();
    echo "<p><strong>Nombre total de commandes :</strong> " . $result['count'] . "</p>";
    
    if ($result['count'] > 0) {
        // Afficher les 5 premières commandes
        $stmt = $conn->query("SELECT id, order_number, customer_name, customer_city, total_amount, status, created_at FROM orders ORDER BY created_at DESC LIMIT 5");
        $orders = $stmt->fetchAll();
        
        echo "<h3>5 dernières commandes :</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Numéro</th><th>Client</th><th>Ville</th><th>Montant</th><th>Statut</th><th>Date</th></tr>";
        
        foreach ($orders as $order) {
            echo "<tr>";
            echo "<td>" . $order['id'] . "</td>";
            echo "<td>" . htmlspecialchars($order['order_number']) . "</td>";
            echo "<td>" . htmlspecialchars($order['customer_name']) . "</td>";
            echo "<td>" . htmlspecialchars($order['customer_city']) . "</td>";
            echo "<td>" . number_format($order['total_amount'], 2) . " DH</td>";
            echo "<td>" . htmlspecialchars($order['status']) . "</td>";
            echo "<td>" . $order['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Tester la requête de order_details.php
        $first_order = $orders[0];
        echo "<h3>Test de la requête order_details.php pour la commande #" . $first_order['id'] . "</h3>";
        
        $stmt = $conn->prepare("
            SELECT o.*, 
                   GROUP_CONCAT(CONCAT(oi.product_name, ' (x', oi.quantity, ')') SEPARATOR ', ') as products_detail,
                   GROUP_CONCAT(CONCAT(oi.product_name, ':', oi.quantity, ':', oi.price) SEPARATOR '|') as products_data
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE o.id = ? AND o.affiliate_id = ?
            GROUP BY o.id
        ");
        $stmt->execute([$first_order['id'], $first_order['affiliate_id'] ?? 1]);
        $order_details = $stmt->fetch();
        
        if ($order_details) {
            echo "<p><strong>✅ Requête réussie !</strong></p>";
            echo "<p><strong>Détails de la commande :</strong></p>";
            echo "<ul>";
            echo "<li><strong>Numéro :</strong> " . htmlspecialchars($order_details['order_number']) . "</li>";
            echo "<li><strong>Client :</strong> " . htmlspecialchars($order_details['customer_name']) . "</li>";
            echo "<li><strong>Email :</strong> " . htmlspecialchars($order_details['customer_email']) . "</li>";
            echo "<li><strong>Téléphone :</strong> " . htmlspecialchars($order_details['customer_phone']) . "</li>";
            echo "<li><strong>Adresse :</strong> " . htmlspecialchars($order_details['customer_address']) . "</li>";
            echo "<li><strong>Ville :</strong> " . htmlspecialchars($order_details['customer_city']) . "</li>";
            echo "<li><strong>Montant total :</strong> " . number_format($order_details['total_amount'], 2) . " DH</li>";
            echo "<li><strong>Commission :</strong> " . number_format($order_details['commission_amount'], 2) . " DH</li>";
            echo "<li><strong>Produits :</strong> " . htmlspecialchars($order_details['products_detail'] ?? 'Aucun produit') . "</li>";
            echo "</ul>";
            
            echo "<p><a href='order_details.php?id=" . $first_order['id'] . "' target='_blank'>Voir la page de détails</a></p>";
        } else {
            echo "<p><strong>❌ Aucun résultat trouvé pour cette commande</strong></p>";
        }
    } else {
        echo "<p><strong>Aucune commande trouvée dans la base de données.</strong></p>";
        echo "<p>Vous devez d'abord créer des commandes de test.</p>";
    }
    
} catch (Exception $e) {
    echo "<p><strong>❌ Erreur :</strong> " . $e->getMessage() . "</p>";
}
?> 