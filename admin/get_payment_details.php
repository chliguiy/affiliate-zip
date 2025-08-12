<?php
session_start();
require_once 'includes/auth.php';
require_once '../config/database.php';

// Vérifier l'authentification admin
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo 'Non autorisé';
    exit();
}

$database = new Database();
$pdo = $database->getConnection();

$affiliate_id = $_GET['id'] ?? 0;

if (!$affiliate_id) {
    echo '<div class="alert alert-danger">ID de l\'affilié manquant</div>';
    exit();
}

// Récupérer les informations de l'affilié
$affiliate_query = "SELECT username, full_name FROM users WHERE id = ?";
$affiliate_stmt = $pdo->prepare($affiliate_query);
$affiliate_stmt->execute([$affiliate_id]);
$affiliate = $affiliate_stmt->fetch(PDO::FETCH_ASSOC);

if (!$affiliate) {
    echo '<div class="alert alert-danger">Affilié non trouvé</div>';
    exit();
}

// Récupérer tous les paiements de cet affilié
$payments_query = "SELECT 
                    o.id,
                    o.affiliate_margin as total_amount,
                    o.created_at,
                    o.status,
                    o.payment_reason,
                    COUNT(oi.id) as packages,
                    GROUP_CONCAT(DISTINCT oi.product_name SEPARATOR ', ') as products
                  FROM orders o
                  LEFT JOIN order_items oi ON o.id = oi.order_id
                  WHERE o.affiliate_id = ? AND o.status IN ('delivered', 'paid', 'confirmed', 'new', 'unconfirmed')
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

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h4 class="mb-3">
                <i class="fas fa-user me-2"></i>
                Détails des Paiements - <?= htmlspecialchars($affiliate['username']) ?>
                <?= !empty($affiliate['full_name']) ? '(' . htmlspecialchars($affiliate['full_name']) . ')' : '' ?>
            </h4>
            
            <!-- Résumé -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h5><?= number_format($total_amount, 0) ?> MAD</h5>
                            <small>Profit Affilié</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h5><?= $total_packages ?></h5>
                            <small>Total Colis</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h5><?= count($payments) ?></h5>
                            <small>Nombre de Commandes</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body text-center">
                            <h5><?= $pending_count ?></h5>
                            <small>En Attente</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tableau des paiements -->
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID Commande</th>
                            <th>Produits</th>
                            <th>Profit</th>
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
                                    <td colspan="8" class="text-center py-4">
                                        <i class="fas fa-inbox fs-1 text-muted mb-3"></i>
                                        <p class="text-muted">Aucun paiement trouvé</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?= $payment['id'] ?></td>
                                        <td>
                                            <small class="text-muted">
                                                <?= htmlspecialchars($payment['products'] ?? 'N/A') ?>
                                            </small>
                                        </td>
                                        <td class="fw-bold text-primary"><?= number_format($payment['total_amount'], 0) ?> MAD (Profit)</td>
                                        <td><?= $payment['packages'] ?></td>
                                        <td><?= date('Y-m-d', strtotime($payment['created_at'])) ?></td>
                                        <td><?= htmlspecialchars($payment['payment_reason'] ?? 'N/A') ?></td>
                                        <td>
                                            <span class="badge <?= in_array($payment['status'], ['confirmed', 'delivered', 'new', 'unconfirmed']) ? 'bg-warning' : 'bg-success' ?>">
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
    </div>
</div> 