<?php
require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

echo "=== DEBUG DONNÉES DU TABLEAU ===\n";

// Simuler la requête exacte de la page
$query = "SELECT 
            COALESCE(u.username, 'Paiement manuel') as affiliate_name,
            COALESCE(u.id, 0) as affiliate_id,
            SUM(o.total_amount) as total_amount,
            SUM(oi.id IS NOT NULL) as total_packages,
            MAX(o.created_at) as last_payment_date,
            CASE 
                WHEN COUNT(CASE WHEN o.status IN ('confirmed', 'delivered', 'new', 'unconfirmed') THEN 1 END) > 0 THEN 'En Attente'
                ELSE 'Payé'
            END as status
          FROM orders o
          LEFT JOIN order_items oi ON o.id = oi.order_id
          LEFT JOIN users u ON o.affiliate_id = u.id
          WHERE o.status = 'delivered'
          GROUP BY u.id, u.username
          ORDER BY total_amount DESC";

$stmt = $pdo->prepare($query);
$stmt->execute();
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Données du tableau:\n";
foreach ($payments as $payment) {
    echo "- Affiliate ID: {$payment['affiliate_id']}, Nom: {$payment['affiliate_name']}, Montant: {$payment['total_amount']}, Statut: {$payment['status']}\n";
}

echo "\n=== VÉRIFICATION DES AFFILIATE_ID DANS ORDERS ===\n";
$orders_check = $pdo->query("SELECT DISTINCT affiliate_id FROM orders WHERE status = 'delivered' ORDER BY affiliate_id")->fetchAll(PDO::FETCH_COLUMN);
echo "Affiliate IDs dans les commandes livrées:\n";
foreach ($orders_check as $affiliate_id) {
    echo "- $affiliate_id\n";
}

echo "\n=== VÉRIFICATION DES UTILISATEURS ===\n";
$users_check = $pdo->query("SELECT id, username, type FROM users WHERE id IN (" . implode(',', $orders_check) . ") ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
echo "Utilisateurs correspondants:\n";
foreach ($users_check as $user) {
    echo "- ID: {$user['id']}, Username: {$user['username']}, Type: {$user['type']}\n";
}

echo "\n=== FIN DEBUG ===\n";
?> 