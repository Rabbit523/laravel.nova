<?php

namespace App\Jobs;

use App\Integration;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ImportMailchimpContacts implements ShouldQueue
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
     * @return void
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
        $this->integration->importMailchimpContacts();
    }

    /**
     * The job failed to process.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function failed(\Exception $exception)
    {
        // Send user notification of failure, etc...
        // DB::rollBack();
    }
}
