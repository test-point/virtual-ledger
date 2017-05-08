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
        if ($messageData['data']['attributes']['status'] != 'processing') {
            $transaction->validation_status = $messageData['data']['attributes']['status'];
            $transaction->validation_message = @$messageData['data']['attributes']['error'];
            $transaction->save();
        }
    }
})->describe('Update processing transactions');

\Illuminate\Support\Facades\Artisan::command('load-messages', function () {
    $apiRequest = new \ApiRequest();
    $users = \App\User::all();
    foreach ($users as $user) {
        $gwToken = $apiRequest->getNewTokenForCustomer($user->customer_id, config('constants.tap_gw_id'));
        $tapGw = new TapGw($gwToken['id_token']);

        $messages = $tapGw->getMessages();

        if (!empty($messages['data'])) {
            foreach ($messages['data'] as $message) {
                $attributes = $message['attributes'];
                $transaction = \App\Transaction::where('message_id', $message['id'])->first();
                $transactionData = [
                    'to_party' => $user->name,
                    'notarized_message' => $attributes['doc_id'],
                    'message_type' => $message['type'],
                    'validation_status' => $attributes['status'],
                ];
                if ($transaction) {
                    if(!file_exists(resource_path('data/keys/' . $transaction->id . '_message.json'))){
                        $messageBody = $tapGw->getMessageBody($message['id']);
                        file_put_contents(resource_path('data/keys/' . $transaction->id . '_message.json'), json_encode($messageBody, JSON_PRETTY_PRINT));
                    }
                    $transaction->update($transactionData);
                } else {
                    $transactionData['created_at'] = \Carbon\Carbon::parse($attributes['sent_at'])->toDateTimeString();
                    $transactionData['updated_at'] = \Carbon\Carbon::parse($attributes['sent_at'])->toDateTimeString();
                    $messageBody = $tapGw->getMessageBody($message['id']);

                    file_put_contents(resource_path('data/keys/' . $transaction->id . '_message.json'), json_encode($messageBody, JSON_PRETTY_PRINT));

                    $transactionData['message_hash'] = $messageBody['hash'];
                    $transactionData['message_id'] = $message['id'];
                    $transactionData['from_party'] = str_replace('urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::', '', $messageBody['sender']);
                    $transaction = \App\Transaction::create($transactionData);
                    file_put_contents(resource_path('data/keys/' . $transaction->id . '_cyphertext_signed.gpg'), @$messageBody['cyphertext']);

                    $transaction->encripted_payload = $transaction->id . '_cyphertext_signed.gpg';
                    $transaction->save();
                }
            }
        }
    }

})->describe('Load transactions');
