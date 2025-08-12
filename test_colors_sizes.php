<?php
require_once 'config/database.php';

// Test de la page avec couleurs et tailles
$_GET['id'] = 106;

echo "<h1>Test de la page order_details.php avec couleurs et tailles</h1>";

// Capturer la sortie
ob_start();
include 'order_details.php';
$output = ob_get_clean();

// Vérifier si la sortie contient les nouvelles informations
if (strpos($output, 'Couleurs disponibles') !== false) {
    echo "<p style='color: green;'>✅ SUCCÈS ! La page affiche les couleurs disponibles</p>";
} else {
    echo "<p style='color: red;'>❌ ÉCHEC ! La page n'affiche pas les couleurs disponibles</p>";
}

if (strpos($output, 'Tailles disponibles') !== false) {
    echo "<p style='color: green;'>✅ SUCCÈS ! La page affiche les tailles disponibles</p>";
} else {
    echo "<p style='color: red;'>❌ ÉCHEC ! La page n'affiche pas les tailles disponibles</p>";
}

if (strpos($output, 'fas fa-palette') !== false) {
    echo "<p style='color: green;'>✅ SUCCÈS ! L'icône palette est présente</p>";
} else {
    echo "<p style='color: red;'>❌ ÉCHEC ! L'icône palette n'est pas présente</p>";
}

if (strpos($output, 'fas fa-ruler') !== false) {
    echo "<p style='color: green;'>✅ SUCCÈS ! L'icône ruler est présente</p>";
} else {
    echo "<p style='color: red;'>❌ ÉCHEC ! L'icône ruler n'est pas présente</p>";
}

echo "<hr>";
echo "<h2>Résultat complet :</h2>";
echo "<div style='border: 1px solid #ccc; padding: 10px; max-height: 600px; overflow-y: auto;'>";
echo $output;
echo "</div>";
?> 