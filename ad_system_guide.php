<?php
/**
 * SHPJEGIM: Si Funksionojnë Reklamat në Noteria
 *
 * Ky dokument shpjegon sistemin e reklamave dhe si integrohet ai në platformën Noteria
 */

echo "<h1>Si Funksionojnë Reklamat në Noteria</h1>";
echo "<p>Sistemi i reklamave është ndërtuar për të shfaqur reklama relevante për përdoruesit e platformës Noteria.</p>";

echo "<h2>1. Zgjedhja e Reklamave</h2>";
echo "<p>Kur një përdorues viziton një faqe, sistemi:</p>";
echo "<ul>";
echo "<li>Merr kontekstin e përdoruesit (lokacion, kategori, pajisje)</li>";
echo "<li>Kërkon reklama aktive nga databaza</li>";
echo "<li>Llogarit një rezultat relevance për çdo reklamë</li>";
echo "<li>Zgjedh reklamat më të mira dhe i shfaq</li>";
echo "</ul>";

echo "<h2>2. Sistemi i Pikëve të Relevance</h2>";
echo "<p>Rezultati i relevance llogaritet bazuar në:</p>";
echo "<ul>";
echo "<li><strong>Reklama të reja:</strong> +0.2 pikë për reklama të krijuara në 7 ditët e fundit</li>";
echo "<li><strong>Performanca:</strong> +0.2 pikë për CTR > 2%, -0.1 pikë për CTR < 0.5%</li>";
echo "<li><strong>Bazë:</strong> 0.5 pikë për të gjitha reklamat</li>";
echo "</ul>";

echo "<h2>3. Gjurmimi i Interaksioneve</h2>";
echo "<p>Sistemi gjurmon dy lloje interaksionesh:</p>";
echo "<dl>";
echo "<dt><strong>Impression (Shfaqje):</strong></dt>";
echo "<dd>Kur reklama shfaqet në faqe. Regjistrohet automatikisht.</dd>";
echo "<dt><strong>Click (Klikim):</strong></dt>";
echo "<dd>Kur përdoruesi klikon në reklamë. Regjistrohet kur klikohet.</dd>";
echo "</dl>";

echo "<h2>4. Tabela të Databazës</h2>";
echo "<h3>ads</h3>";
echo "<p>Ruan të dhënat kryesore të reklamave:</p>";
echo "<ul>";
echo "<li><code>id</code> - ID unike e reklamës</li>";
echo "<li><code>business_name</code> - Emri i biznesit</li>";
echo "<li><code>ad_title</code> - Titulli i reklamës</li>";
echo "<li><code>ad_description</code> - Përshkrimi</li>";
echo "<li><code>ad_link</code> - Linku ku shkon reklama</li>";
echo "<li><code>is_active</code> - Nëse është aktive</li>";
echo "<li><code>impressions</code> - Numri i shfaqjeve</li>";
echo "<li><code>clicks</code> - Numri i klikimeve</li>";
echo "</ul>";

echo "<h3>ad_interactions</h3>";
echo "<p>Ruan çdo interaksion me reklamat:</p>";
echo "<ul>";
echo "<li><code>ad_id</code> - ID e reklamës</li>";
echo "<li><code>interaction_type</code> - 'impression' ose 'click'</li>";
echo "<li><code>user_ip</code> - IP adresa e përdoruesit</li>";
echo "<li><code>created_at</code> - Data dhe ora</li>";
echo "</ul>";

echo "<h2>5. Integrimi në Kod</h2>";
echo "<h3>Marrja e Reklamave</h3>";
echo "<pre><code>";
echo "// Inicializimi
require_once 'AdEngine.php';
\$adEngine = new AdEngine(\$pdo);

// Konteksti i përdoruesit
\$userContext = [
    'user_id' => \$_SESSION['user_id'] ?? null,
    'ip' => \$_SERVER['REMOTE_ADDR'],
    'location' => 'Prishtina',
    'category' => 'inheritance',
    'device' => 'desktop'
];

