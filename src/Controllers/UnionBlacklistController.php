<?php
namespace Yggdrasil\Controllers;

use App\Models\Player;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class UnionBlacklistController extends Controller
{ 
    public function viewBlacklist(Request $request)
    {
        $response = Http::withHeaders(['X-Union-Member-Key' => option('union_member_key')])->get(option('union_api_root').'/blacklist/query', $request->input());
      	return response($response->body(), $response->status())->withHeaders($response->headers());
    }
  
    public function create(Request $request)
    {
        $response = Http::withHeaders(['X-Union-Member-Key' => option('union_member_key')])->post(option('union_api_root').'/blacklist/restful', $request->input());
        if ($response->ok()) {
        	return back();
        }
      	return response($response->body(), $response->status())->withHeaders($response->headers());
    }
  
  	public function invalidate($id)
    {
      	$response = Http::withHeaders(['X-Union-Member-Key' => option('union_member_key')])->put(option('union_api_root').'/blacklist/invalidate/'.$id);
      	return response($response->body(), $response->status())->withHeaders($response->headers());
    }
  
    public function delete($id)
    {
      	$response = Http::withHeaders(['X-Union-Member-Key' => option('union_member_key')])->delete(option('union_api_root').'/blacklist/restful/'.$id);
      	return response($response->body(), $response->status())->withHeaders($response->headers());
    }
  
  	/**
     * 204: No such username
     * 200: { "email" : email }
     */
    public function queryEmail(Request $request)
    {
        $name = $request->input('username');
      
      	$player = Player::where('name', $name)->first();
        if (empty($player)) {
            return response()->noContent();
        }
      	
      	return response()->json(["email" => $player->user->email]);
    }
}