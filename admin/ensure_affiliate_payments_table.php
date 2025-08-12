<?php
require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

echo "<h2>Vérification et création de la table affiliate_payments</h2>";

try {
    // Vérifier si la table existe
    $check_table = "SHOW TABLES LIKE 'affiliate_payments'";
    $table_exists = $pdo->query($check_table)->rowCount() > 0;

    if (!$table_exists) {
        echo "<p style='color: orange;'>⚠️ La table affiliate_payments n'existe pas. Création en cours...</p>";
        
        // Créer la table affiliate_payments
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
        
        $pdo->exec($create_table);
        echo "<p style='color: green;'>✅ Table affiliate_payments créée avec succès</p>";
    } else {
        echo "<p style='color: green;'>✅ La table affiliate_payments existe déjà</p>";
        
        // Vérifier la structure
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
    }

    // Vérifier s'il y a des données
    $count = $pdo->query("SELECT COUNT(*) FROM affiliate_payments")->fetchColumn();
    echo "<p>Nombre de paiements enregistrés : <strong>$count</strong></p>";

    if ($count > 0) {
        // Afficher les derniers paiements
        $payments = $pdo->query("SELECT * FROM affiliate_payments ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
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
    }

    echo "<p style='color: green; font-weight: bold;'>✅ Configuration terminée avec succès !</p>";
    echo "<p><a href='payments_received.php'>← Retour aux paiements</a></p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur : " . $e->getMessage() . "</p>";
}
?>
