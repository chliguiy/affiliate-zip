<?php
// Dashboard avancé d'un confirmateur
session_start();
$is_admin = isset($_SESSION['admin_id']);
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('ID de confirmateur invalide.');
}
$confirmateur_id = (int)$_GET['id'];

require_once '../config/database.php';
$database = new Database();
$pdo = $database->getConnection();

// Régler un paiement (statut -> paye)
if (isset($_POST['regler_paiement']) && isset($_POST['paiement_id'])) {
    $paiement_id = (int)$_POST['paiement_id'];
    $stmt = $pdo->prepare('UPDATE confirmateur_paiements SET statut = "paye" WHERE id = ? AND confirmateur_id = ?');
    $stmt->execute([$paiement_id, $confirmateur_id]);
    header('Location: confirmateur_dashboard.php?id=' . $confirmateur_id);
    exit;
}

// Traitement du paiement manuel (admin)
if ($is_admin && isset($_POST['effectuer_paiement'], $_POST['montant_paiement'])) {
    $montant = floatval($_POST['montant_paiement']);
    if ($montant > 0) {
        $stmt = $pdo->prepare("INSERT INTO confirmateur_paiements (confirmateur_id, montant, statut, date_paiement) VALUES (?, ?, 'paye', NOW())");
        if ($stmt->execute([$confirmateur_id, $montant])) {
            $date_paiement = date('Y-m-d H:i:s');
            $paiement_message = '<div class="alert alert-success mt-3">Paiement de ' . number_format($montant, 2) . ' DH effectué avec succès.</div>';
            $paiement_recap = [
                'montant' => $montant,
                'date' => $date_paiement,
                'confirmateur' => $confirmateur['nom'],
            ];
        } else {
            $paiement_message = '<div class="alert alert-danger mt-3">Erreur lors de l\'enregistrement du paiement.</div>';
        }
    } else {
        $paiement_message = '<div class="alert alert-warning mt-3">Le montant doit être supérieur à 0 DH.</div>';
    }
}

// Infos du confirmateur
$stmt = $pdo->prepare('SELECT * FROM equipe WHERE id = ? AND role = "confirmateur"');
$stmt->execute([$confirmateur_id]);
$confirmateur = $stmt->fetch();
if (!$confirmateur) {
    die('Confirmateur introuvable.');
}

// Récupérer les emails des clients assignés à ce confirmateur
$stmt = $pdo->prepare("SELECT u.email FROM confirmateur_clients cc JOIN users u ON cc.client_id = u.id WHERE cc.confirmateur_id = ? AND cc.status = 'active'");
$stmt->execute([$confirmateur_id]);
$client_emails = $stmt->fetchAll(PDO::FETCH_COLUMN);

$dernieres_commandes = [];
if (count($client_emails) > 0) {
    $in = str_repeat('?,', count($client_emails) - 1) . '?';
    $sql = "SELECT * FROM orders WHERE customer_email IN ($in) ORDER BY created_at DESC LIMIT 10";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($client_emails);
    $dernieres_commandes = $stmt->fetchAll();
}

// Nouvelle logique :
$stats = [
    'livrees' => 0, // Commandes livrées ET confirmées par ce confirmateur
    'confirmees' => 0, // Commandes confirmées par ce confirmateur (tous statuts)
    'total' => 0, // Total des commandes confirmées par ce confirmateur
    'non_livrees' => 0, // Initialisé à 0
    'non_confirmees' => 0, // Initialisé à 0
];
// Commandes confirmées ET livrées par ce confirmateur
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE confirmateur_id = ? AND status = 'delivered'");
$stmt->execute([$confirmateur_id]);
$stats['livrees'] = (int)$stmt->fetchColumn();
// Commandes confirmées par ce confirmateur (tous statuts)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE confirmateur_id = ?");
$stmt->execute([$confirmateur_id]);
$stats['confirmees'] = (int)$stmt->fetchColumn();
$stats['total'] = $stats['confirmees'];

// Gain = 8dh * commandes livrées ET confirmées par ce confirmateur
$gain_total = $stats['livrees'] * 8;
$stmt = $pdo->prepare("SELECT SUM(montant) FROM confirmateur_paiements WHERE confirmateur_id = ? AND statut = 'paye'");
$stmt->execute([$confirmateur_id]);
$total_paye = (float)$stmt->fetchColumn();
if (!$total_paye) $total_paye = 0;
$non_paye = $gain_total - $total_paye;
if ($non_paye < 0) $non_paye = 0;

