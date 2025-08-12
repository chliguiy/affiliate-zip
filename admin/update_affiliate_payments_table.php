<?php
require_once 'config/database.php';

$database = new Database();
$pdo = $database->getConnection();

echo "<h2>Mise à jour de la table affiliate_payments</h2>";

try {
    // Vérifier la structure actuelle
    $structure = $pdo->query("DESCRIBE affiliate_payments")->fetchAll(PDO::FETCH_ASSOC);
    echo "<h3>Structure actuelle :</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Champ</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th><th>Extra</th></tr>";
    foreach ($structure as $column) {
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

    // Ajouter les colonnes manquantes
    $columns_to_add = [
        'raison' => 'TEXT NULL',
        'colis' => 'INT DEFAULT 0',
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
    ];

    foreach ($columns_to_add as $column_name => $column_definition) {
        $check_column = "SHOW COLUMNS FROM affiliate_payments LIKE '$column_name'";
        $column_exists = $pdo->query($check_column)->rowCount() > 0;
        
        if (!$column_exists) {
            $add_column = "ALTER TABLE affiliate_payments ADD COLUMN $column_name $column_definition";
            $pdo->exec($add_column);
            echo "<p style='color: green;'>✅ Colonne '$column_name' ajoutée</p>";
        } else {
            echo "<p style='color: blue;'>ℹ️ Colonne '$column_name' existe déjà</p>";
        }
    }

    // Mettre à jour le type ENUM pour le statut
    $update_enum = "ALTER TABLE affiliate_payments MODIFY COLUMN statut ENUM('en_attente', 'payé', 'réglé') DEFAULT 'réglé'";
    $pdo->exec($update_enum);
    echo "<p style='color: green;'>✅ Type ENUM du statut mis à jour</p>";

    // Mettre à jour la colonne date_paiement
    $update_date = "ALTER TABLE affiliate_payments MODIFY COLUMN date_paiement DATETIME NOT NULL";
    $pdo->exec($update_date);
    echo "<p style='color: green;'>✅ Colonne date_paiement mise à jour</p>";

    echo "<h3>Structure finale :</h3>";
    $final_structure = $pdo->query("DESCRIBE affiliate_payments")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Champ</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th><th>Extra</th></tr>";
    foreach ($final_structure as $column) {
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

    echo "<p style='color: green; font-weight: bold;'>✅ Table affiliate_payments mise à jour avec succès !</p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur : " . $e->getMessage() . "</p>";
}
?> 