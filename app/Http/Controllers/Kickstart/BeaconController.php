<?php

namespace App\Http\Controllers\Kickstart;

use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;

use App\Project;
use App\Contact;
use App\Customer;
use App\Http\Controllers\Controller;

class BeaconController extends Controller
{
    protected $guard = 'customers';

    public function __construct()
    {
        $accept_language = request()->server('HTTP_ACCEPT_LANGUAGE');
        if ($accept_language && substr($accept_language, 0, 2) == 'ja') {
            \App::setLocale('ja');
        }
        Auth::setDefaultDriver('customers');
    }

    protected function guard()
    {
        return Auth::guard('customers');
    }

    public function username()
    {
        return 'email';
    }

    private function getTemplateVars(Project $project)
    {
        $data = [];
        $data['title'] = $project->title;
        $data['subscribe_text'] = '選ぶ';
        $data['modal_lg'] = false;
        $data['primary_color'] = '#5755d9';
        $data['secondary_color'] = '#f1f1fc';
        $data['server_url'] = 'https://app.kinchaku.com';
        $data['plans'] = [];
        $data['project'] = $project;
        $data['stripe_pk'] = config('services.stripe.key');

        if ($project->beacon) {
            $data['subscribe_text'] = array_get(
                $project->beacon->settings,
                'subscribe_text',
                $data['subscribe_text']
            );
            $data['modal_lg'] = array_get($project->beacon->settings, 'modal_lg', false);
            $data['primary_color'] = array_get(
                $project->beacon->settings,
                'primary_color',
                $data['primary_color']
            );
            $data['secondary_color'] = array_get(
                $project->beacon->settings,
                'secondary_color',
                $data['secondary_color']
            );
            $beacon_plans = $project->beacon->plans;
            $data['plans'] = $project->plans->whereIn(
                'id',
                collect($project->beacon->plans)->flatten()
            );
        }

        switch (\App::environment()) {
            case 'local':
                $data['server_url'] = 'http://localhost:8000';
                break;
            case 'dev':
                $data['server_url'] = 'https://dev.kinchaku.com';
                break;
        }

        $data['page_title'] = 'Subscription';
        if (auth('customers')->id()) {
            $data['page_title'] = 'Profile';
        }
        return $data;
    }

    public function show(Request $request, Project $project)
    {
        $preview = array_get($request->route()->parameters(), 'preview', false);
        if (!$preview && (!$project->beacon || !$project->beacon->is_enabled)) {
            info('showBeacon', [$project->id, 'no beacon or not enabled']);
            // TODO: show not found?
            return ok();
        }
        $data = $this->getTemplateVars($project);
        $data['preview'] = $preview;

        if (auth('customers')->id()) {
            return view('kickstart.profile', $data);
        }
        return view('kickstart.index', $data);
    }
}
