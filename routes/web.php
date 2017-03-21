<?php

Auth::routes();

Route::get('/', 'HomeController@index');

Route::group(['middleware' => ['auth']], function () {
    Route::get('/transactions', 'TransactionsController@index');
    Route::post('/transactions', 'TransactionsController@create');
});
