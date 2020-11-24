<?php

namespace PHPCensor\Plugin;

use Exception;
use GuzzleHttp\Client;
use PHPCensor\Builder;
use PHPCensor\Database;
use PHPCensor\Model\Build;
use PHPCensor\Plugin;
use PHPCensor\Plugin\Util\BitbucketNotifyPluginResult;
use PHPCensor\Store\BuildErrorStore;
use PHPCensor\Store\BuildMetaStore;
use PHPCensor\Store\BuildStore;
use PHPCensor\Store\Factory;

class BitbucketNotify extends Plugin
{
    /** @var string */
    protected $url;

    /** @var string */
    protected $authToken;

    /** @var string */
    protected $projectKey;

    /** @var string */
    protected $repositorySlug;

    /** @var bool */
    protected $createTaskPerFail = true;

    /** @var bool */
    protected $createTaskIfFail = true;

    /** @var bool */
    protected $updateBuild = false;

    /** @var string */
    protected $message = '';

    /**
     * @var Client
     */
    protected $httpClient;

    /**
     * @var Database
     */
    protected $pdo;

    /**
     * @return string
     */
    public static function pluginName()
    {
        return 'bitbucket_notify';
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function __construct(Builder $builder, Build $build, array $options = [])
    {
        parent::__construct($builder, $build, $options);

        $this->pdo = Database::getConnection('read');

        $this->httpClient        = new Client();
        $this->url               = \trim($options['url']);
        $this->message           = isset($options['message']) ? $options['message'] : '';
        $this->projectKey        = $options['project_key'];
        $this->repositorySlug    = $options['repository_slug'];
        $this->createTaskPerFail = $options['create_task_per_fail'];
        $this->createTaskIfFail  = $options['create_task_if_fail'];
        $this->updateBuild       = $options['update_build'];

        if (\array_key_exists('auth_token', $options)) {
            $this->authToken = $options['auth_token'];
            /** @deprecated Option "token" is deprecated and will be deleted in version 2.0. Use the option "auth_token" instead. */
        } elseif (\array_key_exists('token', $options)) {
            $builder->logWarning(
                '[DEPRECATED] Option "token" is deprecated and will be deleted in version 2.0. Use the option "auth_token" instead.'
            );

            $this->authToken = $options['token'];
        }

        if (empty($this->message)) {
            $this->message = '## PHP CENSOR Report' . PHP_EOL;
            $this->message .= '```' . PHP_EOL;
            $this->message .= '%STATS%' . PHP_EOL;
            $this->message .= '```' . PHP_EOL;
            $this->message .= '%BUILD_LINK%?is_new=only_new#errors' . PHP_EOL . PHP_EOL;

            $testSettings = $this->getBuilder()->getConfig('test');
            if (isset($testSettings[PhpUnit::pluginName()])) {
                $config = $this->getBuilder()->getSystemConfig('php-censor');
                $censorUrl = $config['url'];
                $buildDirectory = $this->getBuild()->getBuildBranchDirectory();

                $this->message .= $censorUrl . '/artifacts/phpunit/' . $buildDirectory . '/index.html' . PHP_EOL;
            }

            if (isset($testSettings[Pdepend::pluginName()])) {
                $config = $this->getBuilder()->getSystemConfig('php-censor');
                $censorUrl = $config['url'];

                $buildDirectory = $this->getBuild()->getBuildBranchDirectory();

                $summary = $censorUrl . '/artifacts/pdepend/' . $buildDirectory . '/summary.xml';
                $chart = $censorUrl . '/artifacts/pdepend/' . $buildDirectory . '/chart.svg';
                $pyramid = $censorUrl . '/artifacts/pdepend/' . $buildDirectory . '/pyramid.svg';

                $this->message .= sprintf('![Chart](%s "Pdepend Chart")', $chart);
                $this->message .= sprintf('![Pyramid](%s "Pdepend Pyramid")', $pyramid) . PHP_EOL;
                $this->message .= $summary . PHP_EOL;
            }
        }

        if (empty($this->url) ||
            empty($this->message) ||
            empty($this->authToken) ||
            empty($this->projectKey) ||
            empty($this->repositorySlug)
        ) {
            throw new Exception('Please define the url for bitbucket plugin!');
        }
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function execute()
    {
        $pullRequestId = $this->findPullRequestsByBranch();
        $targetBranch = $this->getTargetBranchForPullRequest($pullRequestId);
        $plugins = $this->prepareResult($targetBranch);
        $message = $this->reportGenerator($this->buildResultComparator($plugins));
        if (!empty($message)) {
            $commentId = $this->createCommentInPullRequest($pullRequestId, $message);

            if ($this->createTaskIfFail) {
                $this->createTaskForCommentInPullRequest($commentId, 'pls fix php-censor report');
            }

            if ($this->createTaskPerFail) {
                foreach ($plugins as $plugin) {
                    $taskDescription = $plugin->generateTaskDescription();
                    if (!empty($taskDescription)) {
                        $this->createTaskForCommentInPullRequest($commentId, $taskDescription);
                    }
                }
            }
        }

        if ($this->updateBuild) {
            $this->updateBuild();
        }

        return true;
    }

    /**
     * @return int|null
     */
    protected function findPullRequestsByBranch()
    {
        $endpoint = sprintf('/projects/%s/repos/%s/pull-requests', $this->projectKey, $this->repositorySlug);
        $response = $this->apiRequest($endpoint)->getBody();
        $response = json_decode($response, true);

        foreach ($response['values'] as $pullRequest) {
            if ($pullRequest['fromRef']['displayId'] === $this->getBuild()->getBranch()) {
                return (int)$pullRequest['id'];
            }
        }

        return null;
    }

    protected function getTargetBranchForPullRequest($pullRequestId)
    {
        $endpoint = sprintf(
            '/projects/%s/repos/%s/pull-requests/%d',
            $this->projectKey,
            $this->repositorySlug,
            $pullRequestId
        );

        $response = $this->apiRequest($endpoint)->getBody();
        $response = json_decode($response, true);

        return $response['toRef']['displayId'];
    }

    /**
     * @param int $pullRequestId
     * @param string $message
     * @return int
     */
    protected function createCommentInPullRequest($pullRequestId, $message)
    {
        $endpoint = sprintf(
            '/projects/%s/repos/%s/pull-requests/%s/comments',
            $this->projectKey,
            $this->repositorySlug,
            $pullRequestId
        );

        $response = $this->apiRequest($endpoint, 'post', ['text' => $message])->getBody();
        $response = json_decode($response, true);

        return (int)$response['id'];
    }

    /**
     * @param int $commentId
     * @param string $message
     */
    protected function createTaskForCommentInPullRequest($commentId, $message)
    {
        $this->apiRequest('/tasks', 'post', [
            'anchor' => [
                'id' => $commentId,
                'type' => 'COMMENT',
            ],
            'text' => $message,
        ]);
    }


    protected function updateBuild()
    {
        $endpoint = sprintf(
            '/commits/%s',
            $this->getBuild()->getCommitId()
        );

        switch ($this->getBuild()->getStatus()) {
            case Build::STATUS_SUCCESS:
                $state = 'SUCCESSFUL';
                break;
            case Build::STATUS_FAILED:
                $state = 'FAILED';
                break;
            default:
                $state = 'INPROGRESS';
        }

        $this->buildStatusRequest($endpoint, 'post', [
            'state' => $state,
            'key' => 'php-censor',
            'name' => 'PHP Censor',
            'url' => APP_URL . 'build/view/' . $this->getBuild()->getId(),
            'description' => '',
        ]);
    }

    /**
     * @param string $targetBranch
     * @return BitbucketNotifyPluginResult[]
     * @throws Exception
     */
    protected function prepareResult($targetBranch)
    {
        /** @var BuildErrorStore $buildErrorStore */
        $buildErrorStore = Factory::getStore('BuildError');

        $targetBranchBuildStats = $buildErrorStore->getErrorAmountPerPluginForBuild(
            $this->findLatestBuild($targetBranch)
        );

        $currentBranchBuildStats = $buildErrorStore->getErrorAmountPerPluginForBuild($this->build->getId());

        if (empty($targetBranchBuildStats) && empty($currentBranchBuildStats)) {
            return [];
        }

        $plugins = array_unique(array_merge(array_keys($targetBranchBuildStats), array_keys($currentBranchBuildStats)));
        sort($plugins);

        $result = [];
        foreach ($plugins as $plugin) {
            $result[] = new BitbucketNotifyPluginResult(
                $plugin,
                isset($targetBranchBuildStats[$plugin]) ? $targetBranchBuildStats[$plugin] : 0,
                isset($currentBranchBuildStats[$plugin]) ? $currentBranchBuildStats[$plugin] : 0
            );
        }

        $result[] = $this->getPhpUnitCoverage($targetBranch);
        return $result;
    }

    /**
     * @param string $targetBranch
     * @return BitbucketNotifyPluginResult
     * @throws Exception
     */
    public function getPhpUnitCoverage($targetBranch)
    {
        /** @var BuildMetaStore $buildMetaStore */
        $buildMetaStore = Factory::getStore('BuildMeta');
        $latestTargeBuildId = $this->findLatestBuild($targetBranch);
        $latestCurrentBuildId = $this->findLatestBuild($this->build->getBranch());

        $targetMetaData = $buildMetaStore->getByPluginAndKey(
            $this->findLatestBuild($targetBranch),
            PhpUnit::pluginName(),
            'coverage'
        );
        $currentMetaData = $buildMetaStore->getByPluginAndKey(
            $this->build->getId(),
            PhpUnit::pluginName(),
            'coverage'
        );

        $targetBranchCoverage = [];
        if (!is_null($latestTargeBuildId) && !is_null($targetMetaData)) {
            $targetBranchCoverage = json_decode($targetMetaData->getValue(), true);
        }

        $currentBranchCoverage = [];
        if (!is_null($currentMetaData)) {
            $currentBranchCoverage = json_decode($currentMetaData->getValue(), true);
        }

        return new Plugin\Util\BitbucketNotifyPhpUnitResult(
            PhpUnit::pluginName() . '-coverage',
            isset($targetBranchCoverage['lines']) ? $targetBranchCoverage['lines'] : 0,
            isset($currentBranchCoverage['lines']) ? $currentBranchCoverage['lines'] : 0
        );
    }

    /**
     * @param Util\BitbucketNotifyPluginResult[] $plugins
     * @return array
     */
    protected function buildResultComparator(array $plugins)
    {
        $maxPluginNameLength = 20;
        if (!empty($plugins)) {
            $maxPluginNameLength = max(array_map('strlen', $plugins));
        }

        $lines = [];
        foreach ($plugins as $plugin) {
            $lines[] = $plugin->generateFormattedOutput($maxPluginNameLength);
        }

        return $lines;
    }

    protected function reportGenerator(array $stats)
    {
        $statsString = trim(implode(PHP_EOL, $stats));
        if (empty($stats)) {
            $statsString = 'no changes between your branch and target branch';
        }

        $message = str_replace(['%STATS%'], [$statsString], $this->message);

        return $this->builder->interpolate($message);
    }

    /**
     * @param $branchName
     * @return int
     * @throws Exception
     */
    protected function findLatestBuild($branchName)
    {
        /** @var BuildStore $buildStore */
        $buildStore = Factory::getStore('Build');

        $build = $buildStore->getLatestBuildByProjectAndBranch($this->getBuild()->getProjectId(), $branchName);

        return $build !== null ? $build->getId() : null;
    }

    protected function buildStatusRequest($endpoint, $method = 'get', array $jsonBody = null)
    {
        return $this->request($this->url . '/rest/build-status/1.0' . $endpoint, $method, $jsonBody);
    }

    protected function apiRequest($endpoint, $method = 'get', array $jsonBody = null)
    {
        return $this->request($this->url . '/rest/api/1.0' . $endpoint, $method, $jsonBody);
    }

    protected function request($endpoint, $method = 'get', array $jsonBody = null)
    {
        $options = ['headers' => ['Authorization' => 'Bearer ' . $this->authToken]];
        $jsonBody !== null && $options['json'] = $jsonBody;
        return $this->httpClient->request($method, $endpoint, $options);
    }
}
