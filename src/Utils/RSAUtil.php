<?php

namespace Yggdrasil\Utils;

class RSAUtil {
    use Traits\RSAPrivateTrait;
    use Traits\RSAPublicTrait;

    public function __construct(string $publicKey, string $privateKey) {
        $this->publicKey = $publicKey;
        $this->privateKey = $privateKey;
    }
}