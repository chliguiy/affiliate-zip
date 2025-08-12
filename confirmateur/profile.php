<?php
// Sécurisation de la session confirmateur
if (session_status() === PHP_SESSION_NONE) {
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => $cookieParams['lifetime'],
        'path' => '/',
        'domain' => $cookieParams['domain'],
        'secure' => $cookieParams['secure'],
        'httponly' => $cookieParams['httponly'],
        'samesite' => $cookieParams['samesite'] ?? 'Lax',
    ]);
}
session_start();

if (!isset($_SESSION['confirmateur_id'])) {
    header('Location: ../login.php');
    exit();
}
$confirmateur_id = $_SESSION['confirmateur_id'];

require_once '../config/database.php';
$database = new Database();
$conn = $database->getConnection();

// Récupérer les infos du confirmateur
$stmt = $conn->prepare('SELECT * FROM equipe WHERE id = ? AND role = "confirmateur"');
$stmt->execute([$confirmateur_id]);
$confirmateur = $stmt->fetch();
if (!$confirmateur) {
    session_destroy();
    header('Location: ../login.php');
    exit();
}

// Récupérer les IDs des clients assignés
$stmt = $conn->prepare("SELECT u.id FROM confirmateur_clients cc JOIN users u ON cc.client_id = u.id WHERE cc.confirmateur_id = ? AND cc.status = 'active'");
$stmt->execute([$confirmateur_id]);
$client_ids = array_column($stmt->fetchAll(), 'id');

$nb_nouvelles = $nb_confirmees = $nb_livrees = $nb_refusees = $nb_annulees = $nb_retournees = 0;
if (count($client_ids) > 0) {
    $in = str_repeat('?,', count($client_ids) - 1) . '?';
    $sql = "SELECT status, COUNT(*) as nb FROM orders WHERE affiliate_id IN ($in) GROUP BY status";
    $stmt = $conn->prepare($sql);
    $stmt->execute($client_ids);
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
}
// Gains
$gain_total = $nb_livrees * 8;
$stmt = $conn->prepare("SELECT SUM(montant) FROM confirmateur_paiements WHERE confirmateur_id = ? AND statut = 'paye'");
$stmt->execute([$confirmateur_id]);
$total_paye = (float)$stmt->fetchColumn();
if (!$total_paye) $total_paye = 0;
$non_paye = $gain_total - $total_paye;
if ($non_paye < 0) $non_paye = 0;

