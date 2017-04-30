<?php

Auth::routes();

Route::get('/', 'HomeController@index');

Route::get('/update-templates', 'UpdateTemplatesController@updateInvoiceSamples');

Route::group(['middleware' => ['auth']], function () {
    Route::get('/transactions', 'TransactionsController@index');
    Route::get('/transactions/filters', 'TransactionsController@filters');
    Route::post('/transactions', 'TransactionsController@create');
    Route::get('/download/{filename}', 'TransactionsController@download');
});
