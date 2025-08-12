<?php
session_start();
require_once 'includes/auth.php';
require_once 'config/database.php';

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

$database = new Database();
$pdo = $database->getConnection();

// Récupérer les données du formulaire
$amount = $_POST['amount'] ?? '';
$reason = $_POST['reason'] ?? '';
$affiliate_id = $_POST['affiliate_id'] ?? '';
$payment_date = $_POST['payment_date'] ?? date('Y-m-d');

// Validation des données
if (empty($amount) || !is_numeric($amount) || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Le montant doit être un nombre positif']);
    exit();
}

if (empty($reason)) {
    echo json_encode(['success' => false, 'message' => 'La raison est obligatoire']);
    exit();
}

if (empty($affiliate_id)) {
    echo json_encode(['success' => false, 'message' => 'Veuillez sélectionner un affilié']);
    exit();
}

try {
    // Insérer le nouveau paiement dans la table orders
    $stmt = $pdo->prepare("
        INSERT INTO orders (affiliate_id, total_amount, customer_name, customer_phone, address, city, created_at, status, payment_reason) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $affiliate_id, // affiliate_id sélectionné
        $amount,
        'Paiement manuel', // Nom du client par défaut
        '0000000000', // Téléphone par défaut
        'Adresse par défaut', // Adresse par défaut
        'Ville par défaut', // Ville par défaut
        $payment_date . ' 00:00:00',
        'confirmed', // Statut confirmé
        $reason
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Paiement ajouté avec succès']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'ajout du paiement : ' . $e->getMessage()]);
}
?> 