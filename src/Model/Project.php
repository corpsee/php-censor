<?php

namespace PHPCensor\Model;

use PHPCensor\Common\Project\ProjectInterface;
use PHPCensor\Model\Base\Project as BaseProject;
use PHPCensor\Store\EnvironmentStore;
use PHPCensor\Store\ProjectGroupStore;
use Symfony\Component\Yaml\Dumper as YamlDumper;
use Symfony\Component\Yaml\Parser as YamlParser;

/**
 * @author Dan Cryer <dan@block8.co.uk>
 */
class Project extends BaseProject implements ProjectInterface
{
    public function getLink(): string
    {
        return APP_URL . 'project/view/' . $this->getId();
    }

    /**
     * @return ProjectGroup|null
     */
    public function getGroup()
    {
        $groupId = $this->getGroupId();
        if (empty($groupId)) {
            return null;
        }

        /** @var ProjectGroupStore $groupStore */
        $groupStore = $this->storeRegistry->get('ProjectGroup');

        return $groupStore->getById($groupId);
    }

    /**
     * Get Build models by ProjectId for this Project.
     *
     * @return Build[]
     */
    public function getProjectBuilds()
    {
        return $this->storeRegistry->get('Build')->getByProjectId($this->getId());
    }

    /**
     * Return the latest build from a specific branch, of a specific status, for this project.
     *
     * @param string $branch
     * @param null   $status
     *
     * @return mixed|null
     */
    public function getLatestBuild($branch, $status = null)
    {
        $criteria = [
            'branch'     => $branch,
            'project_id' => $this->getId()
        ];

        if (isset($status)) {
            $criteria['status'] = $status;
        }

        $order  = ['id' => 'DESC'];
        $builds = $this->storeRegistry->get('Build')->getWhere($criteria, 1, 0, $order);

        if (is_array($builds['items']) && count($builds['items'])) {
            $latest = array_shift($builds['items']);

            if (isset($latest) && $latest instanceof Build) {
                return $latest;
            }
        }

        return null;
    }

    /**
     * Return the previous build from a specific branch, for this project.
     *
     * @param string $branch
     *
     * @return mixed|null
     */
    public function getPreviousBuild($branch)
    {
        $criteria = [
            'branch'     => $branch,
            'project_id' => $this->getId()
        ];
        $order  = ['id' => 'DESC'];
        $builds = $this->storeRegistry->get('Build')->getWhere($criteria, 1, 1, $order);

        if (is_array($builds['items']) && count($builds['items'])) {
            $previous = array_shift($builds['items']);

            if (isset($previous) && $previous instanceof Build) {
                return $previous;
            }
        }

        return null;
    }

    /**
     * Return the name of a FontAwesome icon to represent this project, depending on its type.
     *
     * @return string
     */
    public function getIcon()
    {
        switch ($this->getType()) {
            case Project::TYPE_GITHUB:
                $icon = 'github';
                break;

            case Project::TYPE_BITBUCKET:
            case Project::TYPE_BITBUCKET_HG:
            case Project::TYPE_BITBUCKET_SERVER:
                $icon = 'bitbucket';
                break;

            case Project::TYPE_GIT:
            case Project::TYPE_GITLAB:
            case Project::TYPE_GOGS:
            case Project::TYPE_HG:
            case Project::TYPE_SVN:
            default:
                $icon = 'code-fork';
                break;
        }

        return $icon;
    }

    /**
     * @return EnvironmentStore
     */
    protected function getEnvironmentStore()
    {
        return $this->storeRegistry->get('Environment');
    }

    /**
     * Get Environments
     *
     * @return array contain items with \PHPCensor\Model\Environment
     */
    public function getEnvironmentsObjects()
    {
        $projectId = $this->getId();

        if (empty($projectId)) {
            return null;
        }

        return $this->getEnvironmentStore()->getByProjectId($projectId);
    }

    /**
     * Get Environments
     *
     * @return string[]
     */
    public function getEnvironmentsNames()
    {
        $environments      = $this->getEnvironmentsObjects();
        $environmentsNames = [];
        foreach ($environments['items'] as $environment) {
            /** @var Environment $environment */
            $environmentsNames[] = $environment->getName();
        }

        return $environmentsNames;
    }

    /**
     * Get Environments
     *
     * @return string yaml
     */
    public function getEnvironments()
    {
        $environments       = $this->getEnvironmentsObjects();
        $environmentsConfig = [];
        foreach ($environments['items'] as $environment) {
            /** @var Environment $environment */
            $environmentsConfig[$environment->getName()] = $environment->getBranches();
        }

        $yamlDumper = new YamlDumper();

        return $yamlDumper->dump($environmentsConfig, 10, 0);
    }

    /**
     * Set Environments
     *
     * @param string $value yaml
     */
    public function setEnvironments($value)
    {
        $yamlParser          = new YamlParser();
        $environmentsConfig  = $yamlParser->parse($value);
        $environmentsNames   = !empty($environmentsConfig) ? array_keys($environmentsConfig) : [];
        $currentEnvironments = $this->getEnvironmentsObjects();
        $store               = $this->getEnvironmentStore();
        foreach ($currentEnvironments['items'] as $environment) {
            /** @var Environment $environment */
            $key = array_search($environment->getName(), $environmentsNames);
            if ($key !== false) {
                // already exist
                unset($environmentsNames[$key]);
                $environment->setBranches(!empty($environmentsConfig[$environment->getName()]) ? $environmentsConfig[$environment->getName()] : []);
                $store->save($environment);
            } else {
                // remove
                $store->delete($environment);
            }
        }

        if (!empty($environmentsNames)) {
            // add
            foreach ($environmentsNames as $environmentName) {
                $environment = new Environment($this->storeRegistry);
                $environment->setProjectId($this->getId());
                $environment->setName($environmentName);
                $environment->setBranches(!empty($environmentsConfig[$environment->getName()]) ? $environmentsConfig[$environment->getName()] : []);
                $store->save($environment);
            }
        }
    }

    /**
     * @param string $branch
     *
     * @return string[]
     */
    public function getEnvironmentsNamesByBranch($branch)
    {
        $environmentsIds = [];
        $environments      = $this->getEnvironmentsObjects();
        $defaultBranch     = ($branch === $this->getDefaultBranch());
        foreach ($environments['items'] as $environment) {
            /** @var Environment $environment */
            if ($defaultBranch || in_array($branch, $environment->getBranches())) {
                $environmentsIds[] = $environment->getId();
            }
        }

        return $environmentsIds;
    }

    /**
     * @param int $environmentId
     *
     * @return string[]
     */
    public function getBranchesByEnvironment($environmentId)
    {
        $branches     = [];
        $environments = $this->getEnvironmentsObjects();
        foreach ($environments['items'] as $environment) {
            /** @var Environment $environment */
            if ($environmentId == $environment->getId()) {
                return $environment->getBranches();
            }
        }

        return $branches;
    }

    /**
     * @return int
     */
    public function getBuildPriority()
    {
        $config = $this->getBuildConfig();
        if (!$config) {
            return self::DEFAULT_BUILD_PRIORITY;
        }

        if (
            !isset($config['build_settings']['build_priority']) ||
            !(int)$config['build_settings']['build_priority']
        ) {
            return self::DEFAULT_BUILD_PRIORITY;
        }

        $priority = (int)$config['build_settings']['build_priority'];

        if ($priority > self::MAX_BUILD_PRIORITY) {
            return self::MAX_BUILD_PRIORITY;
        }

        if ($priority < self::MIN_BUILD_PRIORITY) {
            return self::MIN_BUILD_PRIORITY;
        }

        return $priority;
    }
}
