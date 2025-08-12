<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "<h3>Correction de la table orders pour les paiements manuels</h3>";
    
    // 1. Vérifier et ajouter la colonne payment_reason
    $stmt = $pdo->prepare("SHOW COLUMNS FROM orders LIKE 'payment_reason'");
    $stmt->execute();
    $paymentReasonColumn = $stmt->fetch();
    
    if (!$paymentReasonColumn) {
        $sql = "ALTER TABLE orders ADD COLUMN payment_reason TEXT NULL AFTER notes";
        $pdo->exec($sql);
        echo "✓ Colonne payment_reason ajoutée.<br>";
    } else {
        echo "✓ Colonne payment_reason existe déjà.<br>";
    }
    
    // 2. Vérifier et ajouter la colonne affiliate_id si elle n'existe pas
    $stmt = $pdo->prepare("SHOW COLUMNS FROM orders LIKE 'affiliate_id'");
    $stmt->execute();
    $affiliateIdColumn = $stmt->fetch();
    
    if (!$affiliateIdColumn) {
        $sql = "ALTER TABLE orders ADD COLUMN affiliate_id INT NULL AFTER id";
        $pdo->exec($sql);
        echo "✓ Colonne affiliate_id ajoutée.<br>";
    } else {
        echo "✓ Colonne affiliate_id existe déjà.<br>";
    }
    
    // 3. Vérifier et ajouter la colonne customer_phone si elle n'existe pas
    $stmt = $pdo->prepare("SHOW COLUMNS FROM orders LIKE 'customer_phone'");
    $stmt->execute();
    $customerPhoneColumn = $stmt->fetch();
    
    if (!$customerPhoneColumn) {
        // Vérifier si la colonne phone existe
        $stmt = $pdo->prepare("SHOW COLUMNS FROM orders LIKE 'phone'");
        $stmt->execute();
        $phoneColumn = $stmt->fetch();
        
        if ($phoneColumn) {
            // Renommer phone en customer_phone
            $sql = "ALTER TABLE orders CHANGE phone customer_phone VARCHAR(20) NOT NULL";
            $pdo->exec($sql);
            echo "✓ Colonne phone renommée en customer_phone.<br>";
        } else {
            $sql = "ALTER TABLE orders ADD COLUMN customer_phone VARCHAR(20) NOT NULL AFTER customer_name";
            $pdo->exec($sql);
            echo "✓ Colonne customer_phone ajoutée.<br>";
        }
    } else {
        echo "✓ Colonne customer_phone existe déjà.<br>";
    }
    
    // 4. Vérifier et ajouter la colonne address si elle n'existe pas
    $stmt = $pdo->prepare("SHOW COLUMNS FROM orders LIKE 'address'");
    $stmt->execute();
    $addressColumn = $stmt->fetch();
    
    if (!$addressColumn) {
        $sql = "ALTER TABLE orders ADD COLUMN address TEXT NOT NULL AFTER customer_phone";
        $pdo->exec($sql);
        echo "✓ Colonne address ajoutée.<br>";
    } else {
        echo "✓ Colonne address existe déjà.<br>";
    }
    
    // 5. Vérifier et ajouter la colonne city si elle n'existe pas
    $stmt = $pdo->prepare("SHOW COLUMNS FROM orders LIKE 'city'");
    $stmt->execute();
    $cityColumn = $stmt->fetch();
    
    if (!$cityColumn) {
        $sql = "ALTER TABLE orders ADD COLUMN city VARCHAR(100) NOT NULL AFTER address";
        $pdo->exec($sql);
        echo "✓ Colonne city ajoutée.<br>";
    } else {
        echo "✓ Colonne city existe déjà.<br>";
    }
    
    echo "<br><strong style='color: green;'>✓ Table orders mise à jour avec succès pour les paiements manuels.</strong>";
    
} catch (Exception $e) {
    echo "<br><strong style='color: red;'>✗ Erreur : " . $e->getMessage() . "</strong>";
}
?> 