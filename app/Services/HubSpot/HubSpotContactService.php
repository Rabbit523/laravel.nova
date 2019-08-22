<?php

namespace App\Services;

use App\Contact;
use App\Integration;
use App\Services\HubSpot\HubSpotClient;
use App\User;

class HubSpotContactService
{
    /**
     * @param Integration $integration
     *
     * @return void
     *
     * @throws \ErrorException
     */
    public function fetch(Integration $integration): void
    {
        $HubSpotClient = new HubSpotClient($integration->details['access_token']);

        foreach ($HubSpotClient->getContacts() as $contact) {
            /** @var Contact|null $company */
            $company = $this->firstOrCreateCompany($integration->user, $contact);

            $integration->user->contacts()->updateOrCreate(
                [
                    'first_name' => $this->getFirstName($contact),
                    'last_name'  => $this->getLastName($contact),
                    'email'      => $this->getEmail($contact),
                ],
                [
                    'source'     => Integration::SERVICE_HUBSPOT,
                    'company_id' => $company ? $company->id : null,
                ]
            );
        }

        return;
    }

    /**
     * @param $contact
     *
     * @return string|null
     */
    private function getFirstName($contact): ?string
    {
        return array_get($contact, 'properties.firstname.value');
    }

    /**
     * @param $contact
     *
     * @return string|null
     */
    private function getLastName($contact): ?string
    {
        return array_get($contact, 'properties.lastname.value');
    }

    /**
     * @param $contact
     *
     * @return string|null
     */
    private function getEmail($contact): ?string
    {
        return array_get($contact, 'identity-profiles.0.identities.0.value');
    }

    /**
     * @param User $user
     * @param array $contact
     *
     * @return Contact|null
     */
    private function firstOrCreateCompany(User $user, array  $contact): ?Contact
    {
        $companyName = array_get($contact, 'properties.company.value');

        if (null === $companyName) {
            return null;
        }

        /** @var Contact $company */
        $company = $user->contacts()->firstOrCreate([
            'is_company' => true,
            'name'       => $companyName,
            'source'     => Integration::SERVICE_HUBSPOT,
            'status' => 'new'
        ]);

        return $company;
    }
}
