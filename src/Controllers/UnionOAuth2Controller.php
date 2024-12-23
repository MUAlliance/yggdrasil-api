<?php

namespace Yggdrasil\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use DB;
use Log;

use App\Models\Player;
use Yggdrasil\Utils\RSAPublicUtil;

class UnionOAuth2Controller extends Controller {

    private int $userInfoTokenTTL = 600; // 10 minutes

    public function __construct() {
        $this->middleware(function ($request, $next) {
            if (option('union_enable_oauth2') == false) {
                throw new ForbiddenOperationException('Union OAuth2 is not enabled on this server.');
            }
            $unionUrl = parse_url(option('union_api_root'));
            $unionDomain = $unionUrl['scheme'] . '://' . $unionUrl['host'];


            return $next($request)
                ->header('Access-Control-Allow-Origin', $unionDomain)
                ->header('Access-Control-Allow-Credentials', 'true')
                ->header('Access-Control-Allow-Methods', 'GET, POST');
        });
        $this->middleware(['auth', 'web'])->only('grant');
    }
    
    public function grant(Request $request) {
        if (!openssl_pkey_get_private(option('union_oauth2_sig_private_key')) || !openssl_pkey_get_public(option('union_oauth2_sig_public_key'))) {
            throw new ForbiddenOperationException('The signature key of this site is invalid. Please contact the site manager.');
        }

        $unionAPIQuery = Http::get(option('union_api_root').'/oauth2/backend')->json();
        $validator = Validator::make($unionAPIQuery, [
            'publicKey' => 'required|string'
        ]);
        if ($validator->fails()) {
            throw new ForbiddenOperationException('Union server down.');
        }
        $unionOAuth2PublicKey = $unionAPIQuery['publicKey'];

        $user = Auth::user();
        $userInfo = base64_encode(json_encode([
            'uid' => $user->uid,
            'nickname' => $user->nickname,
            'email' => $user->email,
            'expires_at' => time() + $this->userInfoTokenTTL
        ]));
        $mac = hash_hmac('sha256', $userInfo, option('union_member_key'));
        if (!openssl_sign($userInfo.'.'.$mac, $signature, option('union_oauth2_sig_private_key'), OPENSSL_ALGO_SHA256)) {
            throw new ForbiddenOperationException('Failed to sign the token.');
        }

        $rsaPublicUtil = new RSAPublicUtil($unionOAuth2PublicKey);
        $token = [
            'userInfo' => $userInfo,
            'mac' => $mac,
            'signature' => base64_encode($signature)
        ];
        $encryptedToken = $rsaPublicUtil->public_encrypt(json_encode($token));
        if ($encryptedToken === null) {
            throw new ForbiddenOperationException('Failed to encrypt the token.');
        }

        $redirectUri = option('union_api_root') . '/oauth2/continue?' . http_build_query(array_merge([
            'userInfoToken' => $encryptedToken,
        ], $request->all()));
        return redirect()->away($redirectUri);
    }

    public function getSigPublicKey() {
        $unionUrl = parse_url(option('union_api_root'));
        $unionDomain = $unionUrl['scheme'] . '://' . $unionUrl['host'];
        return json([
            'signaturePublicKey' => option('union_oauth2_sig_public_key')
        ]);
    }

}