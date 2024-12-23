<?php

namespace Yggdrasil\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;

use Yggdrasil\Models\Profile;

class UnionProfileController extends Controller {
    public function render(Request $request) {
        $players = auth()->user()->players;
        $promisesDup = [];
        $promisesSelf = [];
        $client = new Client(['timeout' => 5.0, 'headers' => [ 'X-Union-Member-Key' => option('union_member_key')]]);
        foreach ($players as $player) {
            $profile = Profile::createFromPlayer($player);
            $promisesDup[] = $client->requestAsync('GET', option('union_api_root').'/profile/unmapped/byname/'.urlencode($profile->name));
            $promisesSelf[] = $client->requestAsync('GET', option('union_api_root').'/profile/detail/'.$profile->uuid);
        }
        $responsesDup = Promise\unwrap($promisesDup);
        $responsesSelf = Promise\unwrap($promisesSelf);
        $profiles = [];
        foreach ($responsesDup as $key => $response) {
            $profiles[] = ['dup_name' => json_decode($response->getBody(), true)];
        }
        
        foreach ($responsesSelf as $key => $response) {
            $profiles[$key]['self'] = json_decode($response->getBody(), true);
            $profiles[$key]['dup_name'] = collect($profiles[$key]['dup_name'])->keyBy('internal_id')->except($profiles[$key]['self']['internal_id'])->diffKeys(collect($profiles[$key]['self']['bind'])->keyBy('internal_id'));
        }
        
        return view('Yggdrasil::union', [ 'profiles' => $profiles, 'servers' => json_decode(option('union_server_list')), 'union_api_root' => option('union_api_root') ]);
    }

    public function bind(Request $request) {
        $response = Http::timeout(5.0)->withHeaders([ 'X-Union-Member-Key' => option('union_member_key')])
            ->post(option('union_api_root').'/profile/bind', ["uuid" => $request->input('uuid')]);
        if ($response->ok()) {
            return [ 'token' => $response->json()['token'] ];
        }
        return response($response->body(), $response->status());
    }

    public function unbind(Request $request) {
        $response = Http::timeout(5.0)->withHeaders([ 'X-Union-Member-Key' => option('union_member_key')])
            ->post(option('union_api_root').'/profile/unbind', ["uuid" => $request->input('uuid')]);
        if ($response->ok()) {
            return;
        }
        return response($response->body(), $response->status());
    }

    public function bindTo(Request $request) {
        $response = Http::timeout(5.0)->withHeaders([ 'X-Union-Member-Key' => option('union_member_key')])
            ->post(option('union_api_root').'/profile/bindto', ["uuid" => $request->input('uuid'), "token" => $request->input('token')]);
        if ($response->ok()) {
            return;
        }
        return response($response->body(), $response->status());
    }
    
    public function requestRemapUUID(Request $request) {
        $response = Http::timeout(5.0)->withHeaders([ 'X-Union-Member-Key' => option('union_member_key')])
            ->post(option('union_api_root').'/profile/remapuuid', ["me" => $request->input('me'), "target" => $request->input('target')]);
        if ($response->ok()) {
            return;
        }
        return response($response->body(), $response->status());
    }
}