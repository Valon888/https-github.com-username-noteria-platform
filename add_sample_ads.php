<?php
/**
 * Script për shtimin e mostrave të ofertave dhe kampanjave
 * Ekzekuto: http://localhost/noteria/add_sample_ads.php
 */

require_once 'confidb.php';

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("❌ Gabim në lidhjen me databazën: " . $e->getMessage());
}

// Shembuj të ofertave
$sample_ads = [
    [
        'advertiser_id' => 1,
        'title' => '🎯 Zbritje 50% në Të Gjithë Produktet',
        'description' => 'Kupon Special! Zbritje e madhe 50% në të gjithë koleksionin e verës. Vlido vetëm këtë javë!',
        'ad_type' => 'card',
        'image_url' => 'https://via.placeholder.com/400x250/FF6B6B/FFFFFF?text=50%25+OFF',
        'cta_url' => 'https://example.com/discount',
        'status' => 'active',
        'start_date' => date('Y-m-d H:i:s'),
        'end_date' => date('Y-m-d H:i:s', strtotime('+30 days'))
    ],
    [
        'advertiser_id' => 1,
        'title' => '🛍️ Koleksioni i Ri i Veshjes',
        'description' => 'Përvelimet më të reja të këtij sezoni. Cilësi premium me çmime të arsyeshme!',
        'ad_type' => 'banner',
        'image_url' => 'https://via.placeholder.com/400x250/4ECDC4/FFFFFF?text=NEW+FASHION',
        'cta_url' => 'https://example.com/fashion',
        'status' => 'active',
        'start_date' => date('Y-m-d H:i:s'),
        'end_date' => date('Y-m-d H:i:s', strtotime('+45 days'))
    ],
    [
        'advertiser_id' => 1,
        'title' => '💎 Bizhuteria Ekskluzive',
        'description' => 'Koleksioni i bizhutës së luksit. Projektim i vetëm, cilësi e garantuar.',
        'ad_type' => 'card',
        'image_url' => 'https://via.placeholder.com/400x250/FFD93D/FFFFFF?text=JEWELRY',
        'cta_url' => 'https://example.com/jewelry',
        'status' => 'active',
        'start_date' => date('Y-m-d H:i:s'),
        'end_date' => date('Y-m-d H:i:s', strtotime('+60 days'))
    ],
    [
        'advertiser_id' => 1,
        'title' => '📱 Teknologjia e Fundit',
        'description' => 'Smartfonë, tabletë dhe aksesorë me garanci zyrore. Blerje online + dërgim falas!',
        'ad_type' => 'banner',
        'image_url' => 'https://via.placeholder.com/400x250/6BCB77/FFFFFF?text=TECH',
        'cta_url' => 'https://example.com/tech',
        'status' => 'active',
        'start_date' => date('Y-m-d H:i:s'),
        'end_date' => date('Y-m-d H:i:s', strtotime('+40 days'))
    ],
    [
        'advertiser_id' => 1,
        'title' => '🏠 Mobilja Moderne',
        'description' => 'Mobilja e stilit të ri për shtëpinë tuaj. Dizajn skandinav me çmime të favorshme.',
        'ad_type' => 'card',
        'image_url' => 'https://via.placeholder.com/400x250/A8D8EA/FFFFFF?text=FURNITURE',
        'cta_url' => 'https://example.com/furniture',
        'status' => 'active',
        'start_date' => date('Y-m-d H:i:s'),
        'end_date' => date('Y-m-d H:i:s', strtotime('+50 days'))
    ],
    [
        'advertiser_id' => 1,
        'title' => '🍕 Ushqim i Shëndetshëm',
        'description' => 'Picat dhe përjasa italiane me përbërës natyral. Dërgim i shpejtë në derën tuaj!',
        'ad_type' => 'card',
        'image_url' => 'https://via.placeholder.com/400x250/FF8C42/FFFFFF?text=FOOD',
        'cta_url' => 'https://example.com/food',
        'status' => 'active',
        'start_date' => date('Y-m-d H:i:s'),
        'end_date' => date('Y-m-d H:i:s', strtotime('+25 days'))
    ],
    [
        'advertiser_id' => 1,
        'title' => '💪 Gjimnasium me Trajner Personal',
        'description' => 'Anëtarësi 3 muaj me 30% zbritje! Pajisje moderne dhe trajnera me përvojë.',
        'ad_type' => 'banner',
        'image_url' => 'https://via.placeholder.com/400x250/FF6B9D/FFFFFF?text=GYM',
        'cta_url' => 'https://example.com/gym',
        'status' => 'active',
        'start_date' => date('Y-m-d H:i:s'),
        'end_date' => date('Y-m-d H:i:s', strtotime('+35 days'))
    ],
    [
        'advertiser_id' => 1,
        'title' => '✈️ Paketa Turizmi në Maldive',
        'description' => 'Pushim i ëndrrës tuaj! Fluturim, hotel dhe plazhe primare të përfshira. Kuota e limituar!',
        'ad_type' => 'banner',
        'image_url' => 'https://via.placeholder.com/400x250/00D2D3/FFFFFF?text=TRAVEL',
        'cta_url' => 'https://example.com/travel',
        'status' => 'active',
        'start_date' => date('Y-m-d H:i:s'),
        'end_date' => date('Y-m-d H:i:s', strtotime('+55 days'))
    ],
    [
        'advertiser_id' => 1,
        'title' => '📚 Kurse Online të Programimit',
        'description' => 'Mëso Python, JavaScript, React dhe më shumë. Sertifikat e pranuar ndërkombëtarisht.',
        'ad_type' => 'card',
        'image_url' => 'https://via.placeholder.com/400x250/667BC6/FFFFFF?text=EDUCATION',
        'cta_url' => 'https://example.com/courses',
        'status' => 'active',
        'start_date' => date('Y-m-d H:i:s'),
        'end_date' => date('Y-m-d H:i:s', strtotime('+90 days'))
    ],
    [
        'advertiser_id' => 1,
        'title' => '🚗 Sigurimi i Makinës - 40% Zbritje',
        'description' => 'Sigurimi më i mirë për makininë tuaj me prim të ulët. Procesi online në 5 minuta!',
        'ad_type' => 'banner',
        'image_url' => 'https://via.placeholder.com/400x250/DA70D6/FFFFFF?text=INSURANCE',
        'cta_url' => 'https://example.com/insurance',
        'status' => 'active',
        'start_date' => date('Y-m-d H:i:s'),
        'end_date' => date('Y-m-d H:i:s', strtotime('+60 days'))
    ],
    [
        'advertiser_id' => 1,
        'title' => '💄 Produktet e Bukurisë - BOGO',
        'description' => 'Blij një produkt dhe merr tjetrin FALAS! Parfumet, kosmetikë dhe më shumë.',
        'ad_type' => 'card',
        'image_url' => 'https://via.placeholder.com/400x250/F08080/FFFFFF?text=BEAUTY',
        'cta_url' => 'https://example.com/beauty',
        'status' => 'active',
        'start_date' => date('Y-m-d H:i:s'),
        'end_date' => date('Y-m-d H:i:s', strtotime('+20 days'))
    ],
    [
        'advertiser_id' => 1,
        'title' => '🐕 Qendra e Kujdesit për Kafshë',
        'description' => 'Veterinar 24/7, grooming, hotel për kafshë. Beso në kujdesin më të mirë!',
        'ad_type' => 'card',
        'image_url' => 'https://via.placeholder.com/400x250/FFB347/FFFFFF?text=PETS',
        'cta_url' => 'https://example.com/pets',
        'status' => 'active',
        'start_date' => date('Y-m-d H:i:s'),
        'end_date' => date('Y-m-d H:i:s', strtotime('+70 days'))
    ]
];

