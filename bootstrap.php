<?php

use App\Models\User;
use App\Models\Player;
use App\Services\Hook;
use Blessing\Filter;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Yggdrasil\Models\Token;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;

use Yggdrasil\Models\Profile;
use Yggdrasil\Exceptions\ProfileUpdateException;

require __DIR__.'/src/Utils/helpers.php';

return function (Filter $filter, Dispatcher $events) {
    if (env('YGG_VERBOSE_LOG')) {
        config(['logging.channels.ygg' => [
            'driver' => 'single',
            'path' => storage_path('logs/yggdrasil.log'),
        ]]);
    } else {
        config(['logging.channels.ygg' => [
            'driver' => 'monolog',
            'handler' => Monolog\Handler\NullHandler::class,
        ]]);
    }

    // 记录访问详情
    if (request()->is('api/yggdrasil/*')) {
        ygg_log_http_request_and_response();
    }

    // 保证用户修改角色名后 UUID 一致
    $callback = function ($model) {
        $new = $model->getAttribute('name');
        $original = $model->getOriginal('name');

        if (!$original || $original === $new) {
            return;
        }

        // 要是能执行到这里就说明新的角色名已经没人在用了
        // 所以残留着的 UUID 映射删掉也没问题
        DB::table('uuid')->where('name', $new)->delete();
        DB::table('uuid')->where('name', $original)->update(['name' => $new]);
        
    };

    // 仅当 UUID 生成算法为「随机生成」时保证修改角色名后 UUID 一致
    // 因为另一种 UUID 生成算法要最大限度兼容盗版模式，所以不做修改
    //if (option('ygg_uuid_algorithm') == 'v4') {
    App\Models\Player::updating($callback);
    //}
    // 当启用Union时，即便使用了盗版UUID，仍然确保角色修改名称后UUID一致。

    // 向用户中心首页添加「快速配置启动器」板块
    if (option('ygg_show_config_section')) {
        $filter->add('grid:user.index', function ($grid) {
            $grid['widgets'][0][0][] = 'Yggdrasil::dnd';

            return $grid;
        });
        Hook::addScriptFileToPage(plugin('yggdrasil-api')->assets('dnd.js'), ['user']);
    }

    $events->listen('user.profile.updated', function (User $user, string $action) {
        if ($action !== 'password') {
            return;
        }

        $identification = $user->email;
        // 吊销所有令牌
        $tokens = Arr::wrap(Cache::get("yggdrasil-id-$identification"));
        array_walk($tokens, function (Token $token) {
            Cache::forget('yggdrasil-token-'.$token->accessToken);
        });
        Cache::forget("yggdrasil-id-$identification");
    });

    // MODIFICATION: UNION
    $events->listen(App\Events\PlayerWasAdded::class, function ($event) {
        $player = $event->player;
        $uuid = Profile::getUuidFromName($player->name);
        $response = Http::timeout(5.0)->withHeaders([ 'X-Union-Member-Key' => option('union_member_key')])
            ->post(option('union_api_root').'/profile',
                [ 'id' => $uuid, 'name' => $player->name ]
            );
        if (!$response->successful()) {
            Log::channel('ygg')->info("Player [$player->name] sync to union failed.");
        }
        Log::channel('ygg')->info("Player [$player->name] is added.");
    });
    
    $events->listen(App\Events\PlayerProfileUpdated::class, function ($event) {
        $player = $event->player;
        $uuid = Profile::getUuidFromName($player->name);
        $response = Http::timeout(5.0)->withHeaders([ 'X-Union-Member-Key' => option('union_member_key')])
            ->put(option('union_api_root').'/profile/'.$uuid,
                [ 'name' => $player->name ]
            );
        if (!$response->successful()) {
            Log::channel('ygg')->info("Player [$player->name] sync to union failed.");
        }
        Log::channel('ygg')->info("Player [$uuid]'s name is changed to [$player->name]. ");
    });

    $events->listen(App\Events\PlayerWillBeDeleted::class, function ($event) {
        $player = $event->player;
        $uuid = Profile::getUuidFromName($player->name);
        $response = Http::timeout(5.0)->withHeaders([ 'X-Union-Member-Key' => option('union_member_key')])
            ->delete(option('union_api_root').'/profile/'.$uuid);
        if (!$response->successful()) {
            Log::channel('ygg')->info("Player [$player->name] sync to union failed.");
        }
        Log::channel('ygg')->info("Player [$player->name] is deleted.");
    });

    if (env('YGG_VERBOSE_LOG')) {
        Hook::addMenuItem('admin', 4, [
            'title' => 'Yggdrasil::log.title',
            'link' => 'admin/yggdrasil-log',
            'icon' => 'fa-history',
        ]);
    }

    Hook::addRoute(function () {
        Route::namespace('Yggdrasil\Controllers')
            ->prefix('api/yggdrasil')
            ->group(__DIR__.'/routes.php');

        Route::middleware(['web', 'auth', 'role:admin'])
            ->namespace('Yggdrasil\Controllers')
            ->prefix('admin')
            ->group(function () {
                Route::get('yggdrasil-log', 'ConfigController@logPage');

                Route::post(
                    'plugins/config/yggdrasil-api/generate',
                    'ConfigController@generate'
                );
            });

        // MODIFICATION: UNION
        Route::middleware(['Yggdrasil\Middleware\UnionHostVerify'])
            ->namespace('Yggdrasil\Controllers')
            ->prefix('api/union/member')
            ->group(function () {
                Route::post('updatelist', 'UnionController@updateList');
                Route::post('updateprivatekey', 'UnionController@updatePrivateKey');
                Route::post('updatebackendkey', 'UnionController@serverUpdatesBackendKey');
                Route::post('sync', 'UnionController@triggerSync');
                Route::post('remapuuid', 'UnionController@remapUUID');
                Route::post('updateplugin', 'UpdateController@update');
              
              	Route::get('queryemail','UnionBlacklistController@queryEmail');
                Route::post('diagnose', 'UnionController@diagnose');
            });

        Route::namespace('Yggdrasil\Controllers')
            ->prefix('api/union/member')
            ->group(function () {
                Route::get('/', 'UnionController@hello');
            });

        Route::namespace('Yggdrasil\Controllers')
            ->prefix('api/union/member/oauth2')
            ->group(function () {
                Route::get('/', 'UnionOAuth2Controller@getSigPublicKey');
                Route::get('grant', 'UnionOAuth2Controller@grant');
            });
        
        Route::middleware(['web', 'auth'])
            ->namespace('Yggdrasil\Controllers')
            ->prefix('union')
            ->group(function () {
                Route::get('/', 'UnionProfileController@render');
                Route::post('bind', 'UnionProfileController@bind');
                Route::post('bindto', 'UnionProfileController@bindto');
                Route::post('unbind', 'UnionProfileController@unbind');
                Route::post('remapuuid', 'UnionProfileController@requestRemapUUID');
                Route::get('security/level', 'UnionController@getSecurityLevel');
            });

        Route::middleware(['web', 'auth', 'role:admin'])
            ->namespace('Yggdrasil\Controllers')
            ->prefix('admin/union')
            ->group(function () {
                Route::post('member/updatelist', 'UnionController@updateList');
                Route::post('member/updateprivatekey', 'UnionController@updatePrivateKey');
                Route::post('member/sync', 'UnionController@triggerSync');
                Route::post('member/diagnose', 'UnionController@triggerDiagnose');
              
                Route::view('view/blacklist', 'Yggdrasil::blacklist');
                Route::get('view/blacklist/list', 'UnionBlacklistController@viewBlacklist');
                Route::post('blacklist/create', 'UnionBlacklistController@create');
                Route::post('blacklist/invalidate/{id}', 'UnionBlacklistController@invalidate');
                Route::post('blacklist/delete/{id}', 'UnionBlacklistController@delete');
              
            });
    });

    Hook::addMenuItem('explore', 1, [
        'title' => 'Yggdrasil::union.title',
        'link' => 'union',
        'icon' => 'fa-paper-plane',
    ]);
  
  	Hook::addMenuItem('admin', 14, [
      'title' => 'Yggdrasil::union.blacklist.title',
      'link'  => '/admin/union/view/blacklist',
      'icon'  => 'fa-ban',
    ]);

    if (option('ygg_enable_ali')) {
        $kernel = app()->make(Illuminate\Contracts\Http\Kernel::class);
        $kernel->pushMiddleware(Yggdrasil\Middleware\AddApiIndicationHeader::class);
    }
};
