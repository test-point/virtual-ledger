<?php

use Illuminate\Foundation\Inspiring;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

\Illuminate\Support\Facades\Artisan::command('transactions', function () {
    $apiRequest = new \ApiRequest();
    $transactions = \App\Transaction::whereIn('validation_status', ['processing', 'in_transit'])->get();
    foreach ($transactions as $transaction) {
        $messageData = $apiRequest->getMessage($transaction->message_id);
        if($messageData['data']['attributes']['status'] != 'processing') {
            $transaction->validation_status = $messageData['data']['attributes']['status'];
            $transaction->validation_message = @$messageData['data']['attributes']['error'];
            $transaction->save();
        }
    }
})->describe('Update processing transactions');
