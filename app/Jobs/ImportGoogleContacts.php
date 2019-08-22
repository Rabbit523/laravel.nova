<?php

namespace App\Jobs;

use App\Integration;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ImportGoogleContacts implements ShouldQueue
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
        logger()->info('Started the process of importing Google contacts for integration: ' . $this->integration->id);
        $this->integration->importGoogleContacts();
        logger()->info('Finished importing of Google contacts for integration: ' . $this->integration->id);
    }
}
