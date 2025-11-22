<?php 
require 'confidb.php'; 
try { 
    // Drop existing triggers if they exist
    $pdo->exec('DROP TRIGGER IF EXISTS update_aktiv_after_busy_change');
    $pdo->exec('DROP TRIGGER IF EXISTS update_busy_after_aktiv_change');
    
    // Create trigger to update aktiv when busy changes
    $pdo->exec('CREATE TRIGGER update_aktiv_after_busy_change
                AFTER UPDATE ON users
                FOR EACH ROW
                BEGIN
                    IF NEW.busy != OLD.busy THEN
                        UPDATE users SET aktiv = NEW.busy WHERE id = NEW.id;
                    END IF;
                END');
                
    // Create trigger to update busy when aktiv changes            
    $pdo->exec('CREATE TRIGGER update_busy_after_aktiv_change
                AFTER UPDATE ON users
                FOR EACH ROW
                BEGIN
                    IF NEW.aktiv != OLD.aktiv THEN
                        UPDATE users SET busy = NEW.aktiv WHERE id = NEW.id;
                    END IF;
                END');
                
    echo 'Triggers created successfully!';
} catch(Exception $e) { 
    echo 'Error: ' . $e->getMessage(); 
} ?>
