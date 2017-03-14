<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\User;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

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
    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
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
        $client = new Client();
        $res = $client->request('GET', 'https://dcp.testpoint.io/api/v0/demo_auth', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json; indent=4',
                'Authorization' => 'JWT ' . $request->get('token'),
            ]
        ]);


        if ($res->getStatusCode() == 200) {
            $result = json_decode($res->getBody(), true);
            $userExist = User::where('name', $result['user'])->first();
            if (!$userExist) {
                User::create([
                    'name' => $result['user'],
                    'email' => $result['user'],
                    'password' => bcrypt($result['user']),
                ]);
            }
        }

        if (Auth::attempt(['name' => $result['user'], 'password' => $result['user']])) {
            Session::put('user', json_encode($result));
            return redirect()->intended('dashboard');
        }
    }

    public function username()
    {
        return 'name';
    }
}
