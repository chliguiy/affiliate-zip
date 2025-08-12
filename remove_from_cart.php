<?php
session_start();
require_once 'config/database.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Vous devez être connecté']));
}

// Vérifier si les données nécessaires sont présentes
if (!isset($_POST['item_id'])) {
    die(json_encode(['success' => false, 'message' => 'ID de l\'article manquant']));
}

$item_id = intval($_POST['item_id']);
$user_id = $_SESSION['user_id'];

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Vérifier si l'article appartient à l'utilisateur et le supprimer
    $stmt = $conn->prepare("DELETE FROM cart_items WHERE id = ? AND user_id = ?");
    $result = $stmt->execute([$item_id, $user_id]);

    if ($stmt->rowCount() > 0) {
        die(json_encode(['success' => true, 'message' => 'Article supprimé du panier']));
    } else {
        die(json_encode(['success' => false, 'message' => 'Article non trouvé']));
    }

} catch (PDOException $e) {
    die(json_encode([
        'success' => false, 
        'message' => 'Erreur lors de la suppression: ' . $e->getMessage()
    ]));
}
?> 