<?php

namespace App\Services\HubSpot;

use Curl\Curl;

class HubSpotClient
{
    /**
     * @var string
     */
    public const API_DOMAIN = 'https://api.hubapi.com';

    /**
     * @var Curl
     */
    private $client;

    /**
     * HubSpotClient constructor.
     *
     * @param string $accessToken
     *
     * @throws \ErrorException
     */
    public function __construct(string $accessToken)
    {
        $this->client = new Curl();
        $this->client->setDefaultJsonDecoder($assoc = true);
        $this->client->setHeader('Accept', 'application/json');
        $this->client->setHeader('Authorization', 'Bearer ' . $accessToken);
    }

    /**
     * Get array of contacts.
     *
     * @return array
     *
     * @throws \Exception
     */
    public function getContacts(): array
    {
        $response = $this->client->get(self::API_DOMAIN . '/contacts/v1/lists/all/contacts/all');

        if (!isset($response['contacts'])) {
            logger()->info('The "contacts" key missed in response!', ['response' => $response]);
            throw new \Exception('No contacts in response!');
        }

        return $response['contacts'];
    }
}
