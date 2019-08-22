<?php


namespace App\Services\Google;

use App\Integration;
use App\Services\Google\Contracts\BaseGoogleService as BaseGoogleServiceInterface;
use Google_Client;

class BaseGoogleService implements BaseGoogleServiceInterface
{
    /**
     * Creates the instance of Google_Client.
     *
     * @param Integration $integration
     *
     * @return Google_Client
     */
    public function getGoogleClient(Integration $integration): Google_Client
    {
        $token = [
            'access_token'  => $integration->details['token'],
            'refresh_token' => $integration->details['refreshToken'],
            'expires_in'    => $integration->details['expiresIn'],
        ];

        $client = new Google_Client();
        $client->useApplicationDefaultCredentials();
        // $client->setApplicationName(env('APP_NAME'));
        // $client->setClientId(env('GOOGLE_CLIENT_ID'));
        // $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
        // $client->setAccessToken(\json_encode($token));

        return $client;
    }
}
