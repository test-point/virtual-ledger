<?php

use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ApiRequest
{

    private $client;
    private $token;
    private $servicesUrl;

    public function __construct()
    {
        $this->client = new Client();
        $this->token = session('token');
        $this->idpDevToken = config('env_vars.idp_dev_token');
        $this->servicesUrl = config('env_vars.services_url');
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
        $response = (array) $this->makeRequest('GET', 'https://dcp.'.$this->servicesUrl.'/urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::' . $receiverAbn . '/keys/', $data);
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
                'pubKey' => \App\User::where('abn', $senderAbn)->first()->sshKeys->public,
                'revoked' => \Carbon\Carbon::now()->addYear()->format('Y-m-d H:i:s'),
                'fingerprint' => $fingerprint,
            ])
        ];
        $url = 'https://dcp.'.$this->servicesUrl.'/urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::' . $senderAbn . '/keys/';
        $requestType = 'POST';
        if($this->getKeyByFingerprint($senderAbn, $fingerprint)){
            $requestType = 'PATCH';
            $url .= $fingerprint;
        }
        return $this->makeRequest($requestType, $url, $data);
    }

    public function getKeyByFingerprint($senderAbn, $fingerprint)
    {
        return $this->makeRequest('GET', 'https://dcp.'.$this->servicesUrl.'/urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::' . $senderAbn . '/keys/' . $fingerprint, []);
    }

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
        $messageFile = md5($message);
        $signatureFile = md5($signature);
        Storage::put($messageFile, $message);
        Storage::put($signatureFile, $signature);
        $data = [
            'multipart' => [
                [
                    'name' => 'signature',
                    'contents' => fopen(storage_path('app/' . $signatureFile), 'r')
                ],
                [
                    'name' => 'message',
                    'contents' => fopen(storage_path('app/' . $messageFile), 'r')
                ],
            ]
        ];
        $response = $this->makeRequest('POST', $endpoint, $data, false);
        Storage::delete($messageFile);
        Storage::delete($signatureFile);
        return $response;
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
        return $this->makeRequest('GET', 'https://tap-gw.'.$this->servicesUrl.'/api/messages/'.$messageId.'/status/', $headers);
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
        return $this->makeRequest('GET', 'https://tap-gw.'.$this->servicesUrl.'/api/messages/', $headers);
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
        $headers = [
            'headers' => [
                'Authorization' => 'Token ' . $this->idpDevToken,
                'Accept' => 'application/json; indent=4',
            ]
        ];
        return $this->makeRequest('POST', 'https://idp-dev.tradewire.io/api/customers/v0/'.$customerId.'/tokens/'.$clientId.'/', $headers);
    }

    public function getDocumentIds($abn)
    {
        $data = $this->makeRequest('GET', 'https://dcp.'.$this->servicesUrl.'/urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::' . $abn . '?format=json');
        return $data['ServiceMetadataReferenceCollection'];
    }

    public function getEndpoints($abn, $documentId)
    {
        $data = $this->makeRequest('GET', 'https://dcp.'.$this->servicesUrl.'/urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::' . $abn . '/service/' . urlencode($documentId) . '?format=json');
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

    public function getEndpointByProcess($abn, $documentId = 'urn:oasis:names:specification:ubl:schema:xsd:ApplicationResponse-2', $process = 'bill-response-v1')
    {
        $data = $this->makeRequest('GET', 'https://dcp.' . $this->servicesUrl . '/urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::' . $abn . '/service/' . urlencode('bdx-docid-qns::' . $documentId) . '?format=json');
        if ($data) {
            foreach ($data['ProcessList'] as $processData) {
                if ($process == $processData['ProcessIdentifier']['value']) {
                    return $processData['ServiceEndpointList'][0]['EndpointURI'];
                }
            }
        }
        return false;
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
        $response = $this->makeRequest('POST', 'https://tap-gw.'.$this->servicesUrl.'/api/endpoints', $headers);
        return $response['data']['id'] ?? false;
    }

    public function createServiceMetadata($endpoint, $token, $abn)
    {
        $data = [
            'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2' => [
                'bill-invoice-v1',
                'bill-rcti-v1',
                'bill-adjustment-v1',
                'bill-taxreceipt-v1',
                'bill-creditnote-v1',
                'bill-debitnote-v1',
            ],
            'urn:oasis:names:specification:ubl:schema:xsd:ApplicationResponse-2' => [
                'bill-response-v1'
            ]
        ];

        foreach($data as $documentIdentifier => $processes) {
            $requestData = [
                'ProcessList' => [],
                'DocumentIdentifier' => [
                    'scheme' => 'bdx-docid-qns',
                    'value' => $documentIdentifier,
                    'id' => 'bdx-docid-qns::' . $documentIdentifier,
                ],
                'ParticipantIdentifier' => [
                    'scheme' => 'urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151',
                    'value' => $abn
                ]
            ];
            foreach ($processes as $process) {
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
            $this->makeRequest('PUT', "https://dcp.".$this->servicesUrl."/urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::$abn/service/bdx-docid-qns::" . urlencode($documentIdentifier), $headers);
        }
        return true;
    }
}