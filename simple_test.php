<?php
echo "Test simple\n";

$final_sale_price = 1200;
$total_amount = 600;
$delivery_fee = 39;

$profit = $final_sale_price - $total_amount - $delivery_fee;

echo "Prix final: $final_sale_price\n";
echo "Total produits: $total_amount\n";
echo "Livraison: $delivery_fee\n";
echo "Profit: $profit\n";
?> 