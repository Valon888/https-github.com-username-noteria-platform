<?php
/**
 * Create Zyrat (Notary Offices) Database Tables
 * Krijon tabelat e nevojshme për regjistrimin e zyrave noteriale
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'confidb.php';

try {
    // 1. Krijo tabelën zyrat (Notary Offices)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS zyrat (
            id INT AUTO_INCREMENT PRIMARY KEY,
            emri VARCHAR(255) NOT NULL UNIQUE,
            qyteti VARCHAR(100) NOT NULL,
            adresa TEXT NOT NULL,
            shteti VARCHAR(100) DEFAULT 'Kosova',
            email VARCHAR(255) UNIQUE NOT NULL,
            email2 VARCHAR(255),
            telefoni VARCHAR(20) UNIQUE NOT NULL,
            operator VARCHAR(50),
            
            -- Të dhënat e regjistrimit zyrtar
            numri_fiskal VARCHAR(20) UNIQUE NOT NULL,
            numri_biznesit VARCHAR(20) UNIQUE NOT NULL,
            numri_licences VARCHAR(20) UNIQUE NOT NULL,
            data_licences DATE,
            
            -- Të dhënat bankare
            banka VARCHAR(255),
            iban VARCHAR(34) UNIQUE NOT NULL,
            llogaria VARCHAR(20),
            
            -- Të dhënat e stafit
            emri_noterit VARCHAR(255),
            vitet_pervoje INT,
            numri_punetoreve INT DEFAULT 1,
            gjuhet VARCHAR(255),
            staff_data JSON,
            
            -- Të dhënat e pagesës
            pagesa DECIMAL(10, 2),
            
            -- Fjalëkalimi dhe autentifikimi
            fjalekalimi VARCHAR(255) NOT NULL,
            username VARCHAR(100) UNIQUE,
            
            -- Status
            status ENUM('aktiv', 'pasiv', 'ne_verifikim', 'i_fshire') DEFAULT 'ne_verifikim',
            verified BOOLEAN DEFAULT FALSE,
            verification_code VARCHAR(100),
            verification_date TIMESTAMP NULL,
            
            -- Abonimi
            abonim_id INT,
            
            -- Audit fields
            data_regjistrimit TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            
            -- Indexes
            INDEX idx_qyteti (qyteti),
            INDEX idx_status (status),
            INDEX idx_email (email),
            INDEX idx_telefoni (telefoni),
            INDEX idx_numri_fiskal (numri_fiskal),
            INDEX idx_abonim_id (abonim_id),
            FOREIGN KEY (abonim_id) REFERENCES abonimet(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p style='color:green;'>✓ Tabela 'zyrat' u krijua ose ekziston tashmë.</p>";
    
    // 2. Krijo tabelën noteri_abonimet (Notary Subscriptions)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS noteri_abonimet (
            id INT AUTO_INCREMENT PRIMARY KEY,
            noter_id INT NOT NULL,
            abonim_id INT NOT NULL,
            data_fillimit DATE NOT NULL,
            data_mbarimit DATE NOT NULL,
            status ENUM('aktiv', 'i_skaduar', 'i_nderprerë', 'i_rrezuar') DEFAULT 'aktiv',
            paguar DECIMAL(10, 2),
            menyra_pageses VARCHAR(100),
            transaksion_id VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_noter_id (noter_id),
            INDEX idx_abonim_id (abonim_id),
            INDEX idx_status (status),
            INDEX idx_data_fillimit (data_fillimit),
            INDEX idx_data_mbarimit (data_mbarimit),
            FOREIGN KEY (noter_id) REFERENCES zyrat(id) ON DELETE CASCADE,
            FOREIGN KEY (abonim_id) REFERENCES abonimet(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p style='color:green;'>✓ Tabela 'noteri_abonimet' u krijua ose ekziston tashmë.</p>";
    
    // 3. Krijo tabelën payment_logs (Payment Logs)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS payment_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            office_id INT,
            office_email VARCHAR(255),
            office_name VARCHAR(255),
            phone_number VARCHAR(20),
            operator VARCHAR(50),
            payment_method VARCHAR(100),
            payment_amount DECIMAL(10, 2),
            payment_details TEXT,
            transaction_id VARCHAR(100) UNIQUE,
            verification_status ENUM('pending', 'verified', 'failed', 'cancelled') DEFAULT 'pending',
            file_path VARCHAR(255),
            numri_fiskal VARCHAR(20),
            numri_biznesit VARCHAR(20),
            abonim_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            verified_at TIMESTAMP NULL,
            
            INDEX idx_office_email (office_email),
            INDEX idx_phone_number (phone_number),
            INDEX idx_transaction_id (transaction_id),
            INDEX idx_verification_status (verification_status),
            INDEX idx_abonim_id (abonim_id),
            INDEX idx_created_at (created_at),
            FOREIGN KEY (office_id) REFERENCES zyrat(id) ON DELETE SET NULL,
            FOREIGN KEY (abonim_id) REFERENCES abonimet(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p style='color:green;'>✓ Tabela 'payment_logs' u krijua ose ekziston tashmë.</p>";
    
    // 4. Krijo tabelën abonimet (Subscription Plans)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS abonimet (
            id INT AUTO_INCREMENT PRIMARY KEY,
            emri VARCHAR(255) NOT NULL,
            përshkrimi TEXT,
            çmimi DECIMAL(10, 2) NOT NULL,
            kohezgjatja INT DEFAULT 12,
            features JSON,
            status ENUM('aktiv', 'pasiv', 'i_fshire') DEFAULT 'aktiv',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p style='color:green;'>✓ Tabela 'abonimet' u krijua ose ekziston tashmë.</p>";
    
    // 5. Krijo tabelën staff (Stafi)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS staff (
            id INT AUTO_INCREMENT PRIMARY KEY,
            office_id INT NOT NULL,
            emri VARCHAR(255) NOT NULL,
            pozita VARCHAR(255),
            email VARCHAR(255),
            telefoni VARCHAR(20),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_office_id (office_id),
            FOREIGN KEY (office_id) REFERENCES zyrat(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p style='color:green;'>✓ Tabela 'staff' u krijua ose ekziston tashmë.</p>";
    
    // 6. Krijo tabelën office_documents (Dokumentet e Zyrës)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS office_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            office_id INT NOT NULL,
            document_type VARCHAR(100),
            file_name VARCHAR(255),
            file_path VARCHAR(255),
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            INDEX idx_office_id (office_id),
            FOREIGN KEY (office_id) REFERENCES zyrat(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p style='color:green;'>✓ Tabela 'office_documents' u krijua ose ekziston tashmë.</p>";
    
    echo "<div style='margin-top: 20px; padding: 20px; background: #ecfdf5; border-radius: 8px; border-left: 4px solid #059669;'>";
    echo "<h2 style='color: #059669; margin-top: 0;'>✅ Të gjitha tabelat u krijuan me sukses!</h2>";
    echo "<p>Bazën e të dhënave tani është gati për regjistrimin e zyrave noteriale.</p>";
    echo "<p><a href='zyrat_register.php' style='color: #059669; font-weight: bold; text-decoration: none;'>Hyr në formën e regjistrimit →</a></p>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div style='margin-top: 20px; padding: 20px; background: #fef2f2; border-radius: 8px; border-left: 4px solid #ef4444;'>";
    echo "<h2 style='color: #ef4444; margin-top: 0;'>❌ Gabim në krijimin e tabelave</h2>";
    echo "<p style='color: #dc2626;'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
    error_log("Database setup error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Përgatitja e Databazës - Noteria</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            max-width: 600px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        h1 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 2rem;
        }
        
        .subtitle {
            color: #718096;
            margin-bottom: 30px;
            font-size: 1.1rem;
        }
        
        p {
            margin: 15px 0;
            line-height: 1.6;
        }
        
        a {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
            font-weight: 600;
        }
        
        a:hover {
            background: #764ba2;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗄️ Përgatitja e Databazës</h1>
        <p class="subtitle">Sistem për regjistrimin e zyrave noteriale</p>
    </div>
</body>
</html>
