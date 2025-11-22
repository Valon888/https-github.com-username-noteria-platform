<?php
// FAQ.php

// Përfshi lidhjen me databazën
include 'db_connection.php';

// Merr pyetjet nga databaza
$sql = "SELECT * FROM faqs";
$result = $conn->query($sql);

// Ruaj pyetjet në një array për t'i përdorur në HTML
$faqs = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $faqs[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8fafc; margin: 0; padding: 0; }
        .container { max-width: 700px; margin: 40px auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,0.07); padding: 32px 24px; }
        h1 { color: #2d6cdf; text-align: center; margin-bottom: 32px; }
        .faq { margin-bottom: 24px; }
        .faq h3 { color: #184fa3; margin-bottom: 8px; }
        .faq p { color: #333; margin: 0; }
        .no-faq { color: #d32f2f; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Pyetjet e Bëra Më Shumë</h1>
        <p>Shihni më poshtë pyetjet më të shpeshta që na bëhen:</p>
        <p>Noteria është platforma juaj për shërbime noteriale online.</p>
        <p>Na kontaktoni nëse keni pyetje të tjera!</p>
        <p>Noteria.com sjellim shërbime të shpejta dhe efikase për nevojat tuaja noteriale në pëllëmbë të dorës.</p>
        <div id="faq-container">
            <?php if (!empty($faqs)): ?>
                <?php foreach ($faqs as $faq): ?>
                    <div class="faq">
                        <h3><?php echo htmlspecialchars($faq["question"]); ?></h3>
                        <p><?php echo nl2br(htmlspecialchars($faq["answer"])); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-faq">Nuk u gjet asnjë pyetje.</div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>