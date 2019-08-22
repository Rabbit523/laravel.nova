<?php
namespace App\Services\Freee;

use App\Integration;
use Curl\Curl;
use Socialite;

class FreeeContactsService
{
    protected $integration;

    /**
     * FreeeContactService constructor
     */
    public function __construct()
    {
    }

    /**
     * @param Integration $integration
     *
     * @return void
     */
    public function fetch(Integration $integration): void
    {
        $this->integration = $integration;
        $this->refreshToken();
        $this->checkToken();
        $this->importFreee();
    }

    private function refreshToken(): void
    {
        $details = $this->integration->details;
        $token = array_get($details, 'token');
        $expires_at = array_get($details, 'expiresAt', 0);
        logger()->debug('before refresh', [date("Y-m-d H:i:s", $expires_at)]);

        if (time() > $expires_at) {
            $data = Socialite::driver('freee')
                ->stateless()
                ->refreshToken(array_get($details, 'refreshToken'));
            // TODO: handle refresh token errors, disable integration?
            $details['token'] = $data['access_token'];
            $details['refreshToken'] = $data['refresh_token'];
            $details['expiresAt'] = $data['expires_in'] + time() - 120;
            unset($details['expiresIn']);
            $details['accessTokenResponseBody'] = $data;
            $this->integration->details = $details;
            $this->integration->save();
        }
    }

    public function checkToken(): bool
    {
        $token = $this->getToken();
        if (!empty($token)) {
            return true;
        }
        logger()->debug('freee import error', ['no auth token']);
        throw new Exception('no auth token');
    }

    private function getToken()
    {
        return array_get($this->integration->details, 'token');
    }

    private function setLastStatus($status, $message = false)
    {
        $this->integration->setLastStatus($status, $message);
    }

    private function curlGET($url, $params)
    {
        $token = $this->getToken();
        $curl = new Curl();
        $curl->setDefaultJsonDecoder($assoc = true);
        $curl->setHeader('Accept', 'application/json');
        $curl->setHeader('Authorization', 'Bearer ' . $token);
        $curl->get($url, $params);
        return $curl;
    }

    public function importFreee()
    {
        $details = $this->integration->details;

        $company_id = array_get($details, 'user.companies.0.id');
        if (!$company_id) {
            logger()->debug('freee integration', ['no company id']);
            throw new Exception('no company id in Freee API response');
        }

        // Get Freee Balance
        $url = 'https://api.freee.co.jp/api/1/walletables';
        $params = [
            'company_id' => $company_id,
            'with_balance' => 'true',
        ];
        $curl = $this->curlGET($url, $params);

        if ($curl->error) {
            logger()->debug('freee get balance error', [
                $curl->errorMessage,
                $curl->rawResponse,
            ]);
            $details['balance'] = [];
            $details['balance_status'] = $curl->errorMessage;
        } else {
            $response = $curl->response;
            if (array_has($response, 'status_code') && array_has($response, 'errors')) {
                logger()->debug('freee get balance error', [array_get($response, 'errors')]);
                $details['balance'] = [];
                $details['balance_status'] = array_get($response, 'errors');
            } else {
                $details['balance'] = array_get($response, 'walletables');
            }
        }
        $this->integration->save();

        // Get Freee contacts
        $url = 'https://api.freee.co.jp/api/1/partners';
        $params = ['company_id' => $company_id];

        $curl = $this->curlGET($url, $params);
        if ($curl->error) {
            logger()->debug('freee get partners error', [
                $curl->errorMessage,
                $curl->rawResponse,
            ]);
            $details['partners'] = [];
            $details['partners_status'] = $curl->errorMessage;
        } else {
            $response = $curl->response;
            if (array_has($response, 'status_code') && array_has($response, 'errors')) {
                logger()->debug('freee get partners error', [array_get($response, 'errors')]);
                $details['partners'] = [];
                $details['partners_status'] = array_get($response, 'errors');
            } else {
                $details['partners'] = array_get($response, 'partners');
                array_map([$this, 'saveFreeePartner'], $details['partners']);
            }
        }
        $this->integration->details = $details;
        $this->integration->last_updated_at = now();
        $this->integration->last_status = 'success';
        $this->integration->save();
        return true;
    }

