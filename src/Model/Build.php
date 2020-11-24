<?php

namespace PHPCensor\Model;

use DateTime;
use DirectoryIterator;
use Exception;
use PHPCensor\Builder;
use PHPCensor\Exception\HttpException;
use PHPCensor\Exception\InvalidArgumentException;
use PHPCensor\Helper\Lang;
use PHPCensor\Model\Base\Build as BaseBuild;
use PHPCensor\Plugin\PhpParallelLint;
use PHPCensor\Store\BuildErrorStore;
use PHPCensor\Store\Factory;
use PHPCensor\Store\ProjectStore;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Parser as YamlParser;

/**
 * @author Dan Cryer <dan@block8.co.uk>
 */
class Build extends BaseBuild
{
    const STAGE_SETUP    = 'setup';
    const STAGE_TEST     = 'test';
    const STAGE_DEPLOY   = 'deploy';
    const STAGE_COMPLETE = 'complete';
    const STAGE_SUCCESS  = 'success';
    const STAGE_FAILURE  = 'failure';
    const STAGE_FIXED    = 'fixed';
    const STAGE_BROKEN   = 'broken';

    /**
     * @var array
     */
    public static $pullRequestSources = [
        self::SOURCE_WEBHOOK_PULL_REQUEST_CREATED,
        self::SOURCE_WEBHOOK_PULL_REQUEST_UPDATED,
        self::SOURCE_WEBHOOK_PULL_REQUEST_APPROVED,
        self::SOURCE_WEBHOOK_PULL_REQUEST_MERGED,
    ];

    /**
     * @var array
     */
    public static $webhookSources = [
        self::SOURCE_WEBHOOK_PUSH,
        self::SOURCE_WEBHOOK_PULL_REQUEST_CREATED,
        self::SOURCE_WEBHOOK_PULL_REQUEST_UPDATED,
        self::SOURCE_WEBHOOK_PULL_REQUEST_APPROVED,
        self::SOURCE_WEBHOOK_PULL_REQUEST_MERGED,
    ];

    /**
     * @var array
     */
    protected $totalErrorsCount = [];

    /**
     * @var string
     */
    protected $buildDirectory;

    /**
     * @var string
     */
    protected $buildBranchDirectory;

    /**
     * @return null|Project
     *
     * @throws HttpException
     */
    public function getProject()
    {
        $projectId = $this->getProjectId();
        if (!$projectId) {
            return null;
        }

        /** @var ProjectStore $projectStore */
        $projectStore = Factory::getStore('Project');

        return $projectStore->getById($projectId);
    }

    /**
     * @param string $name
     * @param mixed  $value
     */
    public function addExtraValue($name, $value)
    {
        $extra = json_decode($this->data['extra'], true);
        if ($extra === false) {
            $extra = [];
        }
        $extra[$name] = $value;

        $this->setExtra($extra);
    }

    /**
     * @param string $name
     */
    public function removeExtraValue($name)
    {
        $extra = $this->getExtra();
        if (!empty($extra[$name])) {
            unset($extra[$name]);
        }

        $this->setExtra($extra);
    }

    /**
     * Get BuildError models by BuildId for this Build.
     *
     * @return BuildError[]
     */
    public function getBuildBuildErrors()
    {
        return Factory::getStore('BuildError')->getByBuildId($this->getId());
    }

    /**
     * Get BuildMeta models by BuildId for this Build.
     *
     * @return BuildMeta[]
     */
    public function getBuildBuildMetas()
    {
        return Factory::getStore('BuildMeta')->getByBuildId($this->getId());
    }

    /**
    * Get link to commit from another source (i.e. Github)
    */
    public function getCommitLink()
    {
        return '#';
    }

    /**
    * Get link to branch from another source (i.e. Github)
    */
    public function getBranchLink()
    {
        return '#';
    }

    /**
     * Get remote branch (from pull request) from another source (i.e. Github)
     */
    public function getRemoteBranch()
    {
        return $this->getExtra('remote_branch');
    }

    /**
     * Get link to remote branch (from pull request) from another source (i.e. Github)
     */
    public function getRemoteBranchLink()
    {
        return '#';
    }

    /**
     * Get link to tag from another source (i.e. Github)
     */
    public function getTagLink()
    {
        return '#';
    }

    /**
     * Return a template to use to generate a link to a specific file.
     *
     * @return string|null
     */
    public function getFileLinkTemplate()
    {
        return null;
    }

    /**
    * Send status updates to any relevant third parties (i.e. Github)
    */
    public function sendStatusPostback()
    {
        return false;
    }

