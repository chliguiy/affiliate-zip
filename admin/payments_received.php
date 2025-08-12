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

// Récupérer les paramètres de filtrage
$date_filter = $_GET['date_filter'] ?? '';
$amount_filter = $_GET['amount_filter'] ?? '';
$limit_filter = $_GET['limit_filter'] ?? '50';
$affiliate_filter = $_GET['affiliate_filter'] ?? '';
$payment_mode_filter = $_GET['payment_mode_filter'] ?? 'Tous';
$status_filter = $_GET['status_filter'] ?? 'Tous';

// Construire la requête avec filtres
$where_conditions = ["o.status = 'delivered'"];
$params = [];

// Appliquer le filtre de statut si spécifié
$status_filter_applied = false;
if (!empty($status_filter) && $status_filter !== 'Tous') {
    $status_filter_applied = true;
}

if (!empty($date_filter)) {
    $where_conditions[] = "DATE(o.created_at) = ?";
    $params[] = $date_filter;
}

if (!empty($amount_filter)) {
    $where_conditions[] = "o.affiliate_margin = ?";
    $params[] = $amount_filter;
}

if (!empty($affiliate_filter)) {
    $where_conditions[] = "o.affiliate_id = ?";
    $params[] = $affiliate_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Nouvelle requête qui combine les paiements en attente ET les paiements réglés
$query = "
    SELECT * FROM (
        -- Paiements en attente (commandes livrées non réglées)
        SELECT 
            COALESCE(u.username, 'Paiement manuel') as affiliate_name,
            COALESCE(u.id, 0) as affiliate_id,
            SUM(COALESCE(o.affiliate_margin, 0)) as total_amount,
            SUM(oi.id IS NOT NULL) as total_packages,
            MAX(o.created_at) as last_payment_date,
            'En Attente' as status,
            'pending' as status_type
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN users u ON o.affiliate_id = u.id
        WHERE $where_clause AND o.status = 'delivered'
        AND o.commission_paid_at IS NULL
        " . ($status_filter_applied && $status_filter === 'Payé' ? "AND 1=0" : "") . "
        GROUP BY u.id, u.username
        
        UNION ALL
        
        -- Paiements réglés (depuis la table affiliate_payments)
        SELECT 
            COALESCE(u.username, 'Paiement manuel') as affiliate_name,
            COALESCE(u.id, 0) as affiliate_id,
            ap.montant as total_amount,
            ap.colis as total_packages,
            ap.date_paiement as last_payment_date,
            'Payé' as status,
            'paid' as status_type
        FROM affiliate_payments ap
        LEFT JOIN users u ON ap.affiliate_id = u.id
        WHERE ap.statut = 'réglé'
        " . ($status_filter_applied && $status_filter === 'En Attente' ? "AND 1=0" : "") . "
    ) combined_payments
    ORDER BY total_amount DESC
    LIMIT ?
";

$params[] = (int)$limit_filter;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les utilisateurs affiliés pour le dropdown
$affiliates_query = "SELECT id, username, full_name FROM users WHERE type = 'affiliate' AND status = 'active' ORDER BY username";
$affiliates_stmt = $pdo->prepare($affiliates_query);
$affiliates_stmt->execute();
$affiliates = $affiliates_stmt->fetchAll(PDO::FETCH_ASSOC);

// Gestion de l'export
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    
    if ($export_type === 'pending') {
        // Exporter les paiements en attente
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=paiements_en_attente_' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
        
        // En-têtes CSV
        fputcsv($output, ['ID Affilié', 'Affilié', 'Profit Affilié', 'Total Colis', 'Dernière Date', 'Statut']);
        
        // Données des paiements en attente
        foreach ($payments as $payment) {
            if ($payment['status'] === 'En Attente') {
                fputcsv($output, [
                    $payment['affiliate_id'],
                    $payment['affiliate_name'],
                    $payment['total_amount'],
                    $payment['total_packages'],
                    $payment['last_payment_date'],
                    $payment['status']
                ]);
            }
        }
        
        fclose($output);
        exit();
        
    } elseif ($export_type === 'products') {
        // Exporter les produits
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=produits_' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
        
        // Récupérer les produits
        $products_query = "SELECT id, name, price, stock, status FROM products ORDER BY name";
        $products_stmt = $pdo->prepare($products_query);
        $products_stmt->execute();
        $products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // En-têtes CSV
        fputcsv($output, ['ID', 'Nom', 'Prix', 'Stock', 'Statut']);
        
        // Données des produits
        foreach ($products as $product) {
            fputcsv($output, [
                $product['id'],
                $product['name'],
                $product['price'],
                $product['stock'],
                $product['status']
            ]);
        }
        
        fclose($output);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiements - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #f8f9fa;
            border-right: 1px solid #dee2e6;
        }
        
        .filter-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .action-buttons {
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
            margin-left: 0.25rem;
        }
        
        .btn-export {
            background: #2563eb;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-size: 0.9rem;
            margin-right: 0.5rem;
        }
        
        .btn-export-light {
            background: #6c757d;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-size: 0.9rem;
            margin-right: 0.5rem;
        }
        
        .btn-settle {
            background: #000;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-size: 0.9rem;
            margin-right: 0.5rem;
        }
        
        .btn-add {
            background: #28a745;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        
        .amount-highlight {
            color: #2563eb;
            font-weight: bold;
        }
        
        .store-logo {
            width: 30px;
            height: 30px;
            background: #2563eb;
            border-radius: 5px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .action-link {
            color: #2563eb;
            text-decoration: none;
            margin-right: 1rem;
        }
        
        .action-link.delete {
            color: #dc3545;
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
                <h2 class="mb-4">Paiements</h2>
                
                <!-- Message informatif -->
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Nouveau :</strong> Les paiements réglés restent maintenant visibles dans la liste avec le statut "Payé". 
                    Utilisez le filtre "Statut" pour voir uniquement les paiements en attente ou payés.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>

                <!-- Filter Section -->
                <div class="filter-section">
                    <h5 class="mb-3">Filtrer les commandes avec :</h5>
                    <form method="GET" action="">
                        <div class="row">
                            <div class="col-md-2">
                                <label class="form-label">Date Paiement</label>
                                <div class="input-group">
                                    <input type="date" name="date_filter" class="form-control" value="<?= htmlspecialchars($date_filter) ?>">
                                    <span class="input-group-text">
                                        <i class="fas fa-calendar"></i>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Montant</label>
                                <input type="number" name="amount_filter" class="form-control" placeholder="Montant" value="<?= htmlspecialchars($amount_filter) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Limite</label>
                                <input type="number" name="limit_filter" class="form-control" value="<?= htmlspecialchars($limit_filter) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Affilié</label>
                                <select name="affiliate_filter" class="form-select">
                                    <option value="">Tous les affiliés</option>
                                    <?php foreach ($affiliates as $affiliate): ?>
                                        <option value="<?= $affiliate['id'] ?>" <?= $affiliate_filter === $affiliate['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($affiliate['username']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Paiement Mode</label>
                                <input type="text" name="payment_mode_filter" class="form-control" value="<?= htmlspecialchars($payment_mode_filter) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Statut</label>
                                <select name="status_filter" class="form-select">
                                    <option value="">Tous</option>
                                    <option value="En Attente" <?= $status_filter === 'En Attente' ? 'selected' : '' ?>>En Attente</option>
                                    <option value="Payé" <?= $status_filter === 'Payé' ? 'selected' : '' ?>>Payé</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-2"></i>
                                    Filtrer
                                </button>
                                <a href="payments_received.php" class="btn btn-secondary">
                                    <i class="fas fa-list me-2"></i>
                                    Liste
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <h5 class="mb-3">Les paiements</h5>
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note importante :</strong> Seules les commandes avec le statut "Livré" (delivered) sont prises en compte pour le calcul des paiements des affiliés.
                    </div>
                    <button class="btn-export" onclick="exportPendingPayments()">
                        <i class="fas fa-file-excel me-2"></i>
                        Exporter paiements en attente via Excel
                    </button>
                    <button class="btn-export-light" onclick="exportProducts()">
                        <i class="fas fa-file-excel me-2"></i>
                        Exporter les produits via Excel
                    </button>
                    <button class="btn-settle" onclick="settleAllPayments()">
                        <i class="fas fa-check me-2"></i>
                        Réglé tous les paiements
                    </button>
                    <button class="btn-add" onclick="addPayment()">
                        <i class="fas fa-plus me-2"></i>
                        + Paiement
                    </button>
                    <a href="ensure_affiliate_payments_table.php" class="btn btn-warning" target="_blank">
                        <i class="fas fa-database me-2"></i>
                        Vérifier Base
                    </a>
                    <a href="test_payment_system.php" class="btn btn-info" target="_blank">
                        <i class="fas fa-vial me-2"></i>
                        Test Système
                    </a>
                    <a href="test_new_payment_behavior.php" class="btn btn-success" target="_blank">
                        <i class="fas fa-check-circle me-2"></i>
                        Test Comportement
                    </a>
                </div>

                <!-- Payments Table -->
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID Affilié</th>
                                <th>Affilié</th>
                                <th>Profit Affilié</th>
                                <th>Total Colis</th>
                                <th>Dernière Date</th>
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
                                        <td><?= $payment['affiliate_id'] ?></td>
                                        <td>
                                            <div class="store-logo me-2"><?= strtoupper(substr(htmlspecialchars($payment['affiliate_name']), 0, 3)) ?></div>
                                            <?= htmlspecialchars($payment['affiliate_name']) ?>
                                        </td>
                                        <td class="amount-highlight"><?= number_format($payment['total_amount'], 0) ?> MAD (Profit)</td>
                                        <td><?= $payment['total_packages'] ?></td>
                                        <td><?= date('Y-m-d', strtotime($payment['last_payment_date'])) ?></td>
                                        <td>
                                            <span class="status-badge <?= $payment['status'] === 'En Attente' ? 'status-pending' : 'status-paid' ?>">
                                                <?= $payment['status'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="action-btn" title="Imprimer" onclick="printPayment(<?= $payment['affiliate_id'] ?>)">
                                                <i class="fas fa-print"></i>
                                            </button>
                                            <button class="action-btn" title="Voir" onclick="viewPaymentModal(<?= $payment['affiliate_id'] ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="action-btn" title="Modifier" onclick="editPaymentModal(<?= $payment['affiliate_id'] ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($payment['status'] === 'En Attente'): ?>
                                                <a href="#" class="action-link" onclick="settlePayment(<?= $payment['affiliate_id'] ?>)">Regler</a>
                                            <?php else: ?>
                                                <span class="text-muted">Déjà réglé</span>
                                            <?php endif; ?>
                                            <a href="#" class="action-link delete" onclick="deletePayment(<?= $payment['affiliate_id'] ?>)">Supprimer</a>
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

    <!-- Modal pour voir les détails du paiement -->
    <div class="modal fade" id="viewPaymentModal" tabindex="-1" aria-labelledby="viewPaymentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewPaymentModalLabel">
                        <i class="fas fa-eye me-2"></i>
                        Détails du Paiement
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="viewPaymentModalBody">
                    <!-- Le contenu sera chargé dynamiquement -->
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    <button type="button" class="btn btn-primary" onclick="printPaymentFromModal()">
                        <i class="fas fa-print me-2"></i>
                        Imprimer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal pour modifier le paiement -->
    <div class="modal fade" id="editPaymentModal" tabindex="-1" aria-labelledby="editPaymentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editPaymentModalLabel">
                        <i class="fas fa-edit me-2"></i>
                        Modifier le Paiement
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="editPaymentModalBody">
                    <!-- Le contenu sera chargé dynamiquement -->
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-success" onclick="savePaymentChanges()">
                        <i class="fas fa-save me-2"></i>
                        Enregistrer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal pour ajouter un paiement -->
    <div class="modal fade" id="addPaymentModal" tabindex="-1" aria-labelledby="addPaymentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addPaymentModalLabel">
                        <i class="fas fa-plus me-2"></i>
                        Ajouter un Paiement
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addPaymentForm" method="POST" action="add_payment_ajax.php">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="amount" class="form-label">
                                    <i class="fas fa-money-bill me-2"></i>
                                    Montant (MAD)
                                </label>
                                <input type="number" 
                                       class="form-control" 
                                       id="amount" 
                                       name="amount" 
                                       step="0.01" 
                                       min="0.01" 
                                       required 
                                       placeholder="Entrez le montant">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="payment_date" class="form-label">
                                    <i class="fas fa-calendar me-2"></i>
                                    Date de Paiement
                                </label>
                                <input type="date" 
                                       class="form-control" 
                                       id="payment_date" 
                                       name="payment_date" 
                                       value="<?= date('Y-m-d') ?>" 
                                       required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="affiliate_id" class="form-label">
                                    <i class="fas fa-user me-2"></i>
                                    Affilié
                                </label>
                                <select class="form-select" id="affiliate_id" name="affiliate_id" required>
                                    <option value="">Sélectionnez un affilié</option>
                                    <?php foreach ($affiliates as $affiliate): ?>
                                        <option value="<?= $affiliate['id'] ?>">
                                            <?= htmlspecialchars($affiliate['username']) ?> 
                                            <?= !empty($affiliate['full_name']) ? '(' . htmlspecialchars($affiliate['full_name']) . ')' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="reason" class="form-label">
                                    <i class="fas fa-comment me-2"></i>
                                    Raison du Paiement
                                </label>
                                <textarea class="form-control" 
                                          id="reason" 
                                          name="reason" 
                                          rows="3" 
                                          required 
                                          placeholder="Entrez la raison du paiement"></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-success" onclick="submitAddPayment()">
                        <i class="fas fa-save me-2"></i>
                        Ajouter le Paiement
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fonctionnalités de la page des paiements
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Payments page loaded');
        });

        // Fonction pour exporter les paiements en attente
        function exportPendingPayments() {
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('export', 'pending');
            window.location.href = currentUrl.toString();
        }

        // Fonction pour exporter les produits
        function exportProducts() {
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('export', 'products');
            window.location.href = currentUrl.toString();
        }

        // Fonction pour régler tous les paiements
        function settleAllPayments() {
            if (confirm('Êtes-vous sûr de vouloir régler tous les paiements en attente ?\n\nTous les paiements passeront de "En Attente" à "Payé" et resteront visibles dans la liste.')) {
                fetch('settle_all_payments.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ ' + data.message + '\n\nTous les paiements sont maintenant marqués comme "Payé" et restent visibles dans la liste.');
                        location.reload();
                    } else {
                        alert('❌ Erreur lors du règlement des paiements : ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('❌ Erreur lors du règlement des paiements');
                });
            }
        }

        // Fonction pour ajouter un paiement
        function addPayment() {
            const modal = new bootstrap.Modal(document.getElementById('addPaymentModal'));
            modal.show();
        }

        // Fonction pour imprimer un paiement
        function printPayment(paymentId) {
            window.open(`print_payment.php?id=${paymentId}`, '_blank');
        }

        // Fonction pour voir un paiement dans un modal
        function viewPaymentModal(paymentId) {
            const modal = new bootstrap.Modal(document.getElementById('viewPaymentModal'));
            modal.show();
            
            // Charger les détails du paiement
            fetch(`get_payment_details.php?id=${paymentId}`)
                .then(response => response.text())
                .then(html => {
                    const modalBody = document.getElementById('viewPaymentModalBody');
                    modalBody.innerHTML = html;
                    modalBody.setAttribute('data-payment-id', paymentId);
                })
                .catch(error => {
                    document.getElementById('viewPaymentModalBody').innerHTML = 
                        '<div class="alert alert-danger">Erreur lors du chargement des détails</div>';
                });
        }

        // Fonction pour modifier un paiement dans un modal
        function editPaymentModal(paymentId) {
            const modal = new bootstrap.Modal(document.getElementById('editPaymentModal'));
            modal.show();
            
            // Charger le formulaire d'édition
            fetch(`get_payment_form.php?id=${paymentId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('editPaymentModalBody').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('editPaymentModalBody').innerHTML = 
                        '<div class="alert alert-danger">Erreur lors du chargement du formulaire</div>';
                });
        }

        // Fonction pour sauvegarder les modifications
        function savePaymentChanges() {
            const form = document.getElementById('editPaymentForm');
            const formData = new FormData(form);
            
            fetch('update_payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Paiement modifié avec succès !');
                    location.reload();
                } else {
                    alert('Erreur lors de la modification : ' + data.message);
                }
            })
            .catch(error => {
                alert('Erreur lors de la modification');
            });
        }

        // Fonction pour imprimer depuis le modal
        function printPaymentFromModal() {
            // Récupérer l'ID du paiement depuis l'URL de la requête AJAX
            const modalBody = document.getElementById('viewPaymentModalBody');
            const paymentId = modalBody.getAttribute('data-payment-id');
            if (paymentId) {
                printPayment(paymentId);
            } else {
                alert('Erreur: ID du paiement non trouvé');
            }
        }

        // Fonction pour régler un paiement
        function settlePayment(paymentId) {
            if (confirm('Êtes-vous sûr de vouloir régler ce paiement ?\n\nLe statut passera de "En Attente" à "Payé" et le paiement restera visible dans la liste.')) {
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
                        alert('✅ Paiement réglé avec succès !\n\nLe paiement est maintenant marqué comme "Payé" et reste visible dans la liste.');
                        location.reload();
                    } else {
                        alert('❌ Erreur lors du règlement : ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('❌ Erreur lors du règlement du paiement');
                });
            }
        }

        // Fonction pour supprimer un paiement
        function deletePayment(paymentId) {
            if (confirm('Êtes-vous sûr de vouloir supprimer ce paiement ?')) {
                fetch('delete_payment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ payment_id: paymentId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Paiement supprimé avec succès !');
                        location.reload();
                    } else {
                        alert('Erreur lors de la suppression : ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Erreur lors de la suppression du paiement');
                });
            }
        }

        // Fonction pour soumettre l'ajout de paiement
        function submitAddPayment() {
            const form = document.getElementById('addPaymentForm');
            const formData = new FormData(form);
            
            // Validation côté client
            const amount = parseFloat(formData.get('amount'));
            const reason = formData.get('reason').trim();
            const affiliateId = formData.get('affiliate_id');
            
            if (isNaN(amount) || amount <= 0) {
                alert('Le montant doit être un nombre positif');
                document.getElementById('amount').focus();
                return;
            }
            
            if (reason.length === 0) {
                alert('La raison du paiement est obligatoire');
                document.getElementById('reason').focus();
                return;
            }
            
            if (!affiliateId) {
                alert('Veuillez sélectionner un affilié');
                document.getElementById('affiliate_id').focus();
                return;
            }
            
            // Soumettre le formulaire
            fetch('add_payment_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Paiement ajouté avec succès !');
                    location.reload();
                } else {
                    alert('Erreur lors de l\'ajout : ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Erreur lors de l\'ajout du paiement');
            });
        }
    </script>
</body>
</html> 