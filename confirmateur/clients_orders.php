<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['confirmateur_id'])) {
    header('Location: ../login.php');
    exit;
}
$confirmateur_id = $_SESSION['confirmateur_id'];

require_once '../config/database.php';
$database = new Database();
$conn = $database->getConnection();

// Statuts professionnels (clé anglaise uniquement)
$status_badges = [
    'new' => 'dark',
    'unconfirmed' => 'secondary',
    'confirmed' => 'primary',
    'shipping' => 'info',
    'shipped' => 'info',
    'delivery' => 'info',
    'distributed' => 'info',
    'delivered' => 'success',
    'completed' => 'success',
    'returned' => 'warning',
    'changed' => 'warning',
    'refused' => 'danger',
    'cancelled' => 'danger',
    'duplicate' => 'danger'
];
$status_translations = [
    'new' => 'Nouveau',
    'unconfirmed' => 'Non confirmé',
    'confirmed' => 'Confirmé',
    'shipping' => 'En livraison',
    'shipped' => 'Expédié',
    'delivered' => 'Livré',
    'returned' => 'Retourné',
    'refused' => 'Refusé',
    'cancelled' => 'Annulé',
    'duplicate' => 'Dupliqué',
    'changed' => 'Changé',
    'completed' => 'Terminé',
    'delivery' => 'Livraison',
    'distributed' => 'Distribué'
];

// Récupérer les affiliés (clients assignés au confirmateur)
$stmt = $conn->prepare("SELECT u.id, u.username, u.email, u.phone, u.city, u.status, u.created_at FROM confirmateur_clients cc JOIN users u ON cc.client_id = u.id WHERE cc.confirmateur_id = ? AND cc.status = 'active' AND u.type = 'affiliate'");
$stmt->execute([$confirmateur_id]);
$affiliates = $stmt->fetchAll();

// Récupérer les emails des clients assignés
$stmt = $conn->prepare("SELECT u.email FROM confirmateur_clients cc JOIN users u ON cc.client_id = u.id WHERE cc.confirmateur_id = ? AND cc.status = 'active'");
$stmt->execute([$confirmateur_id]);
$client_emails = array_column($stmt->fetchAll(), 'email');

// Récupération des commandes détaillées pour chaque affilié
$affiliate_orders = [];
foreach ($affiliates as $affiliate) {
    $stmt = $conn->prepare("
        SELECT 
            o.*, 
            COUNT(oi.id) as total_items,
            GROUP_CONCAT(CONCAT(p.name, ' (', oi.quantity, ')') SEPARATOR ', ') as products
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE o.affiliate_id = ?
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$affiliate['id']]);
    $affiliate_orders[$affiliate['id']] = $stmt->fetchAll();
}

// Récupérer toutes les commandes des clients assignés
$all_orders = [];
if (count($client_emails) > 0) {
    $in = str_repeat('?,', count($client_emails) - 1) . '?';
    $sql = "SELECT * FROM orders WHERE customer_email IN ($in) ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute($client_emails);
    $all_orders = $stmt->fetchAll();
}

