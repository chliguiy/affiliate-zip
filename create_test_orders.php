<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "<h2>Création de commandes de test</h2>";
    
    // Vérifier s'il y a déjà des commandes
    $stmt = $conn->query("SELECT COUNT(*) as count FROM orders");
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        echo "<p>Il y a déjà " . $result['count'] . " commandes dans la base de données.</p>";
        echo "<p><a href='test_order_details.php'>Voir les détails</a></p>";
        exit;
    }
    
    // Créer des commandes de test
    $test_orders = [
        [
            'customer_name' => 'Ahmed Benali',
            'customer_email' => 'ahmed.benali@email.com',
            'customer_phone' => '0612345678',
            'customer_address' => '123 Rue Hassan II',
            'customer_city' => 'Casablanca',
            'total_amount' => 450.00,
            'commission_amount' => 45.00,
            'shipping_cost' => 30.00,
            'status' => 'new',
            'notes' => 'Livraison préférée le matin'
        ],
        [
            'customer_name' => 'Fatima Zahra',
            'customer_email' => 'fatima.zahra@email.com',
            'customer_phone' => '0623456789',
            'customer_address' => '456 Avenue Mohammed V',
            'customer_city' => 'Rabat',
            'total_amount' => 320.00,
            'commission_amount' => 32.00,
            'shipping_cost' => 25.00,
            'status' => 'confirmed',
            'notes' => 'Appeler avant la livraison'
        ],
        [
            'customer_name' => 'Mohammed Alami',
            'customer_email' => 'mohammed.alami@email.com',
            'customer_phone' => '0634567890',
            'customer_address' => '789 Boulevard Mohammed VI',
            'customer_city' => 'Marrakech',
            'total_amount' => 680.00,
            'commission_amount' => 68.00,
            'shipping_cost' => 40.00,
            'status' => 'delivered',
            'notes' => 'Commande urgente'
        ]
    ];
    
    $conn->beginTransaction();
    
    foreach ($test_orders as $index => $order_data) {
        // Générer un numéro de commande
        $order_number = 'CMD-' . date('Y') . '-' . str_pad(($index + 1), 6, '0', STR_PAD_LEFT);
        
        // Insérer la commande
        $stmt = $conn->prepare("
            INSERT INTO orders (
                user_id, affiliate_id, customer_name, customer_email, customer_phone, 
                customer_address, customer_city, order_number, total_amount, 
                commission_amount, shipping_cost, status, notes, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            1, // user_id (utilisateur de test)
            1, // affiliate_id (affilié de test)
            $order_data['customer_name'],
            $order_data['customer_email'],
            $order_data['customer_phone'],
            $order_data['customer_address'],
            $order_data['customer_city'],
            $order_number,
            $order_data['total_amount'],
            $order_data['commission_amount'],
            $order_data['shipping_cost'],
            $order_data['status'],
            $order_data['notes']
        ]);
        
        $order_id = $conn->lastInsertId();
        
        // Créer des articles de commande de test
        $test_products = [
            ['name' => 'T-shirt Premium', 'quantity' => 2, 'price' => 120.00],
            ['name' => 'Jeans Classic', 'quantity' => 1, 'price' => 210.00],
            ['name' => 'Sneakers Sport', 'quantity' => 1, 'price' => 350.00]
        ];
        
        foreach ($test_products as $product) {
            $stmt = $conn->prepare("
                INSERT INTO order_items (
                    order_id, product_id, product_name, quantity, price, created_at
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $order_id,
                1, // product_id (produit de test)
                $product['name'],
                $product['quantity'],
                $product['price']
            ]);
        }
        
        echo "<p>✅ Commande créée : " . $order_number . " - " . $order_data['customer_name'] . "</p>";
    }
    
    $conn->commit();
    
    echo "<h3>🎉 Commandes de test créées avec succès !</h3>";
    echo "<p><a href='test_order_details.php'>Voir les détails des commandes</a></p>";
    echo "<p><a href='order_details.php?id=1'>Voir la première commande</a></p>";
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    echo "<p><strong>❌ Erreur :</strong> " . $e->getMessage() . "</p>";
}
?> 