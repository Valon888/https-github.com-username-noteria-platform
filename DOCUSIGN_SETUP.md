# 🖊️ DocuSign E-Signature Integration Setup

## Setup Instructions

### 1. Create DocuSign Account
- Go to https://developer.docusign.com
- Sign up for a developer account
- Create an application (Integrations > Apps and Keys)

### 2. Get Your Credentials
- Account ID
- Client ID
- Client Secret
- Configure Redirect URI: `http://localhost/noteria/docusign_callback.php`

### 3. Environment Variables Setup

Add to your `.env` file or set as system environment variables:

```bash
DOCUSIGN_ACCOUNT_ID=your_account_id
DOCUSIGN_CLIENT_ID=your_client_id
DOCUSIGN_CLIENT_SECRET=your_client_secret
```

### 4. Create Database Table

Run the setup script:
```bash
php create_docusign_table.php
```

This will create:
- `docusign_envelopes` table to track all signature requests
- Stores envelope IDs, signer info, and signature status

### 5. Configure Webhook in DocuSign

1. Go to DocuSign Developer Console
2. Navigate to Apps and Keys > Connect
3. Add a new webhook with URL: `http://yourdomain.com/noteria/docusign_webhook.php`
4. Subscribe to events:
   - Envelope Sent
   - Envelope Delivered
   - Envelope Completed
   - Envelope Declined
   - Envelope Voided

## Features

✅ **Document Upload & Signature Request**
- Users upload PDF/Word documents
- System sends to DocuSign for signing
- Signer receives email with signature link

✅ **Real-Time Status Tracking**
- Monitor document status in real-time
- History of all signed documents
- Timestamps for each action

✅ **Webhook Integration**
- Automatic updates from DocuSign
- Send confirmation emails when signed
- Audit trail logging

✅ **Security**
- Encrypted document transmission
- Secure signer authentication
- Full audit trail

## API Endpoints

### E-Signature Page
```
/e_signature.php
```
- Upload documents
- Track signature status
- View signature history

### DocuSign Webhook
```
/docusign_webhook.php
```
- Receives events from DocuSign
- Updates database
- Sends notifications

## Usage Example

### Upload Document for Signature

```php
require_once 'docusign_config.php';

$result = createDocuSignEnvelope(
    document_name: 'Marrëveshje Pronësie',
    document_path: '/path/to/document.pdf',
    signer_email: 'user@example.com',
    signer_name: 'Emri Mbiemri',
    call_id: 'call_123456'  // Optional
);

if ($result['success']) {
    echo "Document sent for signature!";
    echo "Envelope ID: " . $result['envelopeId'];
}
```

### Get Envelope Status

```php
$status = getEnvelopeStatus($envelopeId);
echo $status['status'];  // 'sent', 'delivered', 'completed', etc.
```

## Database Schema

```sql
CREATE TABLE docusign_envelopes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    call_id VARCHAR(50),
    envelope_id VARCHAR(100) UNIQUE,
    document_name VARCHAR(255),
    signer_email VARCHAR(255),
    signer_name VARCHAR(255),
    status ENUM('sent', 'delivered', 'signed', 'completed', 'declined', 'voided'),
    signed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## Status Mapping

| DocuSign Status | Noteria Status | Description |
|---|---|---|
| sent | sent | Document sent to signer |
| delivered | delivered | Email delivered to signer |
| completed | signed | Signer has signed the document |
| declined | declined | Signer declined to sign |
| voided | voided | Document has been voided |

## Pricing Impact

**Platform Value Increase: €2,000-3,000**

### Revenue Opportunities
- €0.50 - €2.00 per document signature
- Premium signature packages
- Bulk signing discounts
- White-label e-signature service

### Example Revenue Model
- 100 documents/month × €1.00 = €100/month = €1,200/year
- 500 documents/month × €1.00 = €500/month = €6,000/year
- 1,000 documents/month × €1.00 = €1,000/month = €12,000/year

## Troubleshooting

### Token Errors
- Verify Client ID and Client Secret
- Check that credentials are correctly set in environment
- Token expires after ~1 hour (auto-refreshed)

### Document Upload Fails
- Check file size (max 5MB)
- Verify file format (PDF, DOC, DOCX only)
- Ensure `/uploads/documents/` directory exists and is writable

### Webhook Not Working
- Verify webhook URL is publicly accessible
- Check firewall/port settings
- View DocuSign logs in developer console

## Security Considerations

- API credentials stored in environment variables (never in code)
- Documents encrypted in transit (HTTPS required)
- Audit trail of all signature events
- Webhook signature verification recommended
- Rate limiting on document uploads
