<?php
function ipToLong($ip) {
    // Kthen IP në numër për krahasim
    return sprintf('%u', ip2long($ip));
}

$userIp = $_SERVER['REMOTE_ADDR'];
$userIpLong = ipToLong($userIp);

// Lista e kodeve të vendeve për bllokim
$blockedCountries = [
    'RS','RU','CN','ES','GR','SK','RO','CY','IN','BR','AR','CU','VE','IR','IQ','SY','ZA','DZ','EG'
];

// Lexo CSV-në dhe kontrollo nëse IP është në range të ndaluar
if (($handle = fopen(__DIR__ . '/geoip/GeoLite2-Country-Blocks-IPv4.csv', 'r')) !== FALSE) {
    // Gjej kolonat për IP range dhe vendin
    $header = fgetcsv($handle);
    $networkIdx = array_search('network', $header);
    $countryIdx = array_search('country_iso_code', $header);

    while (($data = fgetcsv($handle)) !== FALSE) {
        $network = $data[$networkIdx];
        $country = $data[$countryIdx];

        if (in_array($country, $blockedCountries)) {
            // Kontrollo nëse IP bie brenda range-it
            list($range, $cidr) = explode('/', $network);
            $rangeLong = ipToLong($range);
            $mask = ~((1 << (32 - $cidr)) - 1);

            if (($userIpLong & $mask) == ($rangeLong & $mask)) {
                header('HTTP/1.1 403 Forbidden');
                exit('Access denied from your country.');
            }
        }
    }
    fclose($handle);
}