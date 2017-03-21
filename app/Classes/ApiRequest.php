<?php

use GuzzleHttp\Client;

class ApiRequest
{

    private $client;
    private $token;

    public function __construct()
    {
        $this->client = new Client();
        $this->token = session('token');
    }


    public function getEndpoints()
    {
        return $this->makeRequest('GET', 'https://tap-gw.testpoint.io/api/endpoints/');
    }

    public function getReceiverPublicKey($receiverAbn)
    {
        return array_first(json_decode($this->client->request('GET', 'https://dcp.testpoint.io/urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::' . $receiverAbn . '/keys/', [])->getBody(), true));
    }

    /**
     * Perform request to remote API
     * @param $type
     * @param $url
     * @param array $headers
     * @return bool|mixed
     */
    private function makeRequest($type, $url, $headers = [], $requiresAuth = true)
    {
        try {
            if ($requiresAuth) {
                $headers = [
                    'headers' => array_merge($headers, [
                        'Authorization' => 'JWT ' . $this->token
                    ])];
            } else {
                $headers = [
                    'headers' => $headers];
            }
            $res = $this->client->request($type, $url, $headers);
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