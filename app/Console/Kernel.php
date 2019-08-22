<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;

use App\Integration;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // update freee integrations
        // TODO: check if it's enabled may be?
        $schedule
            ->call(function () {
                $integrations = Integration::where('service', 'freee');
                $integrations->each(function ($integration) {
                    $integration->importFreee();
                });
            })
            ->hourly();
        // sync mailchimp contacts
        $schedule
            ->call(function () {
                $integrations = Integration::where('service', 'mailchimp');
                $integrations->each(function ($integration) {
                    $integration->importMailchimpContacts();
                });
            })
            ->hourly();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        Artisan::command('parse {id}', function ($id) {
            $ds = \App\DataSource::find($id);
            $this->info("Parsing {$ds->name}!");
            $ds->parse();
        });

        Artisan::command('wh {id}', function ($id) {
            $wh = \App\Webhook::find($id);
            $this->info("Parsing {$wh->type}!");
            $wh->parse();
        });

        Artisan::command('import {name}', function ($name) {
            $path = Storage::path('import/' . $name);
            $this->info("Importing industry categories data from $path");
            $csv = Reader::createFromPath($path, 'r');
            $csv->setHeaderOffset(0);
            $header = $csv->getHeader();
            $lines = $csv->getRecords();
            foreach ($lines as $line) {
                $code = $line['l_category'];
                $parent = false;

                if ($line['m_category']) {
                    $code .= $line['s_category'] ?: $line['m_category'];
                    $parent_code =
                        $line['l_category'] . (!$line['s_category']
                            ? ''
                            : ($line['industry_code']
                                ? $line['s_category']
                                : $line['m_category']));
                    $parent = \App\Industry::where('code', $parent_code)->first();
                }

                $data = [
                    'parent_id' => $parent ? $parent->id : 0,
                    'title' => $line['title'],
                    'code' =>
                    $line['industry_code']
                        ? $line['l_category'] . $line['industry_code']
                        : $code,
                ];

                \App\Industry::create($data);
            }
        });

        Artisan::command('export', function () {
            if (!config('services.kinchaku.key')) {
                $this->error("api key not set");
                return;
            }
            $integration = \App\Integration::where(
                'remote_id',
                config('services.kinchaku.key')
            )
                ->where('service', 'api')
                ->first();
            if (!$integration) {
                $this->error('Unknown API key');
                return;
            }
            $users = \App\User::all();
            foreach ($users as $user) {
                if ($user->email == $integration->user->email) {
                    continue;
                }

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, config('app.url') . "/api/webhook/incoming");
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
                curl_setopt(
                    $ch,
                    CURLOPT_POSTFIELDS,
                    json_encode([
                        "key" => config('services.kinchaku.key'),
                        "event" => 'add_customer',
                        "payload" => $user->toArray(),
                    ])
                );
                curl_setopt($ch, CURLOPT_POST, true);

                // TODO: save request and it's result
                $result = curl_exec($ch);
                $this->info($result);
                curl_close($ch);
            }
        });

        Artisan::command('planstats', function () {
            $subscriptions = \App\CustomerSubscription::where('name', '!=', 'default')->get();
            $plans = \App\Plan::all();
            $subscriptions->each(function ($s) use ($plans) {
                $plan = $plans->firstWhere('payment_id', $s->stripe_plan);
                $data = [
                    'customer_id' => $s->user_id,
                    'plan_id' => $plan->id,
                    'created_at' => $s->created_at,
                    'ends_at' => $s->ends_at,
                ];
                \App\CustomerPlan::firstOrCreate($data);
            });
        });

        require base_path('routes/console.php');
    }
}
