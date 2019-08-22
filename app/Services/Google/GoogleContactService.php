<?php

namespace App\Services\Google;

use App\Contact;
use App\Integration;
use App\Services\Google\Contracts\GoogleContactService as GoogleContactServiceInterface;
use Carbon\Carbon;
use Google_Service_People;
use Google_Service_People_Person;

class GoogleContactService extends BaseGoogleService implements GoogleContactServiceInterface
{
    /**
     * @var array of GET parameters.
     */
    protected $includeFields = [
        'person.names',
        'person.emailAddresses',
        'person.phone_numbers',
        'person.birthdays',
        'person.organizations',
        'person.photos',
        'person.locales',
        'person.addresses',
        'person.genders',
    ];

    /**
     * Methods in service => method in Google_Service_People_Person.
     *
     * @var array
     */
    protected $methodMapping = [
        'prepareNames'         => 'getNames',
        'prepareEmails'        => 'getEmailAddresses',
        'preparePhones'        => 'getPhoneNumbers',
        'prepareBirthdays'     => 'getBirthdays',
        'prepareOrganizations' => 'getOrganizations',
        'preparePhotos'        => 'getPhotos',
        'prepareLocales'       => 'getLocales',
        'prepareGender'        => 'getGenders',
    ];

    /**
     * @param Integration $integration
     *
     * @return void
     */
    public function fetch(Integration $integration): void
    {
        $googleService = new Google_Service_People($this->getGoogleClient($integration));

        $params   = ['requestMask.includeField' => implode(',', $this->includeFields),];
        $response = $googleService->people_connections->listPeopleConnections('people/me', $params);
        $this->integration = $integration;

        /** @var Google_Service_People_Person $person */
        foreach ($response->getConnections() as $person) {
            if (empty($person->getNames())) {
                continue;
            }

            $data = $this->preparePersonData($person);
            $data['source'] = Integration::SERVICE_GOOGLE;
            $data['status'] = 'new';
            /** @var Contact $contact */
            $contact = $integration->user->contacts()->updateOrCreate(
                [
                    'first_name' => $person->names[0]->getGivenName(),
                    'last_name'  => $person->names[0]->getFamilyName(),
                    'is_company' => false,
                ],
                $data
            );

            $personAddresses = $person->getAddresses();
            if (!empty($personAddresses)) {
                foreach ($this->prepareAddresses($personAddresses) as $address)
                    $contact->addresses()->updateOrCreate($address);
            }
        }

        return;
    }

    /**
     * Gets person data and prepares the array of data for contact.
     *
     * @param Google_Service_People_Person $person
     *
     * @return array
     */
    private function preparePersonData(Google_Service_People_Person $person)
    {
        $preparedData = [];

        foreach ($this->methodMapping as $method => $getDataMethod) {
            if (!empty($person->$getDataMethod())) {
                $this->$method($person->$getDataMethod(), $preparedData);
            }
        }

        return $preparedData;
    }

    /**
     * @param array $names
     * @param array $preparedData
     *
     * @return void
     */
    private function prepareNames(array $names, array &$preparedData): void
    {
        /** @var \Google_Service_People_Name $name */
        foreach ($names as $key => $name) {
            if (0 === $key) {
                $preparedData['name']       = $name->getDisplayName();
                $preparedData['first_name'] = $name->getGivenName();
                $preparedData['last_name']  = $name->getFamilyName();
            } else {
                $preparedData['meta']['names'][] = [
                    'display_name' => $name->getDisplayName(),
                    'given_name'   => $name->getGivenName(),
                    'family_name'  => $name->getFamilyName(),
                ];
            }
        }
    }

    /**
     * @param array $emails
     * @param array $preparedData
     *
     * @return void
     */
    private function prepareEmails(array $emails, array &$preparedData): void
    {
        /** @var \Google_Service_People_EmailAddress $email */
        foreach ($emails as $key => $email) {
            if (0 === $key) {
                $preparedData['email'] = $email->getValue();
            } else {
                $preparedData['meta']['emails'][] = [
                    'value' => $email->getValue(),
                ];
            }
        }
    }

