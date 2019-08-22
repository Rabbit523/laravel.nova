<?php
/*
|--------------------------------------------------------------------------
| Kickstart Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Route::domain('{account}.myapp.com')->group(function () {
//     Route::get('user/{id}', function ($account, $id) {
//         //
//     });
// });
Route::group(['namespace' => 'Kickstart'], function () {
    Route::get('/{project}/{preview?}', 'BeaconController@show')->name('kickstart');

    Route::group(['middleware' => 'auth:customers'], function () {
        Route::get('api/{project}/profile', 'ApiController@profile');
        Route::get('api/{project}/invoices', 'ApiController@invoices');
        Route::get('api/{project}/pass', 'ApiController@downloadIosPass');
        Route::get('api/{project}/invoices/{id}', 'ApiController@downloadInvoice')->name('kickstart_invoice');
        Route::post('api/{project}/subscription', 'ApiController@subscribe');
        Route::put('api/{project}/subscription', 'ApiController@changeSubscription');
        Route::patch('api/{project}/subscription', 'ApiController@resumeSubscription');
        Route::delete('api/{project}/subscription', 'ApiController@cancelSubscription');
        Route::put('api/{project}/card', 'ApiController@changeCard');
        Route::post('api/{project}/logout', 'ApiController@logout');
    });

    Route::post('api/{project}/register', 'ApiController@register');
    Route::post('api/{project}/login', 'ApiController@login');
});
