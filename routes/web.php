<?php

Auth::routes();

Route::get('/', 'HomeController@index');

Route::post('/update-templates', 'UpdateTemplatesController@updateInvoiceSamples');

Route::group(['middleware' => ['auth']], function () {
    Route::get('/transactions', 'TransactionsController@index');
    Route::get('/transactions/filters', 'TransactionsController@filters');
    Route::post('/transactions', 'TransactionsController@create');
    Route::get('/download/{filename}', 'TransactionsController@download');
});

Route::get('/social/redirect/{provider}',   ['as' => 'social.redirect',   'uses' => 'Auth\SocialController@getSocialRedirect']);
Route::get('/oauth_login', 'Auth\SocialController@getSocialHandle');
