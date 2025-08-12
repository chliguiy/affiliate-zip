<?php
require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

echo "=== DEBUG SIMPLE ===\n";

// 1. Vérifier la connexion
echo "1. Test de connexion à la base de données...\n";
try {
    $test = $pdo->query("SELECT 1")->fetch();
    echo "✅ Connexion OK\n";
} catch (Exception $e) {
    echo "❌ Erreur de connexion: " . $e->getMessage() . "\n";
    exit();
}

// 2. Compter les utilisateurs
echo "2. Nombre d'utilisateurs...\n";
$count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
echo "Total utilisateurs: $count\n";

// 3. Compter les affiliés
echo "3. Nombre d'affiliés...\n";
$affiliates = $pdo->query("SELECT COUNT(*) FROM users WHERE type = 'affiliate'")->fetchColumn();
echo "Total affiliés: $affiliates\n";

// 4. Compter les commandes
echo "4. Nombre de commandes...\n";
$orders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
echo "Total commandes: $orders\n";

// 5. Compter les commandes livrées
echo "5. Nombre de commandes livrées...\n";
$delivered = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered'")->fetchColumn();
echo "Commandes livrées: $delivered\n";

// 6. Afficher quelques utilisateurs
echo "6. Quelques utilisateurs:\n";
$users = $pdo->query("SELECT id, username, type, status FROM users LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
foreach ($users as $user) {
    echo "- ID: {$user['id']}, Username: {$user['username']}, Type: {$user['type']}, Status: {$user['status']}\n";
}

// 7. Afficher quelques commandes
echo "7. Quelques commandes:\n";
$orders = $pdo->query("SELECT id, affiliate_id, total_amount, status FROM orders LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
foreach ($orders as $order) {
    echo "- ID: {$order['id']}, Affiliate ID: {$order['affiliate_id']}, Montant: {$order['total_amount']}, Status: {$order['status']}\n";
}

echo "=== FIN DEBUG ===\n";
?> 