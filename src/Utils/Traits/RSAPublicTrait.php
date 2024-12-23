<?php

namespace Yggdrasil\Utils\Traits;

trait RSAPublicTrait {
    protected $publicKey;

    public function public_decrypt(string $data): string|null {
        $chunkSize = openssl_pkey_get_details(openssl_get_publickey($this->publicKey))['bits'] / 8;
        $decrypted = '';
        $dataChunks = str_split(base64_decode($data), $chunkSize); // Split data into chunks of 117 bytes
        foreach ($dataChunks as $chunk) {
            if (openssl_public_decrypt($chunk, $partialDecrypted, $this->publicKey, OPENSSL_PKCS1_PADDING)) {
                $decrypted .= $partialDecrypted;
            } else {
                return null; // Return null if any chunk fails to decrypt
            }
        }
        return $decrypted;
    }

    public function public_encrypt(string $data): string|null {
        $chunkSize = openssl_pkey_get_details(openssl_get_publickey($this->publicKey))['bits'] / 8 - 11;
        $encrypted = '';
        $dataChunks = str_split($data, $chunkSize); // Split data into chunks of 100 characters
        foreach ($dataChunks as $chunk) {
            if (openssl_public_encrypt($chunk, $partialEncrypted, $this->publicKey, OPENSSL_PKCS1_PADDING)) {
                $encrypted .= $partialEncrypted;
            } else {
                return null; // Return null if any chunk fails to encrypt
            }
        }
        $encrypted = base64_encode($encrypted);
        return $encrypted;
    }

    public function verify(string $data, string $signature): bool {
        return openssl_verify($data, base64_decode($signature), $this->publicKey);
    }
}