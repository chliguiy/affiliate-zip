<?php
require_once 'config/database.php';

$database = new Database();
$conn = $database->getConnection();

echo "<h1>Test de correspondance des montants</h1>";

// Récupérer la commande #106
$stmt = $conn->prepare("SELECT id, order_number, total_amount, final_sale_price, commission_amount FROM orders WHERE id = ?");
$stmt->execute([106]);
$order = $stmt->fetch();

if ($order) {
    echo "<h2>Commande #{$order['order_number']} (ID: {$order['id']})</h2>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Champ</th><th>Valeur</th><th>Description</th></tr>";
    echo "<tr><td>total_amount</td><td>" . number_format($order['total_amount'], 2) . " DH</td><td>Montant total (utilisé dans order_details.php avant correction)</td></tr>";
    echo "<tr><td>final_sale_price</td><td>" . number_format($order['final_sale_price'], 2) . " DH</td><td>Prix final payé par le client (utilisé dans le tableau des commandes)</td></tr>";
    echo "<tr><td>commission_amount</td><td>" . number_format($order['commission_amount'], 2) . " DH</td><td>Commission/Profit</td></tr>";
    echo "</table>";
    
    echo "<h3>Résultat :</h3>";
    if ($order['total_amount'] == $order['final_sale_price']) {
        echo "<p style='color: green;'>✅ Les montants correspondent !</p>";
    } else {
        echo "<p style='color: red;'>❌ Les montants ne correspondent pas !</p>";
        echo "<p>Différence : " . number_format(abs($order['total_amount'] - $order['final_sale_price']), 2) . " DH</p>";
    }
    
    echo "<h3>Test de la page order_details.php :</h3>";
    echo "<p><a href='order_details.php?id=106' target='_blank'>Voir order_details.php?id=106</a></p>";
    
} else {
    echo "<p style='color: red;'>❌ Commande #106 non trouvée</p>";
}
?> 