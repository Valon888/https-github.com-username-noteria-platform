<?php
// UserKey model for PKI
class UserKey {
    public $id, $user_id, $public_key, $private_key_encrypted;
    public static function generateKeyPair() {
        $config = ["private_key_bits" => 2048, "private_key_type" => OPENSSL_KEYTYPE_RSA];
        $res = openssl_pkey_new($config);
        openssl_pkey_export($res, $privKey);
        $pubKey = openssl_pkey_get_details($res)['key'];
        return ['private' => $privKey, 'public' => $pubKey];
    }
    public static function encryptPrivateKey($privateKey, $password) {
        return openssl_encrypt($privateKey, 'aes-256-cbc', $password, 0, substr(hash('sha256', $password), 0, 16));
    }
    public static function decryptPrivateKey($encrypted, $password) {
        return openssl_decrypt($encrypted, 'aes-256-cbc', $password, 0, substr(hash('sha256', $password), 0, 16));
    }
}
