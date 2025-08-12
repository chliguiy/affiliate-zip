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
           GROUP_CONCAT(CONCAT(oi.product_name, ' (x', oi.quantity, ')') SEPARATOR ', ') as products_detail
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.id = ?
    GROUP BY o.id
");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    die("Commande non trouvée");
}
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
    </style>
</head>

<body>
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
                            <span class="info-label">Montant total :</span>
                            <span class="info-value">
                                <strong class="text-success fs-5">
                                    <?php echo number_format($order['total_amount'] ?? 0, 2); ?> DH
                                </strong>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Commission :</span>
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