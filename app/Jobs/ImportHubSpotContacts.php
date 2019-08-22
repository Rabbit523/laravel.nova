<?php

namespace App\Jobs;

use App\Integration;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ImportHubSpotContacts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The integration instance.
     *
     * @var \App\Integration
     */
    protected $integration;

    /**
     * Create a new job instance.
     *
     * @param Integration $integration
     */
    public function __construct(Integration $integration)
    {
        $this->integration = $integration;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        logger()->info('Started the process of importing HubSpot contacts for integration: ' . $this->integration->id);
        $this->integration->importHubSpotContacts();
        logger()->info('Finished importing of HubSpot contacts for integration: ' . $this->integration->id);
    }
}
