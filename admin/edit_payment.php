<?php
session_start();
require_once 'includes/auth.php';
require_once '../config/database.php';

// Vérifier l'authentification admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit();
}

$payment_id = $_GET['id'] ?? null;

if (!$payment_id) {
    header('Location: payments_received.php');
    exit();
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Traitement du formulaire de modification
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $customer_name = $_POST['customer_name'] ?? '';
        $customer_email = $_POST['customer_email'] ?? '';
        $customer_phone = $_POST['customer_phone'] ?? '';
        $customer_address = $_POST['customer_address'] ?? '';
        $customer_city = $_POST['customer_city'] ?? '';
        $total_amount = $_POST['total_amount'] ?? 0;
        $status = $_POST['status'] ?? '';
        
        // Mettre à jour la commande
        $update_query = "UPDATE orders SET 
                        customer_name = ?,
                        customer_email = ?,
                        customer_phone = ?,
                        customer_address = ?,
                        customer_city = ?,
                        total_amount = ?,
                        status = ?,
                        updated_at = NOW()
                        WHERE id = ?";
        
        $update_stmt = $pdo->prepare($update_query);
        $result = $update_stmt->execute([
            $customer_name,
            $customer_email,
            $customer_phone,
            $customer_address,
            $customer_city,
            $total_amount,
            $status,
            $payment_id
        ]);
        
        if ($result) {
            $success_message = "Paiement modifié avec succès !";
        } else {
            $error_message = "Erreur lors de la modification";
        }
    }
    
    // Récupérer les détails du paiement
    $query = "SELECT 
                o.*,
                u.username as affiliate_name,
                u.email as affiliate_email,
                COUNT(oi.id) as packages
              FROM orders o
              LEFT JOIN users u ON o.affiliate_id = u.id
              LEFT JOIN order_items oi ON o.id = oi.order_id
              WHERE o.id = ?
              GROUP BY o.id";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payment) {
        header('Location: payments_received.php');
        exit();
    }
    
} catch (Exception $e) {
    $error_message = "Erreur: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier le Paiement - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #f8f9fa;
            border-right: 1px solid #dee2e6;
        }
        
        .edit-form {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 2rem;
        }
        
        .back-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 1rem;
        }
        
        .back-btn:hover {
            background: #5a6268;
            color: white;
            text-decoration: none;
        }
        
        .btn-save {
            background: #28a745;
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 5px;
            font-weight: 600;
        }
        
        .btn-save:hover {
            background: #218838;
            color: white;
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
                <a href="payments_received.php" class="back-btn">
                    <i class="fas fa-arrow-left me-2"></i>
                    Retour aux paiements
                </a>
                
                <h2 class="mb-4">Modifier le Paiement</h2>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($success_message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($error_message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Edit Form -->
                <div class="edit-form">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6">
                                <h4>Informations de la commande</h4>
                                <div class="mb-3">
                                    <label class="form-label">ID de la commande</label>
                                    <input type="text" class="form-control" value="<?= $payment['id'] ?>" readonly>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Numéro de commande</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($payment['order_number']) ?>" readonly>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Date de création</label>
                                    <input type="text" class="form-control" value="<?= date('d/m/Y H:i', strtotime($payment['created_at'])) ?>" readonly>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Statut</label>
                                    <select name="status" class="form-select">
                                        <option value="new" <?= $payment['status'] === 'new' ? 'selected' : '' ?>>Nouveau</option>
                                        <option value="confirmed" <?= $payment['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmé</option>
                                        <option value="delivered" <?= $payment['status'] === 'delivered' ? 'selected' : '' ?>>Livré</option>
                                        <option value="paid" <?= $payment['status'] === 'paid' ? 'selected' : '' ?>>Payé</option>
                                        <option value="cancelled" <?= $payment['status'] === 'cancelled' ? 'selected' : '' ?>>Annulé</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Montant total (DH)</label>
                                    <input type="number" name="total_amount" class="form-control" value="<?= $payment['total_amount'] ?>" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h4>Informations client</h4>
                                <div class="mb-3">
                                    <label class="form-label">Nom complet</label>
                                    <input type="text" name="customer_name" class="form-control" value="<?= htmlspecialchars($payment['customer_name']) ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="customer_email" class="form-control" value="<?= htmlspecialchars($payment['customer_email']) ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Téléphone</label>
                                    <input type="text" name="customer_phone" class="form-control" value="<?= htmlspecialchars($payment['customer_phone']) ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Adresse</label>
                                    <textarea name="customer_address" class="form-control" rows="3" required><?= htmlspecialchars($payment['customer_address']) ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Ville</label>
                                    <input type="text" name="customer_city" class="form-control" value="<?= htmlspecialchars($payment['customer_city']) ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($payment['affiliate_name']): ?>
                        <div class="row mt-4">
                            <div class="col-12">
                                <h4>Informations affilié (lecture seule)</h4>
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label">Nom affilié</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($payment['affiliate_name']) ?>" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email affilié</label>
                                        <input type="email" class="form-control" value="<?= htmlspecialchars($payment['affiliate_email']) ?>" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="row mt-4">
                            <div class="col-12 text-center">
                                <button type="submit" class="btn btn-save">
                                    <i class="fas fa-save me-2"></i>
                                    Enregistrer les modifications
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 