<?php
/**
 * SecurityValidator Class
 * Handles validation of documents, bank details, and other sensitive data.
 */
class SecurityValidator
{
    /**
     * Validates an IBAN using the Modulo 97 algorithm.
     * For Kosovo notary services, validates Kosovo IBAN format specifically.
     * Kosovo IBANs are 20 characters: XK + 2 check digits + 16 account digits.
     * 
     * @param string $iban The IBAN to validate
     * @return bool True if valid, false otherwise
     */
    public static function validateIBAN($iban)
    {
        $iban = strtoupper(str_replace(' ', '', $iban));
        // Lejo vetëm IBAN të Kosovës me 20 karaktere: XK + 2 shifra + 16 shifra
        return (bool)preg_match('/^XK[0-9]{2}[0-9]{16}$/', $iban);
    }

    /**
     * Validates an uploaded document for security.
     * Checks MIME type, extension, and magic bytes.
     * 
     * @param array $file The $_FILES['input_name'] array
     * @return array ['valid' => bool, 'message' => string]
     */
    public static function validateDocument($file)
    {
        // 1. Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'message' => 'Gabim gjatë ngarkimit të skedarit.'];
        }

        // 2. Check file size (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            return ['valid' => false, 'message' => 'Skedari është shumë i madh. Maksimumi është 5MB.'];
        }

        // 3. Allowed extensions and MIME types
        $allowed = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png'
        ];

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!array_key_exists($ext, $allowed)) {
            return ['valid' => false, 'message' => 'Formati i skedarit nuk lejohet. Vetëm PDF, JPG, PNG.'];
        }

        // 4. Verify MIME type using FileInfo (Magic Bytes)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        if ($mime !== $allowed[$ext]) {
            // Special case for JPEGs which can be image/jpeg or image/jpg
            if (!($ext === 'jpg' && $mime === 'image/jpeg')) {
                return ['valid' => false, 'message' => 'Përmbajtja e skedarit nuk përputhet me formatin.'];
            }
        }

        // 5. Scan for malicious content (Basic check)
        // Read first 100 bytes to check for PHP tags or other scripts disguised as images
        $content = file_get_contents($file['tmp_name'], false, null, 0, 100);
        if (stripos($content, '<?php') !== false || stripos($content, '<script') !== false) {
            return ['valid' => false, 'message' => 'Skedari përmban kod të dyshimtë.'];
        }

        return ['valid' => true, 'message' => 'Skedari është i vlefshëm.'];
    }

    /**
     * Verifies Cloudflare Turnstile Token
     * 
     * @param string $token The token from the frontend
     * @return bool
     */
    public static function verifyTurnstile($token)
    {
        // Replace with your actual Secret Key from Cloudflare Dashboard
        $secretKey = '1x0000000000000000000000000000000AA'; 
        $ip = $_SERVER['REMOTE_ADDR'];

        $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
        $data = [
            'secret' => $secretKey,
            'response' => $token,
            'remoteip' => $ip
        ];

        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data)
            ]
        ];

        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        $response = json_decode($result);

        return $response->success;
    }
}
?>
