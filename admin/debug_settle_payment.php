<?php
session_start();
require_once 'includes/auth.php';
require_once '../config/database.php';

// Vérifier l'authentification admin
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

$database = new Database();
$pdo = $database->getConnection();

// Récupérer les données JSON
$input = json_decode(file_get_contents('php://input'), true);
$affiliate_id = $input['payment_id'] ?? 0;

echo "<h2>Debug - Règlement de paiement</h2>";
echo "<p><strong>Affiliate ID reçu :</strong> $affiliate_id</p>";

// Vérifier si l'affilié existe
$affiliate_query = "SELECT id, username, full_name, type, status FROM users WHERE id = ?";
$affiliate_stmt = $pdo->prepare($affiliate_query);
$affiliate_stmt->execute([$affiliate_id]);
$affiliate = $affiliate_stmt->fetch(PDO::FETCH_ASSOC);

if (!$affiliate) {
    echo "<p style='color: red;'>❌ Affilié non trouvé avec l'ID: $affiliate_id</p>";
    
    // Afficher tous les utilisateurs pour debug
    echo "<h3>Tous les utilisateurs :</h3>";
    $all_users = $pdo->query("SELECT id, username, full_name, type, status FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Username</th><th>Nom complet</th><th>Type</th><th>Statut</th></tr>";
    foreach ($all_users as $user) {
        echo "<tr>";
        echo "<td>{$user['id']}</td>";
        echo "<td>{$user['username']}</td>";
        echo "<td>{$user['full_name']}</td>";
        echo "<td>{$user['type']}</td>";
        echo "<td>{$user['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    exit();
}

echo "<p style='color: green;'>✅ Affilié trouvé : {$affiliate['username']} (ID: {$affiliate['id']})</p>";

// Vérifier les commandes de cet affilié
$orders_query = "SELECT 
                  o.id,
                  o.total_amount,
                  o.status,
                  o.affiliate_id,
                  o.created_at,
                  COUNT(oi.id) as packages
                FROM orders o
                LEFT JOIN order_items oi ON o.id = oi.order_id
                WHERE o.affiliate_id = ?
                GROUP BY o.id
                ORDER BY o.created_at DESC";

$orders_stmt = $pdo->prepare($orders_query);
$orders_stmt->execute([$affiliate_id]);
$orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Commandes de l'affilié :</h3>";
if (empty($orders)) {
    echo "<p style='color: orange;'>⚠️ Aucune commande trouvée pour cet affilié</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID Commande</th><th>Montant</th><th>Statut</th><th>Colis</th><th>Date</th></tr>";
    foreach ($orders as $order) {
        echo "<tr>";
        echo "<td>{$order['id']}</td>";
        echo "<td>{$order['total_amount']} MAD</td>";
        echo "<td>{$order['status']}</td>";
        echo "<td>{$order['packages']}</td>";
        echo "<td>{$order['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Vérifier les commandes livrées spécifiquement
$delivered_query = "SELECT 
                     o.id,
                     o.total_amount,
                     o.status,
                     COUNT(oi.id) as packages
                   FROM orders o
                   LEFT JOIN order_items oi ON o.id = oi.order_id
                   WHERE o.affiliate_id = ? AND o.status = 'delivered'
                   GROUP BY o.id";

$delivered_stmt = $pdo->prepare($delivered_query);
$delivered_stmt->execute([$affiliate_id]);
$delivered_orders = $delivered_stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Commandes livrées (statut 'delivered') :</h3>";
if (empty($delivered_orders)) {
    echo "<p style='color: orange;'>⚠️ Aucune commande livrée trouvée pour cet affilié</p>";
    
    // Afficher les statuts disponibles
    echo "<h4>Statuts disponibles dans les commandes :</h4>";
    $statuses = $pdo->query("SELECT DISTINCT status FROM orders WHERE affiliate_id = $affiliate_id")->fetchAll(PDO::FETCH_COLUMN);
    echo "<ul>";
    foreach ($statuses as $status) {
        echo "<li>$status</li>";
    }
    echo "</ul>";
} else {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID Commande</th><th>Montant</th><th>Colis</th></tr>";
    $total_amount = 0;
    $total_packages = 0;
    foreach ($delivered_orders as $order) {
        echo "<tr>";
        echo "<td>{$order['id']}</td>";
        echo "<td>{$order['total_amount']} MAD</td>";
        echo "<td>{$order['packages']}</td>";
        echo "</tr>";
        $total_amount += $order['total_amount'];
        $total_packages += $order['packages'];
    }
    echo "<tr style='font-weight: bold;'>";
    echo "<td><strong>TOTAL</strong></td>";
    echo "<td><strong>$total_amount MAD</strong></td>";
    echo "<td><strong>$total_packages</strong></td>";
    echo "</tr>";
    echo "</table>";
}

// Vérifier la table affiliate_payments
echo "<h3>Table affiliate_payments :</h3>";
$payments = $pdo->query("SELECT * FROM affiliate_payments WHERE affiliate_id = $affiliate_id ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

if (empty($payments)) {
    echo "<p style='color: blue;'>ℹ️ Aucun paiement enregistré pour cet affilié</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Montant</th><th>Date</th><th>Statut</th><th>Raison</th><th>Colis</th></tr>";
    foreach ($payments as $payment) {
        echo "<tr>";
        echo "<td>{$payment['id']}</td>";
        echo "<td>{$payment['montant']} MAD</td>";
        echo "<td>{$payment['date_paiement']}</td>";
        echo "<td>{$payment['statut']}</td>";
        echo "<td>{$payment['raison']}</td>";
        echo "<td>{$payment['colis']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}
?> 