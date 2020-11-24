<?php

namespace PHPCensor\Plugin;

use Exception;
use PHPCensor;
use PHPCensor\Builder;
use PHPCensor\Model\Build;
use PHPCensor\Model\BuildMeta;
use PHPCensor\Plugin;
use PHPCensor\ZeroConfigPluginInterface;

/**
 * PHP Mess Detector Plugin - Allows PHP Mess Detector testing.
 *
 * @author Dan Cryer <dan@block8.co.uk>
 */
class PhpMessDetector extends Plugin implements ZeroConfigPluginInterface
{
    /**
     * @var array
     */
    protected $suffixes;

    /**
     * Array of PHPMD rules. Can be one of the builtins (codesize, unusedcode, naming, design, controversial)
     * or a filename (detected by checking for a / in it), either absolute or relative to the project root.
     * @var array
     */
    protected $rules;
    protected $allowedWarnings;

    /**
     * @return string
     */
    public static function pluginName()
    {
        return 'php_mess_detector';
    }

    /**
     * {@inheritdoc}
     */
    public function __construct(Builder $builder, Build $build, array $options = [])
    {
        parent::__construct($builder, $build, $options);

        $this->suffixes        = ['php'];
        $this->rules           = ['codesize', 'unusedcode', 'naming'];
        $this->allowedWarnings = 0;

        if (isset($options['zero_config']) && $options['zero_config']) {
            $this->allowedWarnings = -1;
        }

        if (array_key_exists('allowed_warnings', $options)) {
            $this->allowedWarnings = (int)$options['allowed_warnings'];
        }

        $this->executable = $this->findBinary(['phpmd', 'phpmd.phar']);

        foreach (['rules', 'suffixes'] as $key) {
            $this->overrideSetting($options, $key);
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function canExecuteOnStage($stage, Build $build)
    {
        if (Build::STAGE_TEST === $stage) {
            return true;
        }

        return false;
    }

    /**
     * Runs PHP Mess Detector in a specified directory.
     */
    public function execute()
    {
        if (!$this->tryAndProcessRules()) {
            return false;
        }

        $phpmdBinaryPath = $this->executable;

        $this->executePhpMd($phpmdBinaryPath);

        $errorCount = $this->processReport(trim($this->builder->getLastOutput()));
        $this->build->storeMeta(self::pluginName(), BuildMeta::KEY_WARNINGS, $errorCount);

        return $this->wasLastExecSuccessful($errorCount);
    }

    /**
     * Override a default setting.
     * @param $options
     * @param $key
     */
    protected function overrideSetting($options, $key)
    {
        if (isset($options[$key]) && is_array($options[$key])) {
            $this->{$key} = $options[$key];
        }
    }

    /**
     * Process PHPMD's XML output report.
     *
     * @param $xmlString
     *
     * @return int
     *
     * @throws Exception
     */
    protected function processReport($xmlString)
    {
        $xml = simplexml_load_string($xmlString);

        if (false === $xml) {
            $this->builder->log($xmlString);
            throw new Exception('Could not process PHPMD report XML.');
        }

        $warnings = 0;

        foreach ($xml->file as $file) {
            $fileName = (string)$file['name'];
            $fileName = str_replace($this->builder->buildPath, '', $fileName);

            foreach ($file->violation as $violation) {
                $warnings++;

                $this->build->reportError(
                    $this->builder,
                    self::pluginName(),
                    (string)$violation,
                    PHPCensor\Model\BuildError::SEVERITY_HIGH,
                    $fileName,
                    (int)$violation['beginline'],
                    (int)$violation['endline']
                );
            }
        }

        return $warnings;
    }

    /**
     * Try and process the rules parameter from .php-censor.yml.
     * @return bool
     */
    protected function tryAndProcessRules()
    {
        if (!empty($this->rules) && !is_array($this->rules)) {
            $this->builder->logFailure('The "rules" option must be an array.');
            return false;
        }

        foreach ($this->rules as &$rule) {
            if (strpos($rule, '/') !== false) {
                $rule = $this->builder->buildPath . $rule;
            }
        }

        return true;
    }

    /**
     * Execute PHP Mess Detector.
     * @param $binaryPath
     */
    protected function executePhpMd($binaryPath)
    {
        $cmd = 'cd "%s" && ' . $binaryPath . ' "%s" xml %s %s %s';

        $ignore = '';
        if (is_array($this->ignore) && count($this->ignore) > 0) {
            $ignoreArray = [];
            foreach ($this->ignore as $ignoreItem) {
                $ignoreArray[] = /*$this->builder->buildPath .*/ $ignoreItem;
            }

            $ignore = sprintf(' --exclude "%s"', implode(',', $ignoreArray));
        }

        $suffixes = '';
        if (is_array($this->suffixes) && count($this->suffixes) > 0) {
            $suffixes = ' --suffixes ' . implode(',', $this->suffixes);
        }

        if (!$this->build->isDebug()) {
            $this->builder->logExecOutput(false);
        }

        // Run PHPMD:
        $this->builder->executeCommand(
            $cmd,
            $this->builder->buildPath,
            $this->directory,
            implode(',', $this->rules),
            $ignore,
            $suffixes
        );

        $this->builder->logExecOutput(true);
    }

    /**
     * Returns a bool indicating if the error count can be considered a success.
     *
     * @param int $errorCount
     *
     * @return bool
     */
    protected function wasLastExecSuccessful($errorCount)
    {
        $success = true;

        if (-1 != $this->allowedWarnings && $errorCount > $this->allowedWarnings) {
            $success = false;
            return $success;
        }
        return $success;
    }
}
