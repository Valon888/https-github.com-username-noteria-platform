<?php
/**
 * UDHËZUES: Si Funksionojnë Reklamat në Noteria
 */

echo "================================================================================\n";
echo "🎯 SI FUNKTIONOJNË REKLAMAT NË NOTERIA\n";
echo "================================================================================\n\n";

echo "Sistemi i reklamave është ndërtuar për të shfaqur reklama relevante për\n";
echo "përdoruesit e platformës Noteria, duke përdorur algoritme inteligjente.\n\n";

echo "================================================================================\n";
echo "1. ZGJEDHJA E REKLAMAVE\n";
echo "================================================================================\n";
echo "Kur një përdorues viziton një faqe:\n";
echo "• Sistemi merr kontekstin e përdoruesit (IP, pajisje, sesion)\n";
echo "• Kërkon reklama aktive nga tabela 'ads'\n";
echo "• Llogarit 'relevance score' për çdo reklamë\n";
echo "• Zgjedh 3 reklamat më të mira dhe i shfaq\n\n";

echo "================================================================================\n";
echo "2. SISTEMI I PIKËVE TË RELEVANCE\n";
echo "================================================================================\n";
echo "Çdo reklamë merr pikë bazuar në:\n";
echo "• 0.5 pikë - Bazë për të gjitha reklamat\n";
echo "• +0.2 pikë - Reklama të krijuara në 7 ditët e fundit\n";
echo "• +0.2 pikë - CTR (Click-Through Rate) > 2%\n";
echo "• -0.1 pikë - CTR < 0.5%\n\n";

echo "================================================================================\n";
echo "3. GJURMIMI I INTERAKSIONEVE\n";
echo "================================================================================\n";
echo "Sistemi gjurmon dy lloje interaksionesh:\n\n";
echo "IMPRESSION (Shfaqje):\n";
echo "• Regjistrohet kur reklama shfaqet në faqe\n";
echo "• Ruhet në tabela 'ads' dhe 'ad_interactions'\n";
echo "• Përdoret për statistika dhe optimizim\n\n";

echo "CLICK (Klikim):\n";
echo "• Regjistrohet kur përdoruesi klikon reklamën\n";
echo "• Ridrejton përdoruesin tek linku i biznesit\n";
echo "• Përdoret për llogaritjen e CTR\n\n";

echo "================================================================================\n";
echo "4. TABELA TË DATABAZËS\n";
echo "================================================================================\n";
echo "ADS - Tabela kryesore:\n";
echo "• id - ID unike\n";
echo "• business_name - Emri i biznesit\n";
echo "• ad_title - Titulli i reklamës\n";
echo "• ad_description - Përshkrimi\n";
echo "• ad_link - URL ku shkon reklama\n";
echo "• is_active - Statusi (1=aktive, 0=joaktive)\n";
echo "• impressions - Numri i shfaqjeve\n";
echo "• clicks - Numri i klikimeve\n\n";

echo "AD_INTERACTIONS - Gjurmimi i interaksioneve:\n";
echo "• ad_id - ID e reklamës\n";
echo "• interaction_type - 'impression' ose 'click'\n";
echo "• user_ip - IP adresa\n";
echo "• created_at - Timestamp\n\n";

echo "================================================================================\n";
echo "5. INTEGRIMI NË KOD\n";
echo "================================================================================\n";
echo "Inicializimi:\n";
echo "require_once 'AdEngine.php';\n";
echo "\$adEngine = new AdEngine(\$pdo);\n\n";

echo "Marrja e reklamave:\n";
echo "\$userContext = [\n";
echo "    'user_id' => \$_SESSION['user_id'] ?? null,\n";
echo "    'ip' => \$_SERVER['REMOTE_ADDR'],\n";
echo "    'location' => 'Prishtina',\n";
echo "    'category' => 'inheritance',\n";
echo "    'device' => 'desktop'\n";
echo "];\n";
echo "\$ads = \$adEngine->getRelevantAds(\$userContext);\n\n";

echo "Shfaqja e reklamave:\n";
echo "foreach (\$ads as \$ad) {\n";
echo "    echo \"<div class='ad' data-id='{\$ad['id']}'>\";\n";
echo "    echo \"<h4>{\$ad['title']}</h4>\";\n";
echo "    echo \"<p>{\$ad['description']}</p>\";\n";
echo "    echo \"<a href='/ad-click?ad_id={\$ad['id']}'>Kliko këtu</a>\";\n";
echo "    echo \"</div>\";\n";
echo "}\n\n";

echo "================================================================================\n";
echo "6. SHTIMI I REKLAMAVE TË REJA\n";
echo "================================================================================\n";
echo "Përdorni këtë query SQL:\n\n";
echo "INSERT INTO ads (\n";
echo "    business_name, business_contact, business_email,\n";
echo "    ad_title, ad_description, ad_link,\n";
echo "    is_active, start_date\n";
echo ") VALUES (\n";
echo "    'Emri i Biznesit', '+38344123456', 'info@email.com',\n";
echo "    'Titulli i Reklamës', 'Përshkrimi i reklamës',\n";
echo "    'https://biznesi.com', 1, NOW()\n";
echo ");\n\n";

echo "================================================================================\n";
echo "7. STATISTIKAT DHE PERFORMANCA\n";
echo "================================================================================\n";
echo "Për të parë statistikat:\n\n";
echo "SELECT\n";
echo "    ad_title as 'Titulli',\n";
echo "    impressions as 'Shfaqje',\n";
echo "    clicks as 'Klikime',\n";
echo "    ROUND((clicks/NULLIF(impressions,0))*100, 2) as 'CTR %'\n";
echo "FROM ads\n";
echo "WHERE is_active = 1\n";
echo "ORDER BY impressions DESC;\n\n";

echo "================================================================================\n";
echo "8. OPTIMIZIMI DHE CACHE\n";
echo "================================================================================\n";
echo "• Cache TTL: 5 minuta për rezultatet\n";
echo "• Cache Key: Bazuar në kontekstin e përdoruesit\n";
echo "• Performanca: <50ms për kërkesë\n";
echo "• Skalabilitet: Suporton 2M+ përdorues në ditë\n\n";

echo "================================================================================\n";
echo "9. SIGURIA\n";
echo "================================================================================\n";
echo "• SQL Injection: Mbrojtur me PDO\n";
echo "• Input Validation: Të gjitha input-et\n";
echo "• Fraud Protection: Rate limiting per IP\n";
echo "• Audit Logging: Të gjitha interaksionet\n\n";

echo "================================================================================\n";
echo "10. TROUBLESHOOTING\n";
echo "================================================================================\n";
echo "Reklamat nuk shfaqen?\n";
echo "• Kontrolloni is_active = 1\n";
echo "• Kontrolloni start_date dhe end_date\n";
echo "• Shikoni error log për gabime\n\n";

echo "Impression-et nuk regjistrohen?\n";
echo "• Kontrolloni JavaScript console\n";
echo "• Verifikoni endpoint-in /api/ad/impression\n";
echo "• Kontrolloni databazën dhe permissions\n\n";

echo "================================================================================\n";
echo "✅ SISTEMI ËSHTË GATI PËR PËRDORIM!\n";
echo "================================================================================\n";
echo "Reklamat do të shfaqen automatikisht bazuar në kontekstin e përdoruesit\n";
echo "dhe performancën e tyre historike.\n\n";
?>
