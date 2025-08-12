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

// Récupérer les factures depuis la base de données
$query = "SELECT 
            o.id,
            o.order_number as reference,
            o.created_at as payment_date,
            COUNT(oi.id) as packages,
            SUM(oi.price * oi.quantity) as total,
            o.status
          FROM orders o
          LEFT JOIN order_items oi ON o.id = oi.order_id
          WHERE o.status IN ('confirmed', 'delivered', 'in_delivery')
          GROUP BY o.id
          ORDER BY o.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute();
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factures de Livraison - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #f8f9fa;
            border-right: 1px solid #dee2e6;
        }
        
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background: #f8f9fa;
            border: none;
            padding: 1rem;
            font-weight: 600;
            color: #495057;
        }
        
        .table td {
            padding: 1rem;
            border: none;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }
        
        .btn-confirmed {
            background: #28a745;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        
        .btn-confirmed:hover {
            background: #218838;
            color: white;
        }
        
        .action-btn {
            background: #2563eb;
            color: white;
            border: none;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-left: 0.5rem;
        }
        
        .total-highlight {
            color: #2563eb;
            font-weight: bold;
        }
        
        .reference {
            font-family: monospace;
            font-size: 0.9rem;
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
                <h2 class="mb-4">Facture Livraison</h2>
                
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Référence</th>
                                <th>Date de paiement</th>
                                <th>Colis</th>
                                <th>Total</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($invoices)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4">
                                        <i class="fas fa-inbox fs-1 text-muted mb-3"></i>
                                        <p class="text-muted">Aucune facture trouvée</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($invoices as $invoice): ?>
                                    <tr>
                                        <td>
                                            <span class="reference"><?= htmlspecialchars($invoice['reference']) ?></span>
                                        </td>
                                        <td><?= date('Y-m-d H:i', strtotime($invoice['payment_date'])) ?></td>
                                        <td><?= $invoice['packages'] ?></td>
                                        <td class="total-highlight"><?= number_format($invoice['total'], 2) ?> DH</td>
                                        <td>
                                            <button class="btn-confirmed">
                                                Confirmé
                                            </button>
                                            <button class="action-btn" title="Voir les détails">
                                                <i class="fas fa-eye"></i>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 