<?php

namespace PHPCensor\Plugin;

use PHPCensor\Builder;
use PHPCensor\Model\Build;
use PHPCensor\Model\BuildError;
use PHPCensor\Model\BuildMeta;
use PHPCensor\Plugin;

/**
 * Behat BDD Plugin
 *
 * @author Dan Cryer <dan@block8.co.uk>
 */
class Behat extends Plugin
{
    /**
     * @var string
     */
    protected $features;

    /**
     * @return string
     */
    public static function pluginName()
    {
        return 'behat';
    }

    /**
     * {@inheritdoc}
     */
    public function __construct(Builder $builder, Build $build, array $options = [])
    {
        parent::__construct($builder, $build, $options);

        $this->executable = $this->findBinary(['behat', 'behat.phar']);

        $this->features = '';

        if (!empty($options['features'])) {
            $this->features = $options['features'];
        }
    }

    /**
     * Runs Behat tests.
     */
    public function execute()
    {
        if (!$this->executable) {
            $this->builder->logFailure(sprintf('Could not find %s', 'behat'));

            return false;
        }

        $success = $this->builder->executeCommand($this->executable . ' %s', $this->features);

        list($errorCount, $data) = $this->parseBehatOutput();

        $this->build->storeMeta(self::pluginName(), BuildMeta::KEY_WARNINGS, $errorCount);
        $this->build->storeMeta(self::pluginName(), BuildMeta::KEY_DATA, $data);

        return $success;
    }

    /**
     * Parse the behat output and return details on failures
     *
     * @return array
     */
    public function parseBehatOutput()
    {
        $output = $this->builder->getLastOutput();

        $parts = explode('---', $output);

        if (count($parts) <= 1) {
            return [0, []];
        }

        $lines = explode(PHP_EOL, $parts[1]);

        $storeFailures = false;
        $data          = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ('Failed scenarios:' == $line) {
                $storeFailures = true;
                continue;
            }

            if (strpos($line, ':') === false) {
                $storeFailures = false;
            }

            if ($storeFailures) {
                $lineParts = explode(':', $line);
                $data[]    = [
                    'file' => $lineParts[0],
                    'line' => $lineParts[1],
                ];

                $this->build->reportError(
                    $this->builder,
                    self::pluginName(),
                    'Behat scenario failed.',
                    BuildError::SEVERITY_HIGH,
                    $lineParts[0],
                    $lineParts[1]
                );
            }
        }

        $errorCount = count($data);

        return [$errorCount, $data];
    }
}
