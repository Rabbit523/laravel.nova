<?php

namespace App\Services\Importers;

use App\Contact;
use App\User;
use Exception;
use League\Csv\Reader;

class CSVContactImporter
{
    /** @var string file with path. */
    protected $file;

    /** @var User $user */
    protected $user;

    protected $mapper = [
        'first_name',
        'last_name',
        'company_name',
        'email',
        'phone',
        'street',
        'city',
        'state',
        'postcode',
        'status',
        'type',
    ];

    /**
     * Statuses in Japanese should be mapped to corresponding english values.
     *
     * @var array of statuses map.
     */
    protected $statusesMapping = [
        'japanese' => [
            '新規' => Contact::STATUS_NEW,
            'リード' => Contact::STATUS_LEAD,
            'ファン' => Contact::STATUS_FAN,
            '購読者' => Contact::STATUS_SUBSCRIBER,
            '見込み顧客' => Contact::STATUS_OPPORTUNITY,
            'カスタマー' => Contact::STATUS_CUSTOMER,
            'その他' => Contact::STATUS_OTHER,
        ],
        'english' => [
            Contact::STATUS_NEW => Contact::STATUS_NEW,
            Contact::STATUS_LEAD => Contact::STATUS_LEAD,
            Contact::STATUS_FAN => Contact::STATUS_FAN,
            Contact::STATUS_SUBSCRIBER => Contact::STATUS_SUBSCRIBER,
            Contact::STATUS_OPPORTUNITY => Contact::STATUS_OPPORTUNITY,
            Contact::STATUS_CUSTOMER => Contact::STATUS_CUSTOMER,
            Contact::STATUS_OTHER => Contact::STATUS_OTHER,
        ],
    ];

    /**
     * CSVContactImporter constructor.
     *
     * @param string $pathWithFile
     */
    public function __construct(string $pathWithFile)
    {
        $this->file = $pathWithFile;
        $this->user = auth()->user();
        $this->mapper = array_flip($this->mapper);
    }

    /**
     * Create or update contacts from CSV file.
     *
     * @return bool
     */
    public function import(): bool
    {
        try {
            $csv = Reader::createFromPath(storage_path('app/' . $this->file), 'r');
            $csv->setHeaderOffset(0);
            $header = $csv->getHeader();
            $this->setHeader($header);

            $lines = $csv->getRecords();
            foreach ($lines as $row => $line) {
                $data = [];
                $data['source'] = 'csv';
                $data['first_name'] = $this->get('first_name', $line);
                $data['last_name'] = $this->get('last_name', $line);
                $data['email'] = $this->get('email', $line);
                $data['phone'] = $this->get('phone', $line);
                $data['status'] = $this->matchStatus(strtolower($this->get('status', $line)));

                $is_company = $this->isCompany(strtolower($this->get('type', $line)));
                $this->handleCompanyData($data, $line, $is_company);
                $data['is_company'] = $is_company;

                if ($is_company) {
                    $contact = $this->user->contacts()->updateOrCreate(
                        [
                            'name' => $data['name'],
                            'is_company' => true,
                        ],
                        $data
                    );
                } else {
                    /** @var Contact $contact */
                    $contact = $this->user->contacts()->updateOrCreate(
                        [
                            'first_name' => $data['first_name'],
                            'last_name' => $data['last_name'],
                            'is_company' => false,
                        ],
                        $data
                    );
                }

                $this->attachAddress($contact, $line);
            }
        } catch (Exception $e) {
            log_error($e);
            return false;
        }

        return true;
    }

    /**
     * Finds company and mark is_company as true if exists.
     *
     * @param array $contactData
     * @param array $csvLine
     * @param bool  $isCompany
     *
     * @return void
     */
    private function handleCompanyData(
        array &$contactData,
        array $csvLine,
        bool $isCompany
    ): void {
        $companyName = $this->get('company_name', $csvLine);
        if (empty($companyName)) {
            return;
        }

        if ($isCompany) {
            $contactData['name'] = $companyName;
            return;
        }
        $company = $this->user->contacts()->firstOrCreate([
            'name' => $companyName,
            'is_company' => true,
        ]);
        $contactData['company_id'] = $company->id;
    }

    /**
     * Maps Japanese status to corresponding English status.
     * Sets default value if status is not found.
     *
     * @param string|null $status
     *
     * @return string|null
     */
    private function matchStatus(?string $status): string
    {
        if (array_key_exists($status, $this->statusesMapping['japanese'])) {
            return $this->statusesMapping['japanese'][$status];
        }

        // Have this check because we don't know the language of the file.
        if (array_key_exists($status, $this->statusesMapping['english'])) {
            return $this->statusesMapping['english'][$status];
        }

        logger()->error('Client status does not match!', ['status' => (string) $status]);
        return Contact::STATUS_NEW; // Default status.
    }

    /**
     * Validate type.
     *
     * @param string|null $type
     *
     * @return bool
     */
    private function isCompany(?string $type): bool
    {
        if (in_array($type, [Contact::TYPE_COMPANY_EN, Contact::TYPE_COMPANY_JP], true)) {
            return true;
        }

        if (in_array($type, [Contact::TYPE_CONTACT_EN, Contact::TYPE_CONTACT_JP], true)) {
            return false;
        }

        logger()->error('Client status does not match!', ['type' => (string) $type]);
        return false; // if type is unknown - set it to contact.
    }

    /**
     * Create Address for the new contact.
     *
     * @param Contact $contact
     * @param array   $line
     *
     * @return void
     */
    private function attachAddress(Contact $contact, array &$line): void
    {
        $address['country'] = 'JP';
        $address['city'] = $this->get('city', $line);
        $address['street'] = $this->get('street', $line);
        $address['postcode'] = $this->get('postcode', $line);
        $address['state'] = $this->get('state', $line);

        $contact->addresses()->updateOrCreate(['model_id' => $contact->id], $address);
    }

    /**
     * Set CSV header for mapper to search values in.
     *
     * @param array $header
     *
     * @return void
     */
    private function setHeader(array $header)
    {
        $this->header = $header;
    }

    /**
     * Get contact's data by key(column name).
     *
     * @param string $key
     * @param array  $line
     *
     * @return string|null
     */
    private function get(string $key, array &$line): ?string
    {
        $key = array_get($this->header, $this->mapper[$key]);
        return trim(array_get($line, $key));
    }
}
