<?php

use GuzzleHttp\Client;

class TapGw
{

    private $token;

    public function __construct($token)
    {
        $this->token = $token;
        $this->client = new Client();
        $this->setHeaders();
    }

    /**
     * Get endpoints list for user
     *
     * @return boolean|mixed
     */
    public function getEndpoints()
    {
        $headers = [
            'headers' => $this->headers,
        ];
        return $this->makeRequest('GET', 'https://tap-gw.testpoint.io/api/endpoints', $headers);
    }

    public function getMessages()
    {
         $headers = [
             'headers' => $this->headers
        ];
         return $this->makeRequest('GET', 'https://tap-gw.testpoint.io/api/messages', $headers);
    }

    public function getMessageBody($messageId)
    {
         $headers = [
             'headers' => $this->headers
        ];
         return $this->makeRequest('GET', 'https://tap-gw.testpoint.io/api/messages/'.$messageId.'/body', $headers);
    }

    private function makeRequest($type, $url, $headers = [])
    {
        try {
            $res = $this->client->request($type, $url, $headers);
            return json_decode($res->getBody(), true);
        } catch (Exception $e) {
            Log::debug('Api Request error: ' . $url);
            Log::debug('Api Request error: ' . json_encode($headers));
            Log::debug('Api Request error: ' . $e->getMessage());
        }
        return false;
    }

    private function setHeaders(array $headers = [])
    {
        $this->headers = array_merge($headers, [
            'Authorization' => 'JWT ' . $this->token,
            'Accept' => 'application/json; indent=4',
            'Content-Type' => 'application/json',
        ]);
    }
}