    /**
     * @param array $phones
     * @param array $preparedData
     *
     * @return void
     */
    private function preparePhones(array $phones, array &$preparedData): void
    {
        /** @var \Google_Service_People_PhoneNumber $phone */
        foreach ($phones as $key => $phone) {
            if (0 === $key) {
                $preparedData['phone'] = $phone->getCanonicalForm() ?? $phone->getValue();
            } else {
                $preparedData['meta']['phones'][] = [
                    'type'           => $phone->getType(),
                    'value'          => $phone->getValue(),
                    'canonical_form' => $phone->getCanonicalForm(),
                ];
            }
        }
    }

    /**
     * @param array $birthdays
     * @param array $preparedData
     *
     * @return void
     */
    private function prepareBirthdays(array $birthdays, array &$preparedData): void
    {
        /** @var \Google_Service_People_Birthday $birthdays [0] */
        /** @var \Google_Service_People_Date $date */
        $date = $birthdays[0]->getDate();

        $preparedData['birthday'] = Carbon::createFromFormat(
            'd-m-Y',
            $date->getDay() . '-' . $date->getMonth() . '-' . $date->getYear()
        );
    }

    /**
     * @param array $organizations
     * @param array $preparedData
     *
     * @return void
     */
    private function prepareOrganizations(array $organizations, array &$preparedData): void
    {
        /** @var \Google_Service_People_Organization $organization */
        foreach ($organizations as $key => $organization) {
            if (!$key) {
                $data['source'] = Integration::SERVICE_GOOGLE;
                $company = $this->integration->user->contacts()->where(['name' => $organization->getName(), 'is_company' => true])->first();
                if ($company) {
                    $preparedData['company_id'] = $company->id;
                }
            }

            $preparedData['meta']['organizations'][] = [
                'name'       => $organization->getName(),
                'title'      => $organization->getTitle(),
                'type'       => $organization->getType(),
                'department' => $organization->getDepartment(),
                'domain'     => $organization->getDomain(),
            ];
        }
    }

    /**
     * @param array $photos
     * @param array $preparedData
     *
     * @return void
     */
    private function preparePhotos(array $photos, array &$preparedData): void
    {
        /** @var \Google_Service_People_Photo $photo */
        foreach ($photos as $key => $photo) {
            if (0 === $key) {
                $preparedData['contact_image'] = $photo->getUrl();
            } else {
                $preparedData['meta']['organizations'][] = [
                    'url'      => $photo->getUrl(),
                    'metadata' => $photo->getMetadata(),
                ];
            }
        }
    }

    /**
     * @param array $locales
     * @param array $preparedData
     *
     * @return void
     */
    private function prepareLocales(array $locales, array &$preparedData): void
    {
        /** @var \Google_Service_People_Locale $locale */
        foreach ($locales as $key => $locale) {
            $preparedData['meta']['locales'][] = [
                'value'    => $locale->getValue(),
                'metadata' => $locale->getMetadata(),
            ];
        }
    }

    /**
     * @param array $genders
     * @param array $preparedData
     *
     * @return void
     */
    private function prepareGender(array $genders, array &$preparedData): void
    {
        /** @var \Google_Service_People_Gender $genders [0] */
        $gender = $genders[0]->getValue();

        if (in_array($gender, [Contact::GENDER_MALE, Contact::GENDER_FEMALE, Contact::GENDER_OTHER])) {
            $preparedData['meta']['gender'] = $gender;
        }
    }

    /**
     * @param array $addresses
     *
     * @return array
     */
    private function prepareAddresses(array $addresses): array
    {
        $preparedData = [];
        /** @var \Google_Service_People_Address $address */
        foreach ($addresses as $address) {
            $preparedData[] = [
                'country'  => $address->getCountry(),
                'city'     => $address->getCity(),
                'street'   => $address->getExtendedAddress(),
                'postcode' => $address->getPostalCode(),
                'state'    => $address->getRegion(),
            ];
        }

        return $preparedData;
    }
}