// Supprimer tout ce qui concerne les compteurs/badges de statuts
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Affiliés et leurs Commandes - Confirmateur</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .affiliate-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.07);
            margin-bottom: 32px;
            transition: box-shadow 0.2s;
        }
        .affiliate-card:hover {
            box-shadow: 0 8px 24px rgba(0,0,0,0.13);
        }
        .card-header.bg-gradient-primary {
            background: linear-gradient(90deg, #2563eb 0%, #1e40af 100%);
            color: #fff;
        }
        .badge.rounded-pill {
            font-size: 0.95em;
            padding: 0.5em 1em;
        }
        .table thead th {
            background: #f1f5f9;
            font-weight: 600;
        }
        .table-hover tbody tr:hover {
            background: #f3f6fa;
        }
        .btn-outline-primary, .btn-outline-info, .btn-outline-secondary {
            border-radius: 20px;
        }
        .dropdown-menu {
            min-width: 180px;
        }
    </style>
</head>
<body>
    <?php include '../includes/topbar.php'; ?>
    <div class="container py-4">
        <div class="mb-3">
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-home me-1"></i> Retour au Dashboard
            </a>
        </div>
        <h2 class="mb-4 fw-bold">Affiliés et leurs Commandes</h2>
        <div class="row g-4">
            <?php foreach (
                $affiliates as $affiliate): ?>
            <div class="col-12">
                <div class="card affiliate-card">
                    <div class="card-header bg-gradient-primary d-flex align-items-center justify-content-between">
                        <div>
                            <i class="fas fa-user me-2"></i>
                            <span class="fw-bold"><?php echo htmlspecialchars($affiliate['username']); ?></span>
                            <span class="badge rounded-pill bg-success ms-2">Active</span>
                        </div>
                        <small><?php echo htmlspecialchars($affiliate['email']); ?><?php if ($affiliate['phone']) echo ' | ' . htmlspecialchars($affiliate['phone']); ?><?php if ($affiliate['city']) echo ' | ' . htmlspecialchars($affiliate['city']); ?></small>
                    </div>
                    <div class="card-body">
                        <?php
                        // Calcul des totaux par statut pour cet affilié
                        $status_counts = [];
                        foreach ($affiliate_orders[$affiliate['id']] as $order) {
                            $status = trim(strtolower($order['status'] ?? ''));
                            if (!isset($status_counts[$status])) $status_counts[$status] = 0;
                            $status_counts[$status]++;
                        }
                        ?>
                        <div class="mb-3">
                            <?php foreach ($status_translations as $status_key => $status_label):
                                if (!empty($status_counts[$status_key])): ?>
                                <span class="badge rounded-pill bg-<?php echo $status_badges[$status_key] ?? 'secondary'; ?> me-2 mb-1">
                                    <?php echo $status_label; ?> : <?php echo $status_counts[$status_key]; ?>
                                </span>
                            <?php endif; endforeach; ?>
                        </div>
                        <!-- Liste simple et efficace des statuts de commandes -->
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>N° Commande</th>
                                        <th>Date</th>
                                        <th>Client</th>
                                        <th>Produits</th>
                                        <th>Montant</th>
                                        <th>Commission</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($affiliate_orders[$affiliate['id']] as $order): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                        <td><small class="text-muted"><?php echo htmlspecialchars($order['products'] ?? 'N/A'); ?></small></td>
                                        <td><strong><?php echo number_format($order['final_sale_price'] ?? 0, 2); ?> MAD</strong></td>
                                        <td>
                                            <?php
                                            if (isset($order['affiliate_margin'])) {
                                                echo number_format($order['affiliate_margin'], 2);
                                            } else {
                                                $commission = ($order['final_sale_price'] ?? 0) * 0.05;
                                                echo number_format($commission, 2);
                                            }
                                            ?> MAD
                                        </td>
                                        <td>
                                            <?php
                                            $raw_status = $order['status'] ?? '';
                                            $status = trim(strtolower($raw_status));
                                            $badge = $status_badges[$status] ?? 'secondary';
                                            $label = $status_translations[$status] ?? ($status ? ucfirst($status) : 'Non défini');
                                            ?>
                                            <span class="badge rounded-pill bg-<?php echo $badge; ?>">
                                                <?php echo $label; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="#" class="btn btn-outline-primary view-order-details" data-order-id="<?php echo $order['id']; ?>" title="Voir détails">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="affiliate_details.php?id=<?php echo $affiliate['id']; ?>" class="btn btn-outline-info" title="Détails affilié">
                                                    <i class="fas fa-user"></i>
                                                </a>
                                                <div class="dropdown d-inline-block ms-1">
                                                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                        <i class="fas fa-sync-alt"></i> Statut
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <?php foreach ($status_translations as $key => $label): ?>
                                                            <li>
                                                                <form method="post" action="../admin/change_order_status.php" style="margin:0;">
                                                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                                    <button class="dropdown-item text-<?php
                                                                        if ($key === 'delivered') echo 'success';
                                                                        elseif ($key === 'shipping') echo 'warning';
                                                                        elseif ($key === 'cancelled' || $key === 'refused') echo 'danger';
                                                                        elseif ($key === 'confirmed') echo 'primary';
                                                                        else echo 'secondary';
                                                                    ?><?php if ($status === $key) echo ' fw-bold'; ?>" name="new_status" value="<?php echo $key; ?>">
                                                                        <?php if ($key === 'delivered'): ?><i class="fas fa-check-circle me-1"></i><?php endif; ?>
                                                                        <?php if ($key === 'shipping'): ?><i class="fas fa-truck me-1"></i><?php endif; ?>
                                                                        <?php if ($key === 'cancelled' || $key === 'refused'): ?><i class="fas fa-times-circle me-1"></i><?php endif; ?>
                                                                        <?php if ($key === 'confirmed'): ?><i class="fas fa-check me-1"></i><?php endif; ?>
                                                                        <?php echo $label; ?>
                                                                        <?php if ($status === $key): ?> <i class="fas fa-check ms-1"></i><?php endif; ?>
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Modal détails commande (inchangé) -->
    <div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-labelledby="orderDetailsModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="orderDetailsModalLabel">Détails de la commande</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" id="order-details-content">
            <div class="text-center text-muted"><i class="fas fa-spinner fa-spin"></i> Chargement...</div>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Gestion du bouton œil (voir détails)
        document.querySelectorAll('.view-order-details').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var orderId = this.getAttribute('data-order-id');
                var modal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
                var content = document.getElementById('order-details-content');
                content.innerHTML = '<div class="text-center text-muted"><i class="fas fa-spinner fa-spin"></i> Chargement...</div>';
                fetch('../admin/get_order_details.php?id=' + orderId)
                    .then(response => response.text())
                    .then(html => {
                        content.innerHTML = html;
                    })
                    .catch(() => {
                        content.innerHTML = '<div class="alert alert-danger">Erreur lors du chargement des détails.</div>';
                    });
                modal.show();
            });
        });
    </script>
</body>
</html> 