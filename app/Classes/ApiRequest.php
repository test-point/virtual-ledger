<?php

use GuzzleHttp\Client;

class ApiRequest
{

    private $client;
    private $token;

    public function __construct()
    {
        $this->client = new Client();
        $this->token = \Illuminate\Support\Facades\Session::get('token');
    }


    public function getEndpoints()
    {
        return $this->makeRequest('GET', 'https://tap-gw.testpoint.io/api/endpoints/');
    }

    /**
     * Perform request to remote API
     * @param $type
     * @param $url
     * @param array $headers
     * @return bool|mixed
     */
    private function makeRequest($type, $url, $headers = [])
    {
        try {
            $res = $this->client->request($type, $url, [
                'headers' => array_merge($headers, [
                    'Authorization' => 'JWT ' . $this->token
                ])]);
        } catch (Exception $e) {
            dump([
                'headers' => array_merge($headers, [
                    'Authorization' => 'JWT ' . $this->token
                ])]);
            dump($e->getMessage());
            die;
        }

        return json_decode($res->getBody(), true);
    }
}