<?php
session_start();
require_once 'config/database.php';

echo "<h2>🔍 Test du Système de Connexion Unifiée</h2>";

// Test de connexion à la base de données
try {
    $database = new Database();
    $conn = $database->getConnection();
    echo "<p>✅ Connexion à la base de données réussie</p>";
} catch (Exception $e) {
    echo "<p>❌ Erreur de connexion à la base de données: " . $e->getMessage() . "</p>";
    exit;
}

// Vérifier les tables nécessaires
$tables = ['users', 'admins', 'equipe'];
echo "<h3>📋 Vérification des tables:</h3>";

foreach ($tables as $table) {
    try {
        $stmt = $conn->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "<p>✅ Table '$table': $count enregistrements</p>";
    } catch (Exception $e) {
        echo "<p>❌ Erreur avec la table '$table': " . $e->getMessage() . "</p>";
    }
}

// Afficher quelques comptes de test
echo "<h3>👥 Comptes disponibles pour test:</h3>";

// Admins
try {
    $stmt = $conn->query("SELECT email FROM admins LIMIT 3");
    $admins = $stmt->fetchAll();
    echo "<strong>Admins:</strong><ul>";
    foreach ($admins as $admin) {
        echo "<li>" . htmlspecialchars($admin['email']) . "</li>";
    }
    echo "</ul>";
} catch (Exception $e) {
    echo "<p>❌ Erreur lors de la récupération des admins</p>";
}

// Affiliés
try {
    $stmt = $conn->query("SELECT email FROM users WHERE type = 'affiliate' AND status = 'active' LIMIT 3");
    $affiliates = $stmt->fetchAll();
    echo "<strong>Affiliés actifs:</strong><ul>";
    foreach ($affiliates as $affiliate) {
        echo "<li>" . htmlspecialchars($affiliate['email']) . "</li>";
    }
    echo "</ul>";
} catch (Exception $e) {
    echo "<p>❌ Erreur lors de la récupération des affiliés</p>";
}

// Confirmateurs
try {
    $stmt = $conn->query("SELECT email FROM equipe WHERE role = 'confirmateur' LIMIT 3");
    $confirmateurs = $stmt->fetchAll();
    echo "<strong>Confirmateurs:</strong><ul>";
    foreach ($confirmateurs as $confirmateur) {
        echo "<li>" . htmlspecialchars($confirmateur['email']) . "</li>";
    }
    echo "</ul>";
} catch (Exception $e) {
    echo "<p>❌ Erreur lors de la récupération des confirmateurs</p>";
}

echo "<h3>🔗 Test de redirection:</h3>";
echo "<p><a href='login.php' target='_blank'>➡️ Tester la connexion unifiée</a></p>";
echo "<p><a href='admin/' target='_blank'>➡️ Tester la redirection admin</a></p>";

if (isset($_SESSION['admin_id'])) {
    echo "<p>🔓 Vous êtes connecté en tant qu'admin</p>";
    echo "<p><a href='admin/dashboard.php' target='_blank'>➡️ Dashboard Admin</a></p>";
} elseif (isset($_SESSION['user_id'])) {
    echo "<p>🔓 Vous êtes connecté en tant qu'affilié</p>";
    echo "<p><a href='dashboard.php' target='_blank'>➡️ Dashboard Affilié</a></p>";
} elseif (isset($_SESSION['confirmateur_id'])) {
    echo "<p>🔓 Vous êtes connecté en tant que confirmateur</p>";
    echo "<p><a href='confirmateur/dashboard.php' target='_blank'>➡️ Dashboard Confirmateur</a></p>";
} else {
    echo "<p>🔒 Aucune session active</p>";
}

echo "<hr>";
echo "<p><small>Créé le " . date('Y-m-d H:i:s') . "</small></p>";
?> 