<?php
// docusign_send_document_sdk.php
// Shembull i plotë për dërgimin e një dokumenti për nënshkrim me DocuSign PHP SDK

require_once __DIR__ . '/vendor/autoload.php';

// Vendos këtu access token të marrë nga docusign_auth.php
$access_token = 'VENDOS_KETU_ACCESS_TOKEN';
// Vendos këtu account_id të marrë nga DocuSign (mund ta marrësh nga https://developers.docusign.com/docs/esign-rest-api/how-to/get-account-information/)
$account_id = 'VENDOS_KETU_ACCOUNT_ID';

// Të dhënat e marrësit (nënshkruesit)
$recipient_name = 'Emri Mbiemri';
$recipient_email = 'email@example.com';

// Dokumenti për nënshkrim (PDF i koduar base64)
$file_path = __DIR__ . '/shembull.pdf';
$file_content = file_get_contents($file_path);
$base64_file = base64_encode($file_content);

// Krijo dokumentin
$document = new DocuSign\eSign\Model\Document([
    'document_base64' => $base64_file,
    'name' => 'Dokumenti per nenshkrim',
    'file_extension' => 'pdf',
    'document_id' => '1'
]);

// Krijo nënshkruesin
$signer = new DocuSign\eSign\Model\Signer([
    'email' => $recipient_email,
    'name' => $recipient_name,
    'recipient_id' => '1',
    'routing_order' => '1'
]);

// Vendos pozicionin e nënshkrimit në faqe (anchor)
$sign_here = new DocuSign\eSign\Model\SignHere([
    'anchor_string' => '/nenshkruaj_ketu/',
    'anchor_units' => 'pixels',
    'anchor_x_offset' => '0',
    'anchor_y_offset' => '0'
]);

$tabs = new DocuSign\eSign\Model\Tabs([
    'sign_here_tabs' => [$sign_here]
]);
$signer->setTabs($tabs);

// Krijo marrësit
$recipients = new DocuSign\eSign\Model\Recipients([
    'signers' => [$signer]
]);

// Krijo envelope
$envelope_definition = new DocuSign\eSign\Model\EnvelopeDefinition([
    'email_subject' => 'Ju lutem nënshkruani këtë dokument',
    'documents' => [$document],
    'recipients' => $recipients,
    'status' => 'sent' // ose 'created' për të ruajtur si draft
]);

// Inicializo API client
$config = new DocuSign\eSign\Configuration();
$config->setHost('https://demo.docusign.net/restapi');
$config->addDefaultHeader('Authorization', 'Bearer ' . $access_token);
$apiClient = new DocuSign\eSign\ApiClient($config);

// Dërgo envelope
$envelopeApi = new DocuSign\eSign\Api\EnvelopesApi($apiClient);
try {
    $envelopeSummary = $envelopeApi->createEnvelope($account_id, $envelope_definition);
    echo '<h2>Envelope u dërgua me sukses!</h2>';
    echo '<b>ID e envelope:</b> ' . htmlspecialchars($envelopeSummary->getEnvelopeId());
} catch (Exception $e) {
    echo '<b>Gabim gjatë dërgimit:</b><br>' . htmlspecialchars($e->getMessage());
}

// Për të marrë statusin e envelope ose dokumentin e nënshkruar, mund të përdorësh funksionet:
// $envelopeApi->getEnvelope($account_id, $envelopeId);
// $envelopeApi->getDocument($account_id, $envelopeId, $documentId);
