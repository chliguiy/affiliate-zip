<?php
$current_page = basename($_SERVER['PHP_SELF']);

// Inclure la classe AdminPermissions et initialiser les permissions
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/AdminPermissions.php';

if (!isset($_SESSION['admin_id'])) {
    $permissions = new class {
        public function __call($name, $arguments) {
            return false;
        }
    };
} else {
    $database = new Database();
    $pdo = $database->getConnection();
    $permissions = new AdminPermissions($pdo, $_SESSION['admin_id']);
}

$menu_items = [
    [
        'href' => 'dashboard.php',
        'icon' => 'fas fa-home me-2',
        'label' => 'Tableau de bord',
        'permission' => 'canViewDashboard',
    ],
    [
        'href' => 'messages.php',
        'icon' => 'fas fa-envelope me-2',
        'label' => 'Message',
        'permission' => 'canViewDashboard',
    ],
    [
        'href' => 'users.php',
        'icon' => 'fas fa-users me-2',
        'label' => 'Utilisateurs',
        'permission' => 'canManageUsers',
    ],
    [
        'href' => 'affiliates.php',
        'icon' => 'fas fa-user-tie me-2',
        'label' => 'Affiliés',
        'permission' => 'canManageUsers',
        'active_pages' => ['affiliates.php', 'affiliate_details.php'],
    ],
    [
        'href' => 'orders.php',
        'icon' => 'fas fa-shopping-cart me-2',
        'label' => 'Commandes',
        'permission' => 'canManageOrders',
    ],
    [
        'href' => 'affiliate_orders.php',
        'icon' => 'fas fa-list-alt me-2',
        'label' => 'Affiliés & Commandes',
        'permission' => 'canManageOrders',
    ],
    [
        'href' => 'manage_admins.php',
        'icon' => 'fas fa-user-shield me-2',
        'label' => 'Gestion des Admins',
        'permission' => 'canManageAdmins',
    ],
    [
        'label' => 'Gestion du stock',
        'icon' => 'fas fa-box me-2',
        'permission' => 'canManageStock',
        'submenu' => [
            [
                'href' => 'categories.php',
                'icon' => 'fas fa-folder me-2',
                'label' => 'Catégories',
            ],
            [
                'href' => 'colors.php',
                'icon' => 'fas fa-palette me-2',
                'label' => 'Couleurs',
            ],
            [
                'href' => 'sizes.php',
                'icon' => 'fas fa-ruler me-2',
                'label' => 'Tailles',
            ],
            [
                'href' => 'products.php',
                'icon' => 'fas fa-box me-2',
                'label' => 'Produits',
            ],
        ],
        'active_pages' => ['categories.php', 'colors.php', 'sizes.php', 'products.php'],
    ],
    [
        'label' => 'Équipe',
        'icon' => 'fas fa-users-cog me-2',
        'permission' => 'canManageStock',
        'submenu' => [
            
            [
                'href' => 'equipe_confirmateurs.php',
                'icon' => 'fas fa-user-check me-2',
                'label' => 'Liste des confirmateurs',
            ],
        ],
        'active_pages' => ['equipe_membres.php', 'equipe_confirmateurs.php'],
    ],
    [
        'href' => 'sales.php',  
        'icon' => 'fas fa-chart-line me-2',
        'label' => 'Ventes',
        'permission' => 'canViewReports',
    ],
    [
        'href' => 'pay_affiliates.php',
        'icon' => 'fas fa-money-check-alt me-2',
        'label' => 'Paiement Affiliés',
        'permission' => 'canViewReports',
    ],
    [
        'href' => '../logout.php',
        'icon' => 'fas fa-sign-out-alt me-2',
        'label' => 'Déconnexion',
        'permission' => null, // toujours visible
    ],
];
?>

<style>
    .admin-sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        width: 280px;
        background: #2c3e50;
        color: #ecf0f1;
        padding: 1rem 0;
        z-index: 1000;
    }

    .admin-sidebar .nav-link {
        color: #ecf0f1 !important;
        padding: 0.8rem 1.5rem;
        opacity: 0.8;
        transition: all 0.3s;
    }

    .admin-sidebar .nav-link:hover,
    .admin-sidebar .nav-link.active {
        
        opacity: 1;
        background: rgba(255, 255, 255, 0.1);
    }

    .admin-sidebar .nav-link i {
        width: 24px;
        text-align: center;
        margin-right: 8px;
    }

    .admin-header {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        margin-bottom: 1rem;
    }

    .admin-header h1 {
        font-size: 1.5rem;
        margin: 0;
        color: #ecf0f1;
    }

    .admin-header p {
        font-size: 0.9rem;
        margin: 0;
        opacity: 0.8;
    }

    .nav-section {
        margin-bottom: 1rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        padding-bottom: 1rem;
    }

    .nav-section:last-child {
        border-bottom: none;
    }

    .nav-section-title {
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #95a5a6;
        padding: 0.5rem 1.5rem;
        margin-bottom: 0.5rem;
    }

    li.active > a {
        color: #fff !important;
        background: #2563eb !important;
        font-weight: bold;
        border-radius: 5px;
    }
    .admin-content {
        margin-left: 280px;
    }
</style>

<div class="admin-sidebar">
    <h4 class="mb-4">Admin Panel</h4>
    <ul class="nav flex-column">
        <?php foreach ($menu_items as $item): ?>
            <?php
            $perm = $item['permission'] ?? null;
            if ($perm && !$permissions->$perm()) continue;
            $active_pages = $item['active_pages'] ?? [$item['href'] ?? null];
            $is_active = in_array($current_page, $active_pages);
            if (isset($item['submenu'])): ?>
                <li class="<?= $is_active ? 'active' : '' ?>">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="collapse" data-bs-target="#<?= md5($item['label']) ?>">
                        <i class="<?= $item['icon'] ?>"></i> <?= $item['label'] ?>
                    </a>
                    <div class="collapse <?= $is_active ? 'show' : '' ?>" id="<?= md5($item['label']) ?>">
                        <ul class="nav flex-column ms-3">
                            <?php foreach ($item['submenu'] as $sub): ?>
                                <li class="<?= $current_page == $sub['href'] ? 'active' : '' ?>">
                                    <a class="nav-link" href="<?= $sub['href'] ?>">
                                        <i class="<?= $sub['icon'] ?>"></i> <?= $sub['label'] ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </li>
            <?php else: ?>
                <li class="<?= $is_active ? 'active' : '' ?>">
                    <a class="nav-link" href="<?= $item['href'] ?>">
                        <i class="<?= $item['icon'] ?>"></i> <?= $item['label'] ?>
                    </a>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>
</div> 