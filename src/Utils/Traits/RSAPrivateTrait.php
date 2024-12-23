<?php

namespace Yggdrasil\Utils\Traits;

trait RSAPrivateTrait {
    protected $privateKey;

    public function private_decrypt(string $data): string|null {
        $chunkSize = openssl_pkey_get_details(openssl_get_privatekey($this->privateKey))['bits'] / 8;
        $decrypted = '';
        $dataChunks = str_split(base64_decode($data), $chunkSize); // Split data into chunks of 117 bytes
        foreach ($dataChunks as $chunk) {
            if (openssl_private_decrypt($chunk, $partialDecrypted, $this->privateKey, OPENSSL_PKCS1_PADDING)) {
                $decrypted .= $partialDecrypted;
            } else {
                return null; // Return null if any chunk fails to decrypt
            }
        }
        return $decrypted;
    }

    public function private_encrypt(string $data): string|null {
        $chunkSize = openssl_pkey_get_details(openssl_get_privatekey($this->privateKey))['bits'] / 8 - 11;
        $encrypted = '';
        $dataChunks = str_split($data, $chunkSize); // Split data into chunks of 100 characters
        foreach ($dataChunks as $chunk) {
            if (openssl_private_encrypt($chunk, $partialEncrypted, $this->privateKey, OPENSSL_PKCS1_PADDING)) {
                $encrypted .= $partialEncrypted;
            } else {
                return null; // Return null if any chunk fails to encrypt
            }
        }
        $encrypted = base64_encode($encrypted);
        return $encrypted;
    }
    
    public function sign(string $data): string|null {
        if (openssl_sign($data, $signature, $this->privateKey)) {
            return base64_encode($signature);
        }
        return null;
    }
}