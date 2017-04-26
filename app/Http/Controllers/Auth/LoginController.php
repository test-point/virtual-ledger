<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\ValidationData;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/transactions';

    /**
     * LoginController constructor.
     */
    public function __construct()
    {
        $this->middleware('guest', ['except' => 'logout']);
    }

    /**
     * Get the needed authorization credentials from the request.
     * We using only token for auth
     *
     * @param  \Illuminate\Http\Request $request
     * @return array
     */
    protected function credentials(Request $request)
    {
        return $request->only('token');
    }

    /**
     * We using only token for auth
     * @param Request $request
     */
    protected function validateLogin(Request $request)
    {
        $this->validate($request, [
            'token' => 'required'
        ]);
    }

    /**
     * Reworked auth function - we are using request to api to validate user credentials
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function attemptLogin(Request $request)
    {
        try {
            $token = (new Parser())->parse((string)$request->get('token'));
        } catch (\Exception $e){
            return false;
        }

        $data = new ValidationData();
        $data->setIssuer('https://idp.testpoint.io');
        $data->setAudience($token->getClaim('aud'));

        if ($token->validate($data)) {

            $userExist = User::where('name', $token->getClaim('abn'))->first();
            if (!$userExist) {
                $apiRequest = new \ApiRequest();
                //create new customer for user
                $partisipantsIds = $token->getClaim('urn:oasis:names:tc:ebcore:partyid-type:iso6523');
                $newCustomerData = ($apiRequest->createNewCustomer($partisipantsIds));


                $abnData = \CompanyBookAPI::searchByAbn($token->getClaim('abn'));
                User::create([
                    'name' => $token->getClaim('abn'),
                    'email' => $token->getClaim('abn'),
                    'abn_name' => $abnData['attributes']['extra_data']['name'] ?? 'No ABR entry',
                    'customer_id' => $newCustomerData['uuid'],
                    'password' => bcrypt($token->getClaim('abn')),
                ]);
                //create new endpoint for user
                $gwToken = $apiRequest->getNewTokenForCustomer($newCustomerData['uuid'], 945682);
                $endpoint = $apiRequest->createEndpoint($token->getClaim('abn'), $gwToken['id_token']);
                $dcpToken = $apiRequest->getNewTokenForCustomer($newCustomerData['uuid'], 274953);
                $apiRequest->createServiceMetadata($endpoint, $dcpToken['id_token'], $token->getClaim('abn'));
                //todo POST INFORMATION TO DCL
            }
            if (Auth::attempt(['name' => $token->getClaim('abn'), 'password' => $token->getClaim('abn')])) {

                Session::put('user', json_encode($token->getClaims()));
                Session::put('abn', $token->getClaim('abn'));
                Session::put('token', $request->get('token'));
                $tokenData = $token->getClaims();
                $tokenData = reset($tokenData);
                $userUrn = $tokenData->getName();
                foreach ((array)$tokenData->getValue()[0] as $k1 => $v1) {
                    $userUrn .= ':' . $k1 . '::' . $v1;
                }
                Session::put('user_urn', $userUrn);
                return redirect()->intended('dashboard');
            }
        }
    }

    public function username()
    {
        return 'name';
    }
}
