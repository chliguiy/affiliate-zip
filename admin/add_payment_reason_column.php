<?php
require_once '../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Vérifier si la colonne payment_reason existe déjà
    $stmt = $pdo->prepare("SHOW COLUMNS FROM orders LIKE 'payment_reason'");
    $stmt->execute();
    $columnExists = $stmt->fetch();
    
    if (!$columnExists) {
        // Ajouter la colonne payment_reason
        $sql = "ALTER TABLE orders ADD COLUMN payment_reason TEXT NULL AFTER notes";
        $pdo->exec($sql);
        echo "Colonne payment_reason ajoutée avec succès à la table orders.<br>";
    } else {
        echo "La colonne payment_reason existe déjà dans la table orders.<br>";
    }
    
    echo "Script terminé avec succès.";
    
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage();
}
?> 