<?php
session_start();
if (!isset($_SESSION['confirmateur_id'])) {
    header('Location: ../login.php');
    exit();
}
$confirmateur_id = $_SESSION['confirmateur_id'];

require_once '../config/database.php';
$database = new Database();
$pdo = $database->getConnection();

// Récupérer les IDs des clients assignés
$stmt = $pdo->prepare("SELECT u.id, u.username, u.full_name FROM confirmateur_clients cc JOIN users u ON cc.client_id = u.id WHERE cc.confirmateur_id = ? AND cc.status = 'active'");
$stmt->execute([$confirmateur_id]);
$clients = $stmt->fetchAll() ?: [];
$client_ids = array_column($clients, 'id');

// Initialiser les compteurs
$nb_total = $nb_nouvelles = $nb_confirmees = $nb_livrees = $nb_refusees = $nb_annulees = $nb_retournees = 0;
if (count($client_ids) > 0) {
    $in = str_repeat('?,', count($client_ids) - 1) . '?';
    $sql = "SELECT status, COUNT(*) as nb FROM orders WHERE affiliate_id IN ($in) GROUP BY status";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($client_ids);
    $stats = [];
    foreach ($stmt->fetchAll() as $row) {
        $stats[$row['status']] = $row['nb'];
        $nb_total += $row['nb'];
    }
    $nb_nouvelles   = $stats['new'] ?? 0;
    $nb_confirmees  = $stats['confirmed'] ?? 0;
    $nb_livrees     = $stats['delivered'] ?? 0;
    $nb_refusees    = $stats['refused'] ?? 0;
    $nb_annulees    = $stats['cancelled'] ?? 0;
    $nb_retournees  = $stats['returned'] ?? 0;
}

// Calcul paiement confirmateur
$gain_total = $nb_livrees * 8;
$stmt = $pdo->prepare("SELECT SUM(montant) FROM confirmateur_paiements WHERE confirmateur_id = ? AND statut = 'paye'");
$stmt->execute([$confirmateur_id]);
$total_paye = (float)$stmt->fetchColumn();
if (!$total_paye) $total_paye = 0;
$non_paye = $gain_total - $total_paye;
if ($non_paye < 0) $non_paye = 0;
// Historique paiements
$stmt = $pdo->prepare("SELECT montant, date_paiement, statut FROM confirmateur_paiements WHERE confirmateur_id = ? ORDER BY date_paiement DESC");
$stmt->execute([$confirmateur_id]);
$hist_paiements = $stmt->fetchAll();

// Récupérer le nom du confirmateur
$stmt = $pdo->prepare('SELECT * FROM equipe WHERE id = ? AND role = "confirmateur"');
$stmt->execute([$confirmateur_id]);
$confirmateur = $stmt->fetch();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Confirmateur</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e3e9f7 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', 'Roboto', Arial, sans-serif;
        }
        .dashboard-header {
            background: linear-gradient(90deg, #2563eb 0%, #1e40af 100%);
            color: #fff;
            border-radius: 18px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            padding: 32px 32px 24px 32px;
            margin-bottom: 32px;
            display: flex;
            align-items: center;
            gap: 18px;
        }
        .dashboard-header i {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        .dashboard-header .profile {
            display: flex;
            align-items: center;
            gap: 18px;
        }
        .dashboard-header .profile-avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            color: #2563eb;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .dashboard-header .profile-info {
            display: flex;
            flex-direction: column;
        }
        .dashboard-header .profile-info .name {
            font-size: 1.5rem;
            font-weight: 700;
        }
        .dashboard-header .profile-info .role {
            font-size: 1.1rem;
            opacity: 0.85;
        }
        .stat-cards {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            margin-bottom: 32px;
        }
        .stat-card {
            flex: 1 1 180px;
            min-width: 180px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(44,62,80,0.07);
            padding: 1.5rem 1rem 1.2rem 1rem;
            text-align: center;
            transition: box-shadow 0.2s;
        }
        .stat-card:hover {
            box-shadow: 0 6px 24px rgba(44,62,80,0.13);
        }
        .stat-icon {
            font-size: 2.2rem;
            margin-bottom: 0.5rem;
            opacity: 0.85;
        }
        .stat-value {
            font-size: 2.1rem;
            font-weight: bold;
            margin-bottom: 0.2rem;
        }
        .stat-label {
            font-size: 1.1rem;
            color: #555;
        }
        .payment-section {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(44,62,80,0.07);
            padding: 2rem 1.5rem 1.5rem 1.5rem;
            margin-bottom: 32px;
        }
        .payment-section .row > div {
            margin-bottom: 1.2rem;
        }
        .payment-section .fs-4 {
            font-size: 1.5rem !important;
        }
        .payment-section .fw-bold {
            font-size: 1.1rem;
        }
        .payment-section .table {
            margin-top: 1.2rem;
            background: #f8fafc;
        }
        .main-btn {
            display: inline-block;
            margin: 0 auto 32px auto;
            font-size: 1.15rem;
            padding: 0.8rem 2.2rem;
            border-radius: 12px;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(37,99,235,0.08);
            transition: background 0.2s, box-shadow 0.2s;
        }
        .main-btn:hover {
            box-shadow: 0 4px 16px rgba(37,99,235,0.15);
            filter: brightness(1.08);
        }
        @media (max-width: 991px) {
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                padding: 24px 12px 16px 12px;
            }
            .stat-cards {
                flex-direction: column;
                gap: 14px;
            }
        }
    </style>
