<?php
// pay_bank.php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $zyra_id = $_POST['zyra_id'] ?? '';
    $banka = $_POST['banka'] ?? '';
    // Gjuha
    $lang = $_SESSION['lang'] ?? 'sq';
    $translations = [
        'title' => [
            'sq' => 'Instruksione për pagesë',
            'sr' => 'Instrukcije za plaćanje',
            'en' => 'Payment Instructions',
        ],
        'office' => [
            'sq' => 'Zyra',
            'sr' => 'Kancelarija',
            'en' => 'Office',
        ],
        'bank' => [
            'sq' => 'Banka e zgjedhur',
            'sr' => 'Izabrana banka',
            'en' => 'Selected bank',
        ],
        'amount' => [
            'sq' => 'Shuma për pagesë',
            'sr' => 'Iznos za plaćanje',
            'en' => 'Amount to pay',
        ],
        'continue' => [
            'sq' => 'Ju lutemi vazhdoni me pagesën online përmes bankës së zgjedhur. (Integrimi real mund të shtohet sipas API-së së bankës)',
            'sr' => 'Molimo nastavite sa online plaćanjem putem izabrane banke. (Prava integracija može se dodati prema API-ju banke)',
            'en' => 'Please continue with online payment via the selected bank. (Real integration can be added according to the bank API)',
        ],
        'success' => [
            'sq' => 'Pagesa u krye me sukses! Nga tani ju mund t’i përdorni të gjitha shërbimet e kësaj platforme.',
            'sr' => 'Uplata je uspešno izvršena! Od sada možete koristiti sve usluge ove platforme.',
            'en' => 'Payment was successful! You can now use all services of this platform.',
        ],
        'back' => [
            'sq' => 'Kthehu te regjistrimi',
            'sr' => 'Vrati se na registraciju',
            'en' => 'Back to registration',
        ],
        'missing' => [
            'sq' => 'Të dhënat e pagesës mungojnë!',
            'sr' => 'Podaci o plaćanju nedostaju!',
            'en' => 'Payment data is missing!',
        ],
        'notfound' => [
            'sq' => 'Zyra nuk u gjet!',
            'sr' => 'Kancelarija nije pronađena!',
            'en' => 'Office not found!',
        ],
        'onlypost' => [
            'sq' => 'Kjo faqe mund të aksesohet vetëm pas zgjedhjes së bankës!',
            'sr' => 'Ova stranica može se pristupiti samo nakon izbora banke!',
            'en' => 'This page can only be accessed after selecting a bank!',
        ],
    ];
    if (empty($zyra_id) || empty($banka)) {
        echo '<div style="color:#d32f2f;font-weight:600;">' . $translations['missing'][$lang] . '</div>';
        exit;
    }
    // Kontrollo nëse zyra ekziston
    $stmt = $pdo->prepare('SELECT emri, pagesa FROM zyrat WHERE id = ?');
    $stmt->execute([$zyra_id]);
    $zyra = $stmt->fetch();
    if (!$zyra) {
        echo '<div style="color:#d32f2f;font-weight:600;">' . $translations['notfound'][$lang] . '</div>';
        exit;
    }
    $emri_zyres = htmlspecialchars($zyra['emri']);
    $shuma = htmlspecialchars($zyra['pagesa']);
    // Faturim automatik
    $data_fature = date('Y-m-d H:i:s');
    $status_fature = 'Paguar';
    // Krijo faturën në DB (tabela faturat: zyra_id, banka, shuma, data, status)
    try {
        $stmtF = $pdo->prepare('INSERT INTO faturat (zyra_id, banka, shuma, data, status) VALUES (?, ?, ?, ?, ?)');
        $stmtF->execute([$zyra_id, $banka, $shuma, $data_fature, $status_fature]);
        // Regjistro abonimin aktiv
        $stmtA = $pdo->prepare('UPDATE zyrat SET abonim_aktiv = 1, data_aktivizimit = ? WHERE id = ?');
        $stmtA->execute([$data_fature, $zyra_id]);
    } catch (Exception $ex) {
        // Nëse tabela nuk ekziston, mos ndalo ekzekutimin
    }
    echo '<div style="max-width:400px;margin:60px auto;background:#fff;border-radius:16px;box-shadow:0 8px 24px rgba(0,0,0,0.08);padding:36px 28px;text-align:center;">';
    echo '<h2 style="color:#2d6cdf;">' . $translations['title'][$lang] . '</h2>';
    echo '<div style="margin-bottom:18px;font-size:1.1rem;">' . $translations['office'][$lang] . ': <b>' . $emri_zyres . '</b></div>';
    echo '<div style="margin-bottom:18px;font-size:1.1rem;">' . $translations['bank'][$lang] . ': <b>' . htmlspecialchars($banka) . '</b></div>';
    echo '<div style="margin-bottom:18px;font-size:1.1rem;">' . $translations['amount'][$lang] . ': <b>' . $shuma . ' €</b></div>';
    echo '<div style="background:#e2eafc;padding:12px;border-radius:8px;margin-bottom:18px;">' . $translations['continue'][$lang] . '</div>';
    echo '<div style="background:#eafaf1;color:#388e3c;padding:12px;border-radius:8px;margin-bottom:18px;font-weight:600;">';
    echo $translations['success'][$lang];
    echo '</div>';
    echo '<a href="zyrat_register.php" style="color:#2d6cdf;font-weight:600;">' . $translations['back'][$lang] . '</a>';
    echo '</div>';
} else {
    $lang = $_SESSION['lang'] ?? 'sq';
    $translations = [
        'onlypost' => [
            'sq' => 'Kjo faqe mund të aksesohet vetëm pas zgjedhjes së bankës!',
            'sr' => 'Ova stranica može se pristupiti samo nakon izbora banke!',
            'en' => 'This page can only be accessed after selecting a bank!',
        ],
    ];
    echo '<div style="color:#d32f2f;font-weight:600;max-width:400px;margin:60px auto;">' . $translations['onlypost'][$lang] . '</div>';
}
?>
