<?php
require_once 'config/database.php';

$database = new Database();
$pdo = $database->getConnection();

echo "<h2>Test de la table affiliate_payments</h2>";

// Vérifier si la table existe
$check_table = "SHOW TABLES LIKE 'affiliate_payments'";
$table_exists = $pdo->query($check_table)->rowCount() > 0;

if ($table_exists) {
    echo "<p style='color: green;'>✅ La table affiliate_payments existe</p>";
    
    // Afficher la structure de la table
    $structure = $pdo->query("DESCRIBE affiliate_payments")->fetchAll(PDO::FETCH_ASSOC);
    echo "<h3>Structure de la table :</h3>";
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
    
    // Afficher les données existantes
    $payments = $pdo->query("SELECT * FROM affiliate_payments ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($payments)) {
        echo "<h3>Derniers paiements :</h3>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Affilié ID</th><th>Montant</th><th>Date</th><th>Statut</th><th>Raison</th><th>Colis</th></tr>";
        foreach ($payments as $payment) {
            echo "<tr>";
            echo "<td>{$payment['id']}</td>";
            echo "<td>{$payment['affiliate_id']}</td>";
            echo "<td>{$payment['montant']} MAD</td>";
            echo "<td>{$payment['date_paiement']}</td>";
            echo "<td>{$payment['statut']}</td>";
            echo "<td>{$payment['raison']}</td>";
            echo "<td>{$payment['colis']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>⚠️ Aucun paiement trouvé dans la table</p>";
    }
    
} else {
    echo "<p style='color: red;'>❌ La table affiliate_payments n'existe pas</p>";
    
    // Créer la table
    echo "<h3>Création de la table affiliate_payments...</h3>";
    $create_table = "CREATE TABLE affiliate_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        affiliate_id INT NOT NULL,
        montant DECIMAL(10,2) NOT NULL,
        date_paiement DATETIME NOT NULL,
        statut ENUM('en_attente', 'payé', 'réglé') DEFAULT 'réglé',
        raison TEXT,
        colis INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (affiliate_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    try {
        $pdo->exec($create_table);
        echo "<p style='color: green;'>✅ Table affiliate_payments créée avec succès</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erreur lors de la création : " . $e->getMessage() . "</p>";
    }
}

// Vérifier les affiliés avec des commandes livrées
echo "<h3>Affiliés avec des commandes livrées :</h3>";
$affiliates_query = "SELECT DISTINCT 
                      u.id as affiliate_id,
                      u.username,
                      u.full_name,
                      SUM(o.total_amount) as total_amount,
                      SUM(oi.id IS NOT NULL) as total_packages
                    FROM users u
                    JOIN orders o ON u.id = o.affiliate_id
                    LEFT JOIN order_items oi ON o.id = oi.order_id
                    WHERE o.status = 'delivered' AND u.type = 'affiliate'
                    GROUP BY u.id, u.username, u.full_name
                    HAVING total_amount > 0";

$affiliates = $pdo->query($affiliates_query)->fetchAll(PDO::FETCH_ASSOC);

if (!empty($affiliates)) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID Affilié</th><th>Nom d'utilisateur</th><th>Nom complet</th><th>Montant Total</th><th>Total Colis</th></tr>";
    foreach ($affiliates as $affiliate) {
        echo "<tr>";
        echo "<td>{$affiliate['affiliate_id']}</td>";
        echo "<td>{$affiliate['username']}</td>";
        echo "<td>{$affiliate['full_name']}</td>";
        echo "<td>{$affiliate['total_amount']} MAD</td>";
        echo "<td>{$affiliate['total_packages']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: orange;'>⚠️ Aucun affilié avec des commandes livrées trouvé</p>";
}
?> 