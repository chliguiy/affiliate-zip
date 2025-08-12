<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Vérifier si l'ID de commande est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: orders.php');
    exit;
}

$order_id = $_GET['id'];

// Inclure la configuration de la base de données
require_once 'config/database.php';

// Créer une instance de la base de données
$database = new Database();
$conn = $database->getConnection();

// Si la connexion échoue, afficher un message d'erreur
if (!$conn) {
    die("Erreur de connexion à la base de données");
}

// Récupérer les détails de la commande
$stmt = $conn->prepare("
    SELECT o.*, 
           GROUP_CONCAT(CONCAT(oi.product_name, ' (x', oi.quantity, ')') SEPARATOR ', ') as products_detail,
           GROUP_CONCAT(CONCAT(oi.product_name, ':', oi.quantity, ':', oi.price) SEPARATOR '|') as products_data
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.id = ?
    GROUP BY o.id
");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

// Vérifier si la commande existe
if (!$order) {
    header('Location: orders.php');
    exit;
}

// Système de statuts de suivi
$tracking_statuses = [
    "1" => ["id" => 1, "name" => "Nouvelle demande", "color" => "info"],
    "2" => ["id" => 2, "name" => "En cours de ramassage", "color" => "warning"],
    "3" => ["id" => 3, "name" => "Colis ramassé", "color" => "danger"],
    "4" => ["id" => 4, "name" => "Pris en charge", "color" => "warning"],
    "5" => ["id" => 5, "name" => "En cours d'acheminement", "color" => "warning"],
    "6" => ["id" => 6, "name" => "En cours de livraison", "color" => "warning"],
    "7" => ["id" => 7, "name" => "Livré", "color" => "success"],
    "9" => ["id" => 9, "name" => "Injoignable", "color" => "danger"],
    "10" => ["id" => 10, "name" => "Reporté", "color" => "info"],
    "11" => ["id" => 11, "name" => "Annulé", "color" => "danger"],
    "12" => ["id" => 12, "name" => "Refusé", "color" => "dark"],
    "13" => ["id" => 13, "name" => "Demande de retour", "color" => "danger"],
    "14" => ["id" => 14, "name" => "Retour prêt livreur", "color" => "warning"],
    "15" => ["id" => 15, "name" => "Retour envoyé", "color" => "warning"],
    "16" => ["id" => 16, "name" => "Retour reçu au centre", "color" => "success"],
    "17" => ["id" => 17, "name" => "Retour disponible", "color" => "success"],
    "18" => ["id" => 18, "name" => "Retour reçu au client", "color" => "success"]
];

// Mappage des anciens statuts vers les nouveaux
$status_mapping = [
    'new' => 1,
    'confirmed' => 4,
    'shipping' => 5,
    'delivered' => 7,
    'returned' => 13,
    'refused' => 12,
    'cancelled' => 11,
    'unconfirmed' => 1,
    'processing' => 2,
    'pending' => 1
];

// Déterminer le statut actuel
if ($order) {
    $current_status_id = $status_mapping[$order['status']] ?? 1;
    $current_status = $tracking_statuses[strval($current_status_id)];
} else {
    $current_status = $tracking_statuses['1']; // Statut par défaut
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
        
        .status-timeline {
            position: relative;
            padding: 0;
        }
        
        .status-timeline::before {
            content: '';
            position: absolute;
            top: 0;
            left: 30px;
            height: 100%;
            width: 2px;
            background: #e9ecef;
        }
        
        .timeline-item {
            position: relative;
            padding: 1rem 0 1rem 4rem;
            border: none;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 24px;
            top: 50%;
            transform: translateY(-50%);
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #6c757d;
            border: 3px solid white;
            box-shadow: 0 0 0 3px #e9ecef;
        }
        
        .timeline-item.active::before {
            background: #0d6efd;
            box-shadow: 0 0 0 3px #b6d7ff;
        }
        
        .timeline-item.completed::before {
            background: #198754;
            box-shadow: 0 0 0 3px #b8e6c1;
        }
        
        .timeline-item.danger::before {
            background: #dc3545;
            box-shadow: 0 0 0 3px #f5b7b1;
        }
        
        .timeline-item.warning::before {
            background: #fd7e14;
            box-shadow: 0 0 0 3px #ffd8a8;
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
        
        .btn-back {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
        }
        
        .btn-back:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            transform: translateY(-1px);
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
                    <span class="status-badge bg-<?php echo $current_status['color']; ?>">
                        <?php echo htmlspecialchars($current_status['name']); ?>
                    </span>
                    <div class="mt-3">
                        <a href="orders.php" class="btn btn-back">
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

            <!-- Suivi de la commande -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-route me-2 text-info"></i>
                            Suivi de la Commande
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="status-timeline">
                            <?php foreach ($tracking_statuses as $status_id => $status): ?>
                                <?php 
                                $is_current = ($status_id == $current_status_id);
                                $is_completed = ($status_id < $current_status_id && 
                                    !in_array($current_status_id, [9, 10, 11, 12, 13]) || 
                                    ($current_status_id >= 13 && $status_id >= 13 && $status_id <= $current_status_id));
                                
                                $item_class = '';
                                if ($is_current) {
                                    $item_class = $status['color'] === 'success' ? 'completed' : 
                                                ($status['color'] === 'danger' ? 'danger' : 
                                                ($status['color'] === 'warning' ? 'warning' : 'active'));
                                } elseif ($is_completed) {
                                    $item_class = 'completed';
                                }
                                ?>
                                <div class="timeline-item <?php echo $item_class; ?>">
                                    <div class="timeline-content">
                                        <h6 class="mb-1 <?php echo $is_current ? 'text-primary fw-bold' : ''; ?>">
                                            <?php echo htmlspecialchars($status['name']); ?>
                                            <?php if ($is_current): ?>
                                                <span class="badge bg-<?php echo $status['color']; ?> ms-2">Actuel</span>
                                            <?php endif; ?>
                                        </h6>
                                        <?php if ($is_current || $is_completed): ?>
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo $is_current ? 'En cours' : 'Terminé'; ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Actions rapides -->
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
                            <button class="btn btn-warning" onclick="editOrder(<?php echo htmlspecialchars(json_encode($order)); ?>)">
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

        function editOrder(order) {
            // Rediriger vers la page d'édition ou ouvrir un modal
            window.location.href = `orders.php#edit-${order.id}`;
        }
    </script>
</body>
</html>

<?php
// Inclure le pied de page
require_once 'includes/footer.php';
?> 