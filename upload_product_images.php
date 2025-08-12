<?php
session_start();
require_once 'config/database.php';

// Vérifier l'authentification (optionnel, selon vos besoins)
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

$product_id = $_POST['product_id'] ?? null;
$delete_images = $_POST['delete_images'] ?? [];

if (!$product_id) {
    echo json_encode(['success' => false, 'message' => 'ID du produit manquant']);
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Vérifier que le produit existe
    $stmt = $pdo->prepare("SELECT id, name FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Produit non trouvé']);
        exit();
    }
    
    // Supprimer les images sélectionnées
    if (!empty($delete_images)) {
        foreach ($delete_images as $image_url) {
            // Supprimer de la base de données
            $stmt = $pdo->prepare("DELETE FROM product_images WHERE product_id = ? AND image_url = ?");
            $stmt->execute([$product_id, $image_url]);
            
            // Supprimer le fichier physique
            $file_path = 'uploads/products/' . basename($image_url);
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
    }
    
    // Traiter les nouvelles images
    $uploaded_images = [];
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $upload_dir = 'uploads/products/';
        
        // Créer le dossier s'il n'existe pas
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Vérifier s'il y a déjà des images pour déterminer l'image principale
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM product_images WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $existing_count = $stmt->fetch()['count'];
        $is_first_image = ($existing_count == 0);
        
        foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                $file_name = $_FILES['images']['name'][$key];
                $file_size = $_FILES['images']['size'][$key];
                $file_type = $_FILES['images']['type'][$key];
                
                // Vérifications de sécurité
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                $max_size = 5 * 1024 * 1024; // 5 MB
                
                if (!in_array($file_type, $allowed_types)) {
                    continue; // Ignorer les fichiers non autorisés
                }
                
                if ($file_size > $max_size) {
                    continue; // Ignorer les fichiers trop volumineux
                }
                
                // Générer un nom de fichier unique
                $extension = pathinfo($file_name, PATHINFO_EXTENSION);
                $unique_name = uniqid() . '_' . time() . '.' . $extension;
                $file_path = $upload_dir . $unique_name;
                
                // Déplacer le fichier uploadé
                if (move_uploaded_file($tmp_name, $file_path)) {
                    // Insérer dans la base de données
                    $stmt = $pdo->prepare("
                        INSERT INTO product_images (product_id, image_url, is_primary, sort_order, created_at) 
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $product_id,
                        $unique_name,
                        $is_first_image ? 1 : 0,
                        $existing_count + count($uploaded_images) + 1
                    ]);
                    
                    $uploaded_images[] = $unique_name;
                    $is_first_image = false; // Seule la première image sera principale
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Images traitées avec succès',
        'uploaded_count' => count($uploaded_images),
        'deleted_count' => count($delete_images)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
?> 