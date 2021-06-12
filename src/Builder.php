<?php

namespace PHPCensor;

use DateTime;
use Exception;
use PHPCensor\Common\Exception\RuntimeException;
use PHPCensor\Helper\BuildInterpolator;
use PHPCensor\Logging\BuildLogger;
use PHPCensor\Model\Build;
use PHPCensor\Plugin\Util\Executor;
use PHPCensor\Plugin\Util\Factory as PluginFactory;
use PHPCensor\Store\BuildErrorWriter;
use PHPCensor\Store\BuildStore;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * @author Dan Cryer <dan@block8.co.uk>
 */
class Builder
{
    /**
     * @var string
     */
    public $buildPath;

    /**
     * @var string[]
     */
    public $ignore = [];

    /**
     * @var string[]
     */
    public $binaryPath = '';

    /**
     * @var string[]
     */
    public $priorityPath = 'local';

    /**
     * @var string
     */
    public $directory;

    /**
     * @var string|null
     */
    protected $currentStage = null;

    /**
     * @var bool
     */
    protected $verbose = true;

    /**
     * @var Build
     */
    protected $build;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var array
     */
    protected $config = [];

    protected BuildInterpolator $interpolator;

    /**
     * @var BuildStore
     */
    protected $store;

    /**
     * @var Executor
     */
    protected $pluginExecutor;

    /**
     * @var Helper\CommandExecutorInterface
     */
    protected $commandExecutor;

    /**
     * @var Logging\BuildLogger
     */
    protected $buildLogger;

    /**
     * @var BuildErrorWriter
     */
    private $buildErrorWriter;

    private ConfigurationInterface $configuration;

    private DatabaseManager $databaseManager;

    private StoreRegistry $storeRegistry;

    public function __construct(
        ConfigurationInterface $configuration,
        DatabaseManager $databaseManager,
        StoreRegistry $storeRegistry,
        Build $build,
        LoggerInterface $logger = null
    ) {
        $this->configuration   = $configuration;
        $this->databaseManager = $databaseManager;
        $this->storeRegistry   = $storeRegistry;

        $this->build = $build;
        $this->store = $this->storeRegistry->get('Build');

        $this->buildLogger    = new BuildLogger($logger, $build);
        $pluginFactory        = $this->buildPluginFactory($build);

        /** @var BuildStore $buildStore */
        $buildStore           = $this->storeRegistry->get('Build');
        $this->pluginExecutor = new Plugin\Util\Executor(
            $this->storeRegistry,
            $pluginFactory,
            $this->buildLogger,
            $buildStore
        );

        $executorClass         = 'PHPCensor\Helper\CommandExecutor';
        $this->commandExecutor = new $executorClass(
            $this->buildLogger,
            ROOT_DIR,
            $this->verbose
        );

        $this->interpolator     = new BuildInterpolator($this->storeRegistry);
        $this->buildErrorWriter = new BuildErrorWriter(
            $this->configuration,
            $this->databaseManager,
            $this->storeRegistry,
            $this->build->getProjectId(),
            $this->build->getId()
        );
    }

    public function getBuildLogger(): BuildLogger
    {
        return $this->buildLogger;
    }

    public function getConfiguration(): ConfigurationInterface
    {
        return $this->configuration;
    }

    public function getCurrentStage(): ?string
    {
        return $this->currentStage;
    }

    /**
     * Set the config array, as read from .php-censor.yml
     *
     * @param array $config
     */
    public function setConfig(array $config)
    {
        $this->config = $config;
    }

