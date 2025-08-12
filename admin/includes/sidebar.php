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
        'label' => 'Les paiements',
        'icon' => 'fas fa-dollar-sign me-2',
        'permission' => 'canViewReports',
        'submenu' => [
            [
                'href' => 'invoices.php',
                'icon' => 'fas fa-file-invoice me-2',
                'label' => 'Les factures',
            ],
            [
                'href' => 'payments_received.php',
                'icon' => 'fas fa-money-bill-wave me-2',
                'label' => 'Paiements Reçu',
            ],
            [
                'href' => 'payments_returned.php',
                'icon' => 'fas fa-undo me-2',
                'label' => 'Paiements Retour',
            ],
        ],
        'active_pages' => ['invoices.php', 'payments_received.php', 'payments_returned.php'],
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
    /* Bouton menu mobile */
    .mobile-menu-btn {
        display: none;
        position: fixed;
        top: 20px;
        left: 20px;
        z-index: 1001;
        background: #2563eb;
        color: white;
        border: none;
        border-radius: 8px;
        padding: 12px 16px;
        font-size: 1.2rem;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        transition: all 0.3s ease;
    }
    
    .mobile-menu-btn:hover {
        background: #1d4ed8;
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(37, 99, 235, 0.4);
    }
    
    .mobile-menu-btn:active {
        transform: translateY(0);
    }
    
    /* Overlay pour mobile */
    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 999;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .sidebar-overlay.active {
        opacity: 1;
    }

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
        overflow-y: auto;
        overflow-x: hidden;
        transition: transform 0.3s ease;
    }
    
    /* Styles pour la barre de défilement */
    .admin-sidebar::-webkit-scrollbar {
        width: 6px;
    }
    
    .admin-sidebar::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 3px;
    }
    
    .admin-sidebar::-webkit-scrollbar-thumb {
        background: rgba(37, 99, 235, 0.5);
        border-radius: 3px;
        transition: background 0.3s ease;
    }
    
    .admin-sidebar::-webkit-scrollbar-thumb:hover {
        background: rgba(37, 99, 235, 0.7);
    }
    
    /* Pour Firefox */
    .admin-sidebar {
        scrollbar-width: thin;
        scrollbar-color: rgba(37, 99, 235, 0.5) rgba(255, 255, 255, 0.1);
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
    
    /* Styles responsive */
    @media (max-width: 768px) {
        .mobile-menu-btn {
            display: block;
        }
        
        .admin-sidebar {
            transform: translateX(-100%);
        }
        
        .admin-sidebar.mobile-open {
            transform: translateX(0);
        }
        
        .sidebar-overlay {
            display: block;
        }
        
        /* Ajuster le contenu principal pour mobile */
        .admin-content {
            margin-left: 0 !important;
            padding-top: 80px !important;
        }
    }
    
    /* Desktop styles - garder le sidebar fixe */
    @media (min-width: 769px) {
        .admin-sidebar {
            transform: translateX(0) !important;
        }
    }
    
    .admin-content {
        margin-left: 265px;
    }

    /* Appliquer automatiquement la marge à gauche au contenu principal après le sidebar */
    .admin-sidebar ~ .container,
    .admin-sidebar ~ .container-fluid {
        margin-left: 265px;
    }

    /* Marges pour les colonnes principales dans la même ligne */
    .admin-sidebar ~ .col-md-9,
    .admin-sidebar ~ .col-lg-10 {
        margin-left: 265px;
    }

    /* Éviter les décalages supplémentaires à l'intérieur des lignes bootstrap */
    .admin-sidebar ~ .container .row,
    .admin-sidebar ~ .container-fluid .row {
        margin-left: 0;
    }

    /* Mobile override */
    @media (max-width: 768px) {
        .admin-sidebar ~ .container,
        .admin-sidebar ~ .container-fluid {
            margin-left: 0 !important;
            padding-top: 80px !important;
        }
        .admin-sidebar ~ .col-md-9,
        .admin-sidebar ~ .col-lg-10 {
            margin-left: 0 !important;
            padding-top: 80px !important;
        }
    }
</style>

<!-- Bouton menu mobile -->
<button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Menu">
    <i class="fas fa-bars"></i>
</button>

<!-- Overlay pour mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="admin-sidebar" id="adminSidebar">
    <div class="admin-header">
        <h1>Admin Panel</h1>
        <p>Gestion du système</p>
    </div>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.getElementById('adminSidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
    // Fonction pour ouvrir le menu mobile
    function openMobileMenu() {
        sidebar.classList.add('mobile-open');
        sidebarOverlay.classList.add('active');
        document.body.style.overflow = 'hidden'; // Empêcher le scroll
    }
    
    // Fonction pour fermer le menu mobile
    function closeMobileMenu() {
        sidebar.classList.remove('mobile-open');
        sidebarOverlay.classList.remove('active');
        document.body.style.overflow = ''; // Restaurer le scroll
    }
    
    // Événements pour le bouton menu
    mobileMenuBtn.addEventListener('click', function() {
        if (sidebar.classList.contains('mobile-open')) {
            closeMobileMenu();
        } else {
            openMobileMenu();
        }
    });
    
    // Fermer le menu en cliquant sur l'overlay
    sidebarOverlay.addEventListener('click', closeMobileMenu);
    
    // Fermer le menu en appuyant sur Échap
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('mobile-open')) {
            closeMobileMenu();
        }
    });
    
    // Fermer le menu quand on clique sur un lien (mobile seulement)
    const sidebarLinks = sidebar.querySelectorAll('.nav-link');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                closeMobileMenu();
            }
        });
    });
    
    // Gérer le redimensionnement de la fenêtre
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            closeMobileMenu();
        }
    });
});
</script> 