    /**
     * @return string
     *
     * @throws HttpException
     */
    public function getProjectTitle()
    {
        $project = $this->getProject();
        return $project ? $project->getTitle() : "";
    }

    /**
     * Store build metadata
     *
     * @param string      $key
     * @param string|null $plugin
     * @param mixed       $value
     */
    public function storeMeta($key, $plugin, $value)
    {
        $value = json_encode($value);

        Factory::getStore('Build')->setMeta($this->getId(), $plugin, $key, $value);
    }

    /**
     * Is this build successful?
     */
    public function isSuccessful()
    {
        return ($this->getStatus() === self::STATUS_SUCCESS);
    }

    /**
     * @param Builder $builder
     *
     * @return bool
     *
     * @throws Exception
     */
    public function handleConfigBeforeClone(Builder $builder)
    {
        $buildConfig = $this->getProject()->getBuildConfig();

        if ($buildConfig) {
            $yamlParser  = new YamlParser();
            $buildConfig = $yamlParser->parse($buildConfig);

            if ($buildConfig && is_array($buildConfig)) {
                $builder->logDebug('Config before repository clone (DB): ' . json_encode($buildConfig));

                $builder->setConfig($buildConfig);
            }
        }

        return true;
    }

    /**
     * @param Builder $builder
     * @param string  $buildPath
     *
     * @return bool
     *
     * @throws Exception
     */
    protected function handleConfig(Builder $builder, $buildPath)
    {
        $yamlParser           = new YamlParser();
        $overwriteBuildConfig = $this->getProject()->getOverwriteBuildConfig();
        $buildConfig          = $builder->getConfig();

        $repositoryConfig     = $this->getZeroConfigPlugins($builder);
        $repositoryConfigFrom = '<empty config>';
        if (file_exists($buildPath . '/.php-censor.yml')) {
            $repositoryConfigFrom = '.php-censor.yml';
            $repositoryConfig = $yamlParser->parse(
                file_get_contents($buildPath . '/.php-censor.yml')
            );
        } elseif (file_exists($buildPath . '/.phpci.yml')) {
            $builder->logWarning(
                '[DEPRECATED] Config file name ".phpci.yml" is deprecated and will be deleted in version 2.0. Use a config file name ".php-censor.yml" instead.'
            );

            $repositoryConfigFrom = '.phpci.yml';
            $repositoryConfig = $yamlParser->parse(
                file_get_contents($buildPath . '/.phpci.yml')
            );
        } elseif (file_exists($buildPath . '/phpci.yml')) {
            $builder->logWarning(
                '[DEPRECATED] Config file name "phpci.yml" is deprecated and will be deleted in version 2.0. Use a config file name ".php-censor.yml" instead.'
            );

            $repositoryConfigFrom = 'phpci.yml';
            $repositoryConfig = $yamlParser->parse(
                file_get_contents($buildPath . '/phpci.yml')
            );
        }

        if (isset($repositoryConfig['build_settings']['clone_depth'])) {
            $builder->logWarning(
                'Option "build_settings.clone_depth" supported only in additional DB project config.' .
                ' Please move this option to DB config from your in-repository config file (".php-censor.yml").'
            );
        }

        if (!$buildConfig) {
            $builder->logDebug(
                sprintf('Build config from repository (%s)', $repositoryConfigFrom)
            );

            $buildConfig = $repositoryConfig;
        } elseif ($buildConfig && !$overwriteBuildConfig) {
            $builder->logDebug(
                sprintf('Build config from project (DB) + config from repository (%s)', $repositoryConfigFrom)
            );

            $buildConfig = array_replace_recursive($repositoryConfig, $buildConfig);
        } elseif ($buildConfig) {
            $builder->logDebug('Build config from project (DB)');
        }

        if ($buildConfig && is_array($buildConfig)) {
            $builder->logDebug('Final config: ' . json_encode($buildConfig));

            $builder->setConfig($buildConfig);
        }

        return true;
    }

    /**
     * Get an array of plugins to run if there's no .php-censor.yml file.
     *
     * @param Builder $builder
     *
     * @return array
     *
     * @throws ReflectionException
     */
    protected function getZeroConfigPlugins(Builder $builder)
    {
        $pluginDir = SRC_DIR . 'Plugin/';
        $dir = new DirectoryIterator($pluginDir);

        $config = [
            'build_settings' => [
                'ignore' => [
                    'vendor',
                ]
            ]
        ];

        foreach ($dir as $item) {
            if ($item->isDot()) {
                continue;
            }

            if (!$item->isFile()) {
                continue;
            }

            if ($item->getExtension() != 'php') {
                continue;
            }

            $className = '\PHPCensor\Plugin\\' . $item->getBasename('.php');

            $reflectedPlugin = new ReflectionClass($className);

            if (!$reflectedPlugin->implementsInterface('\PHPCensor\ZeroConfigPluginInterface')) {
                continue;
            }

            foreach ([Build::STAGE_SETUP, Build::STAGE_TEST] as $stage) {
                if ($className::canExecuteOnStage($stage, $this)) {
                    $pluginConfig = [
                        'zero_config' => true,
                    ];

                    if (PhpParallelLint::pluginName() === $className::pluginName()) {
                        $pluginConfig['allow_failures'] = true;
                    }

                    $config[$stage][$className::pluginName()] = $pluginConfig;
                }
            }
        }

        return $config;
    }

