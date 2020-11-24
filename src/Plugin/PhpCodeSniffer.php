<?php

namespace PHPCensor\Plugin;

use Exception;
use PHPCensor;
use PHPCensor\Builder;
use PHPCensor\Model\Build;
use PHPCensor\Model\BuildError;
use PHPCensor\Model\BuildMeta;
use PHPCensor\Plugin;
use PHPCensor\ZeroConfigPluginInterface;

/**
 * PHP Code Sniffer Plugin - Allows PHP Code Sniffer testing.
 *
 * @author Dan Cryer <dan@block8.co.uk>
 */
class PhpCodeSniffer extends Plugin implements ZeroConfigPluginInterface
{
    /**
     * @var array
     */
    protected $suffixes;

    /**
     * @var string
     */
    protected $standard;

    /**
     * @var string
     */
    protected $tabWidth;

    /**
     * @var string
     */
    protected $encoding;

    /**
     * @var int
     */
    protected $allowedErrors;

    /**
     * @var int
     */
    protected $allowedWarnings;

    /**
     * @var int
     */
    protected $severity = null;
    /**
     * @var null|int
     */
    protected $errorSeverity = null;

    /**
     * @var null|int
     */
    protected $warningSeverity = null;

    /**
     * @return string
     */
    public static function pluginName()
    {
        return 'php_code_sniffer';
    }

    /**
     * {@inheritdoc}
     */
    public function __construct(Builder $builder, Build $build, array $options = [])
    {
        parent::__construct($builder, $build, $options);

        $this->suffixes        = ['php'];
        $this->standard        = 'PSR2';
        $this->tabWidth        = '';
        $this->encoding        = '';
        $this->allowedWarnings = 0;
        $this->allowedErrors   = 0;

        $this->executable = $this->findBinary(['phpcs', 'phpcs.phar']);

        if (isset($options['zero_config']) && $options['zero_config']) {
            $this->allowedWarnings = -1;
            $this->allowedErrors   = -1;
        }

        if (!empty($options['allowed_errors']) && is_int($options['allowed_errors'])) {
            $this->allowedErrors = $options['allowed_errors'];
        }

        if (!empty($options['allowed_warnings']) && is_int($options['allowed_warnings'])) {
            $this->allowedWarnings = $options['allowed_warnings'];
        }

        if (isset($options['suffixes'])) {
            $this->suffixes = (array) $options['suffixes'];
        }

        if (!empty($options['tab_width'])) {
            $this->tabWidth = ' --tab-width=' . $options['tab_width'];
        }

        if (!empty($options['encoding'])) {
            $this->encoding = ' --encoding=' . $options['encoding'];
        }

        if (!empty($options['standard'])) {
            $this->standard = $options['standard'];
        }

        if (isset($options['severity']) && is_int($options['severity'])) {
            $this->severity = $options['severity'];
        }

        if (isset($options['error_severity']) && is_int($options['error_severity'])) {
            $this->errorSeverity = $options['error_severity'];
        }

        if (isset($options['warning_severity']) && is_int($options['warning_severity'])) {
            $this->warningSeverity = $options['warning_severity'];
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
     * Runs PHP Code Sniffer in a specified directory, to a specified standard.
     */
    public function execute()
    {
        list($ignore, $standard, $suffixes, $severity, $errorSeverity, $warningSeverity) = $this->getFlags();

        $phpcs = $this->executable;

        if (!$this->build->isDebug()) {
            $this->builder->logExecOutput(false);
        }

        $cmd = 'cd "%s" && ' . $phpcs . ' --report=json %s %s %s %s %s "%s" %s %s %s';
        $this->builder->executeCommand(
            $cmd,
            $this->builder->buildPath,
            $standard,
            $suffixes,
            $ignore,
            $this->tabWidth,
            $this->encoding,
            $this->directory,
            $severity,
            $errorSeverity,
            $warningSeverity
        );

        $output                  = $this->builder->getLastOutput();
        list($errors, $warnings) = $this->processReport($output);

        $this->builder->logExecOutput(true);

        $success = true;
        $this->build->storeMeta(self::pluginName(), BuildMeta::KEY_WARNINGS, $warnings);
        $this->build->storeMeta(self::pluginName(), BuildMeta::KEY_ERRORS, $errors);

        if (-1 != $this->allowedWarnings && $warnings > $this->allowedWarnings) {
            $success = false;
        }

        if (-1 != $this->allowedErrors && $errors > $this->allowedErrors) {
            $success = false;
        }

        return $success;
    }

    /**
     * Process options and produce an arguments string for PHPCS.
     * @return array
     */
    protected function getFlags()
    {
        $ignore = '';
        if (count($this->ignore)) {
            $ignore = sprintf(' --ignore="%s"', implode(',', $this->ignore));
        }

        $standardPath = $this->normalizePath($this->standard);
        if (file_exists($standardPath)) {
            $standard = ' --standard=' . $standardPath;
        } else {
            $standard = ' --standard=' . $this->standard;
        }

        $suffixes = '';
        if (count($this->suffixes)) {
            $suffixes = ' --extensions=' . implode(',', $this->suffixes);
        }

        $severity = '';
        if (null !== $this->severity) {
            $severity = ' --severity=' . $this->severity;
        }

        $errorSeverity = '';
        if (null !== $this->errorSeverity) {
            $errorSeverity = ' --error-severity=' . $this->errorSeverity;
        }

        $warningSeverity = '';
        if (null !== $this->warningSeverity) {
            $warningSeverity = ' --warning-severity=' . $this->warningSeverity;
        }

        return [$ignore, $standard, $suffixes, $severity, $errorSeverity, $warningSeverity];
    }

    /**
     * Process the PHPCS output report.
     * @param $output
     * @return array
     * @throws Exception
     */
    protected function processReport($output)
    {
        $data = json_decode(trim($output), true);

        if (!is_array($data)) {
            $this->builder->log($output);
            throw new Exception('Could not process the report generated by PHP Code Sniffer.');
        }

        $errors   = $data['totals']['errors'];
        $warnings = $data['totals']['warnings'];

        foreach ($data['files'] as $fileName => $file) {
            $fileName = str_replace($this->builder->buildPath, '', $fileName);

            foreach ($file['messages'] as $message) {
                $this->build->reportError(
                    $this->builder,
                    self::pluginName(),
                    'PHPCS: ' . $message['message'],
                    'ERROR' == $message['type'] ? BuildError::SEVERITY_HIGH : BuildError::SEVERITY_LOW,
                    $fileName,
                    $message['line']
                );
            }
        }

        return [$errors, $warnings];
    }
}
