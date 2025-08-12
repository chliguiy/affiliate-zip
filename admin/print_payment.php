<?php
session_start();
require_once 'includes/auth.php';
require_once '../config/database.php';

// Vérifier l'authentification admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit();
}

$database = new Database();
$pdo = $database->getConnection();

$affiliate_id = $_GET['id'] ?? 0;

if (!$affiliate_id) {
    echo "ID de l'affilié manquant";
    exit();
}

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
                    COUNT(oi.id) as packages,
                    GROUP_CONCAT(DISTINCT oi.product_name SEPARATOR ', ') as products
                  FROM orders o
                  LEFT JOIN order_items oi ON o.id = oi.order_id
                  WHERE o.affiliate_id = ? AND o.status = 'delivered'
                  GROUP BY o.id
                  ORDER BY o.created_at DESC";

$payments_stmt = $pdo->prepare($payments_query);
$payments_stmt->execute([$affiliate_id]);
$payments = $payments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculer les totaux
$total_amount = 0;
$total_packages = 0;

foreach ($payments as $payment) {
    $total_amount += $payment['total_amount'];
    $total_packages += $payment['packages'];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reçu de Paiement - <?= htmlspecialchars($affiliate['username']) ?></title>
    <style>
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
        
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        
        .document-title {
            font-size: 18px;
            color: #666;
            margin-top: 10px;
        }
        
        .info-section {
            margin-bottom: 30px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .info-label {
            font-weight: bold;
            color: #333;
        }
        
        .info-value {
            color: #666;
        }
        
        .summary-box {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        
        .summary-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .summary-item {
            text-align: center;
            padding: 15px;
            background: white;
            border-radius: 5px;
            border: 1px solid #e9ecef;
        }
        
        .summary-value {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
        
        .summary-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .table th,
        .table td {
            border: 1px solid #dee2e6;
            padding: 12px;
            text-align: left;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        
        .amount {
            font-weight: bold;
            color: #007bff;
        }
        
        .footer {
            margin-top: 50px;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
        
        .print-button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 20px;
        }
        
        .print-button:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="print-button" onclick="window.print()">
            <i class="fas fa-print"></i> Imprimer
        </button>
    </div>

    <div class="header">
                        <div class="company-name">SCAR AFFILIATE</div>
        <div class="document-title">Reçu de Paiement</div>
    </div>

    <div class="info-section">
        <div class="info-row">
            <span class="info-label">Affilié:</span>
            <span class="info-value"><?= htmlspecialchars($affiliate['username']) ?></span>
        </div>
        <?php if (!empty($affiliate['full_name'])): ?>
        <div class="info-row">
            <span class="info-label">Nom complet:</span>
            <span class="info-value"><?= htmlspecialchars($affiliate['full_name']) ?></span>
        </div>
        <?php endif; ?>
        <div class="info-row">
            <span class="info-label">Date de paiement:</span>
            <span class="info-value"><?= date('d/m/Y H:i') ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Référence:</span>
            <span class="info-value">PAY-<?= $affiliate_id ?>-<?= date('YmdHis') ?></span>
        </div>
    </div>

    <div class="summary-box">
        <div class="summary-title">Résumé du Paiement</div>
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-value"><?= number_format($total_amount, 0) ?> MAD</div>
                <div class="summary-label">Montant Total</div>
            </div>
            <div class="summary-item">
                <div class="summary-value"><?= $total_packages ?></div>
                <div class="summary-label">Total Colis</div>
            </div>
            <div class="summary-item">
                <div class="summary-value"><?= count($payments) ?></div>
                <div class="summary-label">Nombre de Commandes</div>
            </div>
            <div class="summary-item">
                <div class="summary-value"><?= date('d/m/Y') ?></div>
                <div class="summary-label">Date</div>
            </div>
        </div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>ID Commande</th>
                <th>Produits</th>
                <th>Montant</th>
                <th>Colis</th>
                <th>Date</th>
                <th>Raison</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($payments)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: #666;">
                        Aucun paiement trouvé
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><?= $payment['id'] ?></td>
                        <td><?= htmlspecialchars($payment['products'] ?? 'N/A') ?></td>
                        <td class="amount"><?= number_format($payment['total_amount'], 0) ?> MAD</td>
                        <td><?= $payment['packages'] ?></td>
                        <td><?= date('d/m/Y', strtotime($payment['created_at'])) ?></td>
                        <td><?= htmlspecialchars($payment['payment_reason'] ?? 'N/A') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer">
        <p>Ce document certifie que le paiement a été effectué pour les commandes livrées.</p>
        <p>Généré le <?= date('d/m/Y à H:i') ?> par le système SCAR AFFILIATE</p>
    </div>
</body>
</html> 