<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Noteria</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            background: #1a1410;
            color: #333;
            font-family: 'Segoe UI', sans-serif;
            padding: 2rem;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: #faf7f2;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        h1 { color: #b8962e; margin-bottom: 2rem; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }
        table th {
            background: #b8962e;
            color: white;
            padding: 1rem;
            text-align: left;
        }
        table td {
            padding: 1rem;
            border-bottom: 1px solid #ddd;
        }
        table tr:hover { background: #f5f5f5; }
        .btn {
            padding: 0.5rem 1rem;
            margin-right: 0.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .btn-primary { background: #b8962e; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .form-group {
            margin-bottom: 1rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: bold;
        }
        input, select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        .success { color: green; padding: 1rem; background: #d4edda; border-radius: 4px; margin-bottom: 1rem; }
        .error { color: red; padding: 1rem; background: #f8d7da; border-radius: 4px; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 Menaxhimi i Përdoruesve</h1>

        <?php
        require_once __DIR__ . '/../config.php';

        // Handle user creation
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            $action = $_POST['action'];

            if ($action === 'create') {
                $email = trim($_POST['email'] ?? '');
                $emri = trim($_POST['emri'] ?? '');
                $mbiemri = trim($_POST['mbiemri'] ?? '');
                $password = trim($_POST['password'] ?? '');
                $roli = trim($_POST['roli'] ?? '');

                if (empty($email) || empty($emri) || empty($mbiemri) || empty($password)) {
                    echo '<div class="error">❌ Të gjithë fushat janë të detyrueshme</div>';
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_ARGON2ID);
                    $stmt = $pdo->prepare("INSERT INTO users (emri, mbiemri, email, password, roli) VALUES (?, ?, ?, ?, ?)");
                    
                    try {
                        $stmt->execute([$emri, $mbiemri, $email, $hashedPassword, $roli]);
                        echo '<div class="success">✅ Përdoruesi u krijua me sukses!</div>';
                    } catch (Exception $e) {
                        echo '<div class="error">❌ Gabim: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    }
                }
            } elseif ($action === 'delete') {
                $id = intval($_POST['id'] ?? 0);
                try {
                    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
                    echo '<div class="success">✅ Përdoruesi u fshi!</div>';
                } catch (Exception $e) {
                    echo '<div class="error">❌ Gabim: ' . $e->getMessage() . '</div>';
                }
            }
        }

        // Get all users
        $users = $pdo->query("SELECT id, emri, mbiemri, email, roli, created_at FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

        echo '<h2 style="margin-bottom: 1rem;">Përdoruesit Ekzistues</h2>';
        
        if (count($users) > 0) {
            echo '<table>';
            echo '<tr><th>ID</th><th>Emri</th><th>Email</th><th>Roli</th><th>Krijuar</th><th>Aksioni</th></tr>';
            
            foreach ($users as $user) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($user['id']) . '</td>';
                echo '<td>' . htmlspecialchars($user['emri']) . ' ' . htmlspecialchars($user['mbiemri']) . '</td>';
                echo '<td>' . htmlspecialchars($user['email']) . '</td>';
                echo '<td>' . htmlspecialchars($user['roli']) . '</td>';
                echo '<td>' . htmlspecialchars(substr($user['created_at'], 0, 10)) . '</td>';
                echo '<td>';
                echo '<form style="display:inline; margin:0;" method="POST">';
                echo '<input type="hidden" name="action" value="delete">';
                echo '<input type="hidden" name="id" value="' . $user['id'] . '">';
                echo '<button type="submit" class="btn btn-danger" onclick="return confirm(\'Jeni i sigurt?\')">Fshi</button>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</table>';
        } else {
            echo '<p style="color: #666;">Nuk ka përdorues në sistem</p>';
        }

        echo '<hr style="margin: 2rem 0;">';
        echo '<h2 style="margin-bottom: 1rem;">Krijo Përdorues Të Ri</h2>';
        ?>

        <form method="POST">
            <input type="hidden" name="action" value="create">
            
            <div class="form-group">
                <label for="emri">Emri *</label>
                <input type="text" id="emri" name="emri" required>
            </div>
            
            <div class="form-group">
                <label for="mbiemri">Mbiemri *</label>
                <input type="text" id="mbiemri" name="mbiemri" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" required value="endrit.hasani@gmail.com">
            </div>
            
            <div class="form-group">
                <label for="password">Fjalëkalim *</label>
                <input type="password" id="password" name="password" required value="Noteria@2024">
            </div>
            
            <div class="form-group">
                <label for="roli">Roli *</label>
                <select id="roli" name="roli" required>
                    <option value="">Zgjedh rolin</option>
                    <option value="user">Përdorues</option>
                    <option value="noter">Noter</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">Krijo Përdorues</button>
        </form>
    </div>
</body>
</html>
