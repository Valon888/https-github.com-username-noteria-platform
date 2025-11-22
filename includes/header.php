<?php
// Header file for Noteria
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Noteria - Platforma për Zyra Noteriale</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Stilet bazë */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        header {
            background: linear-gradient(135deg, #2d6cdf 0%, #184fa3 100%);
            color: white;
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .logo i {
            margin-right: 10px;
        }
        
        nav ul {
            list-style: none;
            display: flex;
            margin: 0;
            padding: 0;
        }
        
        nav ul li {
            margin-left: 20px;
        }
        
        nav ul li a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        nav ul li a:hover {
            color: #e6f0ff;
        }
        
        .user-menu {
            position: relative;
        }
        
        .user-menu-button {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            border-radius: 50px;
            padding: 8px 15px;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .user-menu-button:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .user-menu-dropdown {
            position: absolute;
            right: 0;
            top: 100%;
            background: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
            min-width: 200px;
            display: none;
            z-index: 100;
        }
        
        .user-menu-dropdown a {
            display: block;
            padding: 12px 15px;
            color: #333;
            text-decoration: none;
            transition: all 0.2s ease;
            border-bottom: 1px solid #eee;
        }
        
        .user-menu-dropdown a:last-child {
            border-bottom: none;
        }
        
        .user-menu-dropdown a:hover {
            background: #f8f9fa;
            color: #2d6cdf;
        }
        
        /* Stile shtesë */
        .main-content {
            padding: 30px 0;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            
            nav ul {
                margin-top: 15px;
                flex-wrap: wrap;
                justify-content: center;
            }
            
            nav ul li {
                margin: 5px 10px;
            }
            
            .user-menu {
                margin-top: 15px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="index.php" class="logo">
                    <i class="fas fa-balance-scale"></i> Noteria
                </a>
                
                <nav>
                    <ul>
                        <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="reservations.php"><i class="fas fa-calendar-alt"></i> Rezervimet</a></li>
                        <li><a href="services.php"><i class="fas fa-gavel"></i> Shërbimet</a></li>
                        <li><a href="contact.php"><i class="fas fa-envelope"></i> Kontakt</a></li>
                    </ul>
                </nav>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                <div class="user-menu">
                    <button class="user-menu-button" onclick="toggleUserMenu()">
                        <i class="fas fa-user-circle"></i>
                        <?php 
                        if (isset($_SESSION['emri'])) {
                            echo htmlspecialchars($_SESSION['emri']);
                        } else {
                            echo "Përdoruesi";
                        }
                        ?>
                        <i class="fas fa-caret-down"></i>
                    </button>
                    <div class="user-menu-dropdown" id="userMenuDropdown">
                        <a href="profile.php"><i class="fas fa-user"></i> Profili Im</a>
                        <a href="settings.php"><i class="fas fa-cog"></i> Cilësimet</a>
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Dilni</a>
                    </div>
                </div>
                <?php else: ?>
                <div>
                    <a href="login.php" style="color: white; margin-right: 15px; text-decoration: none;">
                        <i class="fas fa-sign-in-alt"></i> Hyrja
                    </a>
                    <a href="register.php" style="color: white; text-decoration: none;">
                        <i class="fas fa-user-plus"></i> Regjistrohu
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </header>
    
    <div class="main-content">
        <div class="container">
            <!-- Përmbajtja e faqes do të vijë këtu -->

<script>
function toggleUserMenu() {
    var dropdown = document.getElementById('userMenuDropdown');
    if (dropdown.style.display === 'block') {
        dropdown.style.display = 'none';
    } else {
        dropdown.style.display = 'block';
    }
}

// Mbyll menunë kur klikohet jashtë
window.onclick = function(event) {
    if (!event.target.matches('.user-menu-button') && 
        !event.target.matches('.user-menu-button *')) {
        var dropdown = document.getElementById('userMenuDropdown');
        if (dropdown && dropdown.style.display === 'block') {
            dropdown.style.display = 'none';
        }
    }
};
</script>