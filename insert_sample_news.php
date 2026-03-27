<?php
require_once 'confidb.php';

$sample_news = [
    [
        'title_sq' => 'Mirëseardhje në Platformën e-Noteria',
        'title_sr' => 'Dobrodošli na e-Noteria platformu',
        'title_en' => 'Welcome to the e-Noteria Platform',
        'content_sq' => 'Platforma jonë është dizajnuar për të lehtësuar proceset noteriale në Kosovë. Regjistrohuni për të filluar.',
        'content_sr' => 'Naša platforma je dizajnirana da olakša notarske procese u Kosovu. Registrujte se da počnete.',
        'content_en' => 'Our platform is designed to facilitate notary processes in Kosovo. Register to get started.',
    ],
    [
        'title_sq' => 'Përditësim i Sigurisë',
        'title_sr' => 'Ažuriranje sigurnosti',
        'title_en' => 'Security Update',
        'content_sq' => 'Kemi përmirësuar masat e sigurisë për të mbrojtur të dhënat tuaja personale.',
        'content_sr' => 'Poboljšali smo sigurnosne mere da zaštitimo vaše lične podatke.',
        'content_en' => 'We have improved security measures to protect your personal data.',
    ],
];

foreach ($sample_news as $news) {
    $stmt = $pdo->prepare("INSERT INTO news (title_sq, title_sr, title_en, content_sq, content_sr, content_en) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $news['title_sq'], $news['title_sr'], $news['title_en'],
        $news['content_sq'], $news['content_sr'], $news['content_en']
    ]);
}

echo "Lajme shembull u shtuan me sukses.";
?>