<?php

namespace Yggdrasil\Utils;

class RSAPublicUtil {
    use Traits\RSAPublicTrait;

    public function __construct(string $publicKey) {
        $this->publicKey = $publicKey;
    }
}