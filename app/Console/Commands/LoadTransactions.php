<?php

namespace App\Console\Commands;

use App\Transaction;
use Illuminate\Console\Command;

class LoadTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transactions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update processing transactions';

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
        $transactions = Transaction::whereIn('validation_status', ['processing', 'in_transit', 'transit'])->get();
        foreach ($transactions as $transaction) {
            $messageData = $apiRequest->getMessage($transaction->message_id);
            if ($messageData['data']['attributes']['status'] != 'processing') {
                $transaction->validation_status = $messageData['data']['attributes']['status'];
                $transaction->validation_message = @$messageData['data']['attributes']['error'];
                $transaction->save();
            }
        }
    }
}
