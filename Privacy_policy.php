<?php
// Privacy_policy.php

// Përfshi lidhjen me databazën
include 'db_connection.php';

// Kontrollo nëse lidhja me databazën është e suksesshme
if (!isset($conn) || $conn->connect_error) {
    die("Lidhja me databazën dështoi: " . ($conn ? $conn->connect_error : 'Nuk u gjet lidhja'));
}

// Fetch privacy policy content from the database
$sql = "SELECT * FROM privacy_policy";
$result = $conn->query($sql);

// Store the privacy policy content in a variable
$privacy_policy = "";
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $privacy_policy = $row["content"];
} else {
    // Përmbajtje default nëse nuk ka në databazë
    $privacy_policy = <<<EOT
<h2>Politika e Privatësisë</h2>
<p>
Platforma Noteria respekton privatësinë dhe sigurinë e të dhënave personale të përdoruesve të saj, në përputhje me Ligjin Nr. 06/L-082 për Mbrojtjen e të Dhënave Personale në Kosovë dhe Rregulloren Evropiane për Mbrojtjen e të Dhënave (GDPR).
</p>
<ul>
    <li><strong>1. Qëllimi i mbledhjes së të dhënave</strong><br>
    Të dhënat personale mblidhen vetëm për qëllime të përcaktuara, të ligjshme dhe të qarta, si: regjistrimi i zyrave, menaxhimi i rezervimeve, procesimi i pagesave, komunikimi me përdoruesit dhe përmirësimi i shërbimeve të platformës.</li>
    <li><strong>2. Llojet e të dhënave që mblidhen</strong><br>
    Mund të mblidhen këto të dhëna: emri, mbiemri, email-i, numri i telefonit, të dhënat e zyrës, të dhënat bankare, IP adresa, historiku i përdorimit të platformës dhe çdo informacion tjetër i nevojshëm për funksionimin e platformës.</li>
    <li><strong>3. Bazat ligjore për përpunimin e të dhënave</strong><br>
    Përpunimi i të dhënave bëhet mbi bazën e pëlqimit të përdoruesit, përmbushjes së kontratës, detyrimeve ligjore ose interesit legjitim të platformës.</li>
    <li><strong>4. Ruajtja dhe siguria e të dhënave</strong><br>
    Të gjitha të dhënat personale ruhen në mënyrë të sigurt, me masa teknike dhe organizative për të parandaluar qasjen e paautorizuar, humbjen, ndryshimin ose keqpërdorimin e tyre. Vetëm personeli i autorizuar ka qasje në këto të dhëna.</li>
    <li><strong>5. Shpërndarja e të dhënave</strong><br>
    Të dhënat personale nuk do të ndahen me palë të treta, përveç rasteve kur kërkohet nga ligji ose kur është e nevojshme për ofrimin e shërbimeve (p.sh. procesimi i pagesave).</li>
    <li><strong>6. Të drejtat e përdoruesit</strong><br>
    Përdoruesit kanë të drejtë të kërkojnë akses, korrigjim, fshirje, kufizim të përpunimit, transferim të të dhënave ose të kundërshtojnë përpunimin e të dhënave të tyre personale. Këto të drejta mund të ushtrohen duke kontaktuar administratën e platformës në çdo kohë.</li>
    <li><strong>7. Ruajtja e të dhënave</strong><br>
    Të dhënat personale ruhen vetëm për aq kohë sa është e nevojshme për qëllimet për të cilat janë mbledhur ose sa kërkohet nga ligji.</li>
    <li><strong>8. Transferimi i të dhënave jashtë Kosovës</strong><br>
    Nëse të dhënat transferohen jashtë Kosovës, kjo bëhet vetëm në përputhje me kërkesat ligjore dhe me masa të përshtatshme sigurie.</li>
    <li><strong>9. Cookies dhe teknologji të ngjashme</strong><br>
    Platforma mund të përdorë cookies për të përmirësuar përvojën e përdoruesit dhe për analiza statistikore. Përdoruesit mund të menaxhojnë preferencat e tyre për cookies nëpërmjet shfletuesit.</li>
    <li><strong>10. Ndryshimet në politikën e privatësisë</strong><br>
    Çdo ndryshim në këtë politikë do të publikohet në këtë faqe. Përdoruesit inkurajohen ta rishikojnë rregullisht këtë politikë.</li>
    <li><strong>11. Kontakt</strong><br>
    Për çdo pyetje ose kërkesë lidhur me të dhënat personale, mund të kontaktoni administratën e platformës në: [email ose kontakt zyrtar].</li>
</ul>
<p>
Duke përdorur këtë platformë, ju pranoni politikën tonë të privatësisë dhe rregullat për mbrojtjen e të dhënave personale sipas ligjit në Kosovë dhe GDPR.
</p>
EOT;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Politika e Privatësisë</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8fafc; margin: 0; padding: 0; }
        .container { max-width: 800px; margin: 40px auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,0.07); padding: 32px 24px; }
        h1, h2 { color: #2d6cdf; text-align: center; margin-bottom: 32px; }
        p, ul, li { color: #333; font-size: 1.08rem; }
        ul { padding-left: 22px; }
        .policy-section { margin-bottom: 18px; }
        strong { color: #184fa3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Politika e Privatësisë</h1>
        <div id="privacy-policy-container">
            <?php
            // Nëse përmbajtja është nga databaza, supozohet që është HTML e sigurt
            // Nëse është default, gjithashtu është HTML e kontrolluar
            echo $privacy_policy;
            ?>
        </div>
    </div>
</body>
</html>