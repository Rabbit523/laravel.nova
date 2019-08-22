<?php

namespace App\Logging;

use Bramus\Monolog\Formatter\ColoredLineFormatter;

class CustomizeFormatter
{
    /**
     * Customize the given logger instance.
     *
     * @param  \Illuminate\Log\Logger  $logger
     * @return void
     */
    public function __invoke($logger)
    {
        $formatter = new ColoredLineFormatter(null, null, null, true, true);
        $formatter->includeStacktraces();
        $formatter->setStackLimit(5);
        foreach ($logger->getHandlers() as $handler) {
            $handler->setFormatter($formatter);
        }
    }
}
