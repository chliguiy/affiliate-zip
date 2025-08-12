<?php
session_start();
require_once 'config/database.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Vous devez être connecté']));
}

// Vérifier si les données nécessaires sont présentes
if (!isset($_POST['item_id']) || !isset($_POST['change'])) {
    die(json_encode(['success' => false, 'message' => 'Données manquantes']));
}

$item_id = intval($_POST['item_id']);
$change = intval($_POST['change']);
$user_id = $_SESSION['user_id'];

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Vérifier si l'article appartient à l'utilisateur
    $stmt = $conn->prepare("SELECT ci.id, ci.quantity FROM cart_items ci WHERE ci.id = ? AND ci.user_id = ?");
    $stmt->execute([$item_id, $user_id]);
    $item = $stmt->fetch();

    if (!$item) {
        die(json_encode(['success' => false, 'message' => 'Article non trouvé']));
    }

    $new_quantity = $item['quantity'] + $change;

    if ($new_quantity <= 0) {
        // Supprimer l'article si la quantité devient 0 ou négative
        $stmt = $conn->prepare("DELETE FROM cart_items WHERE id = ?");
        $stmt->execute([$item_id]);
    } else {
        // Mettre à jour la quantité
        $stmt = $conn->prepare("UPDATE cart_items SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$new_quantity, $item_id]);
    }

    die(json_encode(['success' => true, 'message' => 'Quantité mise à jour']));

} catch (PDOException $e) {
    die(json_encode([
        'success' => false, 
        'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
    ]));
}
?> 