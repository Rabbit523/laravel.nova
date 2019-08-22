<?php
namespace App\Logging;

use Monolog\Handler\SlackWebhookHandler;

class SlackLogger
{
    /**
     * Customize the given logger instance.
     *
     * @param  \Illuminate\Log\Logger  $logger
     * @return void
     */
    public function __invoke($logger)
    {
        foreach ($logger->getHandlers() as $handler) {
            if ($handler instanceof SlackWebhookHandler) {
                $handler->pushProcessor(function ($record) {
                    $record["extra"]["endpoint"] = request()->fullUrl();
                    $record["extra"]["user"] = ($user = user())
                        ? $user->name
                        : 'anonymous';
                    if (!array_has($record["context"], "exception")) {
                        return $record;
                    }
                    $e = $record["context"]["exception"];
                    unset($record["context"]["exception"]);
                    $record["extra"]["file"] = $e->getFile() . ':' . $e->getLine();
                    return $record;
                });
            }
        }
    }
}
