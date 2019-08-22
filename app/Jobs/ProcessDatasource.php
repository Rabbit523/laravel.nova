<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

use App\DataSource;

class ProcessDatasource implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The datasource instance.
     *
     * @var \App\DataSource
     */
    protected $datasource;

    /**
     * Create a new job instance.
     *
     * @param  \App\DataSource  $datasource
     * @return void
     */
    public function __construct(DataSource $datasource)
    {
        $this->datasource = $datasource;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (!$this->datasource->parse()) {
            throw new \Exception(array_get($this->datasource->meta, 'message', 'no message'));
        }
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

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array
     */
    public function tags()
    {
        return [$this->datasource->type, $this->datasource->id];
    }
}
