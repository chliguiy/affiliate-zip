<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';

// Récupérer les informations de l'utilisateur
$database = new Database();
$conn = $database->getConnection();

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Récupérer les articles du panier
$stmt = $conn->prepare("
    SELECT ci.id, ci.quantity, p.id as product_id, p.name, p.seller_price as price, p.commission_rate,
           (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, id ASC LIMIT 1) as image_url
    FROM cart_items ci
    JOIN products p ON ci.product_id = p.id
    WHERE ci.user_id = ? AND p.status = 'active'
    ORDER BY ci.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$cart_items = $stmt->fetchAll();

// Calculer le total
$total = 0;
foreach ($cart_items as $item) {
    $total += $item['price'] * $item['quantity'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panier - SCAR AFFILIATE</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        /* Base */
        body {
            background: #f8fafc;
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: #374151;
        }
        .main-content {
            margin-left: 260px;
            padding: 2rem 1rem;
            min-height: 100vh;
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 1rem; }
        }

        /* Container principal */
        .cart-container {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin: 0 auto;
            max-width: 1200px;
        }

        /* Header du panier */
        .cart-header {
            background: #f9fafb;
            color: #374151;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .cart-header h2 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
            color: #111827;
        }

        /* Contenu du panier */
        .cart-content {
            padding: 2rem;
        }

        /* Items du panier */
        .cart-item {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: border-color 0.2s ease;
        }
        .cart-item:hover {
            border-color: #d1d5db;
        }

        .product-image {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
        }

        .product-title {
            font-weight: 500;
            font-size: 1rem;
            color: #111827;
            margin-bottom: 0.25rem;
        }

        .product-price {
            font-weight: 600;
            color: #059669;
            font-size: 1rem;
        }

        /* Contrôles de quantité */
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: #f9fafb;
            padding: 0.5rem;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
        }
        .quantity-btn {
            background: white;
            color: #6b7280;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 500;
        }
        .quantity-btn:hover {
            background: #f3f4f6;
            border-color: #9ca3af;
        }
        .quantity-display {
            font-weight: 500;
            color: #374151;
            min-width: 24px;
            text-align: center;
        }

        /* Bouton supprimer */
        .remove-btn {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
            border-radius: 6px;
            padding: 0.5rem 0.75rem;
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 500;
        }
        .remove-btn:hover {
            background: #fee2e2;
            border-color: #fca5a5;
        }

        /* Panier vide */
        .empty-cart {
            text-align: center;
            padding: 3rem 2rem;
            color: #6b7280;
        }
        .empty-cart i {
            font-size: 3rem;
            color: #d1d5db;
            margin-bottom: 1rem;
        }
        .empty-cart h4 {
            color: #374151;
            margin-bottom: 1rem;
        }

        /* Carte de résumé */
        .summary-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        .summary-card .card-header {
            background: #f9fafb;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
            padding: 1rem 1.5rem;
            font-weight: 600;
        }
        .summary-card .card-body {
            padding: 1.5rem;
        }

        /* Formulaires */
        .form-control {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 0.75rem;
            transition: border-color 0.2s ease;
            background: white;
        }
        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        .input-group-text {
            background: #f9fafb;
            border: 1px solid #d1d5db;
            color: #6b7280;
            font-weight: 500;
        }

        /* Boutons */
        .btn-primary {
            background: #3b82f6;
            border: 1px solid #3b82f6;
            border-radius: 6px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .btn-primary:hover {
            background: #2563eb;
            border-color: #2563eb;
        }
        .btn-outline-primary {
            border: 1px solid #d1d5db;
            color: #374151;
            border-radius: 6px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .btn-outline-primary:hover {
            background: #f9fafb;
            border-color: #9ca3af;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .cart-header h2 { font-size: 1.25rem; }
            .cart-content { padding: 1rem; }
            .cart-item { padding: 1rem; }
        }
    </style>
</head>
<body>
    <?php include 'includes/topbar.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="cart-container">
            <div class="cart-header">
                <h2><i class="fas fa-shopping-cart me-2"></i>Mon Panier</h2>
                <a href="dashboard.php" class="btn btn-outline-primary" style="position: absolute; top: 1rem; right: 1rem;">
                    <i class="fas fa-arrow-left me-2"></i>Retour
                </a>
            </div>
            
            <div class="cart-content">
            
            <?php if (empty($cart_items)): ?>
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart"></i>
                    <h4>Votre panier est vide</h4>
                    <p>Ajoutez des produits à votre panier pour commencer vos achats.</p>
                    <a href="products.php" class="btn btn-primary">
                        <i class="fas fa-shopping-bag me-2"></i>Voir les produits
                    </a>
                </div>
            <?php else: ?>
                <div class="row">
                    <div class="col-lg-8 col-md-12">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="cart-item">
                                <div class="row align-items-center">
                                    <div class="col-md-3 col-4">
                                        <?php if ($item['image_url']): ?>
                                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                                 class="product-image">
                                        <?php else: ?>
                                            <div class="product-image bg-light d-flex align-items-center justify-content-center">
                                                <i class="fas fa-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-4 col-8">
                                        <div class="product-title"><?php echo htmlspecialchars($item['name']); ?></div>
                                        <div class="product-price"><?php echo number_format($item['price'], 2); ?> MAD</div>
                                    </div>
                                    <div class="col-md-3 col-6 mt-3 mt-md-0">
                                        <div class="quantity-controls">
                                            <button class="quantity-btn" onclick="updateQuantity(<?php echo $item['id']; ?>, -1)">-</button>
                                            <span class="quantity-display"><?php echo $item['quantity']; ?></span>
                                            <button class="quantity-btn" onclick="updateQuantity(<?php echo $item['id']; ?>, 1)">+</button>
                                        </div>
                                    </div>
                                    <div class="col-md-2 col-4 text-end mt-3 mt-md-0">
                                        <div class="product-price"><?php echo number_format($item['price'] * $item['quantity'], 2); ?> MAD</div>
                                    </div>
                                    <div class="col-md-12 text-center mt-3 d-md-none">
                                        <button class="remove-btn" onclick="removeItem(<?php echo $item['id']; ?>)">
                                            <i class="fas fa-trash me-2"></i>Supprimer
                                        </button>
                                    </div>
                                    <div class="col-md-1 text-end d-none d-md-block">
                                        <button class="remove-btn" onclick="removeItem(<?php echo $item['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="col-lg-4 col-md-12">
                        <div class="summary-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-calculator me-2"></i>Résumé de la commande</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="fw-bold">Total:</span>
                                    <strong class="product-price"><?php echo number_format($total, 2); ?> MAD</strong>
                                </div>
                                
                                <!-- Formulaire de commande -->
                                <form method="POST" action="process_order.php" id="orderForm">
                                    <!-- Champs cachés pour les produits -->
                                    <?php foreach ($cart_items as $item): ?>
                                        <input type="hidden" name="products[<?php echo $item['product_id']; ?>]" value="<?php echo $item['quantity']; ?>">
                                    <?php endforeach; ?>
                                    
                                    <!-- Prix de vente final et profit affilié -->
                                    <div class="row mb-3">
                                        <div class="col-md-6 mb-3">
                                            <label for="final_sale_price" class="form-label">
                                                <i class="fas fa-dollar-sign me-1"></i>
                                                Prix de vente final
                                            </label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" name="final_sale_price"
                                                       id="final_sale_price" min="<?php echo $total; ?>" step="0.01" 
                                                       value="<?php echo $total; ?>" required>
                                                <span class="input-group-text">MAD</span>
                                            </div>
                                            <small class="text-muted">Prix payé par le client</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="affiliate_profit_display" class="form-label">
                                                <i class="fas fa-chart-line me-1"></i>
                                                Profit affilié
                                            </label>
                                            <div class="input-group">
                                                <span class="input-group-text">Profit</span>
                                                <input type="text" class="form-control" id="affiliate_profit_display" readonly>
                                                <span class="input-group-text">MAD</span>
                                            </div>
                                            <input type="hidden" name="affiliate_margin" id="affiliate_margin_hidden" value="0">
                                        </div>
                                    </div>
                                    
                                    <!-- Nom client -->
                                    <div class="mb-3">
                                        <label for="customer_name" class="form-label">Nom Destinataire (client)</label>
                                        <input type="text" class="form-control" name="customer_name" id="customer_name" required>
                                    </div>
                                    
                                    <!-- Téléphone -->
                                    <div class="mb-3">
                                        <label for="customer_phone" class="form-label">Numéro téléphone</label>
                                        <input type="text" class="form-control" name="customer_phone" id="customer_phone" 
                                               pattern="0[6-7][0-9]{8}" placeholder="06/07xxxxxxxx" required>
                                    </div>
                                    
                                    <!-- Ville -->
                                    <div class="mb-3">
                                        <label for="customer_city" class="form-label">Ville</label>
                                        <select class="form-control" name="customer_city" id="customer_city" required>
                                            <option value="">Sélectionnez une ville</option>
                                            <?php 
                                            require_once 'ApiDelivery.php';
                                            $apiDelivery = new ApiDelivery();
                                            $cities = json_decode($apiDelivery->getCities(), true);
                                            foreach ($cities as $city): 
                                            ?>
                                                <option value="<?php echo htmlspecialchars($city['id']); ?>" 
                                                        data-delivery="<?php echo htmlspecialchars($city['delivered_fees']); ?>">
                                                    <?php echo htmlspecialchars($city['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <!-- Frais de livraison -->
                                    <div class="mb-3">
                                        <label for="delivery_fee" class="form-label">Tarif de Livraison</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" name="delivery_fee" id="delivery_fee" 
                                                   min="0" step="0.01" readonly>
                                            <span class="input-group-text">MAD</span>
                                        </div>
                                        <div class="form-text">
                                            <small class="text-muted">Tarif calculé automatiquement selon la ville</small>
                                        </div>
                                    </div>
                                    
                                    <!-- Adresse -->
                                    <div class="mb-3">
                                        <label for="customer_address" class="form-label">Adresse de livraison</label>
                                        <textarea class="form-control" name="customer_address" id="customer_address" 
                                                  rows="2" required></textarea>
                                    </div>
                                    
                                    <!-- Commentaire -->
                                    <div class="mb-3">
                                        <label for="comment" class="form-label">Commentaire</label>
                                        <textarea class="form-control" name="comment" id="comment" rows="2"></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-credit-card me-2"></i>Passer la commande
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateQuantity(itemId, change) {
            fetch('update_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `item_id=${itemId}&change=${change}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message);
                }
            });
        }

        function removeItem(itemId) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cet article ?')) {
                fetch('remove_from_cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `item_id=${itemId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                });
            }
        }

        function checkout() {
            window.location.href = 'checkout.php';
        }

        // Gestion des frais de livraison automatiques et calcul du profit affilié
        document.addEventListener('DOMContentLoaded', function() {
            const citySelect = document.getElementById('customer_city');
            const deliveryFeeInput = document.getElementById('delivery_fee');
            const finalSalePriceInput = document.getElementById('final_sale_price');
            const affiliateProfitDisplay = document.getElementById('affiliate_profit_display');
            const affiliateMarginHidden = document.getElementById('affiliate_margin_hidden');

            function updateDeliveryFee() {
                const selectedOption = citySelect.options[citySelect.selectedIndex];
                const deliveryFee = selectedOption.getAttribute('data-delivery');
                
                if (deliveryFee) {
                    deliveryFeeInput.value = parseFloat(deliveryFee).toFixed(2);
                } else {
                    deliveryFeeInput.value = '';
                }
                updateAffiliateProfit(); // Recalcule le profit affilié
            }

            function updateAffiliateProfit() {
                const finalSalePrice = parseFloat(finalSalePriceInput.value) || 0;
                const totalCost = <?php echo $total; ?>;
                const deliveryFee = parseFloat(deliveryFeeInput.value) || 0;
                const profit = finalSalePrice - totalCost - deliveryFee;
                
                affiliateProfitDisplay.value = profit.toFixed(2);
                affiliateMarginHidden.value = profit.toFixed(2);
            }

            // Initialiser les frais de livraison et le profit affilié
            updateDeliveryFee();
            updateAffiliateProfit();

            // Mettre à jour quand la ville change
            citySelect.addEventListener('change', updateDeliveryFee);
            
            // Mettre à jour quand le prix de vente final change
            finalSalePriceInput.addEventListener('input', updateAffiliateProfit);
        });

        // Validation du formulaire
        document.getElementById('orderForm').addEventListener('submit', function (e) {
            const customerName = document.getElementById('customer_name').value.trim();
            const customerPhone = document.getElementById('customer_phone').value.trim();
            const customerCity = document.getElementById('customer_city').value;
            const customerAddress = document.getElementById('customer_address').value.trim();

            if (!customerName) {
                alert('Veuillez saisir le nom du destinataire');
                e.preventDefault();
                return false;
            }

            if (!customerPhone) {
                alert('Veuillez saisir le numéro de téléphone');
                e.preventDefault();
                return false;
            }

            if (!customerCity) {
                alert('Veuillez sélectionner une ville');
                e.preventDefault();
                return false;
            }

            if (!customerAddress) {
                alert('Veuillez saisir l\'adresse de livraison');
                e.preventDefault();
                return false;
            }

            // Validation du format du téléphone
            const phoneRegex = /^0[6-7][0-9]{8}$/;
            if (!phoneRegex.test(customerPhone)) {
                alert('Veuillez saisir un numéro de téléphone valide (format: 06xxxxxxxx ou 07xxxxxxxx)');
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>
</html> 