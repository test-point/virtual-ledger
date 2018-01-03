<?php

use GuzzleHttp\Client;

class MessageValidator
{

    const API_ENDPOINT = 'http://bill.testpoint.io/api/v0/validator';

    public static function validate($message)
    {
        $client = new Client([
            'http_errors' => false,
            'headers' => [ 'Content-Type' => 'application/json' ]
        ]);
        $data = [
            'body' => $message
        ];
        $res = $client->post(self::API_ENDPOINT, $data);
        return json_decode($res->getBody(), true);
    }
}