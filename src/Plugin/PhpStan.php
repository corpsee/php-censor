<?php

namespace PHPCensor\Plugin;

use Exception;
use PHPCensor;
use PHPCensor\Builder;
use PHPCensor\Model\Build;
use PHPCensor\Model\BuildError;
use PHPCensor\Plugin;

/**
 * PHPStan focuses on finding errors in your code without actually running it. It catches whole classes of bugs even
 * before you write tests for the code. It moves PHP closer to compiled languages in the sense that the correctness of
 * each line of the code can be checked before you run the actual line.
 * https://github.com/phpstan/phpstan
 */
class PhpStan extends Plugin
{
    /** @var string[] */
    protected $directories = [];
    /** @var int */
    protected $allowedErrors = 0;

    /**
     * @param Builder $builder
     * @param Build $build
     * @param array $options
     *
     * @throws Exception
     */
    public function __construct(Builder $builder, Build $build, array $options = [])
    {
        parent::__construct($builder, $build, $options);

        $this->executable = $this->findBinary(['phpstan', 'phpstan.phar']);

        if (!empty($options['directories']) && is_array($options['directories'])) {
            $this->directories = $options['directories'];
        }

        if (isset($options['allowed_errors']) && is_int($options['allowed_errors'])) {
            $this->allowedErrors = $options['allowed_errors'];
        }
    }

    /**
     * @return bool
     */
    public function execute()
    {
        $phpStan = $this->executable;

        if (!$this->build->isDebug()) {
            $this->builder->logExecOutput(false);
        }

        $this->builder->executeCommand(
            'cd "%s" && ' . $phpStan . ' analyze --error-format=json %s',
            $this->builder->buildPath,
            implode(' ', $this->directories)
        );
        $this->builder->logExecOutput(true);

        // Define that the plugin succeed
        $success = true;

        list($total_errors, $files) = $this->processReport($this->builder->getLastOutput());

        if (0 < $total_errors) {
            if (-1 !== $this->allowedErrors && $total_errors > $this->allowedErrors) {
                $success = false;
            }

            foreach ($files as $file => $payload) {
                if (0 < $payload['errors']) {
                    $file = str_replace($this->build->getBuildPath(), '', $file);
                    $len = strlen($file);
                    $out = '';
                    $filename = (false !== strpos($file, ' (')) ? strstr($file, ' (', true) : $file;

                    foreach ($payload['messages'] as $message) {
                        if (strlen($message['message']) > $len) {
                            $len = strlen($message['message']);
                        }
                        $out .= vsprintf(' %d%s %s' . PHP_EOL, [
                            $message['line'],
                            str_repeat(' ', 6 - strlen($message['line'])),
                            $message['message']
                        ]);

                        $this->build->reportError(
                            $this->builder,
                            self::pluginName(),
                            $message['message'],
                            BuildError::SEVERITY_NORMAL,
                            $filename,
                            $message['line']
                        );
                    }
                    $separator = str_repeat('-', 6) . ' ' . str_repeat('-', $len + 2) . PHP_EOL;

                    $this->builder->logFailure(vsprintf('%s Line   %s' . PHP_EOL . '%s', [
                        $separator,
                        $file,
                        $separator . $out . $separator
                    ]));
                }
            }
        }

        if ($success) {
            $this->builder->logSuccess('[OK] No errors');
        } else {
            $this->builder->log(sprintf('[ERROR] Found %d errors', $total_errors));
        }

        return $success;
    }

    /**
     * @return string
     */
    public static function pluginName()
    {
        return 'php_stan';
    }

    /**
     * @param string $stage
     * @param Build  $build
     *
     * @return bool
     */
    public static function canExecuteOnStage($stage, Build $build)
    {
        return Build::STAGE_TEST === $stage;
    }

    /**
     * @param string $output
     * @return array
     */
    protected function processReport($output)
    {
        $data = json_decode(trim($output), true);

        $totalErrors = 0;
        $files        = [];

        if (!empty($data) && is_array($data) && (0 < $data['totals']['file_errors'])) {
            $totalErrors = $data['totals']['file_errors'];
            $files = $data['files'];
        }

        return [$totalErrors, $files];
    }
}
