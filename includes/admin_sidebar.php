<?php
// This file is included in admin pages for sidebar navigation
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar">
    <div class="logo">
        <a href="index.php">
            <img src="img/logo.png" alt="Noteria Logo">
        </a>
    </div>
    
    <div class="menu">
        <a href="admin_dashboard.php" class="menu-item <?php echo ($current_page == 'admin_dashboard.php') ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="manage_users.php" class="menu-item <?php echo ($current_page == 'manage_users.php') ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>Përdoruesit</span>
        </a>
        
        <a href="manage_documents.php" class="menu-item <?php echo ($current_page == 'manage_documents.php') ? 'active' : ''; ?>">
            <i class="fas fa-file-alt"></i>
            <span>Dokumentet</span>
        </a>
        
        <a href="manage_notaries.php" class="menu-item <?php echo ($current_page == 'manage_notaries.php') ? 'active' : ''; ?>">
            <i class="fas fa-stamp"></i>
            <span>Noteritë</span>
        </a>
        
        <a href="statistics.php" class="menu-item <?php echo ($current_page == 'statistics.php') ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i>
            <span>Statistikat</span>
        </a>
        
        <a href="security_cameras.php" class="menu-item <?php echo ($current_page == 'security_cameras.php') ? 'active' : ''; ?>">
            <i class="fas fa-video"></i>
            <span>Kamerat e Sigurisë</span>
            <span class="badge badge-alert">Ri</span>
        </a>
        
        <a href="settings.php" class="menu-item <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i>
            <span>Cilësimet</span>
        </a>
        
        <div class="admin-info">
            <div class="admin-name"><?php echo htmlspecialchars($_SESSION['emri'] . ' ' . $_SESSION['mbiemri']); ?></div>
            <div class="admin-role">Administrator</div>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Shkyçu
            </a>
        </div>
    </div>
</div>