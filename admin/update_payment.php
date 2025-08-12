<?php
session_start();
require_once '../config/database.php';

// Vérifier l'authentification admin
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

$payment_id = $_POST['payment_id'] ?? null;
$customer_name = $_POST['customer_name'] ?? '';
$customer_email = $_POST['customer_email'] ?? '';
$customer_phone = $_POST['customer_phone'] ?? '';
$customer_address = $_POST['customer_address'] ?? '';
$customer_city = $_POST['customer_city'] ?? '';
$total_amount = $_POST['total_amount'] ?? 0;
$status = $_POST['status'] ?? '';

if (!$payment_id) {
    echo json_encode(['success' => false, 'message' => 'ID de paiement manquant']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Mettre à jour la commande
    $update_query = "UPDATE orders SET 
                    customer_name = ?,
                    customer_email = ?,
                    customer_phone = ?,
                    customer_address = ?,
                    customer_city = ?,
                    total_amount = ?,
                    status = ?,
                    updated_at = NOW()
                    WHERE id = ?";
    
    $update_stmt = $pdo->prepare($update_query);
    $result = $update_stmt->execute([
        $customer_name,
        $customer_email,
        $customer_phone,
        $customer_address,
        $customer_city,
        $total_amount,
        $status,
        $payment_id
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Paiement modifié avec succès']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la modification']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
?> 