<?php
require_once '../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Vérifier la structure de la table orders
    $stmt = $pdo->prepare("DESCRIBE orders");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Structure de la table orders :</h3>";
    echo "<table border='1'>";
    echo "<tr><th>Colonne</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . $column['Default'] . "</td>";
        echo "<td>" . $column['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Vérifier spécifiquement la colonne payment_reason
    $stmt = $pdo->prepare("SHOW COLUMNS FROM orders LIKE 'payment_reason'");
    $stmt->execute();
    $paymentReasonColumn = $stmt->fetch();
    
    if ($paymentReasonColumn) {
        echo "<br><strong style='color: green;'>✓ La colonne payment_reason existe dans la table orders.</strong>";
    } else {
        echo "<br><strong style='color: red;'>✗ La colonne payment_reason n'existe pas dans la table orders.</strong>";
    }
    
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage();
}
?> 