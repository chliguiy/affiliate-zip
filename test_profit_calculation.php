<?php
require_once 'config/database.php';

$database = new Database();
$conn = $database->getConnection();

echo "=== TEST CALCUL PROFIT CORRIGÉ ===\n\n";

// Simuler les données d'une commande
$final_sale_price = 1200;
$delivery_fee = 39;
$products = [
    ['price' => 130, 'quantity' => 1],
    ['price' => 160, 'quantity' => 2],
    ['price' => 150, 'quantity' => 1]
];

echo "Prix de vente final: " . $final_sale_price . " DH\n";
echo "Frais de livraison: " . $delivery_fee . " DH\n";
echo "\nProduits:\n";

$total_amount = 0;
foreach ($products as $i => $product) {
    $subtotal = $product['price'] * $product['quantity'];
    $total_amount += $subtotal;
    echo "- Produit " . ($i+1) . ": " . $product['price'] . " DH × " . $product['quantity'] . " = " . $subtotal . " DH\n";
}

echo "\nTotal amount (tous produits): " . $total_amount . " DH\n";

echo "\n=== CALCULS ===\n";

// Calcul CORRECT (utilisé maintenant)
$affiliate_margin_correct = $final_sale_price - $total_amount - $delivery_fee;
echo "Profit correct (tous produits): " . $affiliate_margin_correct . " DH\n";

// Calcul INCORRECT (ancien code)
$first_product_cost = $products[0]['price'] * $products[0]['quantity'];
$affiliate_margin_incorrect = $final_sale_price - $first_product_cost - $delivery_fee;
echo "Profit incorrect (premier produit): " . $affiliate_margin_incorrect . " DH\n";

echo "\n=== VÉRIFICATION ===\n";
echo "Différence entre les deux calculs: " . abs($affiliate_margin_correct - $affiliate_margin_incorrect) . " DH\n";

if ($affiliate_margin_correct == 561) {
    echo "✅ Le calcul correct donne bien 561 DH (cohérent avec le frontend)\n";
} else {
    echo "❌ Le calcul correct ne donne pas 561 DH\n";
}

echo "\n=== RÉSUMÉ ===\n";
echo "Le problème était que le backend calculait le profit avec le premier produit seulement\n";
echo "au lieu d'utiliser le total de tous les produits comme le frontend.\n";
echo "Maintenant les deux calculs sont cohérents !\n";
?> 