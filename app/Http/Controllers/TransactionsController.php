<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionsRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionsController extends Controller
{
     /**
     * Show user's transactions.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $transactions = Auth::user()->transactions()->paginate(5);
        return view('transactions.index', compact('transactions'));
    }

    public function create(TransactionsRequest $request)
    {
        /**
         * Generate keys for current user
         */
//        dump('echo '.session('abn').' | gpg2 --batch -q --passphrase-fd 0 --quick-gen-key ' . session('user_urn'));
//        exec('echo '.session('abn').' | gpg2 --batch -q --passphrase-fd 0 --quick-gen-key ' . session('user_urn'), $output);
//        dump($output);
//
//        dump('echo '.session('abn').' | gpg2 --armor --export  --batch -q --passphrase-fd 0 -a "'.session('user_urn').'" > '.resource_path() . '/data/keys/public_'.Auth::user()->id.'.key');
//        exec('echo '.session('abn').' | gpg2 --armor --export  --batch -q --passphrase-fd 0 -a "'.session('user_urn').'" > '.resource_path() . '/data/keys/public_'.Auth::user()->id.'.key', $output);
//        dump($output);
//
//        dump('echo '.session('abn').' | gpg2 --armor --export-secret-key  --batch -q --passphrase-fd 0 -a "'.session('user_urn').'" > ' . resource_path() . '/data/keys/private_'.Auth::user()->id.'.key');
//        exec('echo '.session('abn').' | gpg2 --armor --export-secret-key  --batch -q --passphrase-fd 0 -a "'.session('user_urn').'" > ' . resource_path() . '/data/keys/private_'.Auth::user()->id.'.key', $output);
//
//        dump($output);

        $receiverPublicKey = (new \ApiRequest())->getReceiverPublicKey($request->get('receiver_abn'))['pubKey'];

        file_put_contents(resource_path() . '/data/keys/receiver_' . $request->get('receiver_abn') . '.key', $receiverPublicKey);


        /**
         * Set message data
         */
        $message = [
            'cyphertext' => 'hello',
            'hash' => bcrypt(session('user_urn')),
            'sender' => session('user_urn'),
            'reference' => '123',
        ];
        file_put_contents(resource_path() . '/data/messages/' . Auth::user()->id . '.json', json_encode($message, JSON_PRETTY_PRINT));

        /**
         * Send message
         */


        echo 'cd ' . resource_path() . '/data/bash/tap-message-composer && ./make_message_from_invoice.py --document="'.resource_path() . '/data/documents/invoice.json" 
            --sender_private_key="'.resource_path() . '/data/keys/private_'.Auth::user()->id.'.key" 
            --receiver_public_key="'.resource_path() . '/data/keys/receiver_' . $request->get('receiver_abn') . '.key'.'" 
            --message_filename="'.resource_path() . '/data/messages/' . Auth::user()->id . '.json" 
            --sender="'.session('user_urn').'"';
        exec('cd ' . resource_path() . '/data/bash/tap-message-composer && ./make_message_from_invoice.py --document="'.resource_path() . '/data/documents/invoice.json" 
            --sender_private_key="'.resource_path() . '/data/keys/private_'.Auth::user()->id.'.key" 
            --receiver_public_key="'.resource_path() . '/data/keys/receiver_' . $request->get('receiver_abn') . '.key'.'" 
            --message_filename="'.resource_path() . '/data/messages/' . Auth::user()->id . '.json" 
            --sender="'.session('user_urn').'"', $output);
        dd($output);

        dd($receiverPublicKey);

        dd(session('user_urn'));
    }
}
