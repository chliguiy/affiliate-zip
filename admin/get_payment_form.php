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
                    o.total_amount,
                    o.created_at,
                    o.status,
                    o.payment_reason,
                    GROUP_CONCAT(DISTINCT oi.product_name SEPARATOR ', ') as products
                  FROM orders o
                  LEFT JOIN order_items oi ON o.id = oi.order_id
                  WHERE o.affiliate_id = ? AND o.status = 'delivered'
                  GROUP BY o.id
                  ORDER BY o.created_at DESC";

$payments_stmt = $pdo->prepare($payments_query);
$payments_stmt->execute([$affiliate_id]);
$payments = $payments_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h4 class="mb-3">
                <i class="fas fa-edit me-2"></i>
                Modifier les Paiements - <?= htmlspecialchars($affiliate['username']) ?>
                <?= !empty($affiliate['full_name']) ? '(' . htmlspecialchars($affiliate['full_name']) . ')' : '' ?>
            </h4>
            
            <form id="editPaymentForm" method="POST" action="update_payment.php">
                <input type="hidden" name="affiliate_id" value="<?= $affiliate_id ?>">
                
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                                                    <tr>
                            <th>ID Commande</th>
                            <th>Produits</th>
                            <th>Montant</th>
                            <th>Date</th>
                            <th>Raison</th>
                            <th>Nouveau Montant</th>
                            <th>Nouvelle Raison</th>
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
                                        <td>
                                            <?= $payment['id'] ?>
                                            <input type="hidden" name="payment_ids[]" value="<?= $payment['id'] ?>">
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?= htmlspecialchars($payment['products'] ?? 'N/A') ?>
                                            </small>
                                        </td>
                                        <td class="fw-bold text-primary"><?= number_format($payment['total_amount'], 0) ?> MAD</td>
                                        <td><?= date('Y-m-d', strtotime($payment['created_at'])) ?></td>
                                        <td><?= htmlspecialchars($payment['payment_reason'] ?? 'N/A') ?></td>
                                        <td>
                                            <input type="number" 
                                                   class="form-control form-control-sm" 
                                                   name="new_amounts[]" 
                                                   value="<?= $payment['total_amount'] ?>" 
                                                   step="0.01" 
                                                   min="0.01">
                                        </td>
                                        <td>
                                            <textarea class="form-control form-control-sm" 
                                                      name="new_reasons[]" 
                                                      rows="2"><?= htmlspecialchars($payment['payment_reason'] ?? '') ?></textarea>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if (!empty($payments)): ?>
                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>
                            Enregistrer les modifications
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div> 