// Historique paiements
$stmt = $pdo->prepare("SELECT * FROM confirmateur_paiements WHERE confirmateur_id = ? ORDER BY date_paiement DESC");
$stmt->execute([$confirmateur_id]);
$hist_paiements = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Confirmateur - <?php echo htmlspecialchars($confirmateur['nom']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e3e9f7 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', 'Roboto', Arial, sans-serif;
        }
        .dashboard-header {
            background: linear-gradient(90deg, #007bff 0%, #28a745 100%);
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
        .card {
            border-radius: 18px !important;
            box-shadow: 0 2px 16px rgba(0,0,0,0.07);
            border: none;
        }
        .card-header {
            border-radius: 18px 18px 0 0 !important;
            font-weight: 600;
            font-size: 1.1rem;
            letter-spacing: 0.5px;
        }
        .card-body {
            font-size: 1.05rem;
        }
        .stat-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 18px 0 10px 0;
        }
        .stat-card i {
            font-size: 2rem;
            opacity: 0.7;
        }
        .progress {
            background: #e9ecef;
            border-radius: 16px;
            height: 32px;
        }
        .progress-bar {
            font-size: 1.2rem;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: width 0.6s;
        }
        .table {
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .table thead {
            background: #f1f5fa;
            font-weight: 600;
        }
        .table tbody tr:hover {
            background: #f6fafd;
        }
        .btn-primary, .btn-success, .btn-info {
            border-radius: 8px;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(0,123,255,0.08);
            transition: background 0.2s, box-shadow 0.2s;
        }
        .btn-primary:hover, .btn-success:hover, .btn-info:hover {
            box-shadow: 0 4px 16px rgba(0,123,255,0.15);
            filter: brightness(1.08);
        }
        .badge {
            font-size: 1rem;
            border-radius: 8px;
            padding: 0.5em 0.8em;
        }
        @media (max-width: 991px) {
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                padding: 24px 12px 16px 12px;
            }
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        <div class="col-md-9 col-lg-10 p-4">
            <div class="dashboard-header mb-4">
                <i class="fas fa-user-tie"></i>
                <div>
                    <h2 class="mb-1">Dashboard de <?php echo htmlspecialchars($confirmateur['nom']); ?></h2>
                    <div style="font-size:1.1rem;opacity:0.8;">Bienvenue sur votre espace confirmateur</div>
                </div>
            </div>
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header bg-primary text-white">Informations personnelles</div>
                        <div class="card-body">
                            <p><strong>Nom :</strong> <?php echo htmlspecialchars($confirmateur['nom']); ?></p>
                            <p><strong>Email :</strong> <?php echo htmlspecialchars($confirmateur['email']); ?></p>
                            <p><strong>Téléphone :</strong> <?php echo htmlspecialchars($confirmateur['telephone']); ?></p>
                            <p><strong>Adresse :</strong> <?php echo htmlspecialchars($confirmateur['adresse']); ?></p>
                            <p><strong>RIB :</strong> <?php echo htmlspecialchars($confirmateur['rib']); ?></p>
                            <p><strong>Date d'ajout :</strong> <?php echo htmlspecialchars($confirmateur['date_ajout']); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="card text-bg-success mb-2">
                                <div class="card-body">
                                    <h6 class="card-title">Commandes livrées</h6>
                                    <p class="card-text fs-4"><?php echo $stats['livrees']; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card text-bg-warning mb-2">
                                <div class="card-body">
                                    <h6 class="card-title">Commandes non livrées</h6>
                                    <p class="card-text fs-4"><?php echo $stats['non_livrees']; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card text-bg-primary mb-2">
                                <div class="card-body">
                                    <h6 class="card-title">Commandes confirmées</h6>
                                    <p class="card-text fs-4"><?php echo $stats['confirmees']; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card text-bg-danger mb-2">
                                <div class="card-body">
                                    <h6 class="card-title">Commandes non confirmées</h6>
                                    <p class="card-text fs-4"><?php echo $stats['non_confirmees']; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card text-bg-info mb-2">
                                <div class="card-body">
                                    <h6 class="card-title">Montant total gagné</h6>
                                    <p class="card-text fs-4"><?php echo $gain_total; ?> DH</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card text-bg-secondary mb-2">
                                <div class="card-body">
                                    <h6 class="card-title">Montant non payé</h6>
                                    <p class="card-text fs-5"><?php echo $non_paye; ?> DH</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card text-bg-success mb-2">
                                <div class="card-body">
                                    <h6 class="card-title">Montant total payé</h6>
                                    <p class="card-text fs-5"><?php echo $total_paye; ?> DH</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card mt-4 mb-4">
                <div class="card-header bg-info text-white d-flex align-items-center">
                    <i class="fas fa-users me-2"></i>
                    <span>Clients assignés</span>
                </div>
                <div class="card-body bg-light">
                    <div class="row g-3">
                        <?php
                        $stmt = $pdo->prepare('SELECT u.id, u.username, u.email FROM confirmateur_clients cc JOIN users u ON cc.client_id = u.id WHERE cc.confirmateur_id = ? AND cc.status = "active" AND u.type = "affiliate"');
                        $stmt->execute([$confirmateur_id]);
                        $assigned = $stmt->fetchAll();
                        if (count($assigned) === 0) {
                            echo '<div class="col-12 text-muted">Aucun affilié assigné.</div>';
                        } else {
                            foreach ($assigned as $a) {
                                echo '<div class="col-12 col-md-6 col-lg-4">'
                                    . '<div class="p-3 bg-white rounded shadow-sm d-flex flex-column h-100">'
                                    . '<span class="fw-bold mb-1">' . htmlspecialchars($a['username']) . '</span>'
                                    . '<span class="text-secondary small">' . htmlspecialchars($a['email']) . '</span>'
                                    . '</div>'
                                    . '</div>';
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>
            <!-- Progression commandes livrées sur confirmées -->
            <?php
                $progress = 0;
                if ($stats['confirmees'] > 0) {
                    $progress = round(($stats['livrees'] / $stats['confirmees']) * 100);
                }
            ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-gradient text-dark fw-bold" style="background: linear-gradient(90deg, #007bff 0%, #28a745 100%); color: #fff;">Progression des commandes livrées</div>
                        <div class="card-body">
                            <div class="progress" style="height: 32px; background: #e9ecef; border-radius: 16px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: <?php echo $progress; ?>%; font-size: 1.2rem; border-radius: 16px; display: flex; align-items: center; justify-content: center; transition: width 0.6s;">
                                    <?php echo $progress; ?>%
                                </div>
                            </div>
                            <div class="mt-2 text-end text-muted" style="font-size: 0.95rem;">
                                <i class="fas fa-info-circle"></i> <?php echo $stats['livrees']; ?> livrées sur <?php echo $stats['confirmees']; ?> confirmées
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-info text-white">Dernières commandes traitées</div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Date</th>
                                            <th>Client</th>
                                            <th>Statut</th>
                                            <th>Montant</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($dernieres_commandes as $cmd): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($cmd['id']); ?></td>
                                            <td><?php echo htmlspecialchars($cmd['created_at']); ?></td>
                                            <td><?php echo htmlspecialchars($cmd['customer_name'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($cmd['status']); ?></td>
                                            <td><?php echo htmlspecialchars($cmd['total_amount'] ?? ''); ?> DH</td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (count($dernieres_commandes) === 0): ?>
                                        <tr><td colspan="5" class="text-center">Aucune commande trouvée.</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-success text-white">Historique des paiements</div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Montant</th>
                                            <th>Statut</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($hist_paiements as $p): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($p['date_paiement']); ?></td>
                                            <td><?php echo htmlspecialchars($p['montant']); ?> DH</td>
                                            <td>
                                                <?php if ($p['statut'] === 'paye'): ?>
                                                    <span class="badge bg-success">Payé</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">En attente</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($p['statut'] === 'en_attente'): ?>
                                                    <form method="post" style="display:inline;">
                                                        <input type="hidden" name="paiement_id" value="<?php echo $p['id']; ?>">
                                                        <button type="submit" name="regler_paiement" class="btn btn-sm btn-primary">Régler</button>
                                                    </form>
                                                <?php else: ?>
                                                    <i class="fas fa-check text-success"></i>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (count($hist_paiements) === 0): ?>
                                        <tr><td colspan="4" class="text-center">Aucun paiement trouvé.</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if ($is_admin): ?>
                <div class="col-md-4">
                    <div class="card border-primary h-100">
                        <div class="card-header bg-primary text-white">
                            <i class="fas fa-money-check-alt me-2"></i>Effectuer le paiement du montant non payé
                        </div>
                        <div class="card-body d-flex flex-column justify-content-center">
                            <form method="post">
                                <input type="hidden" name="montant_paiement" value="<?php echo $non_paye; ?>">
                                <button type="submit" name="effectuer_paiement" class="btn btn-success" <?php echo ($non_paye <= 0) ? 'disabled' : ''; ?>>
                                    <i class="fas fa-check-circle me-1"></i>Payer <?php echo number_format($non_paye, 2); ?> DH
                                </button>
                            </form>
                            <?php if (isset($paiement_message)) echo $paiement_message; ?>
                            <?php if (isset($paiement_recap)): ?>
                            <div class="alert alert-info mt-2">
                                <strong>Récapitulatif du paiement :</strong><br>
                                Montant : <b><?php echo number_format($paiement_recap['montant'], 2); ?> DH</b><br>
                                Date : <b><?php echo htmlspecialchars($paiement_recap['date']); ?></b><br>
                                Confirmateur : <b><?php echo htmlspecialchars($paiement_recap['confirmateur']); ?></b>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 