// Gestion du formulaire de modification du profil
$message = '';
$message_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $nom = trim($_POST['nom']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $city = trim($_POST['city']);
    if (empty($nom) || empty($email)) {
        $message = 'Le nom et l\'email sont obligatoires.';
        $message_type = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'L\'adresse email n\'est pas valide.';
        $message_type = 'danger';
    } else {
        try {
            $stmt = $conn->prepare("UPDATE equipe SET nom = ?, email = ?, phone = ?, city = ? WHERE id = ?");
            $stmt->execute([$nom, $email, $phone, $city, $confirmateur_id]);
            $confirmateur['nom'] = $nom;
            $confirmateur['email'] = $email;
            $confirmateur['phone'] = $phone;
            $confirmateur['city'] = $city;
            $message = 'Profil mis à jour avec succès !';
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = 'Erreur lors de la mise à jour du profil.';
            $message_type = 'danger';
        }
    }
}
// Changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = 'Tous les champs sont obligatoires.';
        $message_type = 'danger';
    } elseif ($new_password !== $confirm_password) {
        $message = 'Les nouveaux mots de passe ne correspondent pas.';
        $message_type = 'danger';
    } elseif (strlen($new_password) < 6) {
        $message = 'Le nouveau mot de passe doit contenir au moins 6 caractères.';
        $message_type = 'danger';
    } elseif (!password_verify($current_password, $confirmateur['password'])) {
        $message = 'Le mot de passe actuel est incorrect.';
        $message_type = 'danger';
    } else {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE equipe SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $confirmateur_id]);
            $message = 'Mot de passe modifié avec succès !';
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = 'Erreur lors du changement de mot de passe.';
            $message_type = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - Confirmateur</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f7fafc; }
        .main-content { max-width: 900px; margin: 0 auto; padding: 2rem 0; }
        .profile-header { background: #fff; border-radius: 16px; box-shadow: 0 4px 16px rgba(0,0,0,0.07); padding: 2rem; text-align: center; margin-bottom: 2rem; }
        .profile-avatar { width: 100px; height: 100px; border-radius: 50%; background: #2563eb; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; margin: 0 auto 1rem; }
        .profile-name { font-size: 2rem; font-weight: 700; color: #22223b; margin-bottom: 0.5rem; }
        .profile-role { color: #4a5568; font-size: 1.1rem; margin-bottom: 1rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: #fff; border-radius: 12px; padding: 1.2rem; text-align: center; box-shadow: 0 2px 8px rgba(44,62,80,0.07); border-left: 4px solid #2563eb; }
        .stat-value { font-size: 1.7rem; font-weight: 700; color: #2563eb; margin-bottom: 0.5rem; }
        .stat-label { color: #718096; font-size: 0.95rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }
        .form-section { background: #fff; border-radius: 12px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 2px 8px rgba(44,62,80,0.07); }
        .form-section h3 { color: #2563eb; margin-bottom: 1.5rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
        .form-control { border-radius: 8px; border: 2px solid #e2e8f0; padding: 0.75rem 1rem; transition: all 0.3s ease; }
        .form-control:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        .btn-primary { background: #2563eb; border: none; border-radius: 8px; padding: 0.75rem 2rem; font-weight: 600; transition: all 0.3s ease; }
        .btn-primary:hover { background: #1e40af; }
        .btn-warning { border-radius: 8px; }
        .alert-custom { border-radius: 12px; border: none; box-shadow: 0 2px 8px rgba(44,62,80,0.07); }
    </style>
</head>
<body>
    <?php include '../includes/topbar.php'; ?>
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold"><i class="fas fa-user me-2"></i> Mon Profil</h2>
            <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i> Retour au Dashboard</a>
        </div>
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-custom animate-fade-in" role="alert">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        <div class="profile-header animate-fade-in">
            <div class="profile-avatar"><i class="fas fa-user"></i></div>
            <h1 class="profile-name"><?php echo htmlspecialchars($confirmateur['nom'] ?? $confirmateur['username']); ?></h1>
            <p class="profile-role">Confirmateur</p>
            <div class="d-flex justify-content-center gap-2">
                <span class="badge bg-primary">ID: <?php echo htmlspecialchars($confirmateur['id']); ?></span>
            </div>
        </div>
        <div class="stats-grid animate-fade-in">
            <div class="stat-card"><div class="stat-value"><?php echo $nb_livrees; ?></div><div class="stat-label">Commandes Livrées</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $nb_confirmees; ?></div><div class="stat-label">Confirmées</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $nb_nouvelles; ?></div><div class="stat-label">Nouvelles</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $nb_refusees; ?></div><div class="stat-label">Refusées</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $nb_annulees; ?></div><div class="stat-label">Annulées</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $nb_retournees; ?></div><div class="stat-label">Retournées</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo number_format($gain_total, 2); ?> Dhs</div><div class="stat-label">Gains Totaux</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo number_format($total_paye, 2); ?> Dhs</div><div class="stat-label">Gains Payés</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo number_format($non_paye, 2); ?> Dhs</div><div class="stat-label">Non Payé</div></div>
        </div>
        <div class="form-section animate-fade-in">
            <h3><i class="fas fa-user-edit text-primary"></i> Informations Personnelles</h3>
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="nom" class="form-label">Nom</label>
                        <input type="text" class="form-control" id="nom" name="nom" value="<?php echo htmlspecialchars($confirmateur['nom'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="username" class="form-label">Nom d'utilisateur</label>
                        <input type="text" class="form-control" id="username" value="<?php echo isset($confirmateur['username']) ? htmlspecialchars($confirmateur['username']) : '-'; ?>" readonly>
                        <small class="text-muted">Le nom d'utilisateur ne peut pas être modifié</small>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($confirmateur['email'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="phone" class="form-label">Téléphone</label>
                        <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($confirmateur['phone'] ?? ''); ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="city" class="form-label">Ville</label>
                        <input type="text" class="form-control" id="city" name="city" value="<?php echo htmlspecialchars($confirmateur['city'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="created_at" class="form-label">Date d'inscription</label>
                        <input type="text" class="form-control" id="created_at" value="<?php echo isset($confirmateur['created_at']) && $confirmateur['created_at'] ? date('d/m/Y', strtotime($confirmateur['created_at'])) : ''; ?>" readonly>
                    </div>
                </div>
                <button type="submit" name="update_profile" class="btn btn-primary"><i class="fas fa-save me-2"></i> Mettre à Jour le Profil</button>
            </form>
        </div>
        <div class="form-section animate-fade-in">
            <h3><i class="fas fa-lock text-warning"></i> Changer le Mot de Passe</h3>
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="current_password" class="form-label">Mot de Passe Actuel</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="new_password" class="form-label">Nouveau Mot de Passe</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="confirm_password" class="form-label">Confirmer le Nouveau Mot de Passe</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
                <button type="submit" name="change_password" class="btn btn-warning"><i class="fas fa-key me-2"></i> Changer le Mot de Passe</button>
            </form>
        </div>
        <div class="form-section animate-fade-in">
            <h3><i class="fas fa-cog text-info"></i> Actions du Compte</h3>
            <div class="row">
                <div class="col-md-6">
                    <div class="d-grid">
                        <a href="dashboard.php" class="btn btn-outline-primary"><i class="fas fa-chart-line me-2"></i> Retour au Dashboard</a>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-grid">
                        <a href="../logout.php" class="btn btn-outline-danger" onclick="return confirm('Êtes-vous sûr de vouloir vous déconnecter ?')"><i class="fas fa-sign-out-alt me-2"></i> Se Déconnecter</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        setTimeout(function() { $('.alert').fadeOut('slow'); }, 5000);
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Les mots de passe ne correspondent pas');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html> 