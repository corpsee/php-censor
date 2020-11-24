<?php

namespace PHPCensor\Plugin\Util;

use Exception;
use PHPCensor\Helper\Lang;
use PHPCensor\Logging\BuildLogger;
use PHPCensor\Model\Build;
use PHPCensor\Model\BuildMeta;
use PHPCensor\Plugin;
use PHPCensor\Store\BuildStore;
use PHPCensor\Store\Factory as StoreFactory;

/**
 * Plugin Executor - Runs the configured plugins for a given build stage.
 */
class Executor
{
    /**
     * @var BuildLogger
     */
    protected $logger;

    /**
     * @var Factory
     */
    protected $pluginFactory;

    /**
     * @var BuildStore
     */
    protected $store;

    /**
     * @param Factory $pluginFactory
     * @param BuildLogger $logger
     */
    public function __construct(Factory $pluginFactory, BuildLogger $logger, BuildStore $store = null)
    {
        $this->pluginFactory = $pluginFactory;
        $this->logger = $logger;
        $this->store = $store ?: StoreFactory::getStore('Build');
    }

    /**
     * Execute a the appropriate set of plugins for a given build stage.
     *
     * @param array  $config Configuration
     * @param string $stage
     *
     * @return bool
     */
    public function executePlugins($config, $stage)
    {
        $success          = true;
        $pluginsToExecute = [];

        // If we have global plugins to execute for this stage, add them to the list to be executed:
        if (array_key_exists($stage, $config) && is_array($config[$stage])) {
            $pluginsToExecute[] = $config[$stage];
        }

        $pluginsToExecute = $this->getBranchSpecificPlugins($config, $stage, $pluginsToExecute);

        foreach ($pluginsToExecute as $pluginSet) {
            if (!$this->doExecutePlugins($pluginSet, $stage)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * @param array  $config
     * @param string $branch
     *
     * @return bool|array
     */
    public function getBranchSpecificConfig($config, $branch)
    {
        $configSections = array_keys($config);

        foreach ($configSections as $configSection) {
            if (0 === strpos($configSection, 'branch-')) {
                if ($configSection === ('branch-' . $branch)) {
                    return $config[$configSection];
                }

                if (0 === strpos($configSection, 'branch-regex:')) {
                    $pattern = '#' . substr($configSection, 13) . '#u';
                    preg_match($pattern, $branch, $matches);
                    if (!empty($matches[0])) {
                        return $config[$configSection];
                    }
                }
            }
        }

        return [];
    }

    /**
     * Check the config for any plugins specific to the branch we're currently building.
     *
     * @param array  $config
     * @param string $stage
     * @param array  $pluginsToExecute
     *
     * @return array
     */
    protected function getBranchSpecificPlugins($config, $stage, $pluginsToExecute)
    {
        /** @var Build $build */
        $build        = $this->pluginFactory->getResourceFor('PHPCensor\Model\Build');
        $branch       = $build->getBranch();
        $branchConfig = $this->getBranchSpecificConfig($config, $branch);
        if (!$branchConfig) {
            return $pluginsToExecute;
        }

        $plugins = !empty($branchConfig[$stage]) ? $branchConfig[$stage] : [];

        $runOption = 'after';
        if (!empty($branchConfig['run-option'])) {
            $runOption = $branchConfig['run-option'];
        }

        switch ($runOption) {
            // Replace standard plugin set for this stage with just the branch-specific ones:
            case 'replace':
                $pluginsToExecute   = [];
                $pluginsToExecute[] = $plugins;
                break;

            // Run branch-specific plugins before standard plugins:
            case 'before':
                array_unshift($pluginsToExecute, $plugins);
                break;

            // Run branch-specific plugins after standard plugins:
            case 'after':
                array_push($pluginsToExecute, $plugins);
                break;

            default:
                array_push($pluginsToExecute, $plugins);
                break;
        }

        return $pluginsToExecute;
    }

    /**
     * Execute the list of plugins found for a given testing stage.
     * @param $plugins
     * @param $stage
     * @return bool
     * @throws Exception
     */
    protected function doExecutePlugins($plugins, $stage)
    {
        $success = true;

        foreach ($plugins as $plugin => $options) {
            /**
             * @deprecated Plugins names "campfire", "telegram", "xmpp", "email", "irc" and "phpstan" are deprecated and will be
             * deleted in version 2.0. Use the names "campfire_notify", "telegram_notify", "xmpp_notify",
             * "email_notify", "irc_notify" and "php_stan" instead.
             */
            $realPluginName          = $plugin;
            $deprecatedNotifyPlugins = [
                'campfire' => 'campfire_notify',
                'telegram '=> 'telegram_notify',
                'xmpp'     => 'xmpp_notify',
                'email'    => 'email_notify',
                'irc'      => 'irc_notify',
                'phpstan'  => 'php_stan',
            ];

            if (\array_key_exists(\strtolower($realPluginName), $deprecatedNotifyPlugins)) {
                $realPluginName = $deprecatedNotifyPlugins[\strtolower($realPluginName)];

                $this->logger->logWarning(
                    '[DEPRECATED] Plugins name "' . $plugin . '" is deprecated and will be deleted in version 2.0. Use the name "' . $realPluginName . '" instead.'
                );
            }

            $this->logger->log('');
            $this->logger->logSuccess(
                sprintf('RUNNING PLUGIN: %s', Lang::get($realPluginName)) . ' (' .
                'Stage' . ': ' . ucfirst($stage) . ')'
            );

            $this->setPluginStatus($stage, $realPluginName, Plugin::STATUS_RUNNING);

            // Try and execute it
            if ($this->executePlugin($realPluginName, $options)) {
                // Execution was successful
                $this->logger->logSuccess('PLUGIN: SUCCESS');
                $this->setPluginStatus($stage, $realPluginName, Plugin::STATUS_SUCCESS);
            } else {
                $status = Plugin::STATUS_FAILED;

                if ($stage === Build::STAGE_SETUP) {
                    $this->logger->logFailure('PLUGIN: FAILED');
                    // If we're in the "setup" stage, execution should not continue after
                    // a plugin has failed:
                    throw new Exception('Plugin failed: ' . $realPluginName);
                } elseif ($stage === Build::STAGE_DEPLOY) {
                    $this->logger->logFailure('PLUGIN: FAILED');
                    $success = false;
                } else {
                    // If we're in the "test" stage and the plugin is not allowed to fail,
                    // then mark the build as failed:
                    if (empty($options['allow_failures']) && $stage === Build::STAGE_TEST) {
                        $this->logger->logFailure('PLUGIN: FAILED');
                        $success = false;
                    } else {
                        $status = Plugin::STATUS_FAILED_ALLOWED;

                        $this->logger->logFailure('PLUGIN: FAILED (ALLOWED)');
                    }
                }

                $this->setPluginStatus($stage, $realPluginName, $status);
            }
        }

        return $success;
    }

    /**
     * Executes a given plugin, with options and returns the result.
     */
    public function executePlugin($plugin, $options)
    {
        $class = $plugin;
        if (!class_exists($class)) {
            $class = str_replace('_', ' ', $plugin);
            $class = ucwords($class);
            $class = 'PHPCensor\\Plugin\\' . str_replace(' ', '', $class);

            if (!class_exists($class)) {
                $this->logger->logFailure(sprintf('Plugin does not exist: %s', $plugin));

                return false;
            }
        }

        try {
            // Build and run it
            $obj = $this->pluginFactory->buildPlugin($class, (is_null($options) ? [] : $options));

            return $obj->execute();
        } catch (Exception $ex) {
            $this->logger->logFailure('Exception: ' . $ex->getMessage(), $ex);

            return false;
        }
    }

    /**
     * Change the status of a plugin for a given stage.
     *
     * @param string $stage The builder stage.
     * @param string $plugin The plugin name.
     * @param int $status The new status.
     */
    protected function setPluginStatus($stage, $plugin, $status)
    {
        $summary = $this->getBuildSummary();

        if (!isset($summary[$stage][$plugin])) {
            $summary[$stage][$plugin] = [];
        }

        $summary[$stage][$plugin]['status'] = $status;

        if ($status === Plugin::STATUS_RUNNING) {
            $summary[$stage][$plugin]['started'] = time();
        } elseif ($status >= Plugin::STATUS_SUCCESS) {
            $summary[$stage][$plugin]['ended'] = time();
        }

        $this->setBuildSummary($summary);
    }

    /**
     * Fetch the summary data of the current build.
     *
     * @return array
     */
    private function getBuildSummary()
    {
        /** @var Build $build */
        $build = $this->pluginFactory->getResourceFor('PHPCensor\Model\Build');
        $metas = $this->store->getMeta(BuildMeta::KEY_SUMMARY, null, $build->getProjectId(), $build->getId());
        return isset($metas[0]['value']) ? $metas[0]['value'] : [];
    }

    /**
     * Sets the summary data of the current build.
     *
     * @param array $summary
     */
    private function setBuildSummary($summary)
    {
        /** @var Build $build */
        $build = $this->pluginFactory->getResourceFor('PHPCensor\Model\Build');
        $this->store->setMeta($build->getId(), null, BuildMeta::KEY_SUMMARY, \json_encode($summary));
    }
}