    /**
     * Allows specific build types (e.g. Github) to report violations back to their respective services.
     *
     * @param Builder $builder
     * @param string  $plugin
     * @param string  $message
     * @param int $severity
     * @param string  $file
     * @param int $lineStart
     * @param int $lineEnd
     */
    public function reportError(
        Builder $builder,
        $plugin,
        $message,
        $severity = BuildError::SEVERITY_NORMAL,
        $file = null,
        $lineStart = null,
        $lineEnd = null
    ) {
        $writer = $builder->getBuildErrorWriter();
        $writer->write(
            $plugin,
            $message,
            $severity,
            $file,
            $lineStart,
            $lineEnd
        );
    }

    /**
     * @return string|null
     */
    public function getBuildDirectory()
    {
        if (!$this->getId()) {
            return null;
        }

        $createDate = $this->getCreateDate();
        if (empty($this->buildDirectory)) {
            $this->buildDirectory = $this->getProjectId() . '/' . $this->getId() . '_' . substr(
                md5(
                    ($this->getId() . '_' . ($createDate ? $createDate->format('Y-m-d H:i:s') : null))
                ),
                0,
                8
            );
        }

        return $this->buildDirectory;
    }

    /**
     * @return string|null
     */
    public function getBuildBranchDirectory()
    {
        if (!$this->getId()) {
            return null;
        }

        $createDate = $this->getCreateDate();
        if (empty($this->buildBranchDirectory)) {
            $this->buildBranchDirectory = $this->getProjectId() . '/' . $this->getBranch() . '_' . substr(
                md5(
                    ($this->getBranch() . '_' . ($createDate ? $createDate->format('Y-m-d H:i:s') : null))
                ),
                0,
                8
            );
        }

        return $this->buildBranchDirectory;
    }

    /**
     * @return string|null
     */
    public function getBuildPath()
    {
        if (!$this->getId()) {
            return null;
        }

        return rtrim(
            realpath(RUNTIME_DIR . 'builds'),
            '/\\'
        ) . '/' . $this->getBuildDirectory() . '/';
    }

    /**
     * Removes the build directory.
     *
     * @param bool $withArtifacts
     */
    public function removeBuildDirectory($withArtifacts = false)
    {
        // Get the path and remove the trailing slash as this may prompt PHP
        // to see this as a directory even if it's a link.
        $buildPath = rtrim($this->getBuildPath(), '/');

        if (!$buildPath || !is_dir($buildPath)) {
            return;
        }

        try {
            $fileSystem = new Filesystem();

            if (is_link($buildPath)) {
                // Remove the symlink without using recursive.
                exec(sprintf('rm "%s"', $buildPath));
            } else {
                $fileSystem->remove($buildPath);
            }

            if ($withArtifacts) {
                $buildDirectory = $this->getBuildDirectory();

                $fileSystem->remove(PUBLIC_DIR . 'artifacts/pdepend/' . $buildDirectory);
                $fileSystem->remove(PUBLIC_DIR . 'artifacts/phpunit/' . $buildDirectory);
            }
        } catch (Exception $e) {
        }
    }

    /**
     * Get the number of seconds a build has been running for.
     *
     * @return int
     */
    public function getDuration()
    {
        $start = $this->getStartDate();

        if (empty($start)) {
            return 0;
        }

        $end = $this->getFinishDate();

        if (empty($end)) {
            $end = new DateTime();
        }

        return $end->getTimestamp() - $start->getTimestamp();
    }

    /**
     * get time a build has been running for in hour/minute/seconds format (e.g. 1h 21m 45s)
     *
     * @return string
     */
    public function getPrettyDuration()
    {
        $start = $this->getStartDate();
        if (!$start) {
            $start = new DateTime();
        }
        $end = $this->getFinishDate();
        if (!$end) {
            $end = new DateTime();
        }

        $diff  = date_diff($start, $end);
        $parts = [];
        foreach (['y', 'm', 'd', 'h', 'i', 's'] as $timePart) {
            if ($diff->{$timePart} != 0) {
                $parts[] = $diff->{$timePart} . ($timePart == 'i' ? 'm' : $timePart);
            }
        }

        return implode(" ", $parts);
    }