</head>
<body>
<?php include '../includes/topbar.php'; ?>
<div class="container py-4">
    <div class="dashboard-header mb-4">
        <div class="profile">
            <div class="profile-avatar bg-white">
                <i class="fas fa-user"></i>
            </div>
            <div class="profile-info">
                <span class="name">Bienvenue, <?php echo htmlspecialchars($confirmateur['nom'] ?? $confirmateur['full_name'] ?? 'Confirmateur'); ?> !</span>
                <span class="role">Confirmateur</span>
            </div>
        </div>
    </div>
    <div class="stat-cards">
        <div class="stat-card">
            <div class="stat-icon text-primary"><i class="fas fa-list"></i></div>
            <div class="stat-value"><?php echo $nb_total; ?></div>
            <div class="stat-label">Commandes totales</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon text-secondary"><i class="fas fa-plus-circle"></i></div>
            <div class="stat-value"><?php echo $nb_nouvelles; ?></div>
            <div class="stat-label">Nouvelles</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon text-success"><i class="fas fa-check-circle"></i></div>
            <div class="stat-value"><?php echo $nb_livrees; ?></div>
            <div class="stat-label">Livrées</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon text-primary"><i class="fas fa-check-double"></i></div>
            <div class="stat-value"><?php echo $nb_confirmees; ?></div>
            <div class="stat-label">Confirmées</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon text-danger"><i class="fas fa-times-circle"></i></div>
            <div class="stat-value"><?php echo $nb_refusees; ?></div>
            <div class="stat-label">Refusées</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon text-warning"><i class="fas fa-undo"></i></div>
            <div class="stat-value"><?php echo $nb_retournees; ?></div>
            <div class="stat-label">Retournées</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon text-dark"><i class="fas fa-ban"></i></div>
            <div class="stat-value"><?php echo $nb_annulees; ?></div>
            <div class="stat-label">Annulées</div>
        </div>
    </div>
    <a href="clients_orders.php" class="btn btn-outline-primary main-btn mb-4">
        <i class="fas fa-list"></i> Voir la liste complète des commandes
    </a>
    <div class="payment-section">
        <div class="row g-4 mb-3">
            <div class="col-md-4">
                <div class="p-3 bg-light rounded shadow-sm">
                    <div class="fw-bold">Montant total gagné</div>
                    <div class="fs-4 text-success"><?php echo number_format($gain_total, 2); ?> DH</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 bg-light rounded shadow-sm">
                    <div class="fw-bold">Montant total payé</div>
                    <div class="fs-4 text-primary"><?php echo number_format($total_paye, 2); ?> DH</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 bg-light rounded shadow-sm">
                    <div class="fw-bold">Montant non payé</div>
                    <div class="fs-4 text-danger"><?php echo number_format($non_paye, 2); ?> DH</div>
                </div>
            </div>
        </div>
        <h6 class="mt-4 mb-2">Historique des paiements</h6>
        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                    <tr><th>Montant</th><th>Date</th><th>Statut</th></tr>
                </thead>
                <tbody>
                    <?php if (count($hist_paiements) === 0): ?>
                        <tr><td colspan="3" class="text-center text-muted">Aucun paiement enregistré.</td></tr>
                    <?php else: foreach ($hist_paiements as $p): ?>
                        <tr>
                            <td><?php echo number_format($p['montant'], 2); ?> DH</td>
                            <td><?php echo htmlspecialchars($p['date_paiement']); ?></td>
                            <td><?php echo htmlspecialchars($p['statut']); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html> 