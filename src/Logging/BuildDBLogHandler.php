<?php

namespace PHPCensor\Logging;

use PHPCensor\Store\Factory;
use Monolog\Handler\AbstractProcessingHandler;
use PHPCensor\Model\Build;
use Psr\Log\LogLevel;

/**
 * Class BuildDBLogHandler writes the build log to the database.
 */
class BuildDBLogHandler extends AbstractProcessingHandler
{
    /**
     * @var Build
     */
    protected $build;

    protected $logValue;

    /**
     * @var int last flush timestamp
     */
    protected $flushTimestamp = 0;

    /**
     * @var int flush delay, seconds
     */
    protected $flushDelay = 1;

    /**
     * @param Build $build
     * @param bool $level
     * @param bool $bubble
     */
    public function __construct(
        Build $build,
        $level = LogLevel::INFO,
        $bubble = true
    ) {
        parent::__construct($level, $bubble);
        $this->build = $build;
        // We want to add to any existing saved log information.
        $this->logValue = $build->getLog();
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->flushData();
    }

    /**
     * Flush buffered data
     */
    protected function flushData()
    {
        $this->build->setLog($this->logValue);
        Factory::getStore('Build')->save($this->build);
        $this->flushTimestamp = time();
    }

    /**
     * Write a log entry to the build log.
     * @param array $record
     */
    protected function write(array $record)
    {
        $message = (string)$record['message'];
        $message = str_replace(['\/', '//'], '/', $message);
        //$message = str_replace($this->build->getBuildPath(), '<BUILD_PATH>/', $message);
        $message = str_replace(ROOT_DIR, '<PHP_CENSOR_PATH>/', $message);

        $this->logValue .= $message . PHP_EOL;

        if ($this->flushTimestamp < (time() - $this->flushDelay)) {
            $this->flushData();
        }
    }
}
