<?php

namespace PHPCensor\Plugin;

use PHPCensor;
use PHPCensor\Builder;
use PHPCensor\Model\Build;
use PHPCensor\Model\BuildError;
use PHPCensor\Model\BuildMeta;
use PHPCensor\Plugin;
use PHPCensor\ZeroConfigPluginInterface;

/**
 * PHP Docblock Checker Plugin - Checks your PHP files for appropriate uses of Docblocks
 *
 * @author Dan Cryer <dan@block8.co.uk>
 */
class PhpDocblockChecker extends Plugin implements ZeroConfigPluginInterface
{
    protected $skipClasses    = false;
    protected $skipMethods    = false;
    protected $skipSignatures = false;
    /**
     * @var int
     */
    protected $allowedWarnings;

    /**
     * @return string
     */
    public static function pluginName()
    {
        return 'php_docblock_checker';
    }

    /**
     * {@inheritdoc}
     */
    public function __construct(Builder $builder, Build $build, array $options = [])
    {
        parent::__construct($builder, $build, $options);

        $this->allowedWarnings = 0;

        if (isset($options['zero_config']) && $options['zero_config']) {
            $this->allowedWarnings = -1;
        }

        if (array_key_exists('skip_classes', $options)) {
            $this->skipClasses = true;
        }

        if (array_key_exists('skip_methods', $options)) {
            $this->skipMethods = true;
        }

        if (array_key_exists('skip_signatures', $options)) {
            $this->skipSignatures = true;
        }

        if (array_key_exists('allowed_warnings', $options)) {
            $this->allowedWarnings = (int)$options['allowed_warnings'];
        }

        $this->executable = $this->findBinary([
            'phpdoc-checker',
            'phpdoc-checker.phar',
        ]);
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
        // Check that the binary exists:
        $checkerCmd = $this->executable;

        // Build ignore string:
        $ignore = '';
        if (is_array($this->ignore)) {
            $ignore = sprintf(' --exclude="%s"', implode(',', $this->ignore));
        }

        // Are we skipping any checks?
        $add = '';
        if ($this->skipClasses) {
            $add .= ' --skip-classes';
        }

        if ($this->skipMethods) {
            $add .= ' --skip-methods';
        }

        if ($this->skipSignatures) {
            $add .= ' --skip-signatures';
        }

        // Build command string:
        $cmd = $checkerCmd . ' --json --directory="%s"%s%s';

        if (!$this->build->isDebug()) {
            $this->builder->logExecOutput(false);
        }
        // Run checker:
        $this->builder->executeCommand(
            $cmd,
            $this->directory,
            $ignore,
            $add
        );
        $this->builder->logExecOutput(true);

        $output = json_decode($this->builder->getLastOutput(), true);

        $errors = 0;
        if ($output && is_array($output)) {
            $errors = count($output);
            $this->builder->logWarning("Number of error : " . $errors);

            $this->reportErrors($output);
        }
        $this->build->storeMeta(self::pluginName(), BuildMeta::KEY_WARNINGS, $errors);

        $success = true;

        if (-1 != $this->allowedWarnings && $errors > $this->allowedWarnings) {
            $success = false;
        }

        return $success;
    }

    /**
     * Report all of the errors we've encountered line-by-line.
     * @param array $output
     */
    protected function reportErrors(array $output)
    {
        foreach ($output as $error) {
            switch ($error['type']) {
                case 'class':
                    $message  = 'Class ' . $error['class'] . ' is missing a docblock.';
                    $severity = BuildError::SEVERITY_NORMAL;
                    break;

                case 'method':
                    $message  = 'Method ' . $error['class'] . '::' . $error['method'] . ' is missing a docblock.';
                    $severity = BuildError::SEVERITY_NORMAL;
                    break;

                case 'param-missing':
                    $message  = $error['class'] . '::' . $error['method'] . ' @param ' . $error['param'] . ' missing.';
                    $severity = BuildError::SEVERITY_LOW;
                    break;

                case 'param-mismatch':
                    $message = $error['class'] . '::' . $error['method'] . ' @param ' . $error['param'] .
                        '(' . $error['doc-type'] . ') does not match method signature (' . $error['param-type'] . ')';
                    $severity = BuildError::SEVERITY_LOW;
                    break;

                case 'return-missing':
                    $message  = $error['class'] . '::' . $error['method'] . ' @return missing.';
                    $severity = BuildError::SEVERITY_LOW;
                    break;

                case 'return-mismatch':
                    $message = $error['class'] . '::' . $error['method'] . ' @return ' . $error['doc-type'] .
                        ' does not match method signature (' . $error['return-type'] . ')';
                    $severity = BuildError::SEVERITY_LOW;
                    break;

                default:
                    $message  = 'Class ' . $error['class'] . ' invalid/missing a docblock.';
                    $severity = BuildError::SEVERITY_LOW;
                    break;
            }

            $this->build->reportError(
                $this->builder,
                self::pluginName(),
                $message,
                $severity,
                $error['file'],
                $error['line']
            );
        }
    }
}
