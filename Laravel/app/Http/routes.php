<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/
use Illuminate\Http\Response;
Route::get('/', function () {
	return view('welcome', ['title' => 'Fae API v1']);
});

$api = app('Dingo\Api\Routing\Router');

$api->version('v1', function ($api) {
    $api->post('users', 'App\Api\v1\Controllers\UserController@signUp');
    $api->post('authentication', 'App\Api\v1\Controllers\AuthenticationController@login');
    $api->post('reset_login', 'App\Api\v1\Controllers\ResetLoginController@sendResetCode');
    $api->put('reset_login', 'App\Api\v1\Controllers\ResetLoginController@verifyResetCode');
});

$api->version('v1', ['middleware' => 'api.auth', 'providers' => ['fae']], function ($api) {
    $api->delete('authentication/{user_id}', 'App\Api\v1\Controllers\AuthenticationController@logout');

    // functions
    $api->get('users', 'App\Api\v1\Controllers\UserController@getProfile');
});