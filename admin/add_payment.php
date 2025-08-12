<?php
session_start();
require_once 'includes/auth.php';
require_once 'config/database.php';

// Vérifier l'authentification admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit();
}

$database = new Database();
$pdo = $database->getConnection();

$message = '';
$error = '';

// Traitement du formulaire d'ajout de paiement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = $_POST['amount'] ?? '';
    $reason = $_POST['reason'] ?? '';
    $affiliate_id = $_POST['affiliate_id'] ?? '';
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    
    // Validation des données
    if (empty($amount) || !is_numeric($amount) || $amount <= 0) {
        $error = 'Le montant doit être un nombre positif';
    } elseif (empty($reason)) {
        $error = 'La raison est obligatoire';
    } elseif (empty($affiliate_id)) {
        $error = 'Veuillez sélectionner un affilié';
    } else {
        try {
            // Insérer le nouveau paiement dans la table orders
            $stmt = $pdo->prepare("
                INSERT INTO orders (affiliate_id, total_amount, customer_name, customer_phone, address, city, created_at, status, payment_reason) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $affiliate_id, // affiliate_id sélectionné
                $amount,
                'Paiement manuel', // Nom du client par défaut
                '0000000000', // Téléphone par défaut
                'Adresse par défaut', // Adresse par défaut
                'Ville par défaut', // Ville par défaut
                $payment_date . ' 00:00:00',
                'confirmed', // Statut confirmé
                $reason
            ]);
            
            $message = 'Paiement ajouté avec succès !';
            
        } catch (Exception $e) {
            $error = 'Erreur lors de l\'ajout du paiement : ' . $e->getMessage();
        }
    }
}

// Récupérer les utilisateurs affiliés
$affiliates_query = "SELECT id, username, full_name FROM users WHERE type = 'affiliate' AND status = 'active' ORDER BY username";
$affiliates_stmt = $pdo->prepare($affiliates_query);
$affiliates_stmt->execute();
$affiliates = $affiliates_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un Paiement - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #f8f9fa;
            border-right: 1px solid #dee2e6;
        }
        
        .form-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .btn-back {
            background: #6c757d;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .btn-save {
            background: #28a745;
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 5px;
            font-size: 1rem;
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Ajouter un Paiement</h2>
                    <a href="payments_received.php" class="btn btn-back">
                        <i class="fas fa-arrow-left me-2"></i>
                        Retour aux Paiements
                    </a>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Formulaire d'ajout de paiement -->
                <div class="form-container">
                    <form method="POST" action="">
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

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-save">
                                <i class="fas fa-save me-2"></i>
                                Ajouter le Paiement
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validation côté client
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const amountInput = document.getElementById('amount');
            const reasonInput = document.getElementById('reason');
            
            form.addEventListener('submit', function(e) {
                const amount = parseFloat(amountInput.value);
                const reason = reasonInput.value.trim();
                
                if (isNaN(amount) || amount <= 0) {
                    e.preventDefault();
                    alert('Le montant doit être un nombre positif');
                    amountInput.focus();
                    return;
                }
                
                if (reason.length === 0) {
                    e.preventDefault();
                    alert('La raison du paiement est obligatoire');
                    reasonInput.focus();
                    return;
                }
            });
            
            // Formatage automatique du montant
            amountInput.addEventListener('input', function() {
                let value = this.value.replace(/[^\d.]/g, '');
                if (value.includes('.')) {
                    const parts = value.split('.');
                    if (parts[1].length > 2) {
                        parts[1] = parts[1].substring(0, 2);
                        value = parts.join('.');
                    }
                }
                this.value = value;
            });
        });
    </script>
</body>
</html> 