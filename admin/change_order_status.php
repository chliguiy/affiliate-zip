<?php
require_once '../config/database.php';
$database = new Database();
$conn = $database->getConnection();

if (isset($_POST['order_id'], $_POST['new_status'])) {
    // DEBUG TEMPORAIRE : Afficher la valeur reçue
    file_put_contents(__DIR__ . '/debug_status.log', date('Y-m-d H:i:s') . ' | order_id=' . $_POST['order_id'] . ' | new_status=' . $_POST['new_status'] . "\n", FILE_APPEND);
    $order_id = (int)$_POST['order_id'];
    $new_status = $_POST['new_status'];

    // Liste blanche des statuts autorisés (anglais uniquement)
    $allowed_statuses = [
        'new', 'unconfirmed', 'confirmed', 'shipping', 'delivered', 'returned',
        'refused', 'cancelled', 'duplicate', 'changed', 'pending', 'processing'
    ];

    // Mapping français -> anglais pour compatibilité
    $status_map = [
        'en livraison' => 'shipping',
        'dupliqué' => 'duplicate',
        'changer' => 'changed'
    ];

    $new_status = trim(strtolower($new_status));
    if (isset($status_map[$new_status])) {
        $new_status = $status_map[$new_status];
    }

    // Si le statut n'est pas dans la liste blanche, on refuse la modification
    if (!in_array($new_status, $allowed_statuses)) {
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $order_id]);
}
// Rediriger vers la page précédente
header('Location: ' . $_SERVER['HTTP_REFERER']);
exit; 