<?php
session_start();
require_once 'config/database.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Vous devez être connecté']));
}

// Vérifier si les données nécessaires sont présentes
if (!isset($_POST['product_id']) || !isset($_POST['quantity'])) {
    die(json_encode(['success' => false, 'message' => 'Données manquantes']));
}

$product_id = intval($_POST['product_id']);
$quantity = intval($_POST['quantity']);
$user_id = $_SESSION['user_id'];

if ($quantity <= 0) {
    die(json_encode(['success' => false, 'message' => 'Quantité invalide']));
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Vérifier si le produit existe et est actif
    $stmt = $conn->prepare("SELECT id, name, price FROM products WHERE id = ? AND status = 'active'");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if (!$product) {
        die(json_encode(['success' => false, 'message' => 'Produit non trouvé']));
    }

    // Vérifier si le produit est déjà dans le panier
    $stmt = $conn->prepare("SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user_id, $product_id]);
    $existing_item = $stmt->fetch();

    if ($existing_item) {
        // Mettre à jour la quantité
        $new_quantity = $existing_item['quantity'] + $quantity;
        $stmt = $conn->prepare("UPDATE cart_items SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$new_quantity, $existing_item['id']]);
    } else {
        // Ajouter un nouvel article
        $stmt = $conn->prepare("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $product_id, $quantity]);
    }

    // Récupérer le nombre total d'articles dans le panier
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM cart_items WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $cart_count = $stmt->fetch()['total'];

    die(json_encode([
        'success' => true, 
        'message' => 'Produit ajouté au panier',
        'cart_count' => $cart_count
    ]));

} catch (PDOException $e) {
    die(json_encode([
        'success' => false, 
        'message' => 'Erreur lors de l\'ajout au panier: ' . $e->getMessage()
    ]));
}
?> 