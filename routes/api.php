<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['namespace' => 'Api'], function () {
    Route::get('/', 'StatusController@index');

    Route::post('users', 'AuthController@register');
    Route::post('users/login', 'AuthController@login');
    Route::post('users/logout', 'AuthController@logout');
    Route::get('auth/{service}/redirect', 'AuthController@redirect');
    Route::post('auth/{service}/callback', 'AuthController@callback');
    Route::post('auth/password/email', 'AuthController@sendResetLinkEmail');
    Route::post('auth/password/reset', 'AuthController@reset');

    Route::get('records/categories', 'RecordController@categories');
    Route::get('records/{project}/{type}/{name}/download', 'RecordController@download');

    Route::post('webhook/stripe/{key?}', 'StripeWebhookController@handleWebhook');
    Route::post('webhook/incoming', 'WebhookController@handleWebhook');
    Route::post('webhook/mailchimp', 'UserController@mailchimpWebhook');
    Route::get('webhook/mailchimp', 'UserController@mailchimpWebhook');

    Route::get('contactimages/{contact}', 'ContactImageController@show');
    Route::get('user/{user}/branding/{image_type}', 'UserController@showBrandingImage');

    Route::group(['middleware' => 'auth:api'], function () {
        Route::post('users/logout', 'AuthController@logout');

        Route::get('user', 'UserController@index');
        Route::delete('user', 'UserController@destroy');
        Route::post('switch', 'UserController@switch');
        Route::get('industries', 'IndustryController@index');
        Route::get('notifications', 'NotificationController@index');
        Route::put('notifications/read', 'NotificationController@markAsRead');
        Route::get('user/subscription', 'UserController@subscription');
        Route::post('user/trial', 'UserController@startTrial');
        Route::post('user/cards', 'UserController@addCard');

        Route::match(['put', 'patch'], 'user', 'UserController@update');
        Route::post('user/settings', 'UserController@updateSettings');
        Route::get('user/company', 'UserController@getCompany');
        Route::put('user/company', 'UserController@updateCompany');

        Route::get('projects/team', 'ProjectController@team');
        Route::get('teams/joined', 'TeamController@joined');

        Route::get('subscriptions/plans', 'SubscriptionController@plans');
        Route::get('subscriptions/invoices', 'SubscriptionController@invoices');
        Route::get('subscriptions', 'SubscriptionController@index');

        Route::get('invoices', 'InvoiceController@index');
        Route::get('invoices/{id}', 'InvoiceController@show');

        Route::apiResources([
            'projects' => 'ProjectController',
            'teams' => 'TeamController',
            'contacts' => 'ContactController',
            'products' => 'ProductController',
            'plans' => 'PlanController',
            'beacons' => 'BeaconController',
            'coupons' => 'CouponController',
        ]);

        Route::post('projects/{project}/model', 'ProjectController@modelCreate');
        Route::patch('projects/{project}/model', 'ProjectController@modelUpdate');

        Route::get('projects/{project}/beacon', 'ProjectController@getBeacon');
        Route::get('metrics/{project}', 'MetricController@index');
        Route::get('metrics/{project}/{metric}', 'MetricController@show');

        Route::get('projects/{project}/subscription', 'ProjectController@getSubscription');
        Route::delete('projects/{project}/cancel', 'ProjectController@cancelSubscription');
        Route::patch('projects/{project}/resume', 'ProjectController@resumeSubscription');

        Route::get('projects/{project}/transactions', 'TransactionController@index');
        Route::get(
            'projects/{project}/transactions/{transaction}',
            'TransactionController@show'
        );
        Route::post(
            'projects/{project}/transactions/{transaction}/refund',
            'TransactionController@refund'
        );

        Route::patch('teams/{team}/projects', 'TeamController@addProject');
        Route::patch('teams/{team}/members', 'TeamController@addMember');
        Route::delete('teams/{team}/projects/{project}', 'TeamController@removeProject');
        Route::delete('teams/{team}/members/{user}', 'TeamController@removeMember');

        Route::get('records/{project}/{type}/{date?}', 'RecordController@index');
        Route::post('records/{project}/{budget}/{type}/export', 'RecordController@export');
        Route::post('records/{project}', 'RecordController@store');
        Route::put('records/{project}/{record}', 'RecordController@update');
        Route::delete('records/{project}/{record}', 'RecordController@destroy');
        Route::put('records/{project}/{record}/monthly', 'RecordController@storeMonthly');
        Route::put('records/{project}/{record}/daily', 'RecordController@storeDaily');
        Route::post('records/{project}/{type}/upload', 'RecordController@upload');

        Route::get('contacts/trends/{type}', 'ContactController@trends');
        Route::get('contacts/customers/list', 'ContactController@customers');
        Route::put('contacts/bulk/delete', 'ContactController@bulkDestroy');
        Route::get('contacts/tags/list', 'ContactTagController@tags');
        Route::put('contacts/tags/add', 'ContactTagController@bulkCreate');
        Route::put('contacts/tags/delete', 'ContactTagController@bulkDestroy');
        Route::post('contacts/import', 'ContactController@upload');

        Route::get(
            '/integrations/google/import',
            'IntegrationController@importGoogleContacts'
        );
        Route::get('/integrations/hubspot/import', 'IntegrationController@importHubSpotContacts');

        Route::get('integrations', 'IntegrationController@index');
        Route::get('integrations/{service}/oauth', 'IntegrationController@oauthRedirect');
        Route::post('integrations/{service}/oauth', 'IntegrationController@oauthCallback');
        Route::post('integrations/api', 'IntegrationController@generateApikey');
        Route::post('integrations/stripe', 'IntegrationController@saveStripe');
        Route::post('integrations/webhook', 'IntegrationController@createWebhook');
        Route::patch('integrations/webhook', 'IntegrationController@updateWebhook');
        Route::put('integrations/webhook/disable', 'IntegrationController@disableWebhook');
        Route::put('integrations/webhook/test', 'IntegrationController@testWebhook');
        Route::post('integrations/mailchimp', 'IntegrationController@createMailchimp');
        Route::patch('integrations/mailchimp', 'IntegrationController@updateMailchimp');
        Route::post('integrations/mailchimp/import', 'IntegrationController@importMailchimp');
        Route::post('integrations/freee/update', 'IntegrationController@updateFreee');
        Route::delete('integrations/{integration}', 'IntegrationController@delete');

        Route::get('mailchimp/lists', 'MailchimpController@getLists');
        Route::get('mailchimp/templates', 'MailchimpController@getTemplates');
        Route::get('mailchimp/campaigns', 'MailchimpController@getCampaigns');
        Route::post('mailchimp/campaigns', 'MailchimpController@createCampaign');
        Route::patch(
            'mailchimp/campaigns/{campaign_id}',
            'MailchimpController@updateCampaign'
        );
        Route::delete(
            'mailchimp/campaigns/{campaign_id}',
            'MailchimpController@deleteCampaign'
        );
        Route::post(
            'mailchimp/campaigns/{campaign_id}/send',
            'MailchimpController@sendCampaign'
        );

        Route::patch('products/{product}/projects', 'ProductController@addProject');
        Route::delete(
            'products/{product}/projects/{project}',
            'ProductController@removeProject'
        );
        Route::post('plans/{project}', 'PlanController@create');
        Route::post('products/{product}/plans', 'PlanController@store');
        Route::post('products/import', 'ProductController@upload');
        Route::post('plans/import', 'PlanController@upload');

        Route::get('addresses/{model}/{id}', 'AddressController@index');
        Route::post('addresses/{model}/{id}', 'AddressController@store');
        Route::put('addresses/{model}/{id}/{address}', 'AddressController@update');
        Route::delete('addresses/{model}/{id}/{address}', 'AddressController@delete');

        Route::get('stats/{project}/{type}', 'StatsController@index');

        Route::post('connect', 'ConnectController@store');
        Route::patch('connect', 'ConnectController@update');
        Route::put('connect/bank', 'ConnectController@updateBankDetails');
        Route::get('connect/verification', 'ConnectController@showVerification');
        Route::post('connect/verification', 'ConnectController@sendVerification');
        Route::get('connect/balance/current', 'ConnectController@currentBalance');
        Route::get('connect/balance', 'ConnectController@balanceHistory');

        Route::post('contactimages/{contact}', 'ContactImageController@store');
        Route::delete('contactimages/{contact}', 'ContactImageController@destroy');
    });
});
