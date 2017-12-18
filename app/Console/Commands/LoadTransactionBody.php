<?php

namespace App\Console\Commands;

use App\PhpGnupgWrapper;
use App\Transaction;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class LoadTransactionBody extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'load-messages';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Load transactions';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $apiRequest = new \ApiRequest();
        $users = User::all();
        foreach ($users as $user) {
            $gwToken = $apiRequest->getNewTokenForCustomer($user->customer_id, config('constants.tap_gw_id'));
            $tapGw = new \TapGw($gwToken['id_token']);

            $messages = $tapGw->getMessages();

            if (!empty($messages['data'])) {
                foreach ($messages['data'] as $message) {
                    $attributes = $message['attributes'];
                    if (\Carbon\Carbon::parse($attributes['created_at'])->lt(\Carbon\Carbon::now()->subMinutes(10))) {
                        continue;
                    }
                    $messageBody = $tapGw->getMessageBody($message['id']);

                    $transaction = Transaction::where('message_hash', $messageBody['hash'])->first();
                    $transactionData = [
                        'to_party' => $user->name,
                        'notarized_message' => $attributes['doc_id'],
                        'message_type' => $message['type'],
                        'validation_status' => $attributes['status'],
                    ];
                    if ($transaction) {
                        if (!Storage::disk('s3')->exists(config('app.env') . '/' . $transaction->id . '/' . 'message.json')) {
                            $messageBody = $tapGw->getMessageBody($message['id']);
                            Storage::disk('s3')->put(config('app.env') . '/' . $transaction->id . '/message.json', json_encode($messageBody, JSON_PRETTY_PRINT));
                        }
                        $transaction->update($transactionData);
                    } else {
                        $transactionData['created_at'] = \Carbon\Carbon::parse($attributes['sent_at'])->toDateTimeString();
                        $transactionData['updated_at'] = \Carbon\Carbon::parse($attributes['sent_at'])->toDateTimeString();


                        if (!empty($messageBody['reference'])) {

                            $transactionData['message_hash'] = $messageBody['hash'];
                            $transactionData['conversation_id'] = $messageBody['reference'];
                            $transactionData['message_id'] = $message['id'];
                            $transactionData['from_party'] = str_replace('urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::', '', $messageBody['sender']);
                            $transaction = Transaction::create($transactionData);

                            $gpgWrapper = new PhpGnupgWrapper($user->abn);

                            $tapMessage = $gpgWrapper->decryptMessage($messageBody['cyphertext'], $user->fingerprint, $user->abn);

                            Storage::disk('s3')->put(config('app.env') . '/' . $transaction->id . '/message.json', json_encode($messageBody, JSON_PRETTY_PRINT));
                            Storage::disk('s3')->put(config('app.env') . '/' . $transaction->id . '/cyphertext_signed.gpg', $messageBody['cyphertext']);
                            Storage::disk('s3')->put(config('app.env') . '/' . $transaction->id . '/initial_message.json', $tapMessage);

                            $transaction->encripted_payload = 'cyphertext_signed.gpg';
                            $transaction->decripted_payload = 'initial_message.json';
                            $transaction->save();
                        }
                    }
                }
            }
        }
    }
}
