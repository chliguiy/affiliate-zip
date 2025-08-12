<?php
// Test simple pour vérifier que la page fonctionne
echo "<h1>Test de la page simple_order_details.php</h1>";

// Inclure la page directement
$_GET['id'] = 106;

echo "<p>Test de la commande #106...</p>";

// Capturer la sortie
ob_start();
include 'simple_order_details.php';
$output = ob_get_clean();

// Vérifier si la sortie contient les informations
if (strpos($output, 'Informations Client') !== false) {
    echo "<p style='color: green;'>✅ SUCCÈS ! La page affiche les informations client</p>";
} else {
    echo "<p style='color: red;'>❌ ÉCHEC ! La page n'affiche pas les informations client</p>";
}

if (strpos($output, 'hamza (x2)') !== false) {
    echo "<p style='color: green;'>✅ SUCCÈS ! La page affiche les produits</p>";
} else {
    echo "<p style='color: red;'>❌ ÉCHEC ! La page n'affiche pas les produits</p>";
}

if (strpos($output, 'Résumé Financier') !== false) {
    echo "<p style='color: green;'>✅ SUCCÈS ! La page affiche le résumé financier</p>";
} else {
    echo "<p style='color: red;'>❌ ÉCHEC ! La page n'affiche pas le résumé financier</p>";
}

echo "<hr>";
echo "<h2>Résultat complet :</h2>";
echo "<div style='border: 1px solid #ccc; padding: 10px; max-height: 400px; overflow-y: auto;'>";
echo $output;
echo "</div>";
?> 