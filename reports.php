<?php
// Redirect reports.php to raportet.php for backwards compatibility
session_start();
header("Location: raportet.php");
exit();
?>