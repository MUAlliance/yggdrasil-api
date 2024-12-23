<?php

namespace Yggdrasil\Middleware;

use Yggdrasil\Exceptions\ForbiddenOperationException;
use Vectorface\Whip\Whip;

class UnionHostVerify {
    
    public function handle($request, \Closure $next) {
        $host = gethostbyname(parse_url(option('union_api_root'), PHP_URL_HOST));

        $whip = new Whip();
        $ip = $whip->getValidIpAddress();

        if ($host != $ip) {
            throw new ForbiddenOperationException("Union host verification failure.");
        }

        return $next($request);
    }
}