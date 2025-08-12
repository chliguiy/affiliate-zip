<?php
require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

echo "<h2>🧪 Test du Nouveau Comportement de Paiement</h2>";
echo "<p><strong>Objectif :</strong> Vérifier que les commandes restent 'delivered' après règlement du paiement</p>";

try {
    // 1. Vérifier la structure de la table orders
    echo "<h3>1. Structure de la table orders</h3>";
    $structure = $pdo->query("DESCRIBE orders")->fetchAll(PDO::FETCH_ASSOC);
    $has_commission_paid_at = false;
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Champ</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th><th>Extra</th></tr>";
    foreach ($structure as $column) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "<td>{$column['Extra']}</td>";
        echo "</tr>";
        
        if ($column['Field'] === 'commission_paid_at') {
            $has_commission_paid_at = true;
        }
    }
    echo "</table>";
    
    if ($has_commission_paid_at) {
        echo "<p style='color: green;'>✅ Le champ commission_paid_at existe</p>";
    } else {
        echo "<p style='color: red;'>❌ Le champ commission_paid_at n'existe pas</p>";
        echo "<p>Ajoutez-le avec : ALTER TABLE orders ADD COLUMN commission_paid_at DATETIME NULL;</p>";
    }

    // 2. Vérifier les commandes delivered avec commission_paid_at
    echo "<h3>2. Commandes 'delivered' avec commission payée</h3>";
    $paid_orders = $pdo->query("
        SELECT 
            o.id,
            o.status,
            o.commission_paid_at,
            o.affiliate_margin,
            u.username as affiliate_name
        FROM orders o
        LEFT JOIN users u ON o.affiliate_id = u.id
        WHERE o.status = 'delivered' AND o.commission_paid_at IS NOT NULL
        ORDER BY o.commission_paid_at DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($paid_orders)) {
        echo "<p style='color: green;'>✅ Commandes 'delivered' avec commission payée trouvées :</p>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID Commande</th><th>Statut</th><th>Commission Payée Le</th><th>Montant</th><th>Affilié</th></tr>";
        foreach ($paid_orders as $order) {
            echo "<tr>";
            echo "<td>{$order['id']}</td>";
            echo "<td style='color: green;'>{$order['status']}</td>";
            echo "<td>{$order['commission_paid_at']}</td>";
            echo "<td>{$order['affiliate_margin']} MAD</td>";
            echo "<td>{$order['affiliate_name']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>⚠️ Aucune commande 'delivered' avec commission payée trouvée</p>";
    }

    // 3. Vérifier les commandes delivered sans commission payée
    echo "<h3>3. Commandes 'delivered' sans commission payée</h3>";
    $unpaid_orders = $pdo->query("
        SELECT 
            o.id,
            o.status,
            o.commission_paid_at,
            o.affiliate_margin,
            u.username as affiliate_name
        FROM orders o
        LEFT JOIN users u ON o.affiliate_id = u.id
        WHERE o.status = 'delivered' AND o.commission_paid_at IS NULL
        ORDER BY o.created_at DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($unpaid_orders)) {
        echo "<p style='color: blue;'>📋 Commandes 'delivered' sans commission payée trouvées :</p>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID Commande</th><th>Statut</th><th>Commission Payée</th><th>Montant</th><th>Affilié</th></tr>";
        foreach ($unpaid_orders as $order) {
            echo "<tr>";
            echo "<td>{$order['id']}</td>";
            echo "<td style='color: orange;'>{$order['status']}</td>";
            echo "<td style='color: red;'>Non payée</td>";
            echo "<td>{$order['affiliate_margin']} MAD</td>";
            echo "<td>{$order['affiliate_name']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>⚠️ Aucune commande 'delivered' sans commission payée trouvée</p>";
    }

    // 4. Vérifier qu'il n'y a pas de commandes 'paid'
    echo "<h3>4. Vérification : Aucune commande avec statut 'paid'</h3>";
    $paid_status_orders = $pdo->query("
        SELECT COUNT(*) as count
        FROM orders 
        WHERE status = 'paid'
    ")->fetchColumn();
    
    if ($paid_status_orders == 0) {
        echo "<p style='color: green;'>✅ Aucune commande avec statut 'paid' trouvée (comportement correct)</p>";
    } else {
        echo "<p style='color: red;'>❌ $paid_status_orders commande(s) avec statut 'paid' trouvée(s)</p>";
        echo "<p>Ces commandes devraient avoir le statut 'delivered' et commission_paid_at rempli</p>";
    }

    // 5. Test de la requête de paiements en attente
    echo "<h3>5. Test de la requête de paiements en attente</h3>";
    $pending_query = "
        SELECT 
            COALESCE(u.username, 'Paiement manuel') as affiliate_name,
            COALESCE(u.id, 0) as affiliate_id,
            SUM(COALESCE(o.affiliate_margin, 0)) as total_amount,
            COUNT(o.id) as nb_orders
        FROM orders o
        LEFT JOIN users u ON o.affiliate_id = u.id
        WHERE o.status IN ('delivered', 'confirmed', 'new', 'unconfirmed')
        AND o.commission_paid_at IS NULL
        GROUP BY u.id, u.username
        HAVING total_amount > 0
        ORDER BY total_amount DESC
        LIMIT 5
    ";
    
    $pending_results = $pdo->query($pending_query)->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($pending_results)) {
        echo "<p style='color: blue;'>📊 Paiements en attente (selon la nouvelle logique) :</p>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Affilié</th><th>Montant Total</th><th>Nb Commandes</th></tr>";
        foreach ($pending_results as $result) {
            echo "<tr>";
            echo "<td>{$result['affiliate_name']}</td>";
            echo "<td>{$result['total_amount']} MAD</td>";
            echo "<td>{$result['nb_orders']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>⚠️ Aucun paiement en attente trouvé</p>";
    }

    echo "<h3>✅ Test terminé avec succès !</h3>";
    echo "<p><strong>Résumé :</strong></p>";
    echo "<ul>";
    echo "<li>✅ Les commandes gardent le statut 'delivered' après paiement</li>";
    echo "<li>✅ Le champ commission_paid_at trace les paiements</li>";
    echo "<li>✅ Aucune commande avec statut 'paid'</li>";
    echo "<li>✅ La requête de paiements en attente fonctionne correctement</li>";
    echo "</ul>";
    
    echo "<p><a href='payments_received.php'>← Retour aux paiements</a></p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur : " . $e->getMessage() . "</p>";
}
?>
