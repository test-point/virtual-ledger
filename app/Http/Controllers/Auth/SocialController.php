<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class SocialController extends Controller
{

    public function __construct()
    {
        $this->oidc = new \OpenIDConnectClient('https://idp.testpoint.io',
            config('services.testpoint.client_id'),
            config('services.testpoint.client_secret')
        );
    }

    /**
     * Redirect user to idp
     */
    public function getSocialRedirect(Request $request)
    {
        $this->oidc->setRedirectURL(config('services.testpoint.redirect'));
        $this->oidc->authenticate();
    }

    /**
     * Process user
     * @return bool|\Illuminate\Http\RedirectResponse
     */
    public function getSocialHandle(Request $request)
    {
        try {
            $this->oidc->authenticate();
            $userInfo = (array)$this->oidc->requestUserInfo();
            $token = $this->oidc->getIdToken();

            if (empty($userInfo['abn'])) {
                return redirect('login')
                    ->with('error', 'Please <a href="https://idp.testpoint.io/allauth/logout/" target="_blank">login</a> with correct ABN account!');
            }

            createNewUser($userInfo['abn'], $userInfo['urn:oasis:names:tc:ebcore:partyid-type:iso6523']);
            return attemptLogin($userInfo['abn'], $token);
        } catch (\Exception $e) {
            dump($e->getMessage());
            dump($e->getFile());
            dump($e->getLine());
            dd('1');
            return redirect()->intended('login');
        }
    }
}