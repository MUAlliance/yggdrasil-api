<?php

Route::any('', 'ConfigController@hello');

Route::prefix('authserver')
    ->middleware(['Yggdrasil\Middleware\CheckContentType'])
    ->group(function () {
        // 防止暴力破解密码
        Route::middleware(['Yggdrasil\Middleware\Throttle'])
            ->group(function () {
                Route::post('authenticate', 'AuthController@authenticate');
                Route::post('signout', 'AuthController@signout');
            });

        Route::post('refresh', 'AuthController@refresh');

        Route::post('validate', 'AuthController@validate');
        Route::post('invalidate', 'AuthController@invalidate');
    });

Route::prefix('sessionserver/session/minecraft')->group(function () {
    Route::post('join', 'SessionController@joinServer');
    Route::get('hasJoined', 'SessionController@hasJoinedServer');

    Route::get('profile/{uuid}', 'ProfileController@getProfileFromUuid');
});

Route::post('api/profiles/minecraft', 'ProfileController@searchMultipleProfiles');
Route::get('api/users/profiles/minecraft/{username}', 'ProfileController@searchSingleProfile');

Route::prefix('api/user/profile')
    ->middleware(['Yggdrasil\Middleware\CheckBearerToken'])
    ->group(function () {
        Route::put('{uuid}/{type}', 'ProfileController@uploadTexture');
        Route::delete('{uuid}/{type}', 'ProfileController@resetTexture');
    });

Route::get('restore', 'MultiBackendController@hello');
Route::post('restore', 'MultiBackendController@restore');