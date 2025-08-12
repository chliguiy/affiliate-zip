<?php

 $host = "localhost";
     $dbname = "u163515678_affiliate";
     $username = "u163515678_affiliate";
     $password = "affiliate@2025@Adnane";

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $conn->prepare("DELETE FROM admins WHERE role IS NULL OR role = ''");
    $stmt->execute();
    echo "Comptes admin sans rôle supprimés avec succès.";
} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
} 