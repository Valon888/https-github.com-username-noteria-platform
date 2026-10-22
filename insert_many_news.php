<?php
require_once 'confidb.php';

$news_items = [
    [
        'title_sq' => 'Sistemi i Sigurisë së Avancuar',
        'title_sr' => 'Napredni sistem sigurnosti',
        'title_en' => 'Advanced Security System',
        'content_sq' => 'Platforma jonë përfshin sisteme të avancuara sigurie si security.php, SecurityHeaders.php, SecurityValidator.php dhe audit logs për të mbrojtur të dhënat e përdoruesve.',
        'content_sr' => 'Naša platforma uključuje napredne sisteme sigurnosti kao što su security.php, SecurityHeaders.php, SecurityValidator.php i audit logs za zaštitu korisničkih podataka.',
        'content_en' => 'Our platform includes advanced security systems such as security.php, SecurityHeaders.php, SecurityValidator.php, and audit logs to protect user data.',
    ],
    [
        'title_sq' => 'Integrime Pagesash dhe Faturimit',
        'title_sr' => 'Integracije plaćanja i fakturisanja',
        'title_en' => 'Payment and Billing Integrations',
        'content_sq' => 'Mbështesim sisteme pagesash si Paysera, Tinky, dhe faturim automatik me skedarë si billing_dashboard.php, payment_processor.php dhe cron jobs për pagesa mujore.',
        'content_sr' => 'Podržavamo sisteme plaćanja kao što su Paysera, Tinky, i automatsko fakturisanje sa fajlovima kao billing_dashboard.php, payment_processor.php i cron jobs za mesečne uplate.',
        'content_en' => 'We support payment systems like Paysera, Tinky, and automatic billing with files like billing_dashboard.php, payment_processor.php, and cron jobs for monthly payments.',
    ],
    [
        'title_sq' => 'Paneli Admin dhe Raportet',
        'title_sr' => 'Admin paneli i izveštaji',
        'title_en' => 'Admin Panels and Reports',
        'content_sq' => 'Adminët mund të menaxhojnë gjithçka përmes admin_dashboard.php, admin_users.php, admin_reports.php dhe statistikave të detajuara për performancën e platformës.',
        'content_sr' => 'Administratori mogu upravljati svime preko admin_dashboard.php, admin_users.php, admin_reports.php i detaljnih statistika o performansama platforme.',
        'content_en' => 'Admins can manage everything through admin_dashboard.php, admin_users.php, admin_reports.php, and detailed statistics on platform performance.',
    ],
    [
        'title_sq' => 'Thirrje Video dhe Komunikim',
        'title_sr' => 'Video pozivi i komunikacija',
        'title_en' => 'Video Calls and Communication',
        'content_sq' => 'Ofroni thirrje video në kohë reale me video_call.php, video_call_room.php dhe integrime për komunikim si chat.php dhe notifications.',
        'content_sr' => 'Nudimo video pozive u realnom vremenu sa video_call.php, video_call_room.php i integracijama za komunikaciju kao chat.php i obaveštenja.',
        'content_en' => 'Offer real-time video calls with video_call.php, video_call_room.php, and communication integrations like chat.php and notifications.',
    ],
    [
        'title_sq' => 'API dhe Integrime të Jashtme',
        'title_sr' => 'API i spoljne integracije',
        'title_en' => 'API and External Integrations',
        'content_sq' => 'API të fuqishme për integrime me sisteme të jashtme si api.php, docusign për nënshkrime elektronike dhe OpenAI për chatbot.',
        'content_sr' => 'Moćni API-ji za integracije sa spoljnim sistemima kao api.php, docusign za elektronske potpise i OpenAI za chatbot.',
        'content_en' => 'Powerful APIs for integrations with external systems like api.php, docusign for electronic signatures, and OpenAI for chatbot.',
    ],
    [
        'title_sq' => 'Menaxhimi i Dokumenteve dhe Notarëve',
        'title_sr' => 'Upravljanje dokumentima i notarima',
        'title_en' => 'Document and Notary Management',
        'content_sq' => 'Sistemi për menaxhimin e dokumenteve private, noterëve dhe zyrave noteriale me skedarë si private_documents, notaries.php dhe admin_notars.php.',
        'content_sr' => 'Sistem za upravljanje privatnim dokumentima, notarima i notarskim kancelarijama sa fajlovima kao private_documents, notaries.php i admin_notars.php.',
        'content_en' => 'System for managing private documents, notaries, and notary offices with files like private_documents, notaries.php, and admin_notars.php.',
    ],
    [
        'title_sq' => 'Verifikimi dhe Autentifikimi',
        'title_sr' => 'Verifikacija i autentifikacija',
        'title_en' => 'Verification and Authentication',
        'content_sq' => 'Verifikim i fortë me MFA, SMS dhe email verifikime përmes mfa_setup.php, phone_verification_api.php dhe PHPMailer për komunikime.',
        'content_sr' => 'Snažna verifikacija sa MFA, SMS i email verifikacijama preko mfa_setup.php, phone_verification_api.php i PHPMailer za komunikacije.',
        'content_en' => 'Strong verification with MFA, SMS, and email verifications through mfa_setup.php, phone_verification_api.php, and PHPMailer for communications.',
    ],
    [
        'title_sq' => 'Optimizimi dhe Performanca',
        'title_sr' => 'Optimizacija i performanse',
        'title_en' => 'Optimization and Performance',
        'content_sq' => 'Optimizime backend dhe frontend për performancë të lartë me performance.php, frontend_quality_optimization.js dhe caching sisteme.',
        'content_sr' => 'Backend i frontend optimizacije za visoke performanse sa performance.php, frontend_quality_optimization.js i sistemima keširanja.',
        'content_en' => 'Backend and frontend optimizations for high performance with performance.php, frontend_quality_optimization.js, and caching systems.',
    ],
    [
        'title_sq' => 'Reklamat dhe Biznesi',
        'title_sr' => 'Oglasi i biznis',
        'title_en' => 'Advertisements and Business',
        'content_sq' => 'Sistemi reklamash për biznes me ads_setup.php, business_advertising.php dhe track_ad_click.php për analizë.',
        'content_sr' => 'Sistem oglasa za biznis sa ads_setup.php, business_advertising.php i track_ad_click.php za analizu.',
        'content_en' => 'Advertisement system for business with ads_setup.php, business_advertising.php, and track_ad_click.php for analytics.',
    ],
    [
        'title_sq' => 'Backup dhe Siguria e të Dhënave',
        'title_sr' => 'Bekap i sigurnost podataka',
        'title_en' => 'Backup and Data Security',
        'content_sq' => 'Backup automatik dhe siguri të dhënash me backup_data, noteria_backup_full dhe sisteme enkriptimi.',
        'content_sr' => 'Automatski bekap i sigurnost podataka sa backup_data, noteria_backup_full i sistemima enkripcije.',
        'content_en' => 'Automatic backup and data security with backup_data, noteria_backup_full, and encryption systems.',
    ],
];

foreach ($news_items as $news) {
    $stmt = $pdo->prepare("INSERT INTO news (title_sq, title_sr, title_en, content_sq, content_sr, content_en) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $news['title_sq'], $news['title_sr'], $news['title_en'],
        $news['content_sq'], $news['content_sr'], $news['content_en']
    ]);
}

echo "10 lajme të reja u shtuan me sukses.";
?>