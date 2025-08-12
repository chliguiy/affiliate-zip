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

// Infos du confirmateur
$stmt = $pdo->prepare('SELECT * FROM equipe WHERE id = ? AND role = "confirmateur"');
$stmt->execute([$confirmateur_id]);
$confirmateur = $stmt->fetch();
if (!$confirmateur) {
    die('Confirmateur introuvable.');
}

// 1. Récupérer les emails des clients assignés
$stmt = $pdo->prepare("SELECT u.email, u.username, u.full_name FROM confirmateur_clients cc JOIN users u ON cc.client_id = u.id WHERE cc.confirmateur_id = ? AND cc.status = 'active'");
$stmt->execute([$confirmateur_id]);
$clients = $stmt->fetchAll() ?: [];
$client_emails = array_column($clients, 'email');

$nb_nouvelles = $nb_confirmees = $nb_livrees = $nb_refusees = $nb_annulees = $nb_retournees = 0;
$all_orders = [];
if (count($client_emails) > 0) {
    $in = str_repeat('?,', count($client_emails) - 1) . '?';
    // Compteurs simples par statut
    $sql = "SELECT status, COUNT(*) as nb FROM orders WHERE customer_email IN ($in) GROUP BY status";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($client_emails);
    $stats = [];
    foreach ($stmt->fetchAll() as $row) {
        $stats[$row['status']] = $row['nb'];
    }
    $nb_nouvelles   = $stats['new'] ?? 0;
    $nb_confirmees  = $stats['confirmed'] ?? 0;
    $nb_livrees     = $stats['delivered'] ?? 0;
    $nb_refusees    = $stats['refused'] ?? 0;
    $nb_annulees    = $stats['cancelled'] ?? 0;
    $nb_retournees  = $stats['returned'] ?? 0;
    // Tableau de toutes les commandes
    $sql = "SELECT * FROM orders WHERE customer_email IN ($in) ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($client_emails);
    $all_orders = $stmt->fetchAll();
}
// Gain simple
$gain_total = $nb_livrees * 8;
$stmt = $pdo->prepare("SELECT SUM(montant) FROM confirmateur_paiements WHERE confirmateur_id = ? AND statut = 'paye'");
$stmt->execute([$confirmateur_id]);
$total_paye = (float)$stmt->fetchColumn();
if (!$total_paye) $total_paye = 0;
$non_paye = $gain_total - $total_paye;
if ($non_paye < 0) $non_paye = 0;

// 7. Dernières commandes (10)
$dernieres_commandes = array_slice($all_orders, 0, 10);

// Récupérer les commandes détaillées pour chaque client assigné (comme dans clients_orders.php)
$client_orders = [];
foreach ($clients as $client) {
    if (!isset($client['id'])) continue;
    $stmt = $pdo->prepare("
        SELECT 
            o.*, 
            COUNT(oi.id) as total_items,
            GROUP_CONCAT(CONCAT(p.name, ' (', oi.quantity, ')') SEPARATOR ', ') as products
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE o.user_id = ?
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$client['id']]);
    $client_orders[$client['id']] = $stmt->fetchAll();
}

// Correction automatique : récupérer toutes les commandes des clients assignés valides
$clients_by_id = [];
foreach ($clients as $client) {
    if (isset($client['id'])) {
        $clients_by_id[$client['id']] = $client;
    }
}

$all_orders = [];
if (count($clients_by_id) > 0) {
    $in = str_repeat('?,', count($clients_by_id) - 1) . '?';
    $sql = "SELECT o.*, u.full_name, u.username FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.user_id IN ($in) ORDER BY o.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_keys($clients_by_id));
    $all_orders = $stmt->fetchAll();
}

