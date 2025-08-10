<?php
session_start();

// Simulo të dhënat e pagesës nga POST
$service = $_POST['service'] ?? '';
$date = $_POST['date'] ?? '';
$time = $_POST['time'] ?? '';
$zyra_id = $_POST['zyra_id'] ?? '';
$amount = 20.00; // Mund ta llogaritësh sipas shërbimit

// Simulo suksesin e pagesës
if ($service && $date && $time && $zyra_id) {
    // Këtu mund të ruash pagesën në databazë si të suksesshme
    echo "<h2 style='color:green;text-align:center;margin-top:60px;'>Pagesa u krye me sukses!</h2>";
    echo "<div style='text-align:center;'><a href='dashboard.php' style='color:#2d6cdf;font-size:1.2em;'>Kthehu në panel</a></div>";
} else {
    echo "<h2 style='color:red;text-align:center;margin-top:60px;'>Të dhënat mungojnë!</h2>";
    echo "<div style='text-align:center;'><a href='dashboard.php' style='color:#2d6cdf;font-size:1.2em;'>Kthehu në panel</a></div>";
}
?>
<input type="hidden" name="shuma" value="20.00">
<input type="hidden" name="service" value="Shërbimi i zgjedhur">
<input type="hidden" name="date" value="<?php echo date('Y-m-d'); ?>">
<input type="hidden" name="zyra_id" value="<?php echo $zyra_id; ?>">
<form method="POST" enctype="multipart/form-data">
    <!-- ...fushat ekzistuese... -->
    <button type="submit">Rezervo Terminin</button>
    <button type="submit" formaction="paysera_pay.php" formmethod="POST" style="background:#388e3c;margin-top:8px;">Paguaj Online</button>