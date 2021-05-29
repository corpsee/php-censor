<?php

namespace PHPCensor\Plugin;

use Exception;
use PHPCensor\Builder;
use PHPCensor\Common\Exception\RuntimeException;
use PHPCensor\Model\Build;
use PHPCensor\Model\BuildError;
use PHPCensor\Plugin;

/**
 * Launch Phan.
 */
class Phan extends Plugin
{
    /**
     * @var string Location on the server where the files are stored. Preferably in the webroot for inclusion
     *             in the readme.md of the repository
     */
    protected $location;

    /**
     * @var int
     */
    protected $allowedWarnings;

    /**
     * @return string
     */
    public static function pluginName()
    {
        return 'phan';
    }

    /**
     * {@inheritdoc}
     *
     * @param Builder $builder
     * @param Build   $build
     * @param array   $options
     */
    public function __construct(Builder $builder, Build $build, array $options = [])
    {
        parent::__construct($builder, $build, $options);

        $this->location        = $this->builder->buildPath .'phan_tmp';
        $this->allowedWarnings = isset($options['allowed_warnings']) ? $options['allowed_warnings'] : 0;
    }

    /**
     * Executes Phan.
     *
     * @return bool
     */
    public function execute()
    {
        if (!file_exists($this->location)) {
            mkdir($this->location, (0777 & ~umask()), true);
        }

        if (!is_writable($this->location)) {
            throw new RuntimeException(sprintf('The location %s is not writable or does not exist.', $this->location));
        }

        // Find PHP files in a file
        $cmd = 'find -L %s -type f -name "**.php"';

        foreach ($this->ignore as $ignore) {
            $cmd .= ' | grep -v '. $ignore;
        }

        $cmd .= ' > %s';

        $this->builder->executeCommand($cmd, $this->directory, $this->location . '/phan.in');

        $phan = $this->findBinary(['phan', 'phan.phar']);

        // Launch Phan on PHP files with json output
        $cmd = $phan.' -f %s -i -m json -o %s';

        $this->builder->executeCommand($cmd, $this->location . '/phan.in', $this->location . '/phan.out');

        $warningCount = $this->processReport(file_get_contents($this->location . '/phan.out'));

        $this->build->storeMeta((self::pluginName() . '-warnings'), $warningCount);

        $success = true;

        if ($this->allowedWarnings !== -1 && $warningCount > $this->allowedWarnings) {
            $success = false;
        }

        return $success;
    }

    /**
     * Process the Phan Json report.
     *
     * @param string $jsonString
     *
     * @return int
     *
     * @throws Exception
     */
    protected function processReport($jsonString)
    {
        $json = json_decode($jsonString, true);

        if ($json === false || !is_array($json)) {
            $this->builder->log($jsonString);
            throw new RuntimeException('Could not process the report generated by Phan.');
        }

        $warnings = 0;

        foreach ($json as $data) {
            $this->build->reportError(
                $this->builder,
                self::pluginName(),
                $data['check_name']."\n\n".$data['description'],
                $this->severity($data['severity']),
                isset($data['location']['path']) ? $data['location']['path'] : '??',
                isset($data['location']['lines']['begin']) ? $data['location']['lines']['begin'] : '??',
                isset($data['location']['lines']['end']) ? $data['location']['lines']['end'] : '??'
            );

            $warnings++;
        }

        return $warnings;
    }

    /**
     * Transform severity from Phan to PHP-Censor.
     *
     * @param  int $severity
     *
     * @return int
     */
    protected function severity($severity)
    {
        if ($severity == 10) {
            return BuildError::SEVERITY_CRITICAL;
        }

        if ($severity == 5) {
            return BuildError::SEVERITY_NORMAL;
        }

        return BuildError::SEVERITY_LOW;
    }
}
