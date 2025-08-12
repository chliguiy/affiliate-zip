<?php
require_once 'config/database.php';

// Récupérer l'ID de la commande
$order_id = $_GET['id'] ?? 106;

// Connexion à la base de données
$database = new Database();
$conn = $database->getConnection();

// Récupérer les détails de la commande
$stmt = $conn->prepare("
    SELECT o.*, 
           GROUP_CONCAT(CONCAT(oi.product_name, ' (x', oi.quantity, ')') SEPARATOR ', ') as products_detail,
           GROUP_CONCAT(CONCAT(oi.product_name, ':', oi.quantity, ':', oi.price, ':', oi.color, ':', oi.size) SEPARATOR '|') as products_data
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.id = ?
    GROUP BY o.id
");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

// Récupérer les détails des produits commandés avec leurs couleurs et tailles choisies
$stmt = $conn->prepare("
    SELECT 
           oi.id as order_item_id,
           oi.product_name,
           oi.quantity,
           oi.price,
           oi.color,
           oi.size,
           p.id as product_id,
           (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC, id ASC LIMIT 1) as product_image
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
    ORDER BY oi.id
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll();

if (!$order) {
    die("Commande non trouvée");
}

// Inclure l'en-tête
require_once 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails de la commande #<?php echo htmlspecialchars($order['order_number']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .order-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
        }
        .card-header {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            font-weight: 600;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f8f9fa;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #495057;
            min-width: 150px;
        }
        .info-value {
            color: #212529;
            flex: 1;
            text-align: right;
        }
        .status-badge {
            font-size: 0.875rem;
            padding: 0.5rem 1rem;
            border-radius: 50px;
        }
        
        .product-details {
            border-left: 4px solid #007bff;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        
        .product-details h6 {
            color: #495057;
            font-weight: 600;
        }
        
        .product-details .info-row {
            border-bottom: 1px solid #dee2e6;
            padding: 0.5rem 0;
        }
        
        .product-details .info-row:last-child {
            border-bottom: none;
        }
    </style>
</head>

<body>
    <?php include 'includes/topbar.php'; ?>

    <!-- En-tête de la commande -->
    <div class="order-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-receipt me-3"></i>
                        Commande #<?php echo htmlspecialchars($order['order_number']); ?>
                    </h1>
                    <p class="mb-0 opacity-75">
                        <i class="fas fa-calendar me-2"></i>
                        Créée le <?php echo date('d/m/Y à H:i', strtotime($order['created_at'])); ?>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <span class="status-badge bg-info">
                        <?php echo ucfirst(htmlspecialchars($order['status'])); ?>
                    </span>
                    <div class="mt-3">
                        <a href="orders.php" class="btn btn-light">
                            <i class="fas fa-arrow-left me-2"></i>
                            Retour aux commandes
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container py-4">
        <div class="row">
            <!-- Informations de la commande -->
            <div class="col-lg-8">
                <!-- Informations client -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-user me-2 text-primary"></i>
                            Informations Client
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <span class="info-label">Nom complet :</span>
                            <span class="info-value"><?php echo htmlspecialchars($order['customer_name'] ?? 'Non renseigné'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Téléphone :</span>
                            <span class="info-value">
                                <a href="tel:<?php echo htmlspecialchars($order['customer_phone'] ?? ''); ?>" class="text-decoration-none">
                                    <i class="fas fa-phone me-1"></i>
                                    <?php echo htmlspecialchars($order['customer_phone'] ?? 'Non renseigné'); ?>
                                </a>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email :</span>
                            <span class="info-value">
                                <?php if (!empty($order['customer_email'])): ?>
                                    <a href="mailto:<?php echo htmlspecialchars($order['customer_email']); ?>" class="text-decoration-none">
                                        <i class="fas fa-envelope me-1"></i>
                                        <?php echo htmlspecialchars($order['customer_email']); ?>
                                    </a>
                                <?php else: ?>
                                    Non renseigné
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Ville :</span>
                            <span class="info-value"><?php echo htmlspecialchars($order['customer_city'] ?? 'Non renseigné'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Adresse :</span>
                            <span class="info-value"><?php echo htmlspecialchars($order['customer_address'] ?? 'Non renseigné'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Détails de la commande -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-shopping-cart me-2 text-success"></i>
                            Détails de la Commande
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <span class="info-label">Produits :</span>
                            <span class="info-value"><?php echo htmlspecialchars($order['products_detail'] ?? 'Aucun produit'); ?></span>
                        </div>
                        

                        
                        <div class="info-row">
                            <span class="info-label">Mode de livraison :</span>
                            <span class="info-value">Livraison à domicile</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Mode de paiement :</span>
                            <span class="info-value">Paiement à la livraison</span>
                        </div>
                        <?php if (!empty($order['notes'])): ?>
                        <div class="info-row">
                            <span class="info-label">Notes :</span>
                            <span class="info-value"><?php echo htmlspecialchars($order['notes']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Résumé financier -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-calculator me-2 text-warning"></i>
                            Résumé Financier
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <span class="info-label">Montant total payé par le client :</span>
                            <span class="info-value">
                                <strong class="text-success fs-5">
                                    <?php echo number_format($order['final_sale_price'] ?? 0, 2); ?> DH
                                </strong>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Profit/Commission :</span>
                            <span class="info-value">
                                <strong class="text-primary">
                                    <?php echo number_format($order['commission_amount'] ?? 0, 2); ?> DH
                                </strong>
                            </span>
                        </div>
                        <?php if (isset($order['shipping_cost']) && $order['shipping_cost'] > 0): ?>
                        <div class="info-row">
                            <span class="info-label">Frais de livraison :</span>
                            <span class="info-value"><?php echo number_format($order['shipping_cost'], 2); ?> DH</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Actions rapides -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-tools me-2 text-secondary"></i>
                            Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary" onclick="printInvoice(<?php echo $order['id']; ?>)">
                                <i class="fas fa-print me-2"></i>
                                Imprimer la facture
                            </button>
                            <button class="btn btn-warning" onclick="editOrder(<?php echo $order['id']; ?>)">
                                <i class="fas fa-edit me-2"></i>
                                Modifier la commande
                            </button>
                            <?php if ($order['status'] === 'new'): ?>
                            <button class="btn btn-danger" onclick="deleteOrder(<?php echo $order['id']; ?>)">
                                <i class="fas fa-trash me-2"></i>
                                Supprimer
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
                    <!-- Détails des produits commandés -->
            <?php if (!empty($order_items)): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-shopping-cart me-2 text-success"></i>
                        Produits commandés avec leurs spécifications
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($order_items as $item): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="row g-0">
                                        <div class="col-4">
                                            <?php if (!empty($item['product_image'])): ?>
                                                <img src="<?php echo htmlspecialchars($item['product_image']); ?>" 
                                                     alt="<?php echo htmlspecialchars($item['product_name']); ?>" 
                                                     class="img-fluid rounded-start h-100" 
                                                     style="object-fit: cover;">
                                            <?php else: ?>
                                                <div class="bg-light d-flex align-items-center justify-content-center h-100">
                                                    <i class="fas fa-image text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-8">
                                            <div class="card-body">
                                                <h6 class="card-title mb-2"><?php echo htmlspecialchars($item['product_name']); ?></h6>
                                                <div class="mb-2">
                                                    <small class="text-muted">
                                                        <i class="fas fa-hashtag me-1"></i>
                                                        Quantité : <strong><?php echo $item['quantity']; ?></strong>
                                                    </small>
                                                </div>
                                                <div class="mb-2">
                                                    <small class="text-muted">
                                                        <i class="fas fa-tag me-1"></i>
                                                        Prix : <strong><?php echo number_format($item['price'], 2); ?> DH</strong>
                                                    </small>
                                                </div>
                                                
                                                <?php if (!empty($item['color'])): ?>
                                                <div class="mb-2">
                                                    <small class="text-muted">
                                                        <i class="fas fa-palette me-1 text-warning"></i>
                                                        🎨 Couleur choisie :
                                                    </small>
                                                    <div>
                                                        <span class="badge bg-light text-dark border">
                                                            <?php echo htmlspecialchars($item['color']); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($item['size'])): ?>
                                                <div class="mb-2">
                                                    <small class="text-muted">
                                                        <i class="fas fa-ruler me-1 text-info"></i>
                                                        📏 Taille choisie :
                                                    </small>
                                                    <div>
                                                        <span class="badge bg-primary">
                                                            <?php echo htmlspecialchars($item['size']); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <div class="mt-2">
                                                    <small class="text-success">
                                                        <i class="fas fa-calculator me-1"></i>
                                                        Sous-total : <strong><?php echo number_format($item['price'] * $item['quantity'], 2); ?> DH</strong>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function printInvoice(orderId) {
            window.open(`order_label_image.php?order_id=${orderId}`, '_blank');
        }

        function deleteOrder(orderId) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cette commande ?')) {
                window.location.href = `orders.php?action=delete&id=${orderId}`;
            }
        }

        function editOrder(orderId) {
            window.location.href = `orders.php#edit-${orderId}`;
        }
    </script>
</body>
</html>

<?php
// Inclure le pied de page
require_once 'includes/footer.php';
?> 