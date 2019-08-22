<?php

namespace App\Services\Google\Contracts;

use App\Integration;

interface GoogleContactService
{
    /**
     * @param Integration $integration
     *
     * @return void
     */
    public function fetch(Integration $integration): void;
}