// Përpiquni të shtoni mostrat
$added = 0;
$failed = 0;

foreach ($sample_ads as $ad) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO advertisements 
            (advertiser_id, title, description, ad_type, image_url, cta_url, status, start_date, end_date, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([
            $ad['advertiser_id'],
            $ad['title'],
            $ad['description'],
            $ad['ad_type'],
            $ad['image_url'],
            $ad['cta_url'],
            $ad['status'],
            $ad['start_date'],
            $ad['end_date']
        ]);
        
        if ($result) {
            $added++;
        } else {
            $failed++;
        }
    } catch (Exception $e) {
        $failed++;
        error_log("Error adding ad: " . $e->getMessage());
    }
}

// Rezultati
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shtimi i Mostrave të Ofertave</title>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 600px;
            text-align: center;
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        .success {
            color: #10b981;
            font-size: 18px;
            font-weight: 600;
            margin: 15px 0;
        }
        .info {
            color: #666;
            font-size: 16px;
            margin: 10px 0;
        }
        .button {
            display: inline-block;
            margin-top: 30px;
            padding: 12px 30px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .button:hover {
            background: #764ba2;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>✅ Shtimi i Mostrave të Ofertave</h1>
        <p class="success">✨ <?php echo $added; ?> oferta të reja u shtuan me sukses!</p>
        <?php if ($failed > 0): ?>
            <p style="color: #ef4444; font-size: 16px;">❌ <?php echo $failed; ?> oferta dështuan.</p>
        <?php endif; ?>
        <p class="info">Shfletoni marketplace-in për të parë oferta dhe kampanja të reja!</p>
        <a href="marketplace.php" class="button">Shiko Marketplace</a>
    </div>
</body>
</html>