    /**
     * Create a working copy by cloning, copying, or similar.
     *
     * @param Builder $builder
     * @param string  $buildPath
     *
     * @return bool
     */
    public function createWorkingCopy(Builder $builder, $buildPath)
    {
        return false;
    }

    /**
     * Create an SSH key file on disk for this build.
     *
     * @return string
     *
     * @throws HttpException
     */
    protected function writeSshKey()
    {
        $tempKeyFile = tempnam(sys_get_temp_dir(), 'key_');

        file_put_contents($tempKeyFile, $this->getProject()->getSshPrivateKey());

        return $tempKeyFile;
    }

    /**
     * Create an SSH wrapper script for Svn to use, to disable host key checking, etc.
     *
     * @param string $keyFile
     *
     * @return string
     */
    protected function writeSshWrapper($keyFile)
    {
        $sshFlags = '-o CheckHostIP=no -o IdentitiesOnly=yes -o StrictHostKeyChecking=no -o PasswordAuthentication=no';

        // Write out the wrapper script for this build:
        $script = <<<OUT
#!/bin/sh
ssh {$sshFlags} -o IdentityFile={$keyFile} $*

OUT;
        $tempShFile = tempnam(sys_get_temp_dir(), 'sh_');

        file_put_contents($tempShFile, $script);
        shell_exec('chmod +x "' . $tempShFile . '"');

        return $tempShFile;
    }

    /**
     * @return string
     */
    public function getSourceHumanize()
    {
        $parentId   = $this->getParentId();
        $parentLink = '<a href="' . APP_URL . 'build/view/' . $parentId . '">#' . $parentId . '</a>';

        switch ($this->getSource()) {
            case Build::SOURCE_WEBHOOK_PUSH:
                return Lang::get('source_webhook_push');
            case Build::SOURCE_WEBHOOK_PULL_REQUEST_CREATED:
                return Lang::get('source_webhook_pull_request_created');
            case Build::SOURCE_WEBHOOK_PULL_REQUEST_UPDATED:
                return Lang::get('source_webhook_pull_request_updated');
            case Build::SOURCE_WEBHOOK_PULL_REQUEST_APPROVED:
                return Lang::get('source_webhook_pull_request_approved');
            case Build::SOURCE_WEBHOOK_PULL_REQUEST_MERGED:
                return Lang::get('source_webhook_pull_request_merged');
            case Build::SOURCE_MANUAL_WEB:
                return Lang::get('source_manual_web');
            case Build::SOURCE_MANUAL_REBUILD_WEB:
                return Lang::get('source_manual_rebuild_web', $parentLink);
            case Build::SOURCE_MANUAL_CONSOLE:
                return Lang::get('source_manual_console');
            case Build::SOURCE_MANUAL_REBUILD_CONSOLE:
                return Lang::get('source_manual_rebuild_console', $parentLink);
            case Build::SOURCE_PERIODICAL:
                return Lang::get('source_periodical');
            case Build::SOURCE_UNKNOWN:
            default:
                return Lang::get('source_unknown');
        }
    }

    /**
     * Gets the total number of errors for a given build.
     *
     * @param string|null $plugin
     * @param int|null    $severity
     * @param string|null $isNew
     *
     * @return int
     *
     * @throws Exception
     */
    public function getTotalErrorsCount($plugin = null, $severity = null, $isNew = null)
    {
        if (null === $plugin && null === $severity && null === $isNew) {
            return (int)$this->getErrorsTotal();
        }

        $key =
            $plugin . ':' .
            ((null === $severity) ? 'null' : (int)$severity) . ':' .
            $isNew;

        if (!isset($this->totalErrorsCount[$key])) {
            /** @var BuildErrorStore $store */
            $store = Factory::getStore('BuildError');

            $this->totalErrorsCount[$key] = (int)$store->getErrorTotalForBuild(
                $this->getId(),
                $plugin,
                $severity,
                $isNew
            );
        }

        return $this->totalErrorsCount[$key];
    }

    /**
     * @return int
     *
     * @throws InvalidArgumentException
     * @throws HttpException
     */
    public function getErrorsTrend()
    {
        $total    = (int)$this->getErrorsTotal();
        $previous = $this->getErrorsTotalPrevious();

        if (null === $previous) {
            return 0;
        }

        $previous = (int)$previous;
        if ($previous > $total) {
            return 1;
        } elseif ($previous < $total) {
            return -1;
        }

        return 0;
    }
}
