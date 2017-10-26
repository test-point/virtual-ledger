<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SshKeys extends Model
{

    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(\App\User::class);
    }

    public static function addUserKeys(User $user)
    {
        $abn = $user->abn;
        $gnupg = new PhpGnupgWrapper($user->abn);
        $gnupg->generateKeys();
        $gnupg->exportKeys();

        return $user->sshKeys()->create([
            'public' => Storage::get('keys/public_' . $abn . '.key'),
            'private' => Storage::get('keys/private_' . $abn . '.key'),
        ]);
    }
}
