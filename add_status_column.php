<?php
// add_status_column.php - Shton kolonën 'status' në tabelën 'noteri' nëse nuk ekziston

require_once 'config.php';

try {
    // Kontrollo nëse ekziston kolona 'status'
    $stmt = $pdo->query("SHOW COLUMNS FROM noteri LIKE 'status'");
    $columnExists = ($stmt->rowCount() > 0);
    
    if (!$columnExists) {
        // Shto kolonën 'status' nëse nuk ekziston
        $pdo->exec("ALTER TABLE noteri ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active'");
        echo "<div style='background-color: #dff0d8; color: #3c763d; padding: 15px; margin: 20px; border-radius: 5px;'>
                Kolona 'status' u shtua me sukses në tabelën 'noteri'.<br>
                Të gjithë noterët ekzistues janë vendosur automatikisht si 'active'.
              </div>";
    } else {
        echo "<div style='background-color: #d9edf7; color: #31708f; padding: 15px; margin: 20px; border-radius: 5px;'>
                Kolona 'status' ekziston tashmë në tabelën 'noteri'.
              </div>";
    }
    
    // Kontrollo nëse ekziston kolona 'subscription_type'
    $stmt = $pdo->query("SHOW COLUMNS FROM noteri LIKE 'subscription_type'");
    $subscriptionTypeExists = ($stmt->rowCount() > 0);
    
    if (!$subscriptionTypeExists) {
        // Shto kolonën 'subscription_type' nëse nuk ekziston
        $pdo->exec("ALTER TABLE noteri ADD COLUMN subscription_type ENUM('standard', 'premium', 'custom') DEFAULT 'standard'");
        echo "<div style='background-color: #dff0d8; color: #3c763d; padding: 15px; margin: 20px; border-radius: 5px;'>
                Kolona 'subscription_type' u shtua me sukses në tabelën 'noteri'.<br>
                Të gjithë noterët ekzistues janë vendosur automatikisht si 'standard'.
              </div>";
    } else {
        echo "<div style='background-color: #d9edf7; color: #31708f; padding: 15px; margin: 20px; border-radius: 5px;'>
                Kolona 'subscription_type' ekziston tashmë në tabelën 'noteri'.
              </div>";
    }
    
    // Kontrollo nëse ekziston kolona 'custom_price'
    $stmt = $pdo->query("SHOW COLUMNS FROM noteri LIKE 'custom_price'");
    $customPriceExists = ($stmt->rowCount() > 0);
    
    if (!$customPriceExists) {
        // Shto kolonën 'custom_price' nëse nuk ekziston
        $pdo->exec("ALTER TABLE noteri ADD COLUMN custom_price DECIMAL(10,2) DEFAULT NULL");
        echo "<div style='background-color: #dff0d8; color: #3c763d; padding: 15px; margin: 20px; border-radius: 5px;'>
                Kolona 'custom_price' u shtua me sukses në tabelën 'noteri'.
              </div>";
    } else {
        echo "<div style='background-color: #d9edf7; color: #31708f; padding: 15px; margin: 20px; border-radius: 5px;'>
                Kolona 'custom_price' ekziston tashmë në tabelën 'noteri'.
              </div>";
    }
    
    // Kontrollo nëse ekziston kolona 'data_regjistrimit'
    $stmt = $pdo->query("SHOW COLUMNS FROM noteri LIKE 'data_regjistrimit'");
    $dataRegjistrimitExists = ($stmt->rowCount() > 0);
    
    if (!$dataRegjistrimitExists) {
        // Shto kolonën 'data_regjistrimit' nëse nuk ekziston
        $pdo->exec("ALTER TABLE noteri ADD COLUMN data_regjistrimit DATETIME DEFAULT CURRENT_TIMESTAMP");
        echo "<div style='background-color: #dff0d8; color: #3c763d; padding: 15px; margin: 20px; border-radius: 5px;'>
                Kolona 'data_regjistrimit' u shtua me sukses në tabelën 'noteri'.<br>
                Data e regjistrimit për noterët ekzistues është vendosur automatikisht si data aktuale.
              </div>";
    } else {
        echo "<div style='background-color: #d9edf7; color: #31708f; padding: 15px; margin: 20px; border-radius: 5px;'>
                Kolona 'data_regjistrimit' ekziston tashmë në tabelën 'noteri'.
              </div>";
    }
    
    // Kontrollo nëse ekziston kolona 'operator'
    $stmt = $pdo->query("SHOW COLUMNS FROM noteri LIKE 'operator'");
    $operatorExists = ($stmt->rowCount() > 0);
    
    if (!$operatorExists) {
        // Shto kolonën 'operator' nëse nuk ekziston
        $pdo->exec("ALTER TABLE noteri ADD COLUMN operator VARCHAR(100) DEFAULT NULL");
        echo "<div style='background-color: #dff0d8; color: #3c763d; padding: 15px; margin: 20px; border-radius: 5px;'>
                Kolona 'operator' u shtua me sukses në tabelën 'noteri'.
              </div>";
    } else {
        echo "<div style='background-color: #d9edf7; color: #31708f; padding: 15px; margin: 20px; border-radius: 5px;'>
                Kolona 'operator' ekziston tashmë në tabelën 'noteri'.
              </div>";
    }
    
    // Shfaq strukturën e re të tabelës
    $stmt = $pdo->query("DESCRIBE noteri");
    echo "<h2>Struktura aktuale e tabelës 'noteri':</h2>";
    echo "<pre>";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    echo "</pre>";
    
    echo "<div style='text-align: center; margin: 20px;'>
            <a href='admin_noters.php' style='background-color: #337ab7; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>
                Kthehu te menaxhimi i noterëve
            </a>
          </div>";
    
} catch (PDOException $e) {
    echo "<div style='background-color: #f2dede; color: #a94442; padding: 15px; margin: 20px; border-radius: 5px;'>
            Gabim: " . $e->getMessage() . "
          </div>";
}