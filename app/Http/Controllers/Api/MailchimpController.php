<?php

namespace App\Http\Controllers\Api;

use App\MailchimpList;
use App\Http\Controllers\ApiController;
use DrewM\MailChimp\MailChimp;
use Illuminate\Http\Request;

class MailchimpController extends ApiController
{
    /**
     * Get mailchimp lists.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLists(Request $request)
    {
        $integration = user()
            ->integrations()
            ->where('service', 'mailchimp')
            ->first();

        if (!$integration) {
            return $this->respondError('You need to enable Mailchimp integration first');
        }

        try {
            $mailchimp = new MailChimp($integration->remote_id);
            $lists = [];
            $limit = 100;
            $offset = 0;
            do {
                $listsRequest = $mailchimp->get("lists?count=$limit&offset=$offset");
                $total = array_get($listsRequest, 'total_items');
                $lists = array_merge($lists, array_get($listsRequest, 'lists'));
                $offset += $limit;
            } while (count($lists) < $total);

            foreach ($lists as $list) {
                $data[] = [
                    'id' => $list['id'],
                    'name' => $list['name']
                ];
            }

            return $this->respond(['items' => $data]);
        } catch (\Exception $e) {
            return $this->respondError('Mailchimp api key is invalid');
        }
    }

    /**
     * Get mailchimp templates.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTemplates(Request $request)
    {
        $integration = user()
            ->integrations()
            ->where('service', 'mailchimp')
            ->first();

        if (!$integration) {
            return $this->respondError('You need to enable Mailchimp integration first');
        }

        try {
            $mailchimp = new MailChimp($integration->remote_id);
            $templates = [];
            $limit = 100;
            $offset = 0;
            do {
                $templatesRequest = $mailchimp->get("templates?count=$limit&offset=$offset");
                $total = array_get($templatesRequest, 'total_items');
                $templates = array_merge(
                    $templates,
                    array_get($templatesRequest, 'templates')
                );
                $offset += $limit;
            } while (count($templates) < $total);

            foreach ($templates as $template) {
                $data[] = [
                    'id' => $template['id'],
                    'name' => $template['name']
                ];
            }

            return $this->respond(['items' => $data]);
        } catch (\Exception $e) {
            return $this->respondError('Mailchimp api key is invalid');
        }
    }

    /**
     * Get mailchimp campaigns.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCampaigns(Request $request)
    {
        $integration = user()
            ->integrations()
            ->where('service', 'mailchimp')
            ->first();

        if (!$integration) {
            return $this->respondError('You need to enable Mailchimp integration first');
        }

        try {
            $mailchimp = new MailChimp($integration->remote_id);
            $campaigns = [];
            $limit = 100;
            $offset = 0;
            do {
                $campaignsRequest = $mailchimp->get("campaigns?count=$limit&offset=$offset");
                $total = array_get($campaignsRequest, 'total_items');
                $campaigns = array_merge(
                    $campaigns,
                    array_get($campaignsRequest, 'campaigns')
                );
                $offset += $limit;
            } while (count($campaigns) < $total);

            foreach ($campaigns as $campaign) {
                $data[] = [
                    'id' => $campaign['id'],
                    'title' => array_get($campaign, 'settings.title'),
                    'list_id' => array_get($campaign, 'recipients.list_id'),
                    'subject_line' => array_get($campaign, 'settings.subject_line'),
                    'reply_to' => array_get($campaign, 'settings.reply_to'),
                    'from_name' => array_get($campaign, 'settings.from_name'),
                    'template_id' => array_get($campaign, 'settings.template_id'),
                    'status' => array_get($campaign, 'status')
                ];
            }

            return $this->respond(['items' => $data]);
        } catch (\Exception $e) {
            return $this->respondError('Mailchimp api key is invalid');
        }
    }

    public function createCampaign(Request $request)
    {
        $integration = user()
            ->integrations()
            ->where('service', 'mailchimp')
            ->first();

        if (!$integration) {
            return $this->respondError('You need to enable Mailchimp integration first');
        }

        try {
            $mailchimp = new MailChimp($integration->remote_id);
            $data = [
                'recipients' => [
                    'list_id' => $request->get('list_id')
                ],
                'type' => 'regular',
                'content_type' => 'template',
                'settings' => [
                    'title' => $request->get('title'),
                    'subject_line' => $request->get('subject_line'),
                    'reply_to' => $request->get('reply_to'),
                    'from_name' => $request->get('from_name'),
                    'template_id' => $request->get('template_id')
                ]
            ];
            $result = $mailchimp->post('campaigns', $data);

            if ($error = array_get($result, 'errors.0.message')) {
                return $this->respondError($error);
            }

            return $this->respondSuccess();
        } catch (\Exception $e) {
            return $this->respondError('Mailchimp api key is invalid');
        }
    }

    public function updateCampaign(Request $request)
    {
        $integration = user()
            ->integrations()
            ->where('service', 'mailchimp')
            ->first();

        if (!$integration) {
            return $this->respondError('You need to enable Mailchimp integration first');
        }

        try {
            $mailchimp = new MailChimp($integration->remote_id);
            $campaignId = $request->route('campaign_id');
            $data = [
                'recipients' => [
                    'list_id' => $request->get('list_id')
                ],
                'type' => 'regular',
                'content_type' => 'template',
                'settings' => [
                    'title' => $request->get('title'),
                    'subject_line' => $request->get('subject_line'),
                    'reply_to' => $request->get('reply_to'),
                    'from_name' => $request->get('from_name'),
                    'template_id' => $request->get('template_id')
                ]
            ];
            $result = $mailchimp->patch("campaigns/$campaignId", $data);

            if ($error = array_get($result, 'errors.0.message')) {
                return $this->respondError($error);
            }

            return $this->respondSuccess();
        } catch (\Exception $e) {
            return $this->respondError('Mailchimp api key is invalid');
        }
    }

    public function deleteCampaign(Request $request)
    {
        $integration = user()
            ->integrations()
            ->where('service', 'mailchimp')
            ->first();

        if (!$integration) {
            return $this->respondError('You need to enable Mailchimp integration first');
        }

        try {
            $mailchimp = new MailChimp($integration->remote_id);
            $campaignId = $request->route('campaign_id');
            $result = $mailchimp->delete("campaigns/$campaignId");

            if ($error = array_get($result, 'errors.0.message')) {
                return $this->respondError($error);
            }

            return $this->respondSuccess();
        } catch (\Exception $e) {
            return $this->respondError('Mailchimp api key is invalid');
        }
    }

    public function sendCampaign(Request $request)
    {
        $integration = user()
            ->integrations()
            ->where('service', 'mailchimp')
            ->first();

        if (!$integration) {
            return $this->respondError('You need to enable Mailchimp integration first');
        }

        try {
            $mailchimp = new MailChimp($integration->remote_id);
            $campaignId = $request->route('campaign_id');
            $result = $mailchimp->post("campaigns/$campaignId/actions/send");

            if ($error = array_get($result, 'detail')) {
                return $this->respondError($error);
            }

            return $this->respondSuccess();
        } catch (\Exception $e) {
            return $this->respondError('Mailchimp api key is invalid');
        }
    }
}
