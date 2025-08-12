<?php
session_start();
require_once 'includes/auth.php';
require_once 'config/database.php';

// Vérifier l'authentification admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit();
}

$database = new Database();
$pdo = $database->getConnection();

$affiliate_id = $_GET['affiliate_id'] ?? 0;

// Récupérer les informations de l'affilié
$affiliate_query = "SELECT username, full_name FROM users WHERE id = ?";
$affiliate_stmt = $pdo->prepare($affiliate_query);
$affiliate_stmt->execute([$affiliate_id]);
$affiliate = $affiliate_stmt->fetch(PDO::FETCH_ASSOC);

if (!$affiliate) {
    echo "Affilié non trouvé";
    exit();
}

// Récupérer tous les paiements de cet affilié
$payments_query = "SELECT 
                    o.id,
                    o.total_amount,
                    o.created_at,
                    o.status,
                    o.payment_reason,
                    COUNT(oi.id) as packages
                  FROM orders o
                  LEFT JOIN order_items oi ON o.id = oi.order_id
                  WHERE o.affiliate_id = ?
                  GROUP BY o.id
                  ORDER BY o.created_at DESC";

$payments_stmt = $pdo->prepare($payments_query);
$payments_stmt->execute([$affiliate_id]);
$payments = $payments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculer les totaux
$total_amount = 0;
$total_packages = 0;
$pending_count = 0;

foreach ($payments as $payment) {
    $total_amount += $payment['total_amount'];
    $total_packages += $payment['packages'];
    if (in_array($payment['status'], ['confirmed', 'delivered', 'new', 'unconfirmed'])) {
        $pending_count++;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails Paiements - <?= htmlspecialchars($affiliate['username']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .summary-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .amount-highlight {
            color: #2563eb;
            font-weight: bold;
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
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="fas fa-user me-2"></i>
                Détails des Paiements - <?= htmlspecialchars($affiliate['username']) ?>
                <?= !empty($affiliate['full_name']) ? '(' . htmlspecialchars($affiliate['full_name']) . ')' : '' ?>
            </h2>
            <button class="btn btn-secondary" onclick="window.close()">
                <i class="fas fa-times me-2"></i>
                Fermer
            </button>
        </div>

        <!-- Résumé -->
        <div class="summary-card">
            <div class="row">
                <div class="col-md-3">
                    <div class="text-center">
                        <h4 class="text-primary"><?= number_format($total_amount, 0) ?> MAD</h4>
                        <p class="text-muted">Montant Total</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <h4 class="text-success"><?= $total_packages ?></h4>
                        <p class="text-muted">Total Colis</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <h4 class="text-info"><?= count($payments) ?></h4>
                        <p class="text-muted">Nombre de Commandes</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <h4 class="text-warning"><?= $pending_count ?></h4>
                        <p class="text-muted">En Attente</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tableau des paiements -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Montant</th>
                        <th>Colis</th>
                        <th>Date</th>
                        <th>Raison</th>
                        <th>Statut</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <i class="fas fa-inbox fs-1 text-muted mb-3"></i>
                                <p class="text-muted">Aucun paiement trouvé</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?= $payment['id'] ?></td>
                                <td class="amount-highlight"><?= number_format($payment['total_amount'], 0) ?> MAD</td>
                                <td><?= $payment['packages'] ?></td>
                                <td><?= date('Y-m-d', strtotime($payment['created_at'])) ?></td>
                                <td><?= htmlspecialchars($payment['payment_reason'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="status-badge <?= in_array($payment['status'], ['confirmed', 'delivered', 'new', 'unconfirmed']) ? 'status-pending' : 'status-paid' ?>">
                                        <?= in_array($payment['status'], ['confirmed', 'delivered', 'new', 'unconfirmed']) ? 'En Attente' : 'Payé' ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="printPayment(<?= $payment['id'] ?>)">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    <button class="btn btn-sm btn-success" onclick="settlePayment(<?= $payment['id'] ?>)">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function printPayment(paymentId) {
            window.open(`print_payment.php?id=${paymentId}`, '_blank');
        }

        function settlePayment(paymentId) {
            if (confirm('Êtes-vous sûr de vouloir régler ce paiement ?')) {
                fetch('settle_payment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ payment_id: paymentId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Paiement réglé avec succès !');
                        location.reload();
                    } else {
                        alert('Erreur lors du règlement : ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Erreur lors du règlement du paiement');
                });
            }
        }
    </script>
</body>
</html> 