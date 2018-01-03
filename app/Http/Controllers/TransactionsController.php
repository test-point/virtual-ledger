<?php

namespace App\Http\Controllers;

use App\MessageTemplate;
use App\Transaction;
use Illuminate\Support\Facades\Storage;
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
        $abn = Auth::user()->abn;
        $documentIds = $endpoints = false;
        $conversations = Transaction::select('conversation_id')
            ->where(function ($query) use ($abn) {
                $query->where('from_party', $abn);
                $query->orWhere(function ($query1) use ($abn) {
                    $query1->where('to_party', $abn);
                    $query1->where('validation_status', 'sent');
                });
            })
            ->groupBy(['conversation_id'])->orderBy('updated_at', 'DESC')->paginate();
        return view('transactions.index', compact('conversations', 'endpoints', 'documentIds', 'request'));
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

        $user = Auth::user();

        $senderAbn = $user->abn;
        $receiverAbn = $request->get('receiver_abn');

        $messageArray = json_decode($message, true);

        $conversationId = $senderAbn.'/'.$messageArray['Invoice']['id'];

        $validator = Validator::make(['conversation_id' => $conversationId], [
            'conversation_id' => 'unique:transactions,conversation_id'
        ]);
        if ($validator->fails()) {
            return response()->json(['document' => ['You already have a conversation with this message id']], 422);
        }

        $validationResponse = \MessageValidator::validate($message);

        if(!empty($validationResponse['status']) && $validationResponse['status'] == 'error'){
            return response()->json(['document' => [json_encode([
                'message' => $validationResponse['errors'],
                'code' => $validationResponse['code'],
                'title' => $validationResponse['title'],
            ], JSON_PRETTY_PRINT)]], 422);
        }

        $transaction = Transaction::create([
            'from_party' => $senderAbn,
            'to_party' => $receiverAbn,
            'conversation_id' => $conversationId
        ]);

        Storage::disk('s3')->put(config('app.env') . '/' . $transaction->id . '/initial_message.json', $message);

        $apiRequest = new \ApiRequest();
        $token = $apiRequest->getNewTokenForCustomer($user->customer_id);
        $receiverPublicKey = (new \ApiRequest())->getReceiverPublicKey($receiverAbn, $token['id_token'])['pubKey'];
        $gpg = new \App\PhpGnupgWrapper($user->abn);
        $info = $gpg->importKey($receiverPublicKey);

        $receiverInfo = [
            'abn' => $receiverAbn,
            'fingerprint' => $info['fingerprint']
        ];

        $senderInfo = [
            'abn' => $user->abn,
            'fingerprint' => $user->fingerprint
        ];
        
        $tapMessage = $gpg->generateMessage($message, $receiverInfo, $senderInfo, $conversationId);

        $apiResponse = $apiRequest->sendMessage($request->get('endpoint'),
            $tapMessage['message'],
            $tapMessage['signature']
        );

        Storage::disk('s3')->put(config('app.env') . '/' . $transaction->id . '/cyphertext_signed.gpg', $tapMessage['cyphertext']);

        $message = [
            'cyphertext' => $tapMessage['cyphertext'],
            'hash' => $tapMessage['hash'],
            'reference' => $conversationId,
            'sender' => "urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::" . $user->abn
        ];

        Storage::disk('s3')->put(config('app.env') . '/' . $transaction->id . '/message.json', json_encode($message, JSON_PRETTY_PRINT));

        $transaction->message_hash = $tapMessage['hash'];
        $transaction->message_id = $apiResponse['data']['id'];
        $transaction->message_type = $apiResponse['data']['type'];
        $transaction->encripted_payload = 'cyphertext_signed.gpg';
        $transaction->decripted_payload = 'initial_message.json';

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
    public function download($transactionId, $filename)
    {
        $s3 = Storage::disk('s3');
        $client = $s3->getDriver()->getAdapter()->getClient();
        $expiry = "+1 hour";

        $command = $client->getCommand('GetObject', [
            'Bucket' => config('filesystems.disks.s3.bucket'),
            'Key' => config('app.env') . '/' . $transactionId . '/' . $filename
        ]);

        return redirect((string)$client->createPresignedRequest($command, $expiry)->getUri());
    }
}
