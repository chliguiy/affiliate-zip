<?php
// Simuler une session utilisateur
session_start();
$_SESSION['user_id'] = 61; // Utilisateur affilié de la commande #106

// Inclure la page order_details_fixed.php
$_GET['id'] = 106;

echo "<h2>Test de la page order_details_fixed.php</h2>";
echo "<p>Session utilisateur simulée : user_id = " . $_SESSION['user_id'] . "</p>";
echo "<p>Commande testée : ID = " . $_GET['id'] . "</p>";

// Capturer la sortie de order_details_fixed.php
ob_start();
include 'order_details_fixed.php';
$output = ob_get_clean();

// Afficher le résultat
echo "<h3>Résultat de la page :</h3>";
echo "<div style='border: 1px solid #ccc; padding: 10px; max-height: 800px; overflow-y: auto;'>";
echo $output;
echo "</div>";
?> 