<?php
require_once 'config/database.php';

// Créer une instance de la base de données
$database = new Database();
$conn = $database->getConnection();

echo "=== Vérification de la structure de la table orders ===\n\n";

// Vérifier la structure de la table orders
try {
    $stmt = $conn->query("DESCRIBE orders");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Colonnes de la table orders:\n";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    echo "\n=== Vérification des données ===\n";
    
    // Compter le nombre de commandes
    $stmt = $conn->query("SELECT COUNT(*) as total FROM orders");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Nombre total de commandes: " . $count['total'] . "\n";
    
    if ($count['total'] > 0) {
        // Vérifier une commande récente
        $stmt = $conn->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 1");
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "\nDernière commande:\n";
        foreach ($order as $key => $value) {
            echo "- $key: " . (is_null($value) ? 'NULL' : $value) . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}

echo "\n=== Fin de la vérification ===\n";
?> 