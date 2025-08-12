<?php
require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

echo "<h2>Test du système de paiements</h2>";

try {
    // 1. Vérifier la table affiliate_payments
    echo "<h3>1. Vérification de la table affiliate_payments</h3>";
    $check_table = "SHOW TABLES LIKE 'affiliate_payments'";
    $table_exists = $pdo->query($check_table)->rowCount() > 0;
    
    if ($table_exists) {
        echo "<p style='color: green;'>✅ Table affiliate_payments existe</p>";
    } else {
        echo "<p style='color: red;'>❌ Table affiliate_payments n'existe pas</p>";
        exit();
    }

    // 2. Vérifier les commandes en attente
    echo "<h3>2. Commandes en attente</h3>";
    $pending_orders = $pdo->query("
        SELECT 
            u.username,
            COUNT(o.id) as nb_orders,
            SUM(o.affiliate_margin) as total_amount
        FROM orders o
        LEFT JOIN users u ON o.affiliate_id = u.id
        WHERE o.status IN ('delivered', 'confirmed', 'new', 'unconfirmed')
        GROUP BY o.affiliate_id, u.username
        HAVING total_amount > 0
    ")->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($pending_orders)) {
        echo "<p style='color: blue;'>📋 Commandes en attente trouvées :</p>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Affilié</th><th>Nb Commandes</th><th>Montant Total</th></tr>";
        foreach ($pending_orders as $order) {
            echo "<tr>";
            echo "<td>{$order['username']}</td>";
            echo "<td>{$order['nb_orders']}</td>";
            echo "<td>{$order['total_amount']} MAD</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>⚠️ Aucune commande en attente trouvée</p>";
    }

    // 3. Vérifier les paiements réglés
    echo "<h3>3. Paiements réglés</h3>";
    $settled_payments = $pdo->query("
        SELECT 
            u.username,
            COUNT(ap.id) as nb_payments,
            SUM(ap.montant) as total_amount
        FROM affiliate_payments ap
        LEFT JOIN users u ON ap.affiliate_id = u.id
        WHERE ap.statut = 'réglé'
        GROUP BY ap.affiliate_id, u.username
    ")->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($settled_payments)) {
        echo "<p style='color: blue;'>💰 Paiements réglés trouvés :</p>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Affilié</th><th>Nb Paiements</th><th>Montant Total</th></tr>";
        foreach ($settled_payments as $payment) {
            echo "<tr>";
            echo "<td>{$payment['username']}</td>";
            echo "<td>{$payment['nb_payments']}</td>";
            echo "<td>{$payment['total_amount']} MAD</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>⚠️ Aucun paiement réglé trouvé</p>";
    }

                    // 4. Test de la requête combinée
                echo "<h3>4. Test de la requête combinée</h3>";
                $combined_query = "
                    SELECT * FROM (
                        -- Paiements en attente (commandes non réglées)
                        SELECT 
                            COALESCE(u.username, 'Paiement manuel') as affiliate_name,
                            COALESCE(u.id, 0) as affiliate_id,
                            SUM(COALESCE(o.affiliate_margin, 0)) as total_amount,
                            SUM(oi.id IS NOT NULL) as total_packages,
                            MAX(o.created_at) as last_payment_date,
                            'En Attente' as status,
                            'pending' as status_type
                        FROM orders o
                        LEFT JOIN order_items oi ON o.id = oi.order_id
                        LEFT JOIN users u ON o.affiliate_id = u.id
                        WHERE o.status IN ('delivered', 'confirmed', 'new', 'unconfirmed')
                        AND o.commission_paid_at IS NULL
                        GROUP BY u.id, u.username
            
            UNION ALL
            
            -- Paiements réglés (depuis la table affiliate_payments)
            SELECT 
                COALESCE(u.username, 'Paiement manuel') as affiliate_name,
                COALESCE(u.id, 0) as affiliate_id,
                ap.montant as total_amount,
                ap.colis as total_packages,
                ap.date_paiement as last_payment_date,
                'Payé' as status,
                'paid' as status_type
            FROM affiliate_payments ap
            LEFT JOIN users u ON ap.affiliate_id = u.id
            WHERE ap.statut = 'réglé'
        ) combined_payments
        ORDER BY total_amount DESC
        LIMIT 10
    ";

    $combined_results = $pdo->query($combined_query)->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($combined_results)) {
        echo "<p style='color: blue;'>📊 Résultats de la requête combinée :</p>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Affilié</th><th>Montant</th><th>Colis</th><th>Dernière Date</th><th>Statut</th></tr>";
        foreach ($combined_results as $result) {
            $status_color = $result['status'] === 'En Attente' ? 'orange' : 'green';
            echo "<tr>";
            echo "<td>{$result['affiliate_name']}</td>";
            echo "<td>{$result['total_amount']} MAD</td>";
            echo "<td>{$result['total_packages']}</td>";
            echo "<td>{$result['last_payment_date']}</td>";
            echo "<td style='color: $status_color;'>{$result['status']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>⚠️ Aucun résultat dans la requête combinée</p>";
    }

    echo "<h3>✅ Test terminé avec succès !</h3>";
    echo "<p><a href='payments_received.php'>← Retour aux paiements</a></p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erreur : " . $e->getMessage() . "</p>";
}
?>
