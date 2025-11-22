<?php
// fix_database_columns.php - Korrigjon problemet me kolonat në databazë

require_once 'config.php';

// Stili për interfejsin
echo "<!DOCTYPE html>
<html lang='sq'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Korrigjimi i Strukturës së Databazës</title>
    <link href='https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap' rel='stylesheet'>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css'>
    <style>
        :root {
            --primary: #2563eb;
            --success: #16a34a;
            --danger: #dc2626;
            --warning: #f59e0b;
            --info: #0284c7;
            --body-bg: #f3f4f6;
            --card-bg: #ffffff;
            --text: #4b5563;
            --text-light: #6b7280;
            --text-dark: #374151;
            --border: #e5e7eb;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            font-size: 0.95rem;
            line-height: 1.6;
            color: var(--text);
            background-color: var(--body-bg);
            padding: 2rem;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: #fff;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        h1 {
            text-align: center;
            margin-bottom: 2rem;
            color: var(--primary);
            font-weight: 600;
            font-size: 1.8rem;
        }
        
        h2 {
            margin: 1.5rem 0;
            color: var(--text-dark);
            font-size: 1.4rem;
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 0.5rem;
        }
        
        .alert-success {
            background-color: #dcfce7;
            color: #166534;
            border-left: 4px solid #16a34a;
        }
        
        .alert-info {
            background-color: #dbeafe;
            color: #1e40af;
            border-left: 4px solid #2563eb;
        }
        
        .alert-warning {
            background-color: #fef3c7;
            color: #92400e;
            border-left: 4px solid #f59e0b;
        }
        
        .alert-danger {
            background-color: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }
        
        pre {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 0.5rem;
            overflow-x: auto;
            margin: 1rem 0;
            border: 1px solid #e5e7eb;
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background-color: var(--primary);
            color: white;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 500;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            background-color: #1d4ed8;
        }
        
        .btn-container {
            text-align: center;
            margin-top: 2rem;
        }
        
        .step {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            background-color: #ffffff;
        }
        
        .step-header {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .step-icon {
            margin-right: 0.5rem;
            font-size: 1.25rem;
            color: var(--primary);
        }
        
        .step-title {
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .step-body {
            padding-left: 1.75rem;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1><i class='fas fa-database'></i> Korrigjimi i Strukturës së Databazës</h1>";

try {
    // Merr strukturën aktuale të tabelës
    $stmt = $pdo->query("DESCRIBE noteri");
    $currentColumns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $currentColumns[$row['Field']] = $row;
    }
    
    echo "<div class='step'>
            <div class='step-header'>
                <i class='fas fa-check-circle step-icon'></i>
                <div class='step-title'>Kontrollimi i strukturës së tabelës</div>
            </div>
            <div class='step-body'>
                <p>U gjetën " . count($currentColumns) . " kolona në tabelën 'noteri'.</p>
            </div>
        </div>";
    
    // Kontrollo kolonat që nevojiten dhe përditëso ato
    $neededColumns = [
        'status' => [
            'exists' => isset($currentColumns['status']),
            'sql' => "ALTER TABLE noteri ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active'"
        ],
        'subscription_type' => [
            'exists' => isset($currentColumns['subscription_type']),
            'sql' => "ALTER TABLE noteri ADD COLUMN subscription_type ENUM('standard', 'premium', 'custom') DEFAULT 'standard'"
        ],
        'custom_price' => [
            'exists' => isset($currentColumns['custom_price']),
            'sql' => "ALTER TABLE noteri ADD COLUMN custom_price DECIMAL(10,2) DEFAULT NULL"
        ],
        'data_regjistrimit' => [
            'exists' => isset($currentColumns['data_regjistrimit']),
            'sql' => "ALTER TABLE noteri ADD COLUMN data_regjistrimit DATETIME DEFAULT CURRENT_TIMESTAMP"
        ],
        'operator' => [
            'exists' => isset($currentColumns['operator']),
            'sql' => "ALTER TABLE noteri ADD COLUMN operator VARCHAR(100) DEFAULT NULL"
        ]
    ];
    
    // Përditëso kolonat që mungojnë
    $updatedColumns = 0;
    foreach ($neededColumns as $column => $data) {
        echo "<div class='step'>
                <div class='step-header'>
                    <i class='fas fa-" . ($data['exists'] ? 'check' : 'plus') . " step-icon' style='color:" . ($data['exists'] ? 'var(--success)' : 'var(--primary)') . "'></i>
                    <div class='step-title'>Kolona '{$column}'</div>
                </div>
                <div class='step-body'>";
        
        if ($data['exists']) {
            echo "<div class='alert alert-info'>
                    Kolona '{$column}' ekziston tashmë në tabelën 'noteri'.
                  </div>";
        } else {
            try {
                $pdo->exec($data['sql']);
                $updatedColumns++;
                echo "<div class='alert alert-success'>
                        Kolona '{$column}' u shtua me sukses në tabelën 'noteri'.
                      </div>";
            } catch (PDOException $columnError) {
                if (strpos($columnError->getMessage(), 'Duplicate column') !== false) {
                    echo "<div class='alert alert-warning'>
                            Gabim gjatë shtimit të kolonës '{$column}': Kolona duket se ekziston, por me një konfigurim të ndryshëm.
                          </div>";
                    
                    // Përpiqu të përditësosh kolonën nëse ekziston
                    try {
                        $modifySql = "ALTER TABLE noteri MODIFY COLUMN {$column} ";
                        
                        switch($column) {
                            case 'status':
                                $modifySql .= "ENUM('active', 'inactive') DEFAULT 'active'";
                                break;
                            case 'subscription_type':
                                $modifySql .= "ENUM('standard', 'premium', 'custom') DEFAULT 'standard'";
                                break;
                            case 'custom_price':
                                $modifySql .= "DECIMAL(10,2) DEFAULT NULL";
                                break;
                            case 'data_regjistrimit':
                                $modifySql .= "DATETIME DEFAULT CURRENT_TIMESTAMP";
                                break;
                            case 'operator':
                                $modifySql .= "VARCHAR(100) DEFAULT NULL";
                                break;
                        }
                        
                        $pdo->exec($modifySql);
                        echo "<div class='alert alert-success'>
                                Kolona '{$column}' u përditësua me sukses.
                              </div>";
                        $updatedColumns++;
                    } catch (PDOException $modifyError) {
                        echo "<div class='alert alert-danger'>
                                Gabim gjatë përditësimit të kolonës '{$column}': " . $modifyError->getMessage() . "
                              </div>";
                    }
                } else {
                    echo "<div class='alert alert-danger'>
                            Gabim gjatë shtimit të kolonës '{$column}': " . $columnError->getMessage() . "
                          </div>";
                }
            }
        }
        
        echo "</div></div>"; // Mbyll step-body dhe step
    }
    
    // Shfaq mesazhin përfundimtar
    if ($updatedColumns > 0) {
        echo "<div class='alert alert-success'>
                <strong>Sukses!</strong> U përditësuan {$updatedColumns} kolona në databazë.
              </div>";
    } else {
        echo "<div class='alert alert-info'>
                <strong>Informacion:</strong> Të gjitha kolonat e nevojshme ekzistojnë tashmë në databazë. Nuk u bë asnjë ndryshim.
              </div>";
    }
    
    // Shfaq strukturën e re të tabelës
    $stmt = $pdo->query("DESCRIBE noteri");
    $newColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Struktura aktuale e tabelës 'noteri':</h2>";
    echo "<pre>";
    print_r($newColumns);
    echo "</pre>";
    
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>
            <strong>Gabim gjatë përditësimit të databazës:</strong> " . $e->getMessage() . "
          </div>";
}

// Shto butonin për kthim
echo "<div class='btn-container'>
        <a href='admin_noters.php' class='btn'>
            <i class='fas fa-arrow-left'></i> Kthehu te Menaxhimi i Noterëve
        </a>
      </div>
    </div>
</body>
</html>";
?>