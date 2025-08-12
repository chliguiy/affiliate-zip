<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once 'includes/auth.php';
require_once 'includes/AdminPermissions.php';

// Vérification de l'authentification
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$database = new Database();
$conn = $database->getConnection();

$permissions = new AdminPermissions($conn, $_SESSION['admin_id']);

if (!$permissions->canManageOrders()) {
    header('Location: dashboard.php?error=access_denied');
    exit;
}

// Statuts professionnels (clé anglaise uniquement)
$status_badges = [
    'new' => 'dark',           // gris foncé
    'unconfirmed' => 'secondary', // gris
    'confirmed' => 'primary',     // bleu
    'shipping' => 'info',         // bleu clair
    'shipped' => 'info',          // bleu clair
    'delivery' => 'info',         // bleu clair
    'distributed' => 'info',      // bleu clair
    'delivered' => 'success',     // vert
    'completed' => 'success',     // vert
    'returned' => 'warning',      // orange
    'changed' => 'warning',       // orange
    'refused' => 'danger',        // rouge
    'cancelled' => 'danger',      // rouge
    'duplicate' => 'danger'       // rouge
];
$status_translations = [
    'new' => 'Nouveau',
    'unconfirmed' => 'Non confirmé',
    'confirmed' => 'Confirmé',
    'shipping' => 'En livraison',
    'delivered' => 'Livré',
    'returned' => 'Retourné',
    'refused' => 'Refusé',
    'cancelled' => 'Annulé',
    'duplicate' => 'Dupliqué',
    'changed' => 'Changé',
    'pending' => 'En attente',
    'processing' => 'En traitement'
];

// Récupération de tous les affiliés avec leurs statistiques
$stmt = $conn->query("
    SELECT 
        u.id,
        u.username,
        u.email,
        u.phone,
        u.city,
        u.status,
        u.created_at,
        COUNT(DISTINCT o.id) as total_orders,
        COALESCE(SUM(CASE WHEN o.status = 'delivered' THEN o.final_sale_price ELSE 0 END), 0) as total_sales,
        COALESCE(SUM(CASE WHEN o.status = 'delivered' THEN o.commission_amount ELSE 0 END), 0) as total_commission,
        COUNT(DISTINCT CASE WHEN o.status = 'confirmed' THEN o.id END) as confirmed_orders,
        COUNT(DISTINCT CASE WHEN o.status = 'cancelled' THEN o.id END) as cancelled_orders,
        COUNT(DISTINCT CASE WHEN o.status = 'new' THEN o.id END) as pending_orders,
        COUNT(DISTINCT CASE WHEN o.status = 'shipping' THEN o.id END) as shipping_orders,
        COUNT(DISTINCT CASE WHEN o.status = 'returned' THEN o.id END) as returned_orders,
        COUNT(DISTINCT CASE WHEN o.status = 'refused' THEN o.id END) as refused_orders,
        COUNT(DISTINCT CASE WHEN o.status = 'duplicate' THEN o.id END) as duplicate_orders,
        COUNT(DISTINCT CASE WHEN o.status = 'changed' THEN o.id END) as changed_orders,
        COUNT(DISTINCT CASE WHEN o.status = 'shipped' THEN o.id END) as shipped_orders,
        COUNT(DISTINCT CASE WHEN o.status = 'completed' THEN o.id END) as completed_orders,
        COUNT(DISTINCT CASE WHEN o.status = 'delivery' THEN o.id END) as delivery_orders,
        COUNT(DISTINCT CASE WHEN o.status = 'distributed' THEN o.id END) as distributed_orders
    FROM users u
    LEFT JOIN orders o ON o.affiliate_id = u.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE u.type = 'affiliate'
    GROUP BY u.id, u.username, u.email, u.phone, u.city, u.status, u.created_at
    ORDER BY total_sales DESC
");
$affiliates = $stmt->fetchAll();

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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Affiliés et leurs Commandes - Admin</title>
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
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4">
                <h2 class="mb-4 fw-bold">Affiliés et leurs Commandes</h2>
                <div class="row g-4">
                    <?php foreach ($affiliates as $affiliate): ?>
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
                                <!-- Liste simple et efficace des statuts de commandes -->
                                <div class="mb-3 d-flex flex-wrap gap-2 justify-content-center">
                                    <?php
                                    foreach ($status_translations as $key => $label) {
                                        if (in_array($key, ['pending', 'processing'])) continue;
                                        $count = isset($affiliate[$key . '_orders']) ? $affiliate[$key . '_orders'] : 0;
                                        $badge = $status_badges[$key] ?? 'secondary';
                                        echo '<span class="badge bg-' . $badge . ' px-3 py-2" style="font-size:1em;">' . $label . ' : <b>' . $count . '</b></span>';
                                    }
                                    ?>
                                </div>
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
                                                                        <form method="post" action="change_order_status.php" style="margin:0;">
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
        function expandAll() {
            const collapseElements = document.querySelectorAll('.collapse');
            collapseElements.forEach(element => {
                const bsCollapse = new bootstrap.Collapse(element, { show: true });
            });
        }

        function collapseAll() {
            const collapseElements = document.querySelectorAll('.collapse');
            collapseElements.forEach(element => {
                const bsCollapse = new bootstrap.Collapse(element, { hide: true });
            });
        }

        // Mettre à jour le texte du bouton lors du clic
        document.addEventListener('DOMContentLoaded', function() {
            const collapseButtons = document.querySelectorAll('.collapse-btn');
            collapseButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const target = this.getAttribute('data-bs-target');
                    const collapseElement = document.querySelector(target);
                    const icon = this.querySelector('i');
                    
                    if (collapseElement.classList.contains('show')) {
                        icon.className = 'fas fa-chevron-down';
                        this.innerHTML = '<i class="fas fa-chevron-down"></i> Voir les commandes';
                    } else {
                        icon.className = 'fas fa-chevron-up';
                        this.innerHTML = '<i class="fas fa-chevron-up"></i> Masquer les commandes';
                    }
                });
            });

            // Gestion du bouton œil (voir détails)
            document.querySelectorAll('.view-order-details').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    var orderId = this.getAttribute('data-order-id');
                    var modal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
                    var content = document.getElementById('order-details-content');
                    content.innerHTML = '<div class="text-center text-muted"><i class="fas fa-spinner fa-spin"></i> Chargement...</div>';
                    fetch('get_order_details.php?id=' + orderId)
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
        });
    </script>
</body>
</html> 