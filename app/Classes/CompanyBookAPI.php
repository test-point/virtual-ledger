<?php

use GuzzleHttp\Client;

class CompanyBookAPI
{
    public static function searchByAbn($abn)
    {
        $client = new Client();
        $data = json_decode($client->request('GET', 'https://companybook.io/api/v0/businesses/?query=' . $abn)->getBody(), true);
        return $data['data'][0] ?? false;
    }
}