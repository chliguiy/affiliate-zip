<?php
     $host = "localhost";
     $dbname = "u163515678_affiliate";
     $username = "u163515678_affiliate";
     $password = "affiliate@2025@Adnane";

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sql = "ALTER TABLE products ADD COLUMN reorder_point INT NOT NULL DEFAULT 0";
    $conn->exec($sql);
    echo "Colonne 'reorder_point' ajoutée à la table products.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "La colonne 'reorder_point' existe déjà.";
    } else {
        echo "Erreur : " . $e->getMessage();
    }
} 