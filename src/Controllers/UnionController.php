<?php

namespace Yggdrasil\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use DB;
use Log;

use App\Models\Player;

class UnionController extends Controller {
    
    public function hello(Request $request) {
        $enabled_features = [
        	'unionBlacklist',
        ];
        if (option('require_verification')) { $enabled_features[] = 'emailVerification'; }
        if (option('invitation_codes_for_union_enabled', false)) { $enabled_features[] = 'invitationCodesForUnion'; }
        if (option('union_enable_oauth2', false)) { $enabled_features[] = 'unionOAuth2'; }
        return json([
            'yggdrasilApiVersion' => plugin('yggdrasil-api')->version,
            'serverListVersion' => option('union_server_list_version'),
            'privateKeyVersion' => option('union_private_key_version'),
            'enabledFeatures' => $enabled_features
        ])->header('Access-Control-Allow-Origin', '*');
    }
  
  	public function getSecurityLevel() {
        $response = Http::timeout(5.0)->post(option('union_api_root').'/code', ['token' => option('union_member_key')]);
      	if ($response->ok()) {
          	$code = $response->json("code");
          	if ($code != null) {
              	$response = Http::timeout(5.0)->get(option('union_api_root').'/backend/'.$code."/security/level");
              	if ($response->ok()) {
                  	return $response->json();
                }
            }
        }
      	abort(500);
    }
    
    public function serverUpdatesBackendKey(Request $request) {
        option(['union_member_key' => $request->input('key')]);
        
    }

    public function updateList(Request $request) {
        $response = Http::timeout(5.0)->withHeaders([ 'X-Union-Member-Key' => option('union_member_key')])
            ->get(option('union_api_root').'/serverlist');
        if ($response->failed()) {
            return $response;
        }
        option(['union_server_list' => json_encode($response['servers'])]);
        option(['union_server_list_version' => $response['version']]);
        Log::channel('ygg')->info("Updated server list. Response: ".json_encode($response['servers']));
        //return redirect()->back();
        //return option('union_server_list');
    }

    public function updatePrivateKey(Request $request) {
        $response = Http::timeout(5.0)->withHeaders([ 'X-Union-Member-Key' => option('union_member_key')])
            ->get(option('union_api_root').'/privatekey');
        if ($response->failed()) {
            abort($response->status(), trans($response->status()));
        }
        option(['ygg_private_key' => $response['privateKey']]);
        option(['union_private_key_version' => $response['privateKeyVersion']]);
        Log::channel('ygg')->info("Updated private key.");
        //return redirect()->back();
    }

    public function triggerSync(Request $request) {
        $names = Player::all()->pluck('pid', 'name');
        $uuids = DB::table('uuid')->pluck('uuid', 'name');

        $profiles = $uuids->intersectByKeys($names)->flip();

        $response = Http::timeout(5.0)->withHeaders([ 'X-Union-Member-Key' => option('union_member_key')])
            ->post(option('union_api_root').'/sync', [ 'profileList' => $profiles ]);
        
        if ($response->failed()) {
            //abort($response->status(), trans($response->reason()));
            return $response;
        }
        Log::channel('ygg')->info("Triggered sync.");
    }
    
    public function remapUUID(Request $request) {
        $remapped = $request->input('remapped_uuid');
        foreach ($remapped as $uuid => $mapped_uuid) {
            DB::table('uuid')->where('uuid', $uuid)->update(['uuid' => $mapped_uuid]);
        }
        return;
    }

}