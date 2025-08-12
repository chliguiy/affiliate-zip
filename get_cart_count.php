<?php
session_start();
require_once 'config/database.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['count' => 0]));
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Récupérer le nombre d'articles dans le panier
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM cart_items WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch();

    die(json_encode(['count' => intval($result['count'])]));

} catch (PDOException $e) {
    die(json_encode(['count' => 0]));
}
?> 