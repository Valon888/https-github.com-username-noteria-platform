<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Info Panel | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', Arial, sans-serif;
            background: linear-gradient(120deg, #e2eafc 0%, #f8fafc 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
        .container {
            max-width: 1100px;
            margin: 40px auto 0 auto;
            padding: 32px 24px;
            border-radius: 22px;
            background: #fff;
            box-shadow: 0 8px 32px rgba(44,108,223,0.10);
            min-height: 80vh;
        }
        h1 {
            color: #2d6cdf;
            margin-bottom: 32px;
            font-size: 2.2rem;
            font-weight: 800;
            text-align: center;
            letter-spacing: 1px;
        }
        h2 {
            color: #184fa3;
            margin-top: 36px;
            margin-bottom: 18px;
            font-size: 1.3rem;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .card {
            background: #f8fafc;
            border-radius: 18px;
            padding: 24px 18px;
            margin-bottom: 36px;
            box-shadow: 0 4px 24px rgba(44,108,223,0.06);
            transition: box-shadow 0.2s, transform 0.2s;
            animation: fadeInUp 0.7s cubic-bezier(.39,.575,.56,1) both;
        }
        .card:hover {
            box-shadow: 0 8px 32px rgba(44,108,223,0.13);
            transform: translateY(-3px) scale(1.01);
        }
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 12px;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(44,108,223,0.08);
            animation: fadeInUp 0.7s cubic-bezier(.39,.575,.56,1) both;
        }
        th, td {
            padding: 14px 12px;
            text-align: left;
        }
        th {
            background: #e2eafc;
            color: #184fa3;
            font-weight: 800;
            font-size: 1.05rem;
            letter-spacing: 0.5px;
        }
        tr:nth-child(even) {
            background: #f8fafc;
        }
        tr:hover td {
            background: #e2eafc;
        }
        .no-data, .error {
            color: #d32f2f;
            background: #ffeaea;
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 22px;
            font-size: 1.08rem;
            text-align: center;
            border-left: 5px solid #d32f2f;
        }
        .success {
            color: #388e3c;
            background: #eafaf1;
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 22px;
            font-size: 1.08rem;
            text-align: center;
            border-left: 5px solid #388e3c;
        }
        @keyframes fadeInUp {
            0% { opacity: 0; transform: translateY(40px); }
            100% { opacity: 1; transform: none; }
        }
        @media (max-width: 900px) {
            .container { padding: 10px; }
            .card { padding: 12px; }
            table, th, td { font-size: 0.98rem; }
            h1 { font-size: 1.5rem; }
        }
        @media (max-width: 600px) {
            .container { padding: 2px; }
            .card { padding: 6px; }
            table, th, td { font-size: 0.93rem; }
            h1 { font-size: 1.1rem; }
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Paneli i Informacionit - Noteria</h1>
    <?php
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    require_once 'confidb.php';

    // Zyrat
    echo '<div class="card">';
    echo '<h2>Lista e zyrave noteriale</h2>';
    $query = "SELECT * FROM zyrat";
    $stmt = $pdo->query($query);
    if ($stmt) {
        $zyrat = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($zyrat) > 0) {
            echo "<table>";
            echo "<tr><th>ID</th><th>Emri</th><th>Adresa</th><th>Telefon</th><th>Email</th></tr>";
            foreach ($zyrat as $zyra) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($zyra['id']) . "</td>";
                echo "<td>" . htmlspecialchars($zyra['emri']) . "</td>";
                echo "<td>" . htmlspecialchars($zyra['adresa'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($zyra['telefon'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($zyra['email'] ?? 'N/A') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo '<div class="no-data">Nuk ka zyre të regjistruara.</div>';
        }
    } else {
        echo '<div class="error">Gabim në marrjen e të dhënave të zyrave.</div>';
    }
    echo '</div>';

    // Perdoruesit
    echo '<div class="card">';
    echo '<h2>Lista e përdoruesve</h2>';
    $query = "SELECT u.id, u.emri, u.mbiemri, u.email, u.roli, u.zyra_id, z.emri as zyra_emri 
              FROM users u 
              LEFT JOIN zyrat z ON u.zyra_id = z.id";
    $stmt = $pdo->query($query);
    if ($stmt) {
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($users) > 0) {
            echo "<table>";
            echo "<tr><th>ID</th><th>Emri</th><th>Mbiemri</th><th>Email</th><th>Roli</th><th>Zyra ID</th><th>Emri i Zyrës</th></tr>";
            foreach ($users as $user) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($user['id']) . "</td>";
                echo "<td>" . htmlspecialchars($user['emri']) . "</td>";
                echo "<td>" . htmlspecialchars($user['mbiemri']) . "</td>";
                echo "<td>" . htmlspecialchars($user['email']) . "</td>";
                echo "<td>" . htmlspecialchars($user['roli']) . "</td>";
                echo "<td>" . htmlspecialchars($user['zyra_id'] ?? 'NULL') . "</td>";
                echo "<td>" . htmlspecialchars($user['zyra_emri'] ?? 'Pa zyrë') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo '<div class="no-data">Nuk ka përdorues të regjistruar.</div>';
        }
    } else {
        echo '<div class="error">Gabim në marrjen e të dhënave të përdoruesve.</div>';
    }
    echo '</div>';

    // Agim Sylejmani
    echo '<div class="card">';
    echo "<h2>Përdoruesi 'Agim Sylejmani'</h2>";
    $query = "SELECT u.id, u.emri, u.mbiemri, u.email, u.roli, u.zyra_id, z.emri as zyra_emri 
              FROM users u 
              LEFT JOIN zyrat z ON u.zyra_id = z.id
              WHERE u.emri LIKE '%Agim%' AND u.mbiemri LIKE '%Sylejman%'";
    $stmt = $pdo->query($query);
    if ($stmt) {
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($users) > 0) {
            echo "<table>";
            echo "<tr><th>ID</th><th>Emri</th><th>Mbiemri</th><th>Email</th><th>Roli</th><th>Zyra ID</th><th>Emri i Zyrës</th></tr>";
            foreach ($users as $user) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($user['id']) . "</td>";
                echo "<td>" . htmlspecialchars($user['emri']) . "</td>";
                echo "<td>" . htmlspecialchars($user['mbiemri']) . "</td>";
                echo "<td>" . htmlspecialchars($user['email']) . "</td>";
                echo "<td>" . htmlspecialchars($user['roli']) . "</td>";
                echo "<td>" . htmlspecialchars($user['zyra_id'] ?? 'NULL') . "</td>";
                echo "<td>" . htmlspecialchars($user['zyra_emri'] ?? 'Pa zyrë') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo '<div class="no-data">Nuk u gjet asnjë përdorues me emrin Agim Sylejmani.</div>';
        }
    } else {
        echo '<div class="error">Gabim në marrjen e të dhënave për përdoruesin Agim Sylejmani.</div>';
    }
    echo '</div>';

    // Zyra Agim Sylejmani në Viti
    echo '<div class="card">';
    echo "<h2>Zyra 'Noteria Agim Sylejmani në Viti'</h2>";
    $query = "SELECT * FROM zyrat WHERE emri LIKE '%Agim%' AND emri LIKE '%Viti%'";
    $stmt = $pdo->query($query);
    if ($stmt) {
        $zyrat = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($zyrat) > 0) {
            echo "<table>";
            echo "<tr><th>ID</th><th>Emri</th><th>Adresa</th><th>Telefon</th><th>Email</th></tr>";
            foreach ($zyrat as $zyra) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($zyra['id']) . "</td>";
                echo "<td>" . htmlspecialchars($zyra['emri']) . "</td>";
                echo "<td>" . htmlspecialchars($zyra['adresa'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($zyra['telefon'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($zyra['email'] ?? 'N/A') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo '<div class="no-data">Nuk u gjet asnjë zyrë me emrin Noteria Agim Sylejmani në Viti.</div>';
        }
    } else {
        echo '<div class="error">Gabim në marrjen e të dhënave për zyrën Noteria Agim Sylejmani në Viti.</div>';
    }
    echo '</div>';
    ?>
</div>
</body>
</html>