<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;

class SocialController extends Controller
{
    /**
     * Redirect user to idp
     */
    public function getSocialRedirect()
    {
        $oidc = new \OpenIDConnectClient('https://idp.testpoint.io', config('services.testpoint.client_id'));
        $oidc->setRedirectURL(config('services.testpoint.redirect'));
        $oidc->authenticate();
    }

    /**
     * Process user
     * @return bool|\Illuminate\Http\RedirectResponse
     */
    public function getSocialHandle()
    {
        try {
            $oidc = new \OpenIDConnectClient('https://idp.testpoint.io',
                config('services.testpoint.client_id'),
                config('services.testpoint.client_secret'));

            $oidc->authenticate();
            $userInfo = (array)$oidc->requestUserInfo();
            $token = $oidc->getIdToken();

            if(empty($userInfo['abn'])){
                return redirect('login')
                    ->with('error', 'Please <a href="https://idp.testpoint.io/allauth/logout/" target="_blank">login</a> with correct ABN account!');
            }

            createNewUser($userInfo['abn'], $userInfo['urn:oasis:names:tc:ebcore:partyid-type:iso6523']);
            return attemptLogin($userInfo['abn'], $token);
        } catch(\Exception $e){
            return redirect()->intended('login');
        }
    }
}