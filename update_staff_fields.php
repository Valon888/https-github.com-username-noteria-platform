<?php
// filepath: d:\xampp\htdocs\noteria\update_staff_fields.php
// Script për të shtuar fushat e stafit në databazë

require_once 'config.php';

try {
    // Shto kolonat e reja në tabelën 'zyrat'
    $queries = [
        "ALTER TABLE zyrat ADD COLUMN IF NOT EXISTS emri_noterit VARCHAR(100) NULL AFTER data_licences",
        "ALTER TABLE zyrat ADD COLUMN IF NOT EXISTS vitet_pervoje INT DEFAULT 0 NULL AFTER emri_noterit",
        "ALTER TABLE zyrat ADD COLUMN IF NOT EXISTS numri_punetoreve INT DEFAULT 1 NULL AFTER vitet_pervoje",
        "ALTER TABLE zyrat ADD COLUMN IF NOT EXISTS gjuhet VARCHAR(200) NULL AFTER numri_punetoreve",
        "ALTER TABLE zyrat ADD COLUMN IF NOT EXISTS staff_data JSON NULL AFTER gjuhet"
    ];

    foreach ($queries as $query) {
        $pdo->exec($query);
    }

    echo "
    <!DOCTYPE html>
    <html lang='sq'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1'>
        <title>Përditësimi i Databazës | Noteria</title>
        <link href='https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap' rel='stylesheet'>
        <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'>
        <style>
            :root {
                --primary: #1a56db;
                --primary-light: #3b82f6;
                --success: #059669;
            }
            body { 
                font-family: 'Montserrat', Arial, sans-serif;
                background: linear-gradient(145deg, #e2eafc 0%, #f8fafc 100%);
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
                padding: 20px;
            }
            .container {
                background: white;
                border-radius: 10px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
                padding: 40px;
                max-width: 600px;
                width: 100%;
                text-align: center;
            }
            h1 {
                color: var(--primary);
                margin-bottom: 20px;
            }
            .success-box {
                background-color: rgba(5, 150, 105, 0.1);
                border-left: 4px solid var(--success);
                padding: 20px;
                margin: 20px 0;
                text-align: left;
                border-radius: 4px;
            }
            .icon {
                color: var(--success);
                font-size: 50px;
                margin-bottom: 20px;
            }
            .btn {
                background: linear-gradient(90deg, var(--primary) 0%, var(--primary-light) 100%);
                color: white;
                border: none;
                border-radius: 5px;
                padding: 12px 24px;
                font-size: 16px;
                font-weight: bold;
                cursor: pointer;
                text-decoration: none;
                display: inline-block;
                margin-top: 20px;
                transition: all 0.3s ease;
            }
            .btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            }
            ul {
                list-style-type: none;
                padding-left: 20px;
                margin-top: 10px;
            }
            li {
                margin-bottom: 8px;
            }
            li:before {
                content: '✓';
                color: var(--success);
                margin-right: 8px;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <i class='fas fa-database icon'></i>
            <h1>Databaza u përditësua me sukses</h1>
            <div class='success-box'>
                <p>Janë shtuar të gjitha kolonat e nevojshme për të mbështetur të dhënat e stafit:</p>
                <ul>
                    <li>emri_noterit - Emri i noterit kryesor</li>
                    <li>vitet_pervoje - Vitet e përvojës si noter</li>
                    <li>numri_punetoreve - Numri total i punëtorëve</li>
                    <li>gjuhet - Gjuhët e folura në zyrë</li>
                    <li>staff_data - Të dhënat e detajuara të stafit (JSON)</li>
                </ul>
                <p>Tani formulari i regjistrimit do të ruajë të gjitha të dhënat e stafit në databazë.</p>
            </div>
            <a href='zyrat_register.php' class='btn'>Kthehu te Formulari i Regjistrimit</a>
        </div>
    </body>
    </html>
    ";

} catch (PDOException $e) {
    echo "
    <!DOCTYPE html>
    <html lang='sq'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1'>
        <title>Gabim Përditësimi | Noteria</title>
        <link href='https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap' rel='stylesheet'>
        <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'>
        <style>
            :root {
                --primary: #1a56db;
                --error: #ef4444;
            }
            body { 
                font-family: 'Montserrat', Arial, sans-serif;
                background: linear-gradient(145deg, #e2eafc 0%, #f8fafc 100%);
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
                padding: 20px;
            }
            .container {
                background: white;
                border-radius: 10px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
                padding: 40px;
                max-width: 600px;
                width: 100%;
                text-align: center;
            }
            h1 {
                color: var(--error);
                margin-bottom: 20px;
            }
            .error-box {
                background-color: rgba(239, 68, 68, 0.1);
                border-left: 4px solid var(--error);
                padding: 20px;
                margin: 20px 0;
                text-align: left;
                border-radius: 4px;
            }
            .icon {
                color: var(--error);
                font-size: 50px;
                margin-bottom: 20px;
            }
            .btn {
                background: linear-gradient(90deg, var(--primary) 0%, var(--primary-light) 100%);
                color: white;
                border: none;
                border-radius: 5px;
                padding: 12px 24px;
                font-size: 16px;
                font-weight: bold;
                cursor: pointer;
                text-decoration: none;
                display: inline-block;
                margin-top: 20px;
                transition: all 0.3s ease;
            }
            .error-details {
                font-family: monospace;
                background: #f8fafc;
                padding: 10px;
                border-radius: 4px;
                overflow: auto;
                margin-top: 10px;
                font-size: 14px;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <i class='fas fa-exclamation-triangle icon'></i>
            <h1>Gabim gjatë përditësimit të databazës</h1>
            <div class='error-box'>
                <p>Ndodhi një gabim gjatë përditësimit të strukturës së databazës:</p>
                <div class='error-details'>" . htmlspecialchars($e->getMessage()) . "</div>
                <p>Ju lutemi kontaktoni administratorin e sistemit për ndihmë.</p>
            </div>
            <a href='zyrat_register.php' class='btn'>Kthehu te Formulari i Regjistrimit</a>
        </div>
    </body>
    </html>
    ";
}