<?php

namespace App;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class PhpGnupgWrapper
{

    public $abn;
    public $gpg;
    private $keyringPath;

    public function __construct($abn)
    {
//        putenv('GPG_OPTIONS="--no-show-photos --pinentry-mode loopback"');
        putenv('GPG_TTY=$(tty)');

        $this->keyringPath = base_path('.gnupg');

        putenv('GNUPGHOME=' . $this->keyringPath);

        $user = User::where('abn', $abn)->first();
        if($user) {
            //import keys
            $userKeys = $user->sshKeys;
            if(count($userKeys)) {
                $this->gpg = new \gnupg();
                if (!$this->gpg->import($userKeys->public)) {
                    Log::debug('Public key for ' . $user->abn . ': ' . $this->gpg->geterror());
                }
                if (!$this->gpg->import($userKeys->private)) {
                    Log::debug('Private key for ' . $user->abn . ': ' . $this->gpg->geterror());
                }
            }
        }

        $this->abn = $abn;
    }

    /**
     * Generate public / private keys
     */
    public function generateKeys()
    {
        $this->runConsoleCommand('gpg2 --homedir '.$this->keyringPath.' --batch -q --passphrase '.$this->abn.' --quick-gen-key urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::' . $this->abn);
    }

    /**
     * Export public / private keys to files
     */
    public function exportKeys()
    {
        $this->runConsoleCommand('echo ' . $this->abn . ' | gpg2  --homedir '.$this->keyringPath.' --armor --passphrase-fd 0 --export urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::' . $this->abn . ' > ' . storage_path('app/keys/public_' . $this->abn . '.key'));
        $this->runConsoleCommand('echo ' . $this->abn . ' | gpg2  --homedir '.$this->keyringPath.' --armor --passphrase-fd 0 --export-secret-key urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::' . $this->abn . ' > ' . storage_path('app/keys/private_' . $this->abn . '.key'));
    }

    public function importKey($keyData)
    {
        $gnupg = new \gnupg();
        return $gnupg->import($keyData);
    }

    public function generateMessage($message, $receiverInfo, $senderInfo, $reference = '')
    {
        $gpg = new \gnupg();

        $receiverFingerprint = $receiverInfo['fingerprint'];
        $receiverAbn = $receiverInfo['abn'];

        $senderFingerprint = $senderInfo['fingerprint'];
        $senderAbn = $senderInfo['abn'];

        $messageJson = $message;


        $gpg->addsignkey($senderInfo['fingerprint'], $senderInfo['abn']);
        $gpg->setsignmode(GNUPG_SIG_MODE_CLEAR);
        $gpg->sign($messageJson);

        $hash = openssl_digest($messageJson, 'sha256');

        $gpg->addencryptkey($receiverFingerprint);
        $cyphertext = $gpg->encrypt($messageJson);

        $message = json_encode([
            'cyphertext' => $cyphertext, //file_get_contents(storage_path('app/'.$messageId.'/cyphertext_signed.gpg')),
            'hash' => $hash,
            'reference' => $reference,
            'sender' => "urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::" . $senderAbn
        ], JSON_PRETTY_PRINT);


//        $gpg = new \gnupg();
        $gpg->setsignmode(GNUPG_SIG_MODE_DETACH);
        $signature = $gpg->sign($message);

        return [
            'message' => $message,
            'signature' => $signature,
            'hash' => $hash,
            'cyphertext' => $cyphertext,
        ];
    }

    public function decryptMessage($message, $fingerprint, $abn)
    {
        $gpg = $this->gpg;
        $addedKey = $gpg->adddecryptkey($fingerprint, $abn);
        if (!$addedKey) {
            Log::debug($gpg->geterror());
        }
        $decryptedMessage = $gpg->decrypt($message);

        if (!$decryptedMessage) {
            Log::debug($gpg->geterror());
        }

        //remove clearsign
        $clearsignStart = strpos($decryptedMessage, '-----BEGIN PGP SIGNATURE-----');
        if ($clearsignStart) {
            $startPosition = strpos($decryptedMessage, '{');
            $length = $clearsignStart - $startPosition;

            return substr($decryptedMessage, $startPosition, $length);
        }
        return $decryptedMessage;
    }

    /**
     * Run console command
     *
     * @param $cmd
     */
    function runConsoleCommand($cmd)
    {
        $process = new Process($cmd, null, null, null, 3600);
        try {
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            Log::debug('Console command error: ' . $e->getMessage());
        }
    }
}