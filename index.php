
<?php
require_once __DIR__ . '/security.php';
SecurityHeaders::setAllHeaders();
// Set header for proper encoding
header('Content-Type: text/html; charset=utf-8');

// Multilingual support for Noteria index.php
$langs = ['sq' => 'Shqip', 'sr' => 'Српски', 'en' => 'English'];
$lang = $_GET['lang'] ?? 'sq';
if (!in_array($lang, array_keys($langs))) $lang = 'sq';


$text = [
    'title' => [
        'sq' => 'E-Noteria SaaS - Platforma Elektronike për Noterinë në Kosovë | Menaxhim Rezervimesh & Dokumentesh',
        'sr' => 'E-Notaria SaaS - Elektronska Platforma za Notare na Kosovu | Upravljanje Rezervacijama i Dokumentima',
        'en' => 'E-Notaria SaaS - Cloud-Based Notary Management Platform for Kosovo | Online Appointment & Document System',
    ],
    'desc' => [
        'sq' => 'E-Noteria SaaS - Platforma më moderne dhe e sigurt për noterinë në Kosovë. Menaxho rezervime, dokumente, kliente dhe faturim në një sistem elektronik të integrueshëm.',
        'sr' => 'E-Notaria SaaS - Најмодернија и најсигурнија платформа за нотаре на Косову. Управљајте резервацијама, документима, клијентима и фактурирањем у једном систему.',
        'en' => 'E-Notaria SaaS - Modern cloud notary management for Kosovo. Manage appointments, documents, clients & billing. Secure, efficient & affordable online platform.',
    ],
    'nav' => [
        'features' => ['sq'=>'Veçoritë','sr'=>'Карактеристике','en'=>'Features'],
        'pricing' => ['sq'=>'Çmimet','sr'=>'Цене','en'=>'Pricing'],
        'services' => ['sq'=>'Shërbimet','sr'=>'Услуге','en'=>'Services'],
        'news' => ['sq'=>'Lajme','sr'=>'Вести','en'=>'News'],
        'help' => ['sq'=>'Ndihma','sr'=>'Помоћ','en'=>'Help'],
        'contact' => ['sq'=>'Kontakti','sr'=>'Контакт','en'=>'Contact'],
        'login' => ['sq'=>'Hyni','sr'=>'Пријава','en'=>'Login'],
        'register' => ['sq'=>'Regjistrohu Falas','sr'=>'Бесплатна регистрација','en'=>'Register Free'],
    ],
    'hero' => [
        'title' => ['sq'=>'E-Noteria SaaS','sr'=>'E-Notaria SaaS','en'=>'E-Notaria SaaS'],
        'desc' => [
            'sq'=>'Platforma më moderne SaaS për notarinë në Kosovë. Sistemi elektronik për menaxhimin e shërbimeve noteriale.',
            'sr'=>'Најмодернија SaaS платформа за нотаре на Косову. Електронски систем за управљање нотарским услугама.',
            'en'=>'The most modern SaaS platform for notaries in Kosovo. Electronic system for managing notary services.'
        ],
        'start' => ['sq'=>'Fillo Falas','sr'=>'Почни бесплатно','en'=>'Start Free'],
        'learn' => ['sq'=>'Mëso Më Shumë','sr'=>'Сазнај више','en'=>'Learn More'],
        'notary_offices' => ['sq'=>'Zyra Noteriale','sr'=>'Нотарске канцеларије','en'=>'Notary Offices'],
        'active_users' => ['sq'=>'Përdorues Aktivë','sr'=>'Активни корисници','en'=>'Active Users'],
        'daily_reservations' => ['sq'=>'Rezervime në Ditë','sr'=>'Резервације дневно','en'=>'Daily Reservations'],
    ],
    'features_title' => [
        'sq'=>'Veçoritë e Platformës SaaS',
        'sr'=>'Карактеристике SaaS платформе',
        'en'=>'Platform Features'
    ],
    'features_desc' => [
        'sq'=>'Të gjitha mjetet që ju nevojiten për një notari moderne dhe efikase',
        'sr'=>'Сви алати који су вам потребни за модеран и ефикасан нотарјат',
        'en'=>'All the tools you need for a modern and efficient notary office'
    ],
    'feature_cards' => [
        [
            'icon' => 'fa-calendar-check',
            'title' => [
                'sq' => 'Menaxhimi i Rezervimeve',
                'sr' => 'Управљање резервацијама',
                'en' => 'Reservation Management'
            ],
            'desc' => [
                'sq' => 'Sistemi i avancuar për prenotimin dhe menaxhimin e termineve noteriale me njoftime automatike dhe konfirmime.',
                'sr' => 'Напредни систем за заказивање и управљање нотарским терминима са аутоматским обавештењима и потврдама.',
                'en' => 'Advanced system for booking and managing notary appointments with automatic notifications and confirmations.'
            ]
        ],
        [
            'icon' => 'fa-file-contract',
            'title' => [
                'sq' => 'Dokumentet Elektronike',
                'sr' => 'Електронска документа',
                'en' => 'Electronic Documents'
            ],
            'desc' => [
                'sq' => 'Ngarkoni, ruani dhe menaxhoni dokumentet noteriale në format elektronik. Integrim me nënshkrimin elektronik.',
                'sr' => 'Отпремите, чувајте и управљајте нотарским документима у електронском формату. Интеграција са електронским потписом.',
                'en' => 'Upload, store, and manage notary documents electronically. Integration with e-signature.'
            ]
        ],
        [
            'icon' => 'fa-users',
            'title' => [
                'sq' => 'Menaxhimi i Përdoruesve',
                'sr' => 'Управљање корисницима',
                'en' => 'User Management'
            ],
            'desc' => [
                'sq' => 'Sistemi i plotë për menaxhimin e përdoruesve me role të ndryshme dhe leje të personalizuara për çdo organizatë.',
                'sr' => 'Потпун систем за управљање корисницима са различитим улогама и прилагођеним дозволама за сваку организацију.',
                'en' => 'Full user management system with different roles and custom permissions for each organization.'
            ]
        ],
        [
            'icon' => 'fa-credit-card',
            'title' => [
                'sq' => 'Paguar Online',
                'sr' => 'Онлајн плаћање',
                'en' => 'Online Payment'
            ],
            'desc' => [
                'sq' => 'Sistemi i sigurt i pagesave online me integrimin e bankave kosovare dhe ndërkombëtare.',
                'sr' => 'Сигуран систем онлајн плаћања са интеграцијом косовских и међународних банака.',
                'en' => 'Secure online payment system with integration of Kosovar and international banks.'
            ]
        ],
        [
            'icon' => 'fa-chart-bar',
            'title' => [
                'sq' => 'Raportet dhe Statistikat',
                'sr' => 'Извештаји и статистика',
                'en' => 'Reports and Statistics'
            ],
            'desc' => [
                'sq' => 'Paneli i detajuar i raporteve me statistika të plota për performancën dhe efikasitetin e shërbimeve.',
                'sr' => 'Детаљна табла са извештајима и комплетном статистиком о учинку и ефикасности услуга.',
                'en' => 'Detailed reports dashboard with full statistics on service performance and efficiency.'
            ]
        ],
        [
            'icon' => 'fa-shield-alt',
            'title' => [
                'sq' => 'Siguria dhe Privatësia',
                'sr' => 'Безбедност и приватност',
                'en' => 'Security and Privacy'
            ],
            'desc' => [
                'sq' => 'Enkriptimi i të dhënave, certifikimi SSL, dhe përputhshmëria me GDPR dhe ligjet kosovare për mbrojtjen e të dhënave.',
                'sr' => 'Шифровање података, SSL сертификат и усклађеност са GDPR и косовским законима о заштити података.',
                'en' => 'Data encryption, SSL certification, and compliance with GDPR and Kosovo data protection laws.'
            ]
        ]
    ],
    'pricing_title' => [
        'sq'=>'Plane Abonimi',
        'sr'=>'Планови претплате',
        'en'=>'Subscription Plans'
    ],
    'pricing_desc' => [
        'sq'=>'Zgjidhni planin që përshtatet më së miri për zyrën tuaj noteriale',
        'sr'=>'Изаберите план који најбоље одговара вашој нотарској канцеларији',
        'en'=>'Choose the plan that best fits your notary office'
    ],
    'pricing' => [
        'monthly' => [
            'name' => ['sq'=>'Abonim Mujor','sr'=>'Месечна претплата','en'=>'Monthly Subscription'],
            'desc' => [
                'sq'=>'Abonim mujor me qasje të plotë në platformën E-Noteria SaaS',
                'sr'=>'Месечна претплата са пуним приступом платформи E-Noteria SaaS',
                'en'=>'Monthly subscription with full access to E-Noteria SaaS platform'
            ],
            'features' => [
                ['sq'=>'Qasje e plotë në platformë','sr'=>'Потпун приступ платформи','en'=>'Full access to the platform'],
                ['sq'=>'Dokumente të pakufizuara','sr'=>'Неограничена документа','en'=>'Unlimited documents'],
                ['sq'=>'Mbështetje prioritare 24/7','sr'=>'Приоритетна подршка 24/7','en'=>'Priority support 24/7'],
                ['sq'=>'Të gjitha shërbimet e platformës','sr'=>'Све услуге платформе','en'=>'All platform services'],
                ['sq'=>'Mjete të avancuara për noterë','sr'=>'Напредни алати за нотаре','en'=>'Advanced tools for notaries'],
            ],
            'register' => ['sq'=>'Regjistro Zyrën Tënde','sr'=>'Региструј своју канцеларију','en'=>'Register Your Office']
        ],
        'yearly' => [
            'name' => ['sq'=>'Abonim Vjetor','sr'=>'Годишња претплата','en'=>'Yearly Subscription'],
            'desc' => [
                'sq'=>'Abonim vjetor me qasje të plotë në platformën E-Noteria SaaS - Kurseni 200€',
                'sr'=>'Годишња претплата са пуним приступом платформи E-Noteria SaaS - Уштедите 200€',
                'en'=>'Yearly subscription with full access to E-Noteria SaaS platform - Save 200€'
            ],
            'badge' => ['sq'=>'Më Popullor','sr'=>'Најпопуларније','en'=>'Most Popular'],
            'features' => [
                ['sq'=>'Qasje e plotë në platformë','sr'=>'Потпун приступ платформи','en'=>'Full access to the platform'],
                ['sq'=>'Dokumente të pakufizuara','sr'=>'Неограничена документа','en'=>'Unlimited documents'],
                ['sq'=>'Mbështetje prioritare 24/7','sr'=>'Приоритетна подршка 24/7','en'=>'Priority support 24/7'],
                ['sq'=>'Të gjitha shërbimet e platformës','sr'=>'Све услуге платформе','en'=>'All platform services'],
                ['sq'=>'Mjete të avancuara për noterë','sr'=>'Напредни алати за нотаре','en'=>'Advanced tools for notaries'],
                ['sq'=>'Kurseni 200€ me pagesë vjetore','sr'=>'Уштедите 200€ са годишњом уплатом','en'=>'Save 200€ with annual payment'],
                ['sq'=>'Trajnime personale','sr'=>'Личне обуке','en'=>'Personal trainings'],
                ['sq'=>'Këshillime ligjore mujore','sr'=>'Месечне правне консултације','en'=>'Monthly legal consultations'],
            ],
            'register' => ['sq'=>'Regjistro Zyrën Tënde','sr'=>'Региструј своју канцеларију','en'=>'Register Your Office']
        ]
    ],
    'cta_title' => [
        'sq'=>'Gati për të Filluar?','sr'=>'Спремни да почнете?','en'=>'Ready to Get Started?'
    ],
    'cta_desc' => [
        'sq'=>'Bashkohuni me qindra zyra noteriale që besojnë në platformën tonë SaaS. Regjistrohuni sot dhe filloni transformimin dixhital të shërbimeve tuaja noteriale.',
        'sr'=>'Придружите се стотинама нотарских канцеларија које верују нашој SaaS платформи. Региструјте се данас и започните дигиталну трансформацију ваших услуга.',
        'en'=>'Join hundreds of notary offices that trust our SaaS platform. Register today and start the digital transformation of your notary services.'
    ],
    'cta_btn' => [
        'sq'=>'Regjistro Zyrën Tënde Noteriale','sr'=>'Региструј своју нотарску канцеларију','en'=>'Register Your Notary Office'
    ],
    'footer' => [
        'product' => ['sq'=>'Produkti','sr'=>'Производ','en'=>'Product'],
        'support' => ['sq'=>'Suporti','sr'=>'Подршка','en'=>'Support'],
        'legal' => ['sq'=>'Ligjore','sr'=>'Правно','en'=>'Legal'],
        'official' => [
            'sq'=>'Platforma zyrtare SaaS për notarinë në Republikën e Kosovës. Zhvilluar për të modernizuar dhe dixhitalizuar shërbimet noteriale.',
            'sr'=>'Званична SaaS платформа за нотаре у Републици Косово. Развијена за модернизацију и дигитализацију нотарских услуга.',
            'en'=>'The official SaaS platform for notaries in the Republic of Kosovo. Developed to modernize and digitalize notary services.'
        ],
        'copyright' => [
            'sq'=>'&copy; '.date('Y').' E-Noteria. Të gjitha të drejtat e rezervuara.',
            'sr'=>'&copy; '.date('Y').' E-Noteria. Сва права задржана.',
            'en'=>'&copy; '.date('Y').' E-Noteria. All rights reserved.'
        ],
        'developed' => [
            'sq'=>'Zhvilluar nga <strong>Valon Sadiku</strong> | Powered by e-Noteria SaaS Platform',
            'sr'=>'Развио <strong>Валон Садику</strong> | Покреће e-Noteria SaaS Platform',
            'en'=>'Developed by <strong>Valon Sadiku</strong> | Powered by e-Noteria SaaS Platform'
        ],
        'links' => [
            'features' => ['sq'=>'Veçoritë','sr'=>'Карактеристике','en'=>'Features'],
            'pricing' => ['sq'=>'Çmimet','sr'=>'Цене','en'=>'Pricing'],
            'services' => ['sq'=>'Shërbimet','sr'=>'Услуге','en'=>'Services'],
            'news' => ['sq'=>'Lajme','sr'=>'Вести','en'=>'News'],
            'help' => ['sq'=>'Ndihma','sr'=>'Помоћ','en'=>'Help'],
            'login' => ['sq'=>'Hyni','sr'=>'Пријава','en'=>'Login'],
            'register' => ['sq'=>'Regjistro Zyrën','sr'=>'Региструј канцеларију','en'=>'Register Office'],
            'privacy' => ['sq'=>'Politika e Privatësisë','sr'=>'Политика приватности','en'=>'Privacy Policy'],
            'terms' => ['sq'=>'Kushtet e Përdorimit','sr'=>'Услови коришћења','en'=>'Terms of Use'],
            'security' => ['sq'=>'Siguria','sr'=>'Безбедност','en'=>'Security'],
            'compliance' => ['sq'=>'Compliance','sr'=>'Усклађеност','en'=>'Compliance'],
            'help_center' => ['sq'=>'Qendra e Ndihmës','sr'=>'Центар за помоћ','en'=>'Help Center'],
            'docs' => ['sq'=>'Dokumentacioni','sr'=>'Документација','en'=>'Documentation'],
            'api' => ['sq'=>'API Reference','sr'=>'API Референца','en'=>'API Reference'],
            'contact' => ['sq'=>'Kontakti','sr'=>'Контакт','en'=>'Contact'],
        ]
    ]
];

