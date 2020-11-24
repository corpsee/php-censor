<?php

namespace PHPCensor\Plugin;

use Exception;
use PHPCensor;
use PHPCensor\Builder;
use PHPCensor\Config;
use PHPCensor\Model\Build;
use PHPCensor\Model\BuildError;
use PHPCensor\Model\BuildMeta;
use PHPCensor\Plugin;
use PHPCensor\Plugin\Option\PhpUnitOptions;
use PHPCensor\Plugin\Util\PhpUnitResultJson;
use PHPCensor\Plugin\Util\PhpUnitResultJunit;
use PHPCensor\ZeroConfigPluginInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * PHP Unit Plugin - A rewrite of the original PHP Unit plugin
 *
 * @author Dan Cryer <dan@block8.co.uk>
 * @author Pablo Tejada <pablo@ptejada.com>
 */
class PhpUnit extends Plugin implements ZeroConfigPluginInterface
{
    /**
     * @var string
     */
    protected $buildDirectory;

    /**
     * @var string
     */
    protected $buildBranchDirectory;

    /**
     * @var string
     */
    protected $buildLocation;

    /**
     * @var string
     */
    protected $buildBranchLocation;

    /**
     * @var PhpUnitOptions
     */
    protected $options;

    /**
     * @return string
     */
    public static function pluginName()
    {
        return 'php_unit';
    }

    /**
     * Standard Constructor
     * $options['config']                       Path to a PHPUnit XML configuration file.
     * $options['run_from']                     The directory where the phpunit command will run from when using 'config'.
     * $options['coverage']                     Value for the --coverage-html command line flag.
     * $options['required_classes_coverage']    Optional required classes coverage percentage
     * $options['required_methods_coverage']    Optional required methods coverage percentage
     * $options['required_lines_coverage']      Optional required lines coverage percentage
     * $options['directory']                    Optional directory or list of directories to run PHPUnit on.
     * $options['args']                         Command line args (in string format) to pass to PHP Unit
     *
     * @param Builder  $builder
     * @param Build    $build
     * @param string[] $options
     */
    public function __construct(Builder $builder, Build $build, array $options = [])
    {
        parent::__construct($builder, $build, $options);

        $this->buildDirectory       = $build->getBuildDirectory();
        $this->buildBranchDirectory = $build->getBuildBranchDirectory();

        $this->buildLocation       = PUBLIC_DIR . 'artifacts/phpunit/' . $this->buildDirectory;
        $this->buildBranchLocation = PUBLIC_DIR . 'artifacts/phpunit/' . $this->buildBranchDirectory;

        $this->options = new PhpUnitOptions($options, $this->buildLocation);

        $this->executable = $this->findBinary(['phpunit', 'phpunit.phar']);
    }

    /**
     * {@inheritdoc}
     */
    public static function canExecuteOnStage($stage, Build $build)
    {
        if (Build::STAGE_TEST === $stage && !is_null(PhpUnitOptions::findConfigFile($build->getBuildPath()))) {
            return true;
        }

        return false;
    }

    /**
     * Runs PHP Unit tests in a specified directory, optionally using specified config file(s).
     *
     * @return bool
     */
    public function execute()
    {
        $xmlConfigFiles = $this->options->getConfigFiles($this->build->getBuildPath());
        $directories    = $this->options->getDirectories($this->builder);
        if (empty($xmlConfigFiles) && empty($directories)) {
            $this->builder->logFailure('Neither a configuration file nor a test directory found.');
            return false;
        }

        $cmd      = $this->executable;
        $lastLine = exec($cmd . ' --log-json . --version');
        if (false !== strpos($lastLine, '--log-json')) {
            $logFormat = 'junit'; // --log-json is not supported
        } else {
            $logFormat = 'json';
        }

        $success = [];

        // Run any directories
        if (!empty($directories)) {
            foreach ($directories as $directory) {
                $success[] = $this->runConfig($directory, null, $logFormat);
            }
        } else {
            // Run any config files
            if (!empty($xmlConfigFiles)) {
                foreach ($xmlConfigFiles as $configFile) {
                    $success[] = $this->runConfig($this->options->getTestsPath(), $configFile, $logFormat);
                }
            }
        }

        return !in_array(false, $success);
    }

    /**
     * Run the tests defined in a PHPUnit config file or in a specific directory.
     *
     * @param string      $directory
     * @param string|null $configFile
     * @param string      $logFormat
     *
     * @return bool|mixed
     *
     * @throws Exception
     */
    protected function runConfig($directory, $configFile, $logFormat)
    {
        $allowPublicArtifacts = (bool)Config::getInstance()->get(
            'php-censor.build.allow_public_artifacts',
            false
        );

        $fileSystem = new Filesystem();

        $options   = clone $this->options;
        $buildPath = $this->build->getBuildPath();

        // Save the results into a log file
        $logFile = tempnam(sys_get_temp_dir(), 'jlog_');
        $options->addArgument('log-' . $logFormat, $logFile);

        // Removes any current configurations files
        $options->removeArgument('configuration');
        if (null !== $configFile) {
            // Only the add the configuration file been passed
            $options->addArgument('configuration', $buildPath . $configFile);
        }

        if ($options->getOption('coverage') && $allowPublicArtifacts) {
            if (!$fileSystem->exists($this->buildLocation)) {
                $fileSystem->mkdir($this->buildLocation, (0777 & ~umask()));
            }

            if (!is_writable($this->buildLocation)) {
                throw new Exception(sprintf(
                    'The location %s is not writable or does not exist.',
                    $this->buildLocation
                ));
            }
        }

        $arguments = $this->builder->interpolate($options->buildArgumentString());
        $cmd       = $this->executable . ' %s %s';
        $success   = $this->builder->executeCommand($cmd, $arguments, $directory);
        $output    = $this->builder->getLastOutput();
        $covHtmlOk = false;

        if ($fileSystem->exists($this->buildLocation.'/index.html') &&
            $options->getOption('coverage') &&
            $allowPublicArtifacts) {
            $covHtmlOk = true;
            $fileSystem->remove($this->buildBranchLocation);
            $fileSystem->mirror($this->buildLocation, $this->buildBranchLocation);
        }

        $this->processResults($logFile, $logFormat);

        $config = $this->builder->getSystemConfig('php-censor');

        if ($options->getOption('coverage')) {
            $currentCoverage = $this->extractCoverage($output);
            $this->build->storeMeta(self::pluginName(), BuildMeta::KEY_COVERAGE, $currentCoverage);

            if ($covHtmlOk) {
                $this->builder->logSuccess(
                    sprintf(
                        "\nPHPUnit successful build coverage report.\nYou can use coverage report for this build: %s\nOr coverage report for last build in the branch: %s",
                        $config['url'] . '/artifacts/phpunit/' . $this->buildDirectory . '/index.html',
                        $config['url'] . '/artifacts/phpunit/' . $this->buildBranchDirectory . '/index.html'
                    )
                );
            } elseif ($allowPublicArtifacts) {
                $this->builder->logFailure(
                    sprintf(
                        "\nPHPUnit could not build coverage report.\nmissing: %s\nlast of this branch: %s",
                        $config['url'] . '/artifacts/phpunit/' . $this->buildDirectory . '/index.html',
                        $config['url'] . '/artifacts/phpunit/' . $this->buildBranchDirectory . '/index.html'
                    )
                );
            }

            return $this->checkRequiredCoverage($currentCoverage);
        }

        return $success;
    }

    /**
     * Extracts coverage from output
     *
     * @param string $output
     *
     * @return array
     */
    protected function extractCoverage($output)
    {
        preg_match(
            '#Classes:[\s]*(.*?)%[^M]*?Methods:[\s]*(.*?)%[^L]*?Lines:[\s]*(.*?)\%#s',
            $output,
            $matches
        );

        return [
            'classes' => !empty($matches[1]) ? $matches[1] : '0.00',
            'methods' => !empty($matches[2]) ? $matches[2] : '0.00',
            'lines'   => !empty($matches[3]) ? $matches[3] : '0.00',
        ];
    }

    /**
     * Checks required test coverage
     *
     * @param array $coverage
     *
     * @return bool
     */
    protected function checkRequiredCoverage($coverage)
    {
        foreach ($coverage as $key => $currentValue) {
            if ($requiredValue = $this->options->getOption(implode('_', ['required', $key, 'coverage']))) {
                if (bccomp($requiredValue, $currentValue) === 1) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Saves the test results
     *
     * @param string $logFile
     * @param string $logFormat
     *
     * @throws Exception If failed to parse the log file
     */
    protected function processResults($logFile, $logFormat)
    {
        if (file_exists($logFile)) {
            if ('json' === $logFormat) {
                $parser = new PhpUnitResultJson($logFile, $this->build->getBuildPath());
            } else {
                $parser = new PhpUnitResultJunit($logFile, $this->build->getBuildPath());
            }

            $this->build->storeMeta(self::pluginName(), BuildMeta::KEY_DATA, $parser->parse()->getResults());
            $this->build->storeMeta(self::pluginName(), BuildMeta::KEY_ERRORS, $parser->getFailures());

            foreach ($parser->getErrors() as $error) {
                $severity = $error['severity'] ==
                $parser::SEVERITY_ERROR ?
                BuildError::SEVERITY_CRITICAL :
                BuildError::SEVERITY_HIGH;
                $this->build->reportError(
                    $this->builder,
                    self::pluginName(),
                    $error['message'],
                    $severity,
                    $error['file'],
                    $error['line']
                );
            }
            unlink($logFile);
        } else {
            throw new Exception('log output file does not exist: ' . $logFile);
        }
    }
}
