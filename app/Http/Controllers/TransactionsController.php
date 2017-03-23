<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionsRequest;
use App\Transaction;
use Collective\Remote\RemoteFacade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class TransactionsController extends Controller
{
     /**
     * Show user's transactions.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $transactions = Auth::user()->transactions()->orderby('id', 'desc')->paginate(5);
        return view('transactions.index', compact('transactions'));
    }

    /**
     * Create new message route
     *
     * @param TransactionsRequest $request
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function create(TransactionsRequest $request)
    {
        /**
         * Generate keys for current user
         */
//        $this->runConsoleCommand('sudo rngd -r /dev/urandom');

        $this->runConsoleCommand('gpg2 --batch -q --passphrase "" --quick-gen-key ' . session('user_urn'));
        $this->runConsoleCommand('gpg2 --batch -q --passphrase "" --quick-gen-key ' . $request->get('receiver_abn'));

        $transaction = Auth::user()->transactions()->create([
            'from_party' => session('abn'),
            'to_party' => $request->get('receiver_abn'),
        ]);

        //save json to file
        file_put_contents(resource_path('data/documents/'.$transaction->id.'_initial_message.json'), $request->get('document'));

        // gpg2 --fingerprint urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::123123123
        $this->runConsoleCommand('gpg2 --armor --export urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::' . session('abn') . ' > ' . resource_path('data/keys/public_'.session('abn').'.key'));

        $this->runConsoleCommand('gpg2 --fingerprint urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::' . session('abn') . ' > ' . resource_path('data/keys/'.session('abn').'_fingerprint.key'));

        $fingerprint = str_replace(' ', '', explode(PHP_EOL, explode('Key fingerprint = ', file_get_contents(resource_path('data/keys/'.session('abn').'_fingerprint.key')))[1])[0]);
        $apiRequest = new \ApiRequest();
        $token = $apiRequest->getNewTokenForCustomer(Auth::user()->customer_id);
        $apiRequest->sendSenderPublicKey(session('abn'), $fingerprint, $token['id_token']);

        $receiverPublicKey = (new \ApiRequest())->getReceiverPublicKey($request->get('receiver_abn'), $token['id_token'])['pubKey'];

        file_put_contents(resource_path() . '/data/keys/receiver_' . $request->get('receiver_abn') . '.key', $receiverPublicKey);


        $this->runConsoleCommand('gpg2 --local-user "urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::'.session('abn').'" \
                        --output "'.resource_path('data/keys/'.$transaction->id.'_signed_file.json').'" \
                        --clearsign "'.resource_path('data/documents/'.$transaction->id.'_initial_message.json') . '"'
        );

        $this->runConsoleCommand('gpg2 --verify '. resource_path('data/keys/'.$transaction->id.'_signed_file.json'));

        $this->runConsoleCommand('openssl dgst -sha256 -out "'.resource_path('data/keys/'.$transaction->id.'_signed_file.hash').'" "'.resource_path('data/keys/'.$transaction->id.'_signed_file.json').'"');

        $this->runConsoleCommand('gpg2 --armour --output "'.resource_path('data/keys/'.$transaction->id . '_cyphertext_signed.gpg').'" --encrypt \
          --recipient "urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::'.$request->get('receiver_abn').'" '.
          resource_path('data/keys/'.$transaction->id.'_signed_file.json'));

        $hash = trim(explode(' ', file_get_contents(resource_path('data/keys/'.$transaction->id.'_signed_file.hash')))[1]);
        $message = [
            'cyphertext'=> file_get_contents(resource_path('data/keys/'.$transaction->id . '_cyphertext_signed.gpg')),
            'hash'=> $hash,
            'reference' =>  "",
            'sender' => "urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::" . session('abn')
        ];

        file_put_contents(resource_path('data/keys/'.$transaction->id.'_message.json'), json_encode($message, JSON_PRETTY_PRINT));

        $this->runConsoleCommand('gpg2 --local-user "urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::'.session('abn').'" --output '.resource_path('data/keys/'.$transaction->id.'_message.json.sig').' --detach-sign '.resource_path('data/keys/'.$transaction->id.'_message.json'));

        $apiResponse = $apiRequest->sendMessage($request->get('endpoint'),
            resource_path('data/keys/'.$transaction->id.'_message.json'),
            resource_path('data/keys/'.$transaction->id.'_message.json.sig')
        );

        $transaction->message_hash = $hash;
        $transaction->message_id = $apiResponse['data']['id'];
        $transaction->message_type = $apiResponse['data']['type'];
        $transaction->encripted_payload = $transaction->id . '_cyphertext_signed.gpg';
        $transaction->decripted_payload = $transaction->id.'_signed_file.json';

        //todo make backgroud task to update messages with processing status
        sleep(5);
        $messageData = $apiRequest->getMessage($apiResponse['data']['id']);

        $transaction->validation_status = $messageData['data']['attributes']['status'];
        $transaction->validation_message = @$messageData['data']['attributes']['error'];
        $transaction->save();

        return redirect('transactions');
    }

    /**
     * Download message files route
     *
     * @param $filename
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function download($filename)
    {
        return response()->download(resource_path('data/keys/' . $filename), $filename);
    }

    private function runConsoleCommand($cmd)
    {
        $process = new Process($cmd, null, null, null, 3600);
        try {
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            echo '<pre>' . print_r($e->getMessage(), 1) . '</pre>';
        }
    }
}
