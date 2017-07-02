<?php

use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class ApiRequest
{

    private $client;
    private $token;

    public function __construct()
    {
        $this->client = new Client();
        $this->token = session('token');
        $this->idpDevToken = '18c2b0ab927d8a3c9bf9ef78419a8f6d4535e47f';
    }

    /**
     * Key receiver public key from DCP service
     * @param string $receiverAbn
     *
     * @return mixed
     */
    public function getReceiverPublicKey($receiverAbn, $token)
    {
        $data = [];
        $response = (array) $this->makeRequest('GET', 'https://dcp.testpoint.io/urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::' . $receiverAbn . '/keys/', $data);
        if(!empty($response)) {
            $response = array_filter($response, function ($entry) {
                return empty($entry['revoked']) || Carbon::now()->lt(Carbon::parse($entry['revoked']));
            });
        }
        return array_first($response);
    }

    /**
     * Send sender public key using bearer token
     *
     * @param string $senderAbn
     * @param string $fingerprint
     * @param string $token
     *
     * @return mixed
     */
    public function sendSenderPublicKey($senderAbn, $fingerprint, $token)
    {
        $data = [
            'headers' => [
                'Authorization' => 'JWT ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'pubKey' => file_get_contents(resource_path('data/keys/public_'.$senderAbn.'.key')),
                'revoked' => \Carbon\Carbon::now()->addYear()->format('Y-m-d H:i:s'),
                'fingerprint' => $fingerprint,
            ])
        ];
        $url = 'https://dcp.testpoint.io/urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::' . $senderAbn . '/keys/';
        $requestType = 'POST';
        if($this->getKeyByFingerprint($senderAbn, $fingerprint)){
            $requestType = 'PATCH';
            $url .= $fingerprint;
        }
        return $this->makeRequest($requestType, $url, $data);
    }

    public function getKeyByFingerprint($senderAbn, $fingerprint)
    {
        return $this->makeRequest('GET', 'https://dcp.testpoint.io/urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::' . $senderAbn . '/keys/' . $fingerprint, []);
    }

//    public function getKeys($abn, $token)
//    {
//        $data = [
//            'headers' => [
//                'Authorization' => 'JWT ' . $token,
//                'Content-Type' => 'application/json',
//            ],
//        ];
//        return $this->makeRequest('GET', 'https://dcp.testpoint.io/urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::' . $abn . '/keys/', $data);
//    }

    /**
     * Send new message to tap-gw
     *
     * @param string $endpoint
     * @param string $message
     * @param string $signature
     *
     * @return bool|mixed
     */
    public function sendMessage($endpoint, $message, $signature)
    {
        $data = [
            'multipart' => [
                [
                    'name' => 'signature',
                    'contents' => fopen($signature, 'r')
                ],
                [
                    'name' => 'message',
                    'contents' => fopen($message, 'r')
                ],
            ]
        ];
        return $this->makeRequest('POST', $endpoint, $data, false);
    }

    /**
     * Get tap message
     *
     * @param string $messageId
     *
     * @return mixed
     */
    public function getMessage($messageId)
    {
        $headers = [
            'headers' => [
                'Authorization' => 'Token ' . $this->idpDevToken
            ]
        ];
        return $this->makeRequest('GET', 'https://tap-gw.testpoint.io/api/messages/'.$messageId.'/status/', $headers);
    }

     /**
     * Get tap message
     *
     * @param string $messageId
     *
     * @return mixed
     */
    public function getMessages($token, $participantId, $endpointId, $status)
    {
        $headers = [
            'headers' => [
                'Authorization' => 'JWT ' . $token
            ],
            'body' => json_encode([
                'participantId' => $participantId,
                'endpointId' => $endpointId,
                'status' => $status,
            ])
        ];
        return $this->makeRequest('GET', 'https://tap-gw.testpoint.io/api/messages/', $headers);
    }

    /**
     * Generate message endpoint url
     *
     * @param string $endpointId
     *
     * @return string
     */
    private function getMessagesEndpoint($endpointId)
    {
        return 'http://tap-gw.testpoint.io/api/endpoints/' . $endpointId . '/message/';
    }

    /**
     * Perform request to remote API
     *
     * @param string $type
     * @param string $url
     * @param array  $headers
     *
     * @return mixed
     */
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

    /**
     * Create new customer in https://idp-dev.tradewire.io/api/customers/v0/
     * for user during first login
     *
     * @param array $partisipandIds
     *
     * @return mixed
     */
    public function createNewCustomer($partisipandIds)
    {
        $headers = [
            'headers' => [
                'Authorization' => 'Token ' . $this->idpDevToken,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'participant_ids' => $partisipandIds
            ])
        ];
        return $this->makeRequest('POST', 'https://idp-dev.tradewire.io/api/customers/v0/', $headers);
    }

