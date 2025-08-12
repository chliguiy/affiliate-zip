<?php
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$current_status = $_GET['status'] ?? '';
?>

<style>
    :root {
        --sidebar-bg1: #e0e0e0;
        --sidebar-bg2: #e0e0e0;
        --sidebar-color: #212529;
        --sidebar-accent: #007bff;
    }
    
    /* Bouton menu mobile */
    .mobile-menu-btn {
        display: none;
        position: fixed;
        top: 20px;
        left: 20px;
        z-index: 1001;
        background: var(--sidebar-accent);
        color: white;
        border: none;
        border-radius: 8px;
        padding: 12px 16px;
        font-size: 1.2rem;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
        transition: all 0.3s ease;
    }
    
    .mobile-menu-btn:hover {
        background: #0056b3;
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 123, 255, 0.4);
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
    
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        width: 260px;
        background: var(--sidebar-bg1);
        color: var(--sidebar-color);
        padding: 0 0 2rem 0;
        z-index: 1000;
        box-shadow: 2px 0 16px rgba(44,62,80,0.04);
        display: flex;
        flex-direction: column;
        transition: transform 0.3s ease;
        overflow-y: auto;
        overflow-x: hidden;
    }
    
    /* Styles pour la barre de défilement */
    .sidebar::-webkit-scrollbar {
        width: 6px;
    }
    
    .sidebar::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.1);
        border-radius: 3px;
    }
    
    .sidebar::-webkit-scrollbar-thumb {
        background: rgba(0, 123, 255, 0.5);
        border-radius: 3px;
        transition: background 0.3s ease;
    }
    
    .sidebar::-webkit-scrollbar-thumb:hover {
        background: rgba(0, 123, 255, 0.7);
    }
    
    /* Pour Firefox */
    .sidebar {
        scrollbar-width: thin;
        scrollbar-color: rgba(0, 123, 255, 0.5) rgba(0, 0, 0, 0.1);
    }
    
    .sidebar-header {
        padding: 2.2rem 0 1.2rem 0;
        text-align: center;
        border-bottom: 1px solid #e9ecef;
        margin-bottom: 1.5rem;
    }
    
        .brand-logo {
        display: flex;
        justify-content: center;
        align-items: center;
        margin-bottom: 1rem;
    }
    
    .logo-img {
        width: 150px;
        height: 150px;
        object-fit: contain;
        border-radius: 22px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }
    
    .logo-img:hover {
        transform: scale(1.05);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
    }
    .sidebar .nav {
        flex-direction: column;
        gap: 0.2rem;
        width: 100%;
    }
    .sidebar .nav-link {
        color: var(--sidebar-color) !important;
        padding: 0.85rem 2rem 0.85rem 1.5rem;
        border-radius: 8px 0 0 8px;
        margin: 0.1rem 0;
        font-size: 1.08rem;
        opacity: 0.92;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        font-weight: 500;
        border-left: 3px solid transparent;
        background: none;
    }
    .sidebar .nav-link i {
        margin-right: 12px;
        font-size: 1.1rem;
        min-width: 22px;
        text-align: center;
    }
    .sidebar .nav-link.active, .sidebar .nav-link:hover {
        background: #e9ecef !important;
        color: var(--sidebar-accent) !important;
        opacity: 1;
        border-left: 3px solid var(--sidebar-accent);
        font-weight: 600;
        transform: translateX(4px);
    }
    .sidebar .submenu {
        padding-left: 2.5rem;
        margin-bottom: 0.2rem;
    }
    .sidebar .submenu .nav-link {
        font-size: 0.98rem;
        padding: 0.5rem 1.5rem;
        border-radius: 6px 0 0 6px;
        opacity: 0.8;
        border-left: 2px solid transparent;
    }
    .sidebar .submenu .nav-link.active, .sidebar .submenu .nav-link:hover {
        background: rgba(255,255,255,0.13) !important;
        border-left: 2px solid #fff;
        opacity: 1;
        font-weight: 500;
    }
    .sidebar .logout {
        margin-top: auto;
        color: #fff !important;
        font-weight: 600;
        opacity: 0.9;
    }
    .sidebar .logout:hover {
        color: #fc5c7d !important;
        background: rgba(255,255,255,0.13) !important;
    }
    
    /* Styles responsive */
    @media (max-width: 768px) {
        .mobile-menu-btn {
            display: block;
        }
        
        .sidebar {
            transform: translateX(-100%);
        }
        
        .sidebar.mobile-open {
            transform: translateX(0);
        }
        
        .sidebar-overlay {
            display: block;
        }
        
        /* Ajuster le contenu principal pour mobile */
        .main-content {
            margin-left: 0 !important;
            padding-top: 80px !important;
        }
    }
    
    /* Desktop styles - garder le sidebar fixe */
    @media (min-width: 769px) {
        .sidebar {
            transform: translateX(0) !important;
        }
    }
