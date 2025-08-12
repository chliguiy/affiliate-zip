<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';

// Récupérer les informations de l'utilisateur
$database = new Database();
$conn = $database->getConnection();

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Récupérer les articles du panier
$stmt = $conn->prepare("
    SELECT ci.id, ci.quantity, p.id as product_id, p.name, p.price, p.commission_rate,
           (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, id ASC LIMIT 1) as image_url
    FROM cart_items ci
    JOIN products p ON ci.product_id = p.id
    WHERE ci.user_id = ? AND p.status = 'active'
    ORDER BY ci.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$cart_items = $stmt->fetchAll();

if (empty($cart_items)) {
    header('Location: cart.php');
    exit();
}

// Calculer le total
$total = 0;
foreach ($cart_items as $item) {
    $total += $item['price'] * $item['quantity'];
}

// Traitement du formulaire de commande
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Récupérer les données du formulaire
        $customer_name = trim($_POST['customer_name'] ?? '');
        $customer_phone = trim($_POST['customer_phone'] ?? '');
        $customer_address = trim($_POST['customer_address'] ?? '');
        $customer_city = trim($_POST['customer_city'] ?? '');
        $comment = trim($_POST['comment'] ?? '');
        
        // Validation des données
        if (empty($customer_name) || empty($customer_phone) || empty($customer_address) || empty($customer_city)) {
            throw new Exception('Tous les champs sont obligatoires.');
        }
        
        // Commencer une transaction
        $conn->beginTransaction();
        
        // Générer un numéro de commande unique et séquentiel
        require_once 'includes/system_integration.php';
        $systemIntegration = new SystemIntegration();
        $order_number = $systemIntegration->generateOrderNumberPublic();
        
        // Créer la commande
        $stmt = $conn->prepare("
            INSERT INTO orders (order_number, affiliate_id, customer_name, customer_phone, customer_address, customer_city, total_amount, commission_amount, notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $commission_amount = $total * 0.1; // 10% de commission par défaut
        $stmt->execute([$order_number, $_SESSION['user_id'], $customer_name, $customer_phone, $customer_address, $customer_city, $total, $commission_amount, $comment]);
        $order_id = $conn->lastInsertId();
        
        // Ajouter les articles à la commande
        $stmt = $conn->prepare("
            INSERT INTO order_items (order_id, product_id, product_name, quantity, price, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        foreach ($cart_items as $item) {
            $stmt->execute([$order_id, $item['product_id'], $item['name'], $item['quantity'], $item['price']]);
        }
        
        // Vider le panier
        $stmt = $conn->prepare("DELETE FROM cart_items WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        
        // Valider la transaction
        $conn->commit();
        
        // Rediriger vers la confirmation de commande
        header('Location: order_confirmation.php?order_id=' . $order_id);
        exit();
        
    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finaliser la commande - SCAR AFFILIATE</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        
        .main-content {
            margin-left: 260px;
            padding: 0 1rem 0;
            min-height: 100vh;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 0 1rem 0;
            }
        }
        
        .checkout-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            margin: 2rem auto;
            max-width: 1200px;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .summary-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/topbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="checkout-container p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-credit-card me-2"></i>Finaliser la commande</h2>
                <a href="cart.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Retour au panier
                </a>
            </div>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Informations de livraison</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="checkoutForm">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="customer_name" class="form-label">Nom complet *</label>
                                        <input type="text" class="form-control" id="customer_name" name="customer_name" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="customer_phone" class="form-label">Téléphone *</label>
                                        <input type="tel" class="form-control" id="customer_phone" name="customer_phone" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="customer_city" class="form-label">Ville *</label>
                                    <select class="form-select" id="customer_city" name="customer_city" required>
                                        <option value="">Sélectionner une ville</option>
                                        <option value="Casablanca" data-delivery="30">Casablanca (30 MAD)</option>
                                        <option value="Rabat" data-delivery="40">Rabat (40 MAD)</option>
                                        <option value="Marrakech" data-delivery="50">Marrakech (50 MAD)</option>
                                        <option value="Fès" data-delivery="45">Fès (45 MAD)</option>
                                        <option value="Agadir" data-delivery="60">Agadir (60 MAD)</option>
                                        <option value="Tanger" data-delivery="55">Tanger (55 MAD)</option>
                                        <option value="Autre" data-delivery="70">Autre (70 MAD)</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="customer_address" class="form-label">Adresse de livraison *</label>
                                    <textarea class="form-control" id="customer_address" name="customer_address" rows="3" required></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="comment" class="form-label">Commentaire (optionnel)</label>
                                    <textarea class="form-control" id="comment" name="comment" rows="2"></textarea>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="summary-card">
                        <h5 class="mb-3">Résumé de la commande</h5>
                        
                        <?php foreach ($cart_items as $item): ?>
                            <div class="d-flex align-items-center mb-2">
                                <?php if ($item['image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                         class="product-image me-2">
                                <?php else: ?>
                                    <div class="product-image bg-light d-flex align-items-center justify-content-center me-2">
                                        <i class="fas fa-image text-muted"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="flex-grow-1">
                                    <div class="fw-bold"><?php echo htmlspecialchars($item['name']); ?></div>
                                    <small class="text-muted">Quantité: <?php echo $item['quantity']; ?></small>
                                </div>
                                <div class="text-end">
                                    <strong><?php echo number_format($item['price'] * $item['quantity'], 2); ?> MAD</strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <hr>
                        <div class="d-flex justify-content-between">
                            <span class="h5 mb-0">Total:</span>
                            <strong class="h5 mb-0"><?php echo number_format($total, 2); ?> MAD</strong>
                        </div>
                        
                        <button type="submit" form="checkoutForm" class="btn btn-primary w-100 mt-3">
                            <i class="fas fa-credit-card me-2"></i>Confirmer la commande
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 