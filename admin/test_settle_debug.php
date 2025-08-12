<?php
require_once 'config/database.php';

$database = new Database();
$pdo = $database->getConnection();

echo "<h2>Test de règlement de paiement</h2>";

// Afficher tous les utilisateurs disponibles
echo "<h3>Utilisateurs disponibles :</h3>";
$all_users = $pdo->query("SELECT id, username, full_name, type, status FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>ID</th><th>Username</th><th>Nom complet</th><th>Type</th><th>Statut</th></tr>";
foreach ($all_users as $user) {
    echo "<tr>";
    echo "<td>{$user['id']}</td>";
    echo "<td>{$user['username']}</td>";
    echo "<td>{$user['full_name']}</td>";
    echo "<td>{$user['type']}</td>";
    echo "<td>{$user['status']}</td>";
    echo "</tr>";
}
echo "</table>";

// Trouver un affilié avec des commandes
echo "<h3>Affiliés avec des commandes :</h3>";
$affiliates_with_orders = $pdo->query("
    SELECT DISTINCT 
        u.id, u.username, u.full_name, u.type, u.status,
        COUNT(o.id) as total_orders,
        SUM(CASE WHEN o.status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders
    FROM users u
    LEFT JOIN orders o ON u.id = o.affiliate_id
    WHERE u.type = 'affiliate'
    GROUP BY u.id, u.username, u.full_name, u.type, u.status
    HAVING total_orders > 0
    ORDER BY delivered_orders DESC, total_orders DESC
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($affiliates_with_orders)) {
    echo "<p style='color: red;'>❌ Aucun affilié avec des commandes trouvé</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Username</th><th>Nom complet</th><th>Total Commandes</th><th>Commandes Livrées</th></tr>";
    foreach ($affiliates_with_orders as $affiliate) {
        echo "<tr>";
        echo "<td>{$affiliate['id']}</td>";
        echo "<td>{$affiliate['username']}</td>";
        echo "<td>{$affiliate['full_name']}</td>";
        echo "<td>{$affiliate['total_orders']}</td>";
        echo "<td>{$affiliate['delivered_orders']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Prendre le premier affilié avec des commandes livrées pour le test
    $test_affiliate = null;
    foreach ($affiliates_with_orders as $affiliate) {
        if ($affiliate['delivered_orders'] > 0) {
            $test_affiliate = $affiliate;
            break;
        }
    }
    
    if ($test_affiliate) {
        $test_affiliate_id = $test_affiliate['id'];
        echo "<h3>Test avec l'affilié : {$test_affiliate['username']} (ID: $test_affiliate_id)</h3>";
        
        // Vérifier les commandes livrées de cet affilié
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

        echo "<h4>Commandes livrées de cet affilié :</h4>";
        if (empty($delivered_orders)) {
            echo "<p style='color: orange;'>⚠️ Aucune commande livrée trouvée pour cet affilié</p>";
        } else {
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>ID Commande</th><th>Montant</th><th>Colis</th></tr>";
            $total_amount = 0;
            $total_packages = 0;
            foreach ($delivered_orders as $order) {
                echo "<tr>";
                echo "<td>{$order['id']}</td>";
                echo "<td>{$order['total_amount']} MAD</td>";
                echo "<td>{$order['packages']}</td>";
                echo "</tr>";
                $total_amount += $order['total_amount'];
                $total_packages += $order['packages'];
            }
            echo "<tr style='font-weight: bold;'>";
            echo "<td><strong>TOTAL</strong></td>";
            echo "<td><strong>$total_amount MAD</strong></td>";
            echo "<td><strong>$total_packages</strong></td>";
            echo "</tr>";
            echo "</table>";
            
            echo "<p style='color: green;'>✅ Cet affilié a des commandes livrées à régler</p>";
        }
    } else {
        echo "<p style='color: orange;'>⚠️ Aucun affilié avec des commandes livrées trouvé</p>";
    }
}
?> 