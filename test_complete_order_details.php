<?php
// Test complet de la page order_details.php avec toutes les informations
$_GET['id'] = 106;

echo "<h1>Test complet de order_details.php</h1>";
echo "<p>Test de la commande #106 avec TOUTES les informations des produits</p>";

// Capturer la sortie
ob_start();
include 'order_details.php';
$output = ob_get_clean();

// Vérifier les éléments clés
$checks = [
    '🎨 Couleurs disponibles' => 'Couleurs disponibles avec emoji',
    '📏 Tailles disponibles' => 'Tailles disponibles avec emoji',
    'Prix vendeur' => 'Prix vendeur affiché',
    'Prix revendeur' => 'Prix revendeur affiché',
    'Commission' => 'Commission affichée',
    'Stock disponible' => 'Stock disponible affiché',
    'badge bg-light' => 'Badges pour les couleurs',
    'badge bg-primary' => 'Badges pour les tailles',
    'fas fa-palette' => 'Icône palette',
    'fas fa-ruler' => 'Icône règle',
    'img-fluid rounded' => 'Images des produits'
];

echo "<h2>Vérifications :</h2>";
foreach ($checks as $search => $description) {
    if (strpos($output, $search) !== false) {
        echo "<p style='color: green;'>✅ $description</p>";
    } else {
        echo "<p style='color: red;'>❌ $description</p>";
    }
}

echo "<hr>";
echo "<h2>Résultat complet :</h2>";
echo "<div style='border: 1px solid #ccc; padding: 10px; max-height: 800px; overflow-y: auto;'>";
echo $output;
echo "</div>";
?> 