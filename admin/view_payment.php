<?php
session_start();
require_once 'includes/auth.php';
require_once '../config/database.php';

// Vérifier l'authentification admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit();
}

$payment_id = $_GET['id'] ?? null;

if (!$payment_id) {
    header('Location: payments_received.php');
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Récupérer les détails du paiement
    $query = "SELECT 
                o.*,
                u.username as affiliate_name,
                u.email as affiliate_email,
                COUNT(oi.id) as packages
              FROM orders o
              LEFT JOIN users u ON o.affiliate_id = u.id
              LEFT JOIN order_items oi ON o.id = oi.order_id
              WHERE o.id = ?
              GROUP BY o.id";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        header('Location: payments_received.php');
        exit();
    }
    
    // Récupérer les éléments de la commande
    $items_query = "SELECT 
                      oi.*,
                      p.name as product_name,
                      p.image as product_image
                    FROM order_items oi
                    LEFT JOIN products p ON oi.product_id = p.id
                    WHERE oi.order_id = ?";
    
    $items_stmt = $pdo->prepare($items_query);
    $items_stmt->execute([$payment_id]);
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    header('Location: payments_received.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails du Paiement - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #f8f9fa;
            border-right: 1px solid #dee2e6;
        }
        
        .payment-details {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-paid {
            background-color: #d4edda;
            color: #155724;
        }
        
        .items-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }
        
        .back-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 1rem;
        }
        
        .back-btn:hover {
            background: #5a6268;
            color: white;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4">
                <a href="payments_received.php" class="back-btn">
                    <i class="fas fa-arrow-left me-2"></i>
                    Retour aux paiements
                </a>
                
                <h2 class="mb-4">Détails du Paiement</h2>

                <!-- Payment Details -->
                <div class="payment-details">
                    <div class="row">
                        <div class="col-md-6">
                            <h4>Informations de la commande</h4>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>ID:</strong></td>
                                    <td><?= $payment['id'] ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Numéro de commande:</strong></td>
                                    <td><?= htmlspecialchars($payment['order_number']) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Date de création:</strong></td>
                                    <td><?= date('d/m/Y H:i', strtotime($payment['created_at'])) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Statut:</strong></td>
                                    <td>
                                        <span class="status-badge <?= $payment['status'] === 'paid' ? 'status-paid' : 'status-pending' ?>">
                                            <?= $payment['status'] === 'paid' ? 'Payé' : 'En Attente' ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Montant total:</strong></td>
                                    <td><strong><?= number_format($payment['total_amount'], 2) ?> DH</strong></td>
                                </tr>
                                <tr>
                                    <td><strong>Nombre de colis:</strong></td>
                                    <td><?= $payment['packages'] ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h4>Informations client</h4>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Nom:</strong></td>
                                    <td><?= htmlspecialchars($payment['customer_name']) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Email:</strong></td>
                                    <td><?= htmlspecialchars($payment['customer_email']) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Téléphone:</strong></td>
                                    <td><?= htmlspecialchars($payment['customer_phone']) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Adresse:</strong></td>
                                    <td><?= htmlspecialchars($payment['customer_address']) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Ville:</strong></td>
                                    <td><?= htmlspecialchars($payment['customer_city']) ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <?php if ($payment['affiliate_name']): ?>
                    <div class="row mt-4">
                        <div class="col-12">
                            <h4>Informations affilié</h4>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Nom affilié:</strong></td>
                                    <td><?= htmlspecialchars($payment['affiliate_name']) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Email affilié:</strong></td>
                                    <td><?= htmlspecialchars($payment['affiliate_email']) ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Order Items -->
                <div class="items-table">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            Produits commandés
                        </h5>
                    </div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Image</th>
                                <th>Quantité</th>
                                <th>Prix unitaire</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4">
                                        <i class="fas fa-inbox fs-1 text-muted mb-3"></i>
                                        <p class="text-muted">Aucun produit trouvé</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['product_name'] ?: $item['name']) ?></td>
                                        <td>
                                            <?php if ($item['product_image']): ?>
                                                <img src="../uploads/products/<?= htmlspecialchars($item['product_image']) ?>" 
                                                     alt="Product" class="product-image">
                                            <?php else: ?>
                                                <div class="product-image bg-light d-flex align-items-center justify-content-center">
                                                    <i class="fas fa-image text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $item['quantity'] ?></td>
                                        <td><?= number_format($item['price'], 2) ?> DH</td>
                                        <td><strong><?= number_format($item['price'] * $item['quantity'], 2) ?> DH</strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 