    private function saveFreeePartner($p)
    {
        logger()->debug('saveFreeePartner', $p);
        $query = $this->integration->user->contacts();
        $data = [];
        $company_contact = false;
        $data['is_company'] = !array_has($p, 'parent_company_id');
        $data['company_id'] = array_get($p, 'parent_company_id');
        $data['email'] = array_get($p, 'email');
        $data['meta'] = [
            'freee_partner_id' => $p['id'],
        ];
        $data['name'] = array_get($p, 'long_name') ?: array_get($p, 'name');
        $data['name_katakana'] = array_get($p, 'name_kana');
        $data['phone'] = array_get($p, 'phone');
        $data['status'] = 'new';
        $data['source'] = 'freee';

        // shortcut1 and shortcut2 used as last and first name
        if (!empty($p['shortcut1']) && !empty($p['shortcut2'])) {
            $data['first_name'] = array_get($p, 'shortcut2');
            $data['last_name'] = array_get($p, 'shortcut1');
            $data['name'] = null;
            $data['is_company'] = false;
        }

        $address = array_get($p, 'address_attributes');

        // if has contact_name, create another contact
        if (array_has($p, 'contact_name') && !empty($p['contact_name'])) {
            $data['is_company'] = true;
            $company_contact = [
                'name' => array_get($p, 'contact_name'),
                'email' => array_get($p, 'email'),
                'id' => array_get($p, 'id'),
            ];
            unset($data['email']);
            logger()->debug('has_contact!', $company_contact);
        }
        if (!empty($data['email'])) {
            $query = $query->where('email', $data['email']);
        }
        if ($data['is_company']) {
            $query = $query->whereIsCompany(true);
        } else {
            $query = $query->whereIsCompany(false);
        }
        $contact = $query->where('meta->freee_partner_id', $p['id'])->first();
        if (!$contact) {
            if (!empty($data['name'])) {
                $query = $query->where('name', $data['name']);
            } else {
                $query = false;
            }
            $contact = $query ? $query->first() : false;
        }

        if ($contact) {
            $changed = false;
            if (!array_has($contact->meta, 'freee_partner_id')) {
                logger()->debug('no partner id', $contact->meta);
                $changed = true;
                $contact->meta = array_merge($contact->meta, [
                    'freee_partner_id' => $p['id'],
                    'address' => array_get($p, 'address_attributes'),
                ]);
            }
            if ($contact->name != $data['name']) {
                logger()->debug('different name', [$contact->name, $data['name']]);
                $changed = true;
                $contact->name = $data['name'];
            }
            if ($contact->phone != $data['phone']) {
                logger()->debug('different phone', [$contact->phone, $data['phone']]);
                $changed = true;
                $contact->phone = $data['phone'];
            }
            if (!empty($data['email']) && $contact->email != $data['email']) {
                logger()->debug('different email', [$contact->email, $data['email']]);
                $changed = true;
                $contact->email = $data['email'];
            }

            if (!$contact->hasAddress('freee') && !empty($address)) {
                $changed = true;
                $contact->addresses()->create([
                    'role' => 'freee',
                    'country' => 'JP',
                    'postcode' => array_get($address, 'zipcode'),
                    'street' => array_get($address, 'street_name1'),
                    'other' => array_get($address, 'street_name2'),
                ]);
            }
            if ($changed) {
                $result = $contact->save();
                logger()->debug("changed", [$result]);
                // $this->updated++;
            }
        } else {
            $contact = $this->integration->user->contacts()->create($data);
            if (!empty($address)) {
                $contact->addresses()->create([
                    'role' => 'freee',
                    'country' => 'JP',
                    'postcode' => array_get($address, 'zipcode'),
                    'street' => array_get($address, 'street_name1'),
                    'other' => array_get($address, 'street_name2'),
                ]);
            }
            // $this->added++;
        }
        if ($company_contact) {
            $company_contact['parent_company_id'] = $contact->id;
            $this->saveFreeePartner($company_contact);
        }
    }
}
