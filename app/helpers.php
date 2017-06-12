<?php

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

function runConsoleCommand($cmd)
{
    $process = new Process($cmd, null, null, null, 3600);
    try {
        $process->mustRun();
    } catch (ProcessFailedException $e) {
        Log::debug('Console command error: ' . $e->getMessage());
    }
}

/**
 * Replace data in MessageTemplate
 *
 * @param $message
 * @param $receiverAbn
 * @param $senderAbn
 * @return mixed
 */
function replaceABNData($message, $receiverAbn, $senderAbn)
{
    $message['Invoice']['accountingSupplierParty']['party']['partyLegalEntity'][0]['companyID'] = ['ABN' => "$senderAbn"];
    $message['Invoice']['accountingCustomerParty']['party']['partyLegalEntity'][0]['companyID'] = ['ABN' => "$receiverAbn"];

    $message['Invoice']['accountingSupplierParty']['party']['partyIdentification'][0] = ['ABN' => "$senderAbn"];
    $message['Invoice']['accountingCustomerParty']['party']['partyIdentification'][0] = ['ABN' => "$receiverAbn"];

    return $message;
}

/**
 * Check user existence and create new one if doesn't exist
 * @param $abn
 * @param $partisipantsIds
 */
function createNewUser($abn, $partisipantsIds)
{
    $userExist = \App\User::where('name', $abn)->first();
    if (!$userExist) {
        $apiRequest = new \ApiRequest();

        //create new customer for user
        $newCustomerData = ($apiRequest->createNewCustomer($partisipantsIds));

        $abnData = \CompanyBookAPI::searchByAbn($abn);
        try {
            \App\User::create([
                'name' => $abn,
                'email' => $abn,
                'abn_name' => $abnData['attributes']['extra_data']['name'] ?? 'No ABR entry',
                'customer_id' => $newCustomerData['uuid'],
                'password' => bcrypt($abn),
                'fingerprint' => ''
            ]);
        } catch (Exception $e){
            Log::debug('Create user error: ' . $e->getMessage());
        }
        //create new endpoint for user
        $gwToken = $apiRequest->getNewTokenForCustomer($newCustomerData['uuid'], 945682);
        $endpoint = $apiRequest->createEndpoint($abn, $gwToken['id_token']);
        $dcpToken = $apiRequest->getNewTokenForCustomer($newCustomerData['uuid'], 274953);
        $apiRequest->createServiceMetadata($endpoint, $dcpToken['id_token'], $abn);

        //create public / private keys for user
        runConsoleCommand('gpg2 --batch -q --passphrase "" --quick-gen-key urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::' . $abn);
    }
}

/**
 * Attempt login and set user data
 * @param $abn
 * @param $token
 * @return bool|\Illuminate\Http\RedirectResponse
 */
function attemptLogin($abn, $token)
{
    if (Auth::attempt(['name' => $abn, 'password' => $abn])) {

        session()->put('abn', $abn);
        session()->put('token', $token);

        //export user keys
        runConsoleCommand('gpg2 --armor --export urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::' . $abn . ' > ' . resource_path('data/keys/public_' . $abn . '.key'));
        runConsoleCommand('gpg2 --fingerprint urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::' . $abn . ' > ' . resource_path('data/keys/' . $abn . '_fingerprint.key'));

         //get user's fingerprint
        $gnupg = gnupg_init();
        $info = gnupg_import($gnupg, file_get_contents(resource_path('data/keys/public_' . $abn . '.key')));
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!empty($info['fingerprint'])) {
            $user->fingerprint = $info['fingerprint'];
            $user->save();
        }

        //upload user public key to dcp
        $fingerprint = str_replace(' ', '', explode(PHP_EOL, explode('Key fingerprint = ', file_get_contents(resource_path('data/keys/' . $abn . '_fingerprint.key')))[1])[0]);
        $apiRequest = new \ApiRequest();
        $token = $apiRequest->getNewTokenForCustomer(Auth::user()->customer_id);
        $apiRequest->sendSenderPublicKey($abn, $fingerprint, $token['id_token']);

        return redirect()->intended('transactions');
    }
    return false;
}