// Merr reklamat
\$ads = \$adEngine->getRelevantAds(\$userContext);
</code></pre>";

echo "<h3>Shfaqja e Reklamave</h3>";
echo "<pre><code>";
echo "foreach (\$ads as \$ad) {
    echo \"<div class='ad-item' data-ad-id='{\$ad['id']}'>\";
    echo \"<h4>{\$ad['title']}</h4>\";
    echo \"<p>{\$ad['description']}</p>\";
    echo \"<a href='/ad-click?ad_id={\$ad['id']}' target='_blank'>Mëso më shumë</a>\";
    echo \"</div>\";
}
</code></pre>";

echo "<h3>Gjurmimi i Impression-eve (JavaScript)</h3>";
echo "<pre><code>";
echo "document.addEventListener('DOMContentLoaded', function() {
    const adItems = document.querySelectorAll('.ad-item');
    adItems.forEach(item => {
        const adId = item.dataset.adId;
        // Regjistro impression
        fetch('/api/ad/impression', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ad_id: adId})
        });
    });
});
</code></pre>";

echo "<h2>6. Menaxhimi i Reklamave</h2>";
echo "<p>Për të shtuar një reklamë të re:</p>";
echo "<ol>";
echo "<li>Hyni në databazë MySQL</li>";
echo "<li>Insertoni në tabelën <code>ads</code>:</li>";
echo "</ol>";

echo "<pre><code>";
echo "INSERT INTO ads (
    business_name, business_contact, business_email,
    ad_title, ad_description, ad_link,
    is_active, start_date
) VALUES (
    'Biznesi Im', '+38344123456', 'info@biznesi.com',
    'Shërbime Ligjore Profesionale', 'Oferta speciale për shërbime ligjore',
    'https://biznesi.com', 1, NOW()
);
</code></pre>";

echo "<h2>7. Statistikat dhe Performanca</h2>";
echo "<p>Për të parë statistikat e reklamave:</p>";
echo "<pre><code>";
echo "SELECT
    ad_title,
    impressions,
    clicks,
    ROUND((clicks/impressions)*100, 2) as ctr_percent
FROM ads
WHERE is_active = 1
ORDER BY impressions DESC;
</code></pre>";

echo "<h2>8. Optimizimi dhe Cache</h2>";
echo "<p>Sistemi përdor cache për performancë:</p>";
echo "<ul>";
echo "<li><strong>Cache TTL:</strong> 5 minuta për rezultatet e reklamave</li>";
echo "<li><strong>Cache Key:</strong> Bazuar në lokacion, kategori, pajisje dhe orë</li>";
echo "<li><strong>Invalidate:</strong> Kur regjistrohet një impression i ri</li>";
echo "</ul>";

echo "<h2>9. Siguria</h2>";
echo "<ul>";
echo "<li><strong>SQL Injection:</strong> Mbrojtur me PDO prepared statements</li>";
echo "<li><strong>Input Validation:</strong> Të gjitha input-et validohen</li>";
echo "<li><strong>Fraud Protection:</strong> Kontrolli i IP dhe rate limiting</li>";
echo "</ul>";

echo "<h2>10. Troubleshooting</h2>";
echo "<h3>Reklamat nuk shfaqen</h3>";
echo "<ul>";
echo "<li>Kontrolloni nëse <code>is_active = 1</code></li>";
echo "<li>Kontrolloni <code>start_date</code> dhe <code>end_date</code></li>";
echo "<li>Kontrolloni error log-un për gabime</li>";
echo "</ul>";

echo "<h3>Impression-et nuk regjistrohen</h3>";
echo "<ul>";
echo "<li>Kontrolloni JavaScript console për gabime</li>";
echo "<li>Kontrolloni endpoint-in <code>/api/ad/impression</code></li>";
echo "<li>Kontrolloni databazën për tabela dhe permissions</li>";
echo "</ul>";

echo "<hr>";
echo "<p><strong>Sistemi është gati për përdorim!</strong> Reklamat do të shfaqen automatikisht bazuar në kontekstin e përdoruesit dhe performancën e tyre.</p>";
?>
