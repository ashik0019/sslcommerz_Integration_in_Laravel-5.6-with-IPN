<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

Route::get('/home', 'HomeController@index')->name('home');
// SSLCOMMERZ Start
Route::get('/pay', 'PublicSslCommerzPaymentController@index');
Route::POST('/success', 'PublicSslCommerzPaymentController@success');
Route::POST('/fail', 'PublicSslCommerzPaymentController@fail');
Route::POST('/cancel', 'PublicSslCommerzPaymentController@cancel');
Route::POST('/ipn', 'PublicSslCommerzPaymentController@ipn');
//SSLCOMMERZ END
