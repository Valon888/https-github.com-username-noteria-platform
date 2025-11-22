<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistemi i Faturimit | Noteria</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background: var(--gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .welcome-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
            text-align: center;
        }

        .welcome-header {
            background: var(--gradient);
            color: white;
            padding: 3rem 2rem;
        }

        .welcome-header h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .welcome-header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .welcome-content {
            padding: 3rem 2rem;
        }

        .login-options {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .login-btn {
            padding: 1.25rem 2rem;
            border-radius: 15px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }

        .admin-btn {
            background: linear-gradient(135deg, #1a56db, #1e40af);
            color: white;
        }

        .admin-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(26, 86, 219, 0.3);
        }

        .user-btn {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
        }

        .user-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(22, 163, 74, 0.3);
        }

        .features {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e5e7eb;
        }

        .features h3 {
            margin-bottom: 1rem;
            color: #374151;
        }

        .features ul {
            list-style: none;
            text-align: left;
            max-width: 300px;
            margin: 0 auto;
        }

        .features li {
            padding: 0.5rem 0;
            color: #6b7280;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .features li i {
            color: #16a34a;
            width: 20px;
        }
    </style>
</head>
<body>
    <div class="welcome-container">
        <div class="welcome-header">
            <h1><i class="fas fa-robot"></i></h1>
            <h1>Sistemi i Faturimit</h1>
            <p>Automatizimi dhe menaxhimi i pagesave</p>
        </div>

        <div class="welcome-content">
            <div class="login-options">
                <a href="admin_login.php" class="login-btn admin-btn">
                    <i class="fas fa-shield-alt"></i>
                    Hyrje si Administrator
                </a>

                <a href="login.php" class="login-btn user-btn">
                    <i class="fas fa-user"></i>
                    Hyrje si Përdorues
                </a>
            </div>

            <div class="features">
                <h3><i class="fas fa-star"></i> Veçoritë Kryesore</h3>
                <ul>
                    <li><i class="fas fa-clock"></i> Faturim automatik në ora 07:00</li>
                    <li><i class="fas fa-credit-card"></i> Procesim automatik i pagesave</li>
                    <li><i class="fas fa-chart-bar"></i> Statistika dhe raporte</li>
                    <li><i class="fas fa-bell"></i> Njoftime automatike email</li>
                    <li><i class="fas fa-cog"></i> Konfigurim i lehtë</li>
                    <li><i class="fas fa-lock"></i> Sistem i sigurt me nivele qasje</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>