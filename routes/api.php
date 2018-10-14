<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
//
//Route::get('/user', function (Request $request) {
//    return $request->user();
//})->middleware('auth:api');


$api = app('Dingo\Api\Routing\Router');


$api->version('v1', ['namespace' => 'Wormhole\Http\Controllers\Api\V1'], function ($api) {
    $api->post('send_cmd/{hash}', [
        'as'   => 'api.gtw.sendCmd',
        'uses' => 'CommonController@sendCmd'
    ]);

    $api->any('test/{hash}', [
        'as'   => 'api.test',
        'uses' => 'TestController@test'
    ]);
});

//QuChong
$api->version('QianNiu', ['namespace' => 'Wormhole\Protocols\LaiChongV2\TenRoad\Controllers\Api',

], function ($api) {
    $api->any('test/{hash}', [
        'as'   => 'api.test',
        'uses' => 'EvseController@test'
    ]);

    $api->post('start_charge/{hash}', [
        'as'   => 'api.startCharge',
        'uses' => 'EvseController@startCharge'
    ]);

    $api->post('continue_charge/{hash}', [
        'as'   => 'api.renew',
        'uses' => 'EvseController@renew'
    ]);

    $api->post('stop_charge/{hash}', [
        'as'   => 'api.stopCharge',
        'uses' => 'EvseController@stopCharge'
    ]);

    $api->post('charge_realtime/{hash}', [
        'as'   => 'api.chargeRealtime',
        'uses' => 'EvseController@chargeRealtime'
    ]);


    $api->post('set_hearbeat/{hash}', [
        'as'   => 'api.setHearbeat',
        'uses' => 'EvseController@setHearbeat'
    ]);

    $api->post('set_server_info/{hash}', [
        'as'   => 'api.setServerInfo',
        'uses' => 'EvseController@setServerInfo'
    ]);

    $api->post('empty_turnover/{hash}', [
        'as'   => 'api.emptyTurnover',
        'uses' => 'EvseController@emptyTurnover'
    ]);

    $api->post('set_parameter/{hash}', [
        'as'   => 'api.setParameter',
        'uses' => 'EvseController@setParameter'
    ]);

    $api->post('set_dateTime/{hash}', [
        'as'   => 'api.setDateTime',
        'uses' => 'EvseController@setDateTime'
    ]);




    $api->post('get_hearbeat/{hash}', [
        'as'   => 'api.getHearbeat',
        'uses' => 'EvseController@getHearbeat'
    ]);

    $api->post('get_meter/{hash}', [
        'as'   => 'api.getMeter',
        'uses' => 'EvseController@getMeter'
    ]);

    $api->post('get_turnover/{hash}', [
        'as'   => 'api.getTurnover',
        'uses' => 'EvseController@getTurnover'
    ]);

    $api->post('get_channel/{hash}', [
        'as'   => 'api.getChannel',
        'uses' => 'EvseController@getChannel'
    ]);

    $api->post('get_parameter/{hash}', [
        'as'   => 'api.getParameter',
        'uses' => 'EvseController@getParameter'
    ]);


    $api->post('get_date_time/{hash}', [
        'as'   => 'api.getDateTime',
        'uses' => 'EvseController@getDateTime'
    ]);

    $api->post('set_id/{hash}', [
        'as'   => 'api.setId',
        'uses' => 'EvseController@setId'
    ]);

    $api->post('get_id/{hash}', [
        'as'   => 'api.getId',
        'uses' => 'EvseController@getId'
    ]);

    $api->post('device_identification/{hash}', [
        'as'   => 'api.deviceIdentification',
        'uses' => 'EvseController@deviceIdentification'
    ]);


});
