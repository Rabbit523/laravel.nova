<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ExportContact implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $data;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $data = $this->data;
        $payload = [
            'email' => $data['email'],
            'name' => $data['name'],
            'language' => $data['language'],
            'accepts_marketing' => true
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, config('app.url') . "/api/webhook/incoming");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            json_encode([
                "key" => config('services.kinchaku.key'),
                "event" => 'add_customer',
                "payload" => $payload
            ])
        );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);

        // TODO: save request and it's result
        $result = curl_exec($ch);
        logger()->debug($result);
        curl_close($ch);
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
        return ['webhook', 'export', $this->data['email']];
    }
}