// Calculer le nombre de commandes 'new' (nouvelles/en attente) pour les clients assignés (même sans confirmateur_id)
$new_orders_count = 0;
if (count($client_emails) > 0) {
    $in = str_repeat('?,', count($client_emails) - 1) . '?';
    $sql = "SELECT COUNT(*) FROM orders WHERE customer_email IN ($in) AND status = 'new'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($client_emails);
    $new_orders_count = (int)$stmt->fetchColumn();
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Confirmateur</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
    .topbar-affiliate {
        background: #fff !important;
        box-shadow: 0 2px 12px rgba(44,62,80,0.10) !important;
        border-radius: 0 !important;
        margin-bottom: 1.5rem;
    }
    .topbar-affiliate .icon-btn {
        background: none !important;
        border-radius: 50% !important;
        padding: 10px !important;
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        transition: background 0.2s;
    }
    .topbar-affiliate .icon-btn:hover {
        background: #f5f8ff !important;
    }
    </style>
</head>
<body class="bg-light">
<?php include '../includes/topbar.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-end mb-3">
        <a href="clients_orders.php" class="btn btn-outline-primary">
            <i class="fas fa-users me-1"></i> Voir tous les clients & commandes
        </a>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Remplacer le nom et le rôle dans la topbar pour le confirmateur
      var profileName = document.querySelector('.topbar-affiliate .profile-name');
      var profileRole = document.querySelector('.topbar-affiliate .profile-role');
      if (profileName) profileName.textContent = <?php echo json_encode($confirmateur['nom'] ?? $confirmateur['full_name'] ?? 'Confirmateur'); ?>;
      if (profileRole) profileRole.textContent = 'confirmateur';
    });
    </script>
    <h2 class="mb-4">Bienvenue, <?php echo htmlspecialchars($confirmateur['nom']); ?> !</h2>
    <div class="row mb-4 g-3">
        <div class="col-12 col-md-2">
            <div class="card shadow-sm border-0 text-white bg-success rounded-4 h-100">
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <div class="mb-2"><i class="fas fa-check-circle fa-2x"></i></div>
                    <div class="small">Commandes livrées</div>
                    <div class="display-6 fw-bold"><?php echo $nb_livrees; ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-2">
            <div class="card shadow-sm border-0 text-white bg-primary rounded-4 h-100">
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <div class="mb-2"><i class="fas fa-check-double fa-2x"></i></div>
                    <div class="small">Confirmées</div>
                    <div class="display-6 fw-bold"><?php echo $nb_confirmees; ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-2">
            <div class="card shadow-sm border-0 text-white bg-secondary rounded-4 h-100">
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <div class="mb-2"><i class="fas fa-plus-circle fa-2x"></i></div>
                    <div class="small">Nouvelles commandes</div>
                    <div class="display-6 fw-bold"><?php echo $nb_nouvelles; ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-2">
            <div class="card shadow-sm border-0 text-white bg-danger rounded-4 h-100">
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <div class="mb-2"><i class="fas fa-times-circle fa-2x"></i></div>
                    <div class="small">Refusées</div>
                    <div class="display-6 fw-bold"><?php echo $nb_refusees; ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-2">
            <div class="card shadow-sm border-0 text-dark bg-light rounded-4 h-100">
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <div class="mb-2"><i class="fas fa-ban fa-2x"></i></div>
                    <div class="small">Annulées</div>
                    <div class="display-6 fw-bold"><?php echo $nb_annulees; ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-2">
            <div class="card shadow-sm border-0 text-dark bg-warning rounded-4 h-100">
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <div class="mb-2"><i class="fas fa-undo fa-2x"></i></div>
                    <div class="small">Retournées</div>
                    <div class="display-6 fw-bold"><?php echo $nb_retournees; ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-2">
            <div class="card shadow-sm border-0 text-dark bg-info rounded-4 h-100">
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <div class="mb-2"><i class="fas fa-plus-square fa-2x"></i></div>
                    <div class="small">Nouvelles commandes (clients assignés)</div>
                    <div class="display-6 fw-bold"><?php echo $new_orders_count; ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="card mt-3">
      <div class="card-header">Dernières commandes confirmées</div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0"><thead><tr><th>ID</th><th>Client</th><th>Date</th><th>Statut</th></tr></thead><tbody>
        <?php foreach ($dernieres_commandes as $cmd): ?>
        <tr><td><?php echo $cmd['id']; ?></td><td><?php echo htmlspecialchars($cmd['customer_name']); ?></td><td><?php echo $cmd['confirmed_at']; ?></td><td><?php echo $cmd['status']; ?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
        <?php if (count($dernieres_commandes) === 0): ?><div class="alert alert-warning m-2">Aucune commande confirmée trouvée pour ce confirmateur.<br>Si tu viens de confirmer une commande, vérifie que le champ confirmateur_id est bien renseigné dans la base.</div><?php endif; ?>
      </div>
    </div>
    <div class="card mb-4">
        <div class="card-header bg-info text-white"><i class="fas fa-users me-2"></i>Clients assignés</div>
        <div class="card-body bg-light">
            <div class="row g-3">
                <?php
                if (count($clients) === 0) {
                    echo '<div class="col-12 text-muted">Aucun client assigné.</div>';
                } else {
                    foreach ($clients as $c) {
                        // Récupérer le téléphone
                        $stmt = $pdo->prepare('SELECT phone, id FROM users WHERE email = ? LIMIT 1');
                        $stmt->execute([$c['email']]);
                        $user = $stmt->fetch();
                        $phone = $user['phone'] ?? '';
                        $user_id = $user['id'] ?? null;
                        // Nombre total de commandes
                        $stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?');
                        $stmt->execute([$user_id]);
                        $total_cmd = (int)$stmt->fetchColumn();
                        // Nombre de commandes livrées
                        $stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = "delivered"');
                        $stmt->execute([$user_id]);
                        $delivered_cmd = (int)$stmt->fetchColumn();
                        echo '<div class="col-12 col-md-6 col-lg-4">'
                            . '<div class="p-3 bg-white rounded shadow-sm d-flex flex-column h-100">'
                            . '<div class="mb-2"><i class="fas fa-user-circle fa-2x text-info me-2"></i>'
                            . '<span class="fw-bold">' . htmlspecialchars($c['full_name'] ?? $c['username']) . '</span></div>'
                            . '<span class="text-secondary small mb-1"><i class="fas fa-envelope me-1"></i>' . htmlspecialchars($c['email']) . '</span>'
                            . ($phone ? '<span class="text-secondary small mb-1"><i class="fas fa-phone me-1"></i>' . htmlspecialchars($phone) . '</span>' : '')
                            . '<span class="small mb-1">Commandes totales : <b>' . $total_cmd . '</b></span>'
                            . '<span class="small mb-2">Livrées : <b>' . $delivered_cmd . '</b></span>'
                            . '<a href="clients_orders.php?id=' . $user_id . '" class="btn btn-outline-primary btn-sm mt-auto"><i class="fas fa-eye me-1"></i>Voir commandes</a>'
                            . '</div>'
                            . '</div>';
                    }
                }
                ?>
            </div>
        </div>
    </div>
</div>
</body>
</html> 