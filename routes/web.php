<?php

Auth::routes();

Route::get('/', 'HomeController@index');
Route::get('/home', 'HomeController@index');

Route::post('/update-templates', 'UpdateTemplatesController@updateInvoiceSamples');

Route::group(['middleware' => ['auth']], function () {
    Route::get('/transactions', 'TransactionsController@index');
    Route::get('/transactions/filters', 'TransactionsController@filters');
    Route::get('/transactions/get-template', 'TransactionsController@getTemplate');
    Route::post('/transactions', 'TransactionsController@create');
    Route::get('/download/{transactionId}/{filename}', 'TransactionsController@download');
});

Route::get('/social/redirect/{provider}',   ['as' => 'social.redirect',   'uses' => 'Auth\SocialController@getSocialRedirect']);
Route::get('/oauth_login', 'Auth\SocialController@getSocialHandle');

Route::get('/error-log', function(){
    if(file_exists(storage_path('logs/laravel.log'))) {
        $handle = fopen(storage_path('logs/laravel.log'), "r");
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                echo $line . '<br>';
            }

            fclose($handle);
        }
    }
});

Route::get('/error-log-clear', function(){
    return file_put_contents(storage_path('logs/laravel.log'), '');
});
