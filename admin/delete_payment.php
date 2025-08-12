<?php
session_start();
require_once '../config/database.php';

// Vérifier l'authentification admin
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

// Vérifier que c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Récupérer les données JSON
$input = json_decode(file_get_contents('php://input'), true);
$payment_id = $input['payment_id'] ?? null;

if (!$payment_id) {
    echo json_encode(['success' => false, 'message' => 'ID de paiement manquant']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Commencer une transaction
    $pdo->beginTransaction();
    
    // Supprimer d'abord les éléments de commande
    $query1 = "DELETE FROM order_items WHERE order_id = ?";
    $stmt1 = $pdo->prepare($query1);
    $stmt1->execute([$payment_id]);
    
    // Puis supprimer la commande
    $query2 = "DELETE FROM orders WHERE id = ?";
    $stmt2 = $pdo->prepare($query2);
    $result = $stmt2->execute([$payment_id]);
    
    if ($result) {
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Paiement supprimé avec succès']);
    } else {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression']);
    }
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
}
?> 