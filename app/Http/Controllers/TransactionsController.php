<?php

namespace App\Http\Controllers;

use App\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\Process\Process;
use App\Http\Requests\TransactionsRequest;
use Symfony\Component\Process\Exception\ProcessFailedException;

class TransactionsController extends Controller
{
    /**
     * Show user's transactions.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $documentIds = $endpoints = false;
        $transactions = Transaction::where('from_party', session('abn'))->orWhere('to_party', session('abn'))->orderby('id', 'desc')->paginate(25);
        return view('transactions.index', compact('transactions', 'endpoints', 'documentIds', 'request'));
    }

    /**
     * Render filters view
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function filters(Request $request)
    {
        $apiRequest = new \ApiRequest();
        $documentIds = $endpoints = false;
        if ($request->get('receiver_abn') && !$request->get('document_id')) {
            $documentIds = $apiRequest->getDocumentIds($request->get('receiver_abn'));
            session()->put('documentIds', $documentIds);
            if(count($documentIds) === 1){
                $request->merge( [ 'document_id' => array_first($documentIds) ] );
            }
        }
        if ($request->get('receiver_abn') && $request->get('document_id')) {
            $endpoints = $apiRequest->getEndpoints($request->get('receiver_abn'), $request->get('document_id'));
            $documentIds = session('documentIds');
            session()->put('endpoints', $documentIds);
            if(count($endpoints) === 1){
                $request->merge( [ 'endpoint' => array_first($endpoints) ] );
            }
        }
        $data = [
            'endpoints' => $endpoints,
            'documentIds' => $documentIds,
            'request' => $request
        ];
        return response()->json(['html' => view('transactions.create')->with($data)->render()]);
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

        runConsoleCommand('gpg2 --batch -q --passphrase "" --quick-gen-key ' . session('user_urn'));
        runConsoleCommand('gpg2 --batch -q --passphrase "" --quick-gen-key ' . 'urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::' . $request->get('receiver_abn'));

        $transaction = Auth::user()->transactions()->create([
            'from_party' => session('abn'),
            'to_party' => $request->get('receiver_abn'),
        ]);

        //save json to file
        file_put_contents(resource_path('data/documents/' . $transaction->id . '_initial_message.json'), $request->get('document'));

        // gpg2 --fingerprint urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::123123123
        runConsoleCommand('gpg2 --armor --export urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::' . session('abn') . ' > ' . resource_path('data/keys/public_' . session('abn') . '.key'));

        runConsoleCommand('gpg2 --fingerprint urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::' . session('abn') . ' > ' . resource_path('data/keys/' . session('abn') . '_fingerprint.key'));

        $fingerprint = str_replace(' ', '', explode(PHP_EOL, explode('Key fingerprint = ', file_get_contents(resource_path('data/keys/' . session('abn') . '_fingerprint.key')))[1])[0]);
        $apiRequest = new \ApiRequest();
        $token = $apiRequest->getNewTokenForCustomer(Auth::user()->customer_id);
        $apiRequest->sendSenderPublicKey(session('abn'), $fingerprint, $token['id_token']);

        $receiverPublicKey = (new \ApiRequest())->getReceiverPublicKey($request->get('receiver_abn'), $token['id_token'])['pubKey'];

        file_put_contents(resource_path() . '/data/keys/receiver_' . $request->get('receiver_abn') . '.key', $receiverPublicKey);


        runConsoleCommand('gpg2 --local-user "urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::' . session('abn') . '" \
                        --output "' . resource_path('data/keys/' . $transaction->id . '_signed_file.json') . '" \
                        --clearsign "' . resource_path('data/documents/' . $transaction->id . '_initial_message.json') . '"'
        );

        runConsoleCommand('gpg2 --verify ' . resource_path('data/keys/' . $transaction->id . '_signed_file.json'));

        runConsoleCommand('openssl dgst -sha256 -out "' . resource_path('data/keys/' . $transaction->id . '_signed_file.hash') . '" "' . resource_path('data/keys/' . $transaction->id . '_signed_file.json') . '"');

        runConsoleCommand('gpg2 --armour --output "' . resource_path('data/keys/' . $transaction->id . '_cyphertext_signed.gpg') . '" --encrypt \
          --recipient "urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::' . $request->get('receiver_abn') . '" ' .
            resource_path('data/keys/' . $transaction->id . '_signed_file.json'));

        $hash = trim(explode(' ', file_get_contents(resource_path('data/keys/' . $transaction->id . '_signed_file.hash')))[1]);
        $message = [
            'cyphertext' => file_get_contents(resource_path('data/keys/' . $transaction->id . '_cyphertext_signed.gpg')),
            'hash' => $hash,
            'reference' => "",
            'sender' => "urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::" . session('abn')
        ];

        file_put_contents(resource_path('data/keys/' . $transaction->id . '_message.json'), json_encode($message, JSON_PRETTY_PRINT));

        runConsoleCommand('gpg2 --local-user "urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::' . session('abn') . '" --output ' . resource_path('data/keys/' . $transaction->id . '_message.json.sig') . ' --detach-sign ' . resource_path('data/keys/' . $transaction->id . '_message.json'));

        $apiResponse = $apiRequest->sendMessage($request->get('endpoint'),
            resource_path('data/keys/' . $transaction->id . '_message.json'),
            resource_path('data/keys/' . $transaction->id . '_message.json.sig')
        );

        $transaction->message_hash = $hash;
        $transaction->message_id = $apiResponse['data']['id'];
        $transaction->message_type = $apiResponse['data']['type'];
        $transaction->encripted_payload = $transaction->id . '_cyphertext_signed.gpg';
        $transaction->decripted_payload = $transaction->id . '_signed_file.json';

        $transaction->validation_status = $apiResponse['data']['attributes']['status'];
        $transaction->save();

        session()->forget('documentIds');
        session()->forget('endpoints');
        session()->flash('status', 'Message has been sent!');

        return response()->json(['status' => 'success']);
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
}