    /**
     * Access a variable from the .php-censor.yml file.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getConfig($key = null)
    {
        $value = null;
        if (null === $key) {
            $value = $this->config;
        } elseif (isset($this->config[$key])) {
            $value = $this->config[$key];
        }

        return $value;
    }

    /**
     * Access a variable from the config.yml
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getSystemConfig($key)
    {
        return $this->configuration->get($key);
    }

    public function execute()
    {
        $this->build->setStatusRunning();
        $this->build->setStartDate(new DateTime());
        $this->store->save($this->build);
        $this->build->sendStatusPostback();

        $success = true;

        $previousBuild = $this->build->getProject()->getPreviousBuild($this->build->getBranch());
        $previousState = Build::STATUS_PENDING;

        if ($previousBuild) {
            $previousState = $previousBuild->getStatus();
        }

        try {
            // Set up the build:
            $this->setupBuild();

            // Run the core plugin stages:
            foreach ([Build::STAGE_SETUP, Build::STAGE_TEST, Build::STAGE_DEPLOY] as $stage) {
                $this->currentStage = $stage;
                $success &= $this->pluginExecutor->executePlugins($this->config, $stage);
                if (!$success) {
                    break;
                }
            }

            // Set the status so this can be used by complete, success and failure
            // stages.
            if ($success) {
                $this->build->setStatusSuccess();
            } else {
                $this->build->setStatusFailed();
            }
        } catch (Exception $ex) {
            $success = false;
            $this->build->setStatusFailed();
            $this->buildLogger->logFailure('Exception: ' . $ex->getMessage(), $ex);
        }

        try {
            if ($success) {
                $this->currentStage = Build::STAGE_SUCCESS;
                $this->pluginExecutor->executePlugins($this->config, Build::STAGE_SUCCESS);

                if (Build::STATUS_FAILED === $previousState) {
                    $this->currentStage = Build::STAGE_FIXED;
                    $this->pluginExecutor->executePlugins($this->config, Build::STAGE_FIXED);
                }
            } else {
                $this->currentStage = Build::STAGE_FAILURE;
                $this->pluginExecutor->executePlugins($this->config, Build::STAGE_FAILURE);

                if (Build::STATUS_SUCCESS === $previousState || Build::STATUS_PENDING === $previousState) {
                    $this->currentStage = Build::STAGE_BROKEN;
                    $this->pluginExecutor->executePlugins($this->config, Build::STAGE_BROKEN);
                }
            }
        } catch (Exception $ex) {
            $this->buildLogger->logFailure('Exception: ' . $ex->getMessage(), $ex);
        }

        $this->buildLogger->log('');
        if (Build::STATUS_FAILED === $this->build->getStatus()) {
            $this->buildLogger->logFailure('BUILD FAILED!');
        } else {
            $this->buildLogger->logSuccess('BUILD SUCCESS!');
        }

        // Flush errors to make them available to plugins in complete stage
        $this->buildErrorWriter->flush();

        try {
            // Complete stage plugins are always run
            $this->currentStage = Build::STAGE_COMPLETE;
            $this->pluginExecutor->executePlugins($this->config, Build::STAGE_COMPLETE);
        } catch (Exception $ex) {
            $this->buildLogger->logFailure('Exception: ' . $ex->getMessage());
        }

        // Update the build in the database, ping any external services, etc.
        $this->build->sendStatusPostback();
        $this->build->setFinishDate(new DateTime());

        $removeBuilds = (bool)$this->configuration->get('php-censor.build.remove_builds', true);
        if ($removeBuilds) {
            // Clean up:
            $this->buildLogger->log('');
            $this->buildLogger->logSuccess('REMOVING BUILD.');
            $this->build->removeBuildDirectory();
        }

        $this->buildErrorWriter->flush();

        $this->setErrorTrend();

        $this->store->save($this->build);
    }

    protected function setErrorTrend()
    {
        $this->build->setErrorsTotal($this->store->getErrorsCount($this->build->getId()));

        $trend = $this->store->getBuildErrorsTrend(
            $this->build->getId(),
            $this->build->getProjectId(),
            $this->build->getBranch()
        );

        if (isset($trend[1])) {
            $previousBuild = $this->store->getById($trend[1]['build_id']);
            if ($previousBuild &&
                !in_array(
                    $previousBuild->getStatus(),
                    [Build::STATUS_PENDING, Build::STATUS_RUNNING],
                    true
                )) {
                $this->build->setErrorsTotalPrevious((int)$trend[1]['count']);
            }
        }
    }

    /**
     * Used by this class, and plugins, to execute shell commands.
     *
     * @param string ...$params
     *
     * @return bool
     */
    public function executeCommand(...$params)
    {
        return $this->commandExecutor->executeCommand($params);
    }

    /**
     * Returns the output from the last command run.
     *
     * @return string
     */
    public function getLastOutput()
    {
        return $this->commandExecutor->getLastOutput();
    }