require_once 'confidb.php';
// require_once 'tenant_manager.php';

// Initialize tenant system
if (!function_exists('init_tenant_system')) {
    function init_tenant_system($pdo) {
        // Stub: Initialize tenant system if needed
        // This is a placeholder to avoid undefined function error
    }
}
init_tenant_system($pdo);

// Get available plans for display
$stmt = $pdo->query("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY price_monthly");
$plans = $stmt->fetchAll();

// Get some statistics
$tenant_count = 144; // Zyra noteriale aktive
$user_count = 1000000; // Mbi 1 milion përdorues
$reservation_count = 500000; // Qindra mijëra rezervime në ditë
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $text['title'][$lang]; ?></title>
    <meta name="description" content="<?php echo $text['desc'][$lang]; ?>">
    <link rel="stylesheet" href="/assets/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/fontawesome/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" integrity="sha512-1cK78a1o+ht2JcaW6g8OXYwqpev9+6GqOkz9xmBN9iUUhIndKtxwILGWYOSibOKjLsEdjyjZvYDq/cZwNeak0w==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        :root {
            --primary: #0033A0;
            --secondary: #FFD700;
            --accent: #10B981;
            --dark: #1f2937;
            --light: #f8fafc;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--dark);
        }

        /* Navigation */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            padding: 1rem 0;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary) !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 10px !important;
            transition: all 0.3s ease;
        }

        .navbar-brand img {
            height: 50px;
            width: auto;
            object-fit: contain;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
            transition: all 0.3s ease;
        }

        .navbar-brand:hover img {
            filter: drop-shadow(0 4px 8px rgba(0, 51, 160, 0.4));
            transform: scale(1.05);
        }

        .navbar-brand i {
            color: var(--secondary);
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, var(--primary) 0%, #002266 100%);
            color: white;
            padding: 8rem 0 6rem;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.05"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.05"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.03"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            text-shadow: 0 4px 8px rgba(0,0,0,0.3);
        }

        .hero p {
            font-size: 1.3rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        .hero-stats {
            display: flex;
            gap: 2rem;
            margin-top: 3rem;
            justify-content: center;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--secondary);
            display: block;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        /* Features Section */
        .features {
            padding: 6rem 0;
            background: var(--light);
        }

        .section-title {
            text-align: center;
            margin-bottom: 4rem;
        }

        .section-title h2 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .feature-card {
            background: white;
            border-radius: 15px;
            padding: 2.5rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            height: 100%;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: white;
        }

        .feature-card h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--primary);
        }

        /* Pricing Section */
        .pricing {
            padding: 6rem 0;
            background: white;
        }

        .pricing-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: 2px solid transparent;
            transition: all 0.3s ease;
            position: relative;
        }

        .pricing-card.popular {
            border-color: var(--secondary);
            transform: scale(1.05);
        }

        .pricing-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }

        .pricing-header {
            margin-bottom: 2rem;
        }

        .pricing-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .pricing-price {
            font-size: 3rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .pricing-price .currency {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .pricing-price .period {
            font-size: 1rem;
            font-weight: 400;
            color: #6b7280;
        }

        .pricing-features {
            list-style: none;
            padding: 0;
            margin-bottom: 2rem;
        }

        .pricing-features li {
            padding: 0.5rem 0;
            color: #4b5563;
        }

        .pricing-features li i {
            color: var(--accent);
            margin-right: 0.5rem;
        }

        .btn-pricing {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border: none;
            color: white;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .btn-pricing:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 51, 160, 0.3);
        }

        /* CTA Section */
        .cta {
            background: linear-gradient(135deg, var(--primary) 0%, #002266 100%);
            color: white;
            padding: 6rem 0;
            text-align: center;
        }

        .cta h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }

        .cta p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        .btn-cta {
            background: var(--secondary);
            color: var(--primary);
            padding: 1.2rem 3rem;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1.1rem;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(255, 215, 0, 0.3);
        }

        .btn-cta:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 215, 0, 0.4);
        }

        /* Footer */
        .footer {
            background: var(--dark);
            color: white;
            padding: 3rem 0 1rem;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .footer-section h5 {
            color: var(--secondary);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .footer-links {
            list-style: none;
            padding: 0;
        }

        .footer-links li {
            margin-bottom: 0.5rem;
        }

        .footer-links a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .footer-links a:hover {
            color: var(--secondary);
        }

        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 2rem;
            text-align: center;
            color: rgba(255,255,255,0.6);
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }

            .hero-stats {
                flex-direction: column;
                gap: 1rem;
            }

            .pricing-price {
                font-size: 2.5rem;
            }

            .cta h2 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Message/Error Display -->
    <?php
    $message = $_GET['message'] ?? null;
    $error = $_GET['error'] ?? null;
    $message_content = '';
    $message_type = '';
    
    if ($message === 'user_access_denied') {
        $message_content = [
            'sq' => 'Përdoruesit e thjeshtë nuk mund të hyjnë në panelin e kontrollit të adminit. Ju lutem kontaktoni administratorin për më shumë informacione.',
            'sr' => 'Обични корисници не могу приступити администраторској контролној табли. Молимо контактирајте администратора за више информација.',
            'en' => 'Regular users cannot access the admin control panel. Please contact the administrator for more information.'
        ];
        $message_type = 'info';
    } elseif ($error === 'unauthorized_access') {
        $message_content = [
            'sq' => 'Nuk keni qasje në këtë faqe. Vetëm administatorët mund ta aksesojnë këtë seksion.',
            'sr' => 'Немате приступ овој страници. Само администратори могу приступити овом делу.',
            'en' => 'You do not have access to this page. Only administrators can access this section.'
        ];
        $message_type = 'warning';
    } elseif ($message === 'session_expired') {
        $message_content = [
            'sq' => 'Seanca juaj ka skaduar. Ju lutemi hyni përsëri në sistem.',
            'sr' => 'Ваша сесија је истекла. Молимо вас да се поново пријавите.',
            'en' => 'Your session has expired. Please log in again.'
        ];
        $message_type = 'warning';
    }
    
    if ($message_content):
    ?>
    <div style="background-color: <?php echo $message_type === 'info' ? '#e3f2fd' : '#fff3cd'; ?>; border-left: 4px solid <?php echo $message_type === 'info' ? '#2196F3' : '#ff9800'; ?>; padding: 16px; margin: 16px 0; border-radius: 4px; margin-top: 80px;">
        <div style="color: <?php echo $message_type === 'info' ? '#1565c0' : '#856404'; ?>; font-weight: 500;">
            <?php echo isset($message_content[$lang]) ? $message_content[$lang] : $message_content['en']; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Logo shown only in navbar below -->
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#" style="display:inline-flex;align-items:center;gap:10px;">
                <img src="images/pngwing.com (1).png" alt="E-Noteria Logo" style="height:60px;width:auto;vertical-align:middle;margin-right:12px;">
                <?php echo $text['hero']['title'][$lang]; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#features"><?php echo $text['nav']['features'][$lang]; ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#pricing"><?php echo $text['nav']['pricing'][$lang]; ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="services.php"><?php echo $text['nav']['services'][$lang]; ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="news.php"><?php echo $text['nav']['news'][$lang]; ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="ndihma.php"><?php echo $text['nav']['help'][$lang]; ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php"><?php echo $text['nav']['contact'][$lang]; ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php"><?php echo $text['nav']['login'][$lang]; ?></a>
                    </li>
                        <li class="nav-item">
                        </li>
                    <li class="nav-item">
                        <a class="btn btn-pricing ms-3" href="register.php"><?php echo $text['nav']['register'][$lang]; ?></a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center hero-content">
                    <h1 data-aos="fade-up"><?php echo $text['hero']['title'][$lang]; ?></h1>
                    <p data-aos="fade-up" data-aos-delay="200">
                        <?php echo $text['hero']['desc'][$lang]; ?>
                    </p>
                    <div data-aos="fade-up" data-aos-delay="400">
                        <a href="register.php" class="btn-cta me-3">
                            <i class="fas fa-rocket me-2"></i><?php echo $text['hero']['start'][$lang]; ?>
                        </a>
                        <a href="#features" class="btn btn-outline-light">
                            <i class="fas fa-info-circle me-2"></i><?php echo $text['hero']['learn'][$lang]; ?>
                        </a>
                    </div>

                    <div class="hero-stats" data-aos="fade-up" data-aos-delay="600">
                        <div class="stat-item">
                            <span class="stat-number"><?php echo number_format($tenant_count); ?>+</span>
                            <span class="stat-label"><?php echo $text['hero']['notary_offices'][$lang]; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo number_format($user_count); ?>+</span>
                            <span class="stat-label"><?php echo $text['hero']['active_users'][$lang]; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo number_format($reservation_count); ?>+</span>
                            <span class="stat-label"><?php echo $text['hero']['daily_reservations'][$lang]; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features">
        <div class="container">
            <div class="section-title" data-aos="fade-up">
                <h2><?php echo $text['features_title'][$lang]; ?></h2>
                <p><?php echo $text['features_desc'][$lang]; ?></p>
            </div>
            <div class="row g-4">
                <?php foreach ($text['feature_cards'] as $i => $card): ?>
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?php echo 100 + $i*100; ?>">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas <?php echo $card['icon']; ?>"></i>
                        </div>
                        <h3><?php echo $card['title'][$lang]; ?></h3>
                        <p><?php echo $card['desc'][$lang]; ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="pricing">
        <div class="container">
            <div class="section-title" data-aos="fade-up">
                <h2><?php echo $text['pricing_title'][$lang]; ?></h2>
                <p><?php echo $text['pricing_desc'][$lang]; ?></p>
            </div>
            <div class="row g-4 justify-content-center">
                <!-- Abonim Mujor -->
                <div class="col-lg-5 col-md-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="pricing-card">
                        <div class="pricing-header">
                            <div class="pricing-name"><?php echo $text['pricing']['monthly']['name'][$lang]; ?></div>
                            <div class="pricing-price">
                                <span class="currency">€</span>30.00
                                <span class="period">/<?php echo ($lang=='en'?'month':($lang=='sr'?'месец':'muaj')); ?></span>
                            </div>
                            <p class="text-muted"><?php echo $text['pricing']['monthly']['desc'][$lang]; ?></p>
                        </div>
                        <ul class="pricing-features">
                            <?php foreach($text['pricing']['monthly']['features'] as $f): ?>
                                <li><i class="fas fa-check"></i><?php echo $f[$lang]; ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <a href="zyrat_register.php?plan=1" class="btn-pricing">
                            <?php echo $text['pricing']['monthly']['register'][$lang]; ?>
                        </a>
                    </div>
                </div>
                <!-- Abonim Vjetor -->
                <div class="col-lg-5 col-md-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="pricing-card popular">
                        <div class="badge bg-warning text-dark position-absolute" style="top: -10px; right: 20px;">
                            <?php echo $text['pricing']['yearly']['badge'][$lang]; ?>
                        </div>
                        <div class="pricing-header">
                            <div class="pricing-name"><?php echo $text['pricing']['yearly']['name'][$lang]; ?></div>
                            <div class="pricing-price">
                                <span class="currency">€</span>360.00
                                <span class="period">/<?php echo ($lang=='en'?'year':($lang=='sr'?'година':'vitë')); ?></span>
                            </div>
                            <p class="text-muted"><?php echo $text['pricing']['yearly']['desc'][$lang]; ?></p>
                        </div>
                        <ul class="pricing-features">
                            <?php foreach($text['pricing']['yearly']['features'] as $f): ?>
                                <li><i class="fas fa-check"></i><?php echo $f[$lang]; ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <a href="zyrat_register.php?plan=2" class="btn-pricing">
                            <?php echo $text['pricing']['yearly']['register'][$lang]; ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center" data-aos="fade-up">
                    <h2><?php echo $text['cta_title'][$lang]; ?></h2>
                    <p><?php echo $text['cta_desc'][$lang]; ?></p>
                    <a href="zyrat_register.php" class="btn-cta">
                        <i class="fas fa-rocket me-2"></i><?php echo $text['cta_btn'][$lang]; ?>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h5><i class="fas fa-balance-scale me-2"></i>E-Noteria SaaS</h5>
                    <p><?php echo $text['footer']['official'][$lang]; ?></p>
                    <div class="mt-3">
                        <a href="#" class="text-white me-3"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="footer-section">
                    <h5><?php echo $text['footer']['product'][$lang]; ?></h5>
                    <ul class="footer-links">
                        <li><a href="#features"><?php echo $text['footer']['links']['features'][$lang]; ?></a></li>
                        <li><a href="#pricing"><?php echo $text['footer']['links']['pricing'][$lang]; ?></a></li>
                        <li><a href="services.php"><?php echo $text['footer']['links']['services'][$lang]; ?></a></li>
                        <li><a href="news.php"><?php echo $text['footer']['links']['news'][$lang]; ?></a></li>
                        <li><a href="ndihma.php"><?php echo $text['footer']['links']['help'][$lang]; ?></a></li>
                        <li><a href="login.php"><?php echo $text['footer']['links']['login'][$lang]; ?></a></li>
                        <li><a href="zyrat_register.php"><?php echo $text['footer']['links']['register'][$lang]; ?></a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h5><?php echo $text['footer']['support'][$lang]; ?></h5>
                    <ul class="footer-links">
                        <li><a href="ndihma.php"><?php echo $text['footer']['links']['help'][$lang]; ?></a></li>
                        <li><a href="#"><?php echo $text['footer']['links']['help_center'][$lang]; ?></a></li>
                        <li><a href="#"><?php echo $text['footer']['links']['docs'][$lang]; ?></a></li>
                        <li><a href="api_reference.php"><?php echo $text['footer']['links']['api'][$lang]; ?></a></li>
                        <li><a href="contact.php"><?php echo $text['footer']['links']['contact'][$lang]; ?></a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h5><?php echo $text['footer']['legal'][$lang]; ?></h5>
                    <ul class="footer-links">
                        <li><a href="privatesia.php"><?php echo $text['footer']['links']['privacy'][$lang]; ?></a></li>
                        <li><a href="terms.php"><?php echo $text['footer']['links']['terms'][$lang]; ?></a></li>
                        <li><a href="security.php"><?php echo $text['footer']['links']['security'][$lang]; ?></a></li>
                        <li><a href="compliance.php"><?php echo $text['footer']['links']['compliance'][$lang]; ?></a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p><?php echo $text['footer']['copyright'][$lang]; ?></p>
                <p><?php echo $text['footer']['developed'][$lang]; ?></p>
            </div>
        </div>
    </footer>

    <!-- Language Switcher Dropdown (fixed, visible, reliable) -->
    <div style="position:fixed;top:10px;right:10px;z-index:1200;">
        <div class="dropdown">
            <button class="btn btn-light dropdown-toggle" type="button" id="langDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <?php echo $langs[$lang]; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="langDropdown" style="z-index:1201;background:#fff;">
                <?php foreach($langs as $k=>$v): ?>
                    <li><a class="dropdown-item<?php if($lang==$k)echo ' active'; ?>" href="?lang=<?php echo $k; ?>"><?php echo $v; ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <script src="/assets/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js" integrity="sha512-A7AYk1fGKX6S2SsHywmPkrnzTZHrgiVT7GcQkLGDe2ev0aWb8zejytzS8wjo7PGEXKqJOrjQ4oORtnimIRZBtw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            once: true
        });

        // Smooth scrolling (skip anchors with href='#' or empty hash)
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                const href = this.getAttribute('href');
                if (href === '#' || href === '' || href === null) return; // skip invalid
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.style.background = 'rgba(255, 255, 255, 0.98)';
                navbar.style.boxShadow = '0 2px 20px rgba(0,0,0,0.1)';
            } else {
                navbar.style.background = 'rgba(255, 255, 255, 0.95)';
                navbar.style.boxShadow = '0 2px 20px rgba(0,0,0,0.1)';
            }
        });
    </script>
</body>
</html>
