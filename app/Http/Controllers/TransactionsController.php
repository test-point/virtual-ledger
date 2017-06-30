<?php

namespace App\Http\Controllers;

use App\MessageTemplate;
use App\Transaction;
use Validator;
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
        $transactions = Transaction::where('from_party', session('abn'))->orWhere(['to_party' => session('abn'), 'validation_status' => 'sent'])->orderby('id', 'desc')->paginate(25);
        return view('transactions.index', compact('transactions', 'endpoints', 'documentIds', 'request'));
    }

    /**
     * Render filters view
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function filters(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'receiver_abn' => 'required|abn'
        ]);
        if($validator->fails()){
            return response()->json($validator->errors(), 422);
        }
        $apiRequest = new \ApiRequest();
        $documentIds = $endpoints = $abnNotConfigured = false;

        if ($request->get('receiver_abn') && !$request->get('document_id')) {
            $documentIds = $apiRequest->getDocumentIds($request->get('receiver_abn'));
            if(!count($documentIds)) {
                $abnNotConfigured = true;
            }
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
            'request' => $request,
            'templates' => MessageTemplate::all(),
            'abnNotConfigured' => $abnNotConfigured
        ];
        return response()->json(['html' => view('transactions.create')->with($data)->render()]);
    }

    /**
     * Generate message content for a selected template
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTemplate(Request $request)
    {
        $templateContent = '';
        $templateId = $request->get('template_id');
        if ($templateId) {
            $templateContent = replaceABNData(json_decode(MessageTemplate::find($templateId)->content, true), $request->get('receiver_abn'), Auth::user()->name);
            $templateContent = json_encode($templateContent, JSON_PRETTY_PRINT);
        }
        return response()->json(['html' => $templateContent]);
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
        $apiRequest = new \ApiRequest();
        $token = $apiRequest->getNewTokenForCustomer(Auth::user()->customer_id);
        $receiverPublicKey = (new \ApiRequest())->getReceiverPublicKey($request->get('receiver_abn'), $token['id_token'])['pubKey'];

        if(!$receiverPublicKey) {
            return response()->json([['This receiver ABN doesn\'t have any active public key in DCP']], 422);
        }

        /**
         * Generate keys for current user
         */

        if($request->get('document')){
            $message = $request->get('document');
        } else {
            $message = file_get_contents($request->file('template_file'));
            $validator = Validator::make(['template_file' => $message], [
                'template_file' => 'json'
            ]);
            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }
        }

        $senderAbn = session('abn');
        $receiverAbn = $request->get('receiver_abn');

        $transaction = Transaction::create([
            'from_party' => $senderAbn,
            'to_party' => $receiverAbn,
        ]);

        //save json to file
        file_put_contents(resource_path('data/keys/' . $transaction->id . '_initial_message.json'), $message);

        file_put_contents(resource_path('data/keys/receiver_' . $receiverAbn . '.key'), $receiverPublicKey);

        //import receiver public key
        $gnupg = gnupg_init();
        $info = gnupg_import($gnupg, file_get_contents(resource_path('data/keys/receiver_' . $receiverAbn . '.key')));
        $receiverFilgerprint = $info['fingerprint'];

        $gnupg = gnupg_init();
        $info = gnupg_import($gnupg, file_get_contents(resource_path('data/keys/public_' . $senderAbn . '.key')));
        $senderFilgerprint = $info['fingerprint'];


        runConsoleCommand('gpg2 --local-user "'.$senderFilgerprint.'" \
                        --output "' . resource_path('data/keys/' . $transaction->id . '_signed_file.json') . '" \
                        --clearsign "' . resource_path('data/keys/' . $transaction->id . '_initial_message.json') . '"'
        );

        runConsoleCommand('gpg2 --verify ' . resource_path('data/keys/' . $transaction->id . '_signed_file.json'));

        runConsoleCommand('openssl dgst -sha256 -out "' . resource_path('data/keys/' . $transaction->id . '_signed_file.hash') . '" \
        "' . resource_path('data/keys/' . $transaction->id . '_signed_file.json') . '"');

        runConsoleCommand('gpg2 --trust-model always --armour --output "' . resource_path('data/keys/' . $transaction->id . '_cyphertext_signed.gpg') . '" --encrypt \
          --recipient "'.$receiverFilgerprint.'" ' . resource_path('data/keys/' . $transaction->id . '_signed_file.json'));

        $hash = trim(explode(' ', file_get_contents(resource_path('data/keys/' . $transaction->id . '_signed_file.hash')))[1]);
        $message = [
            'cyphertext' => file_get_contents(resource_path('data/keys/' . $transaction->id . '_cyphertext_signed.gpg')),
            'hash' => $hash,
            'reference' => "",
            'sender' => "urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::" . $senderAbn
        ];

        file_put_contents(resource_path('data/keys/' . $transaction->id . '_message.json'), json_encode($message, JSON_PRETTY_PRINT));

        runConsoleCommand('gpg2 --local-user "'.$senderFilgerprint.'" \
        --output ' . resource_path('data/keys/' . $transaction->id . '_message.json.sig') . ' \
        --detach-sign ' . resource_path('data/keys/' . $transaction->id . '_message.json'));

        $apiResponse = $apiRequest->sendMessage($request->get('endpoint'),
            resource_path('data/keys/' . $transaction->id . '_message.json'),
            resource_path('data/keys/' . $transaction->id . '_message.json.sig')
        );

        $transaction->message_hash = $hash;
        $transaction->message_id = $apiResponse['data']['id'];
        $transaction->message_type = $apiResponse['data']['type'];
        $transaction->encripted_payload = $transaction->id . '_cyphertext_signed.gpg';
        $transaction->decripted_payload = $transaction->id . '_initial_message.json';

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
