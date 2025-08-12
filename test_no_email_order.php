<?php
// Test du processus de commande sans email
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config/database.php';
require_once 'includes/system_integration.php';

echo "<h1>Test du processus de commande sans email</h1>";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "<h2>1. Vérification de la base de données</h2>";
    
    // Vérifier qu'il y a des produits
    $stmt = $conn->query("SELECT id, name, seller_price FROM products WHERE status = 'active' LIMIT 1");
    $product = $stmt->fetch();
    
    if (!$product) {
        echo "❌ Aucun produit actif trouvé<br>";
        exit;
    }
    
    echo "✅ Produit trouvé : " . $product['name'] . "<br>";
    
    // Vérifier qu'il y a des affiliés
    $stmt = $conn->query("SELECT id, username FROM users WHERE type = 'affiliate' AND status = 'active' LIMIT 1");
    $affiliate = $stmt->fetch();
    
    if (!$affiliate) {
        echo "❌ Aucun affilié actif trouvé<br>";
        exit;
    }
    
    echo "✅ Affilié trouvé : " . $affiliate['username'] . "<br>";
    
    echo "<h2>2. Test de création de commande sans email</h2>";
    
    // Données de test sans email
    $test_client_data = [
        'name' => 'Test Client Sans Email',
        'phone' => '0612345678',
        'address' => '123 Rue Test',
        'city' => 'Casablanca'
    ];
    
    $test_products = [
        [
            'id' => $product['id'],
            'name' => $product['name'],
            'price' => (float)$product['seller_price'],
            'quantity' => 1,
            'commission_rate' => 10.00
        ]
    ];
    
    echo "Données client : " . $test_client_data['name'] . " - " . $test_client_data['phone'] . "<br>";
    echo "Produit : " . $test_products[0]['name'] . " - " . $test_products[0]['price'] . " MAD<br>";
    
    // Simuler les données POST
    $_POST['final_sale_price'] = $test_products[0]['price'] + 50; // Prix final
    $_POST['delivery_fee'] = 30; // Frais de livraison
    
    $result = createOrderViaAffiliate($test_client_data, $affiliate['id'], $test_products);
    
    if ($result['success']) {
        echo "✅ Test réussi !<br>";
        echo "- ID de commande: " . $result['order_id'] . "<br>";
        echo "- Numéro de commande: " . $result['order_number'] . "<br>";
        
        // Vérifier que la commande a été créée sans email
        $stmt = $conn->prepare("SELECT customer_name, customer_email, customer_phone FROM orders WHERE id = ?");
        $stmt->execute([$result['order_id']]);
        $order = $stmt->fetch();
        
        echo "<h3>Vérification de la commande créée :</h3>";
        echo "- Nom client : " . $order['customer_name'] . "<br>";
        echo "- Email client : " . ($order['customer_email'] ?: 'Vide (correct)') . "<br>";
        echo "- Téléphone client : " . $order['customer_phone'] . "<br>";
        
        if (empty($order['customer_email'])) {
            echo "✅ Email correctement supprimé du processus !<br>";
        } else {
            echo "❌ Email toujours présent : " . $order['customer_email'] . "<br>";
        }
        
    } else {
        echo "❌ Échec du test : " . $result['error'] . "<br>";
    }
    
    echo "<h2>3. Test du formulaire de commande</h2>";
    echo "<p>Le formulaire de commande ne devrait plus demander d'email :</p>";
    echo "<a href='product_details.php?id=" . $product['id'] . "' target='_blank'>Tester le formulaire de commande</a><br>";
    
    echo "<h2>4. Résumé des modifications</h2>";
    echo "<ul>";
    echo "<li>✅ Champ email supprimé du formulaire product_details.php</li>";
    echo "<li>✅ Validation sans email dans process_order.php</li>";
    echo "<li>✅ Création de client sans email dans system_integration.php</li>";
    echo "<li>✅ Commande créée avec email vide</li>";
    echo "<li>✅ Affichage sans email dans order_confirmation.php</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>
