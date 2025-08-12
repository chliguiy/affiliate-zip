<?php
require_once 'config/database.php';

$database = new Database();
$conn = $database->getConnection();

echo "<h1>Test des couleurs et tailles commandées</h1>";

// Récupérer les articles de la commande #106
$stmt = $conn->prepare("
    SELECT 
        oi.id,
        oi.product_name,
        oi.quantity,
        oi.price,
        oi.color,
        oi.size,
        oi.product_id
    FROM order_items oi
    WHERE oi.order_id = ?
    ORDER BY oi.id
");
$stmt->execute([106]);
$items = $stmt->fetchAll();

if (!empty($items)) {
    echo "<h2>Articles de la commande #106 :</h2>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Produit</th><th>Quantité</th><th>Prix</th><th>Couleur</th><th>Taille</th><th>Product ID</th></tr>";
    
    foreach ($items as $item) {
        $color = $item['color'] ?: '<span style="color: red;">VIDE</span>';
        $size = $item['size'] ?: '<span style="color: red;">VIDE</span>';
        
        echo "<tr>";
        echo "<td>{$item['id']}</td>";
        echo "<td>{$item['product_name']}</td>";
        echo "<td>{$item['quantity']}</td>";
        echo "<td>{$item['price']} DH</td>";
        echo "<td>{$color}</td>";
        echo "<td>{$size}</td>";
        echo "<td>{$item['product_id']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Vérifier la structure de la table order_items
    echo "<h2>Structure de la table order_items :</h2>";
    $stmt = $conn->query("DESCRIBE order_items");
    $columns = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Colonne</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th><th>Extra</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "<td>{$column['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} else {
    echo "<p style='color: red;'>❌ Aucun article trouvé pour la commande #106</p>";
}

// Vérifier d'autres commandes pour voir si elles ont des couleurs/tailles
echo "<h2>Vérification d'autres commandes :</h2>";
$stmt = $conn->query("
    SELECT 
        o.id,
        o.order_number,
        COUNT(oi.id) as total_items,
        COUNT(CASE WHEN oi.color IS NOT NULL AND oi.color != '' THEN 1 END) as items_with_color,
        COUNT(CASE WHEN oi.size IS NOT NULL AND oi.size != '' THEN 1 END) as items_with_size
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    GROUP BY o.id, o.order_number
    ORDER BY o.created_at DESC
    LIMIT 10
");
$orders = $stmt->fetchAll();

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Commande</th><th>Total Articles</th><th>Avec Couleur</th><th>Avec Taille</th></tr>";
foreach ($orders as $order) {
    echo "<tr>";
    echo "<td>{$order['order_number']}</td>";
    echo "<td>{$order['total_items']}</td>";
    echo "<td>{$order['items_with_color']}</td>";
    echo "<td>{$order['items_with_size']}</td>";
    echo "</tr>";
}
echo "</table>";
?> 