    /**
     * Specify whether exec output should be logged.
     *
     * @param bool $enableLog
     */
    public function logExecOutput($enableLog = true)
    {
        $this->commandExecutor->logExecOutput = $enableLog;
    }

    /**
     * Find a binary required by a plugin.
     *
     * @param array|string $binary
     * @param string       $priorityPath
     * @param string       $binaryPath
     * @param array        $binaryName
     * @return string
     *
     * @throws Exception when no binary has been found.
     */
    public function findBinary($binary, $priorityPath = 'local', $binaryPath = '', $binaryName = [])
    {
        return $this->commandExecutor->findBinary($binary, $priorityPath, $binaryPath, $binaryName);
    }

    /**
     * Replace every occurrence of the interpolation vars in the given string
     * Example: "This is build %BUILD_ID%" => "This is build 182"
     *
     * @param string $input
     *
     * @return string
     */
    public function interpolate($input)
    {
        return $this->interpolator->interpolate($input);
    }

    /**
     * Set up a working copy of the project for building.
     *
     * @throws Exception
     *
     * @return bool
     */
    protected function setupBuild()
    {
        $this->buildPath = $this->build->getBuildPath();

        $this->commandExecutor->setBuildPath($this->buildPath);

        $this->build->handleConfigBeforeClone($this);

        $workingCopySuccess = true;
        // Create a working copy of the project:
        if (!$this->build->createWorkingCopy($this, $this->buildPath)) {
            $workingCopySuccess = false;
        }

        \chdir($this->buildPath);

        $version = (string)\trim(\file_get_contents(ROOT_DIR . 'VERSION.md'));
        $version = !empty($version) ? $version : '0.0.0 (UNKNOWN)';

        $this->interpolator->setupInterpolationVars(
            $this->build,
            APP_URL,
            $version
        );

        // Does the project's .php-censor.yml request verbose mode?
        if (!isset($this->config['build_settings']['verbose']) || !$this->config['build_settings']['verbose']) {
            $this->verbose = false;
        }

        // Does the project have any paths it wants plugins to ignore?
        if (!empty($this->config['build_settings']['ignore'])) {
            $this->ignore = $this->config['build_settings']['ignore'];
        }

        if (!empty($this->config['build_settings']['binary_path'])) {
            $this->binaryPath = rtrim(
                $this->interpolate($this->config['build_settings']['binary_path']),
                '/\\'
            ) . '/';
        }

        if (!empty($this->config['build_settings']['priority_path']) &&
            in_array(
                $this->config['build_settings']['priority_path'],
                Plugin::AVAILABLE_PRIORITY_PATHS,
                true
            )) {
            $this->priorityPath = $this->config['build_settings']['priority_path'];
        }

        $directory = $this->buildPath;

        // Does the project have a global directory for plugins ?
        if (!empty($this->config['build_settings']['directory'])) {
            $directory = $this->config['build_settings']['directory'];
        }

        $this->directory = rtrim(
            $this->interpolate($directory),
            '/\\'
        ) . '/';

        $this->buildLogger->logSuccess(sprintf('Working copy created: %s', $this->buildPath));

        if (!$workingCopySuccess) {
            throw new RuntimeException('Could not create a working copy.');
        }

        return true;
    }

    public function log(string $message, string $level = LogLevel::INFO, array $context = []): void
    {
        $this->buildLogger->log($message, $level, $context);
    }

    public function logWarning(string $message): void
    {
        $this->buildLogger->logWarning($message);
    }

    public function logSuccess(string $message): void
    {
        $this->buildLogger->logSuccess($message);
    }

    public function logFailure(string $message, ?\Throwable $exception = null): void
    {
        $this->buildLogger->logFailure($message, $exception);
    }

    public function logDebug(string $message): void
    {
        $this->buildLogger->logDebug($message);
    }

    /**
     * Returns a configured instance of the plugin factory.
     *
     * @param Build $build
     *
     * @return PluginFactory
     */
    private function buildPluginFactory(Build $build)
    {
        $pluginFactory = new PluginFactory($this, $build);

        return $pluginFactory;
    }

    /**
     * @return BuildErrorWriter
     */
    public function getBuildErrorWriter()
    {
        return $this->buildErrorWriter;
    }
}
