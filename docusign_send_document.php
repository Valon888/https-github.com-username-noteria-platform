<?php
// docusign_send_document.php
// Shembull për të dërguar një dokument për nënshkrim me DocuSign API

require_once 'vendor/autoload.php';

$accessToken = 'VENDOS_KETU_ACCESS_TOKEN'; // Merr nga docusign_auth.php
$accountId = 'VENDOS_KETU_ACCOUNT_ID'; // Merr nga DocuSign dashboard
$basePath = 'https://demo.docusign.net/restapi';

$config = new DocuSign\eSign\Configuration();
$config->setHost($basePath);
$config->addDefaultHeader("Authorization", "Bearer " . $accessToken);

$apiClient = new DocuSign\eSign\ApiClient($config);
$envelopeApi = new DocuSign\eSign\Api\EnvelopesApi($apiClient);

$envelopeDefinition = new DocuSign\eSign\Model\EnvelopeDefinition([
    'email_subject' => "Ju lutemi nënshkruani dokumentin",
    'documents' => [
        new DocuSign\eSign\Model\Document([
            'document_base64' => base64_encode(file_get_contents('dokument.pdf')),
            'name' => 'Dokumenti Noterial',
            'file_extension' => 'pdf',
            'document_id' => '1'
        ])
    ],
    'recipients' => new DocuSign\eSign\Model\Recipients([
        'signers' => [
            new DocuSign\eSign\Model\Signer([
                'email' => 'klient@email.com',
                'name' => 'Emri Mbiemri',
                'recipient_id' => '1',
                'routing_order' => '1',
                'tabs' => new DocuSign\eSign\Model\Tabs([
                    'sign_here_tabs' => [
                        new DocuSign\eSign\Model\SignHere([
                            'anchor_string' => '/nenshkrimi/',
                            'anchor_units' => 'pixels',
                            'anchor_x_offset' => '0',
                            'anchor_y_offset' => '0'
                        ])
                    ]
                ])
            ])
        ]
    ]),
    'status' => 'sent'
]);

try {
    $envelopeSummary = $envelopeApi->createEnvelope($accountId, $envelopeDefinition);
    echo "Envelope ID: " . htmlspecialchars($envelopeSummary->getEnvelopeId());
} catch (Exception $e) {
    echo "<b>Gabim:</b> " . htmlspecialchars($e->getMessage());
}
