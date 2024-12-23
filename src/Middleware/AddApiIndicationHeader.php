<?php

namespace Yggdrasil\Middleware;

use Illuminate\Http\Response;

class AddApiIndicationHeader
{
    public function handle($request, \Closure $next)
    {
        /** @var Response */
        $response = $next($request);
        
        if (!isset($response->exclude_ali_header)) {
            $response->header('X-Authlib-Injector-API-Location', url('api/yggdrasil'));
        }

        return $response;
    }
}
