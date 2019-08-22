<?php

namespace App\Services\Google\Contracts;

use App\Integration;
use Google_Client;

interface BaseGoogleService
{
    /**
     * @param Integration $integration
     *
     * @return Google_Client
     */
    public function getGoogleClient(Integration $integration): Google_Client;
}