//    public function getCustomer($customerId)
//    {
//        $headers = [
//            'headers' => [
//                'Authorization' => 'Token ' . $this->idpDevToken,
//                'Accept' => 'application/json; indent=4',
//            ]
//        ];
//        return $this->makeRequest('POST', 'https://idp-dev.tradewire.io/api/customers/v0/'.$customerId, $headers);
//    }

    /**
     * Generate new token for customer
     *
     * @param string $customerId
     * @param string $clientId
     *
     * @return mixed
     */
    public function getNewTokenForCustomer($customerId, $clientId = '274953')
    {
//        $cacheKey = 'token_' . $customerId . '_' . $clientId;
//        $token = cache()->get($cacheKey);
//        if(!$token) {
        $headers = [
            'headers' => [
                'Authorization' => 'Token ' . $this->idpDevToken,
                'Accept' => 'application/json; indent=4',
            ]
        ];
        $token = $this->makeRequest('POST', 'https://idp-dev.tradewire.io/api/customers/v0/'.$customerId.'/tokens/'.$clientId.'/', $headers);
//        cache()->put($cacheKey, $token, Carbon::now()->addSeconds($token['expires_in']));
        return $token;
    }

    public function getDocumentIds($abn)
    {
        $data = $this->makeRequest('GET', 'https://dcp.testpoint.io/urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::' . $abn . '?format=json');
        return $data['ServiceMetadataReferenceCollection'];
    }

    public function getEndpoints($abn, $documentId)
    {
        $data = $this->makeRequest('GET', 'https://dcp.testpoint.io/urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::' . $abn . '/service/' . urlencode($documentId) . '?format=json');
        if(!$data){
            return false;
        }
        $result = array_map(function ($item) {
            return array_map(function ($item1) use ($item) {
                return implode(':', $item['ProcessIdentifier']) . ' - ' . $item1['EndpointURI'];
            }, $item['ServiceEndpointList']);
        }, $data['ProcessList']);
        //multidimensional array to one-dimensional array
        return array_reduce($result, 'array_merge', array());
    }

    /**
     * Create new endpoint for user
     *
     * @param $abn
     * @param $token
     *
     * @return mixed
     */
    public function createEndpoint($abn, $token)
    {
        $headers = [
            'headers' => [
                'Authorization' => 'JWT ' . $token,
                'Accept' => 'application/json; indent=4',
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                "participant_id" => "urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::$abn"
            ])
        ];
        $response = $this->makeRequest('POST', 'https://tap-gw.testpoint.io/api/endpoints', $headers);
        return $response['data']['id'] ?? false;
    }

    public function createServiceMetadata($endpoint, $token, $abn)
    {
        $processes = [
            'bill-invoice-v1',
            'bill-rcti-v1',
            'bill-adjustment-v1',
            'bill-taxreceipt-v1',
            'bill-creditnote-v1',
            'bill-debitnote-v1',
        ];
        $requestData = [
            'ProcessList' => [],
            'DocumentIdentifier' => [
                'scheme' => 'bdx-docid-qns',
                'value' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                'id' => 'bdx-docid-qns::urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
            ],
            'ParticipantIdentifier' => [
                'scheme' => 'urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151',
                'value' => $abn
            ]
        ];
        foreach($processes as $process){
            $requestData['ProcessList'][] = [
                'ProcessIdentifier' => [
                    'scheme' => 'digitalbusinesscouncil.com.au',
                    'value' => $process,
                ],
                'ServiceEndpointList' => [
                    [
                        'ServiceActivationDate' => Carbon::now()->format('Y-m-d'),
                        'Certificate' => '123',
                        'EndpointURI' => "http://tap-gw.testpoint.io/api/endpoints/$endpoint/message/",
                        'transportProfile' => 'TBD',
                        'ServiceExpirationDate' => Carbon::now()->addYears(1)->format('Y-m-d'),
                        'RequireBusinessLevelSignature' => "false",
                        'TechnicalInformationUrl' => '123',
                        'MinimumAuthenticationLevel' => '0',
                        'ServiceDescription' => '123',

                    ]
                ]
            ];
        }

        $headers = [
            'headers' => [
                'Authorization' => 'JWT ' . $token,
                'Accept' => 'application/json; indent=4',
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($requestData)
        ];

        return $this->makeRequest('PUT', "https://dcp.testpoint.io/urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::$abn/service/bdx-docid-qns::urn:oasis:names:specification:ubl:schema:xsd:Invoice-2", $headers);
    }
}