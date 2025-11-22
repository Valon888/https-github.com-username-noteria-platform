<?php require 'confidb.php'; try { $stmt = $pdo->exec('UPDATE users SET aktiv = busy'); echo 'Column values updated successfully'; } catch(Exception $e) { echo 'Error: ' . $e->getMessage(); } ?>