</style>

<!-- Bouton menu mobile -->
<button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Menu">
    <i class="fas fa-bars"></i>
</button>

<!-- Overlay pour mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="brand-logo">
                    <img src="assets\images\logo.png" alt="SCAR AFFILIATE Logo" class="logo-img">
                </div>
            </div>
    <nav class="nav flex-column">
        <a href="dashboard.php" class="nav-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-th-large"></i> Dashboard
        </a>
        <div class="nav-item">
            <a href="orders.php" class="nav-link <?php echo $current_page === 'orders' && empty($current_status) ? 'active' : ''; ?>">
                <i class="fas fa-list-alt"></i> Commandes
            </a>
            <div class="submenu">
                <a href="orders.php?status=new" class="nav-link <?php echo $current_page === 'orders' && $current_status === 'new' ? 'active' : ''; ?>">Nouvelle</a>
                <a href="orders.php?status=unconfirmed" class="nav-link <?php echo $current_page === 'orders' && $current_status === 'unconfirmed' ? 'active' : ''; ?>">Non confirmé</a>
                <a href="orders.php?status=confirmed" class="nav-link <?php echo $current_page === 'orders' && $current_status === 'confirmed' ? 'active' : ''; ?>">Confirmé</a>
                <a href="orders.php?status=shipping" class="nav-link <?php echo $current_page === 'orders' && $current_status === 'shipping' ? 'active' : ''; ?>">En livraison</a>
                <a href="orders.php?status=delivered" class="nav-link <?php echo $current_page === 'orders' && $current_status === 'delivered' ? 'active' : ''; ?>">Livré</a>
                <a href="orders.php?status=returned" class="nav-link <?php echo $current_page === 'orders' && $current_status === 'returned' ? 'active' : ''; ?>">Retourné</a>
                <a href="orders.php?status=refused" class="nav-link <?php echo $current_page === 'orders' && $current_status === 'refused' ? 'active' : ''; ?>">Refusé</a>
                <a href="orders.php?status=cancelled" class="nav-link <?php echo $current_page === 'orders' && $current_status === 'cancelled' ? 'active' : ''; ?>">Annulé</a>
                <a href="orders.php?status=duplicate" class="nav-link <?php echo ($_GET['status'] ?? '') === 'duplicate' ? 'active' : ''; ?>"><i class="fas fa-copy"></i> Dupliqué</a>
                <a href="orders.php?status=changed" class="nav-link <?php echo ($_GET['status'] ?? '') === 'changed' ? 'active' : ''; ?>"><i class="fas fa-exchange-alt"></i> Changé</a>
            </div>
        </div>
        <a href="categories.php" class="nav-link <?php echo $current_page === 'categories' ? 'active' : ''; ?>">
            <i class="fas fa-folder"></i> Catégories
        </a>
        <a href="products.php" class="nav-link <?php echo $current_page === 'products' ? 'active' : ''; ?>">
            <i class="fas fa-box"></i> Produits
        </a>
        <a href="payments.php" class="nav-link <?php echo $current_page === 'payments' ? 'active' : ''; ?>">
            <i class="fas fa-wallet"></i> Paiements
        </a>
        <a href="claims.php" class="nav-link <?php echo $current_page === 'claims' ? 'active' : ''; ?>">
            <i class="fas fa-exclamation-triangle"></i> Réclamations
        </a>
        <a href="logout.php" class="nav-link logout mt-auto">
            <i class="fas fa-sign-out-alt"></i> Déconnexion
        </a>
    </nav>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.getElementById('sidebar');
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

<!-- HTML du menu latéral affilié ici --> 