<?php
// docusign_get_signed_document.php
// Shembull për të marrë statusin dhe shkarkuar dokumentin e nënshkruar nga DocuSign

require_once __DIR__ . '/vendor/autoload.php';

use DocuSign\eSign\Configuration;
use DocuSign\eSign\ApiClient;
use DocuSign\eSign\Api\EnvelopesApi;

// Vendos këtu access token dhe account_id
$access_token = 'VENDOS_KETU_ACCESS_TOKEN';
$account_id = 'VENDOS_KETU_ACCOUNT_ID';
$envelope_id = 'VENDOS_KETU_ENVELOPE_ID'; // ID e envelope të marrë nga dërgimi
$document_id = '1'; // Zakonisht "1" për dokumentin kryesor

$config = new DocuSign\eSign\Configuration();
$config->setHost('https://demo.docusign.net/restapi');
$config->addDefaultHeader('Authorization', 'Bearer ' . $access_token);
$apiClient = new DocuSign\eSign\ApiClient($config);
$envelopeApi = new DocuSign\eSign\Api\EnvelopesApi($apiClient);

try {
    // Merr statusin e envelope
    $envelope = $envelopeApi->getEnvelope($account_id, $envelope_id);
    echo '<h2>Statusi i envelope:</h2>';
    echo '<b>' . htmlspecialchars($envelope->getStatus()) . '</b><br><br>';

    // Nëse është "completed", shkarko dokumentin e nënshkruar
    if ($envelope->getStatus() === 'completed') {
        $signedDocument = $envelopeApi->getDocument($account_id, $envelope_id, $document_id);
        $fileName = 'dokument_i_nenshkruar.pdf';
        file_put_contents($fileName, $signedDocument);
        echo 'Dokumenti i nënshkruar u shkarkua si <b>' . htmlspecialchars($fileName) . '</b>.';
    } else {
        echo 'Dokumenti nuk është ende i nënshkruar.';
    }
} catch (Exception $e) {
    echo '<b>Gabim:</b><br>' . htmlspecialchars($e->getMessage());
}
