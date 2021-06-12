<?php

namespace PHPCensor\Helper;

use Exception;
use PHPCensor\Model\Build as BaseBuild;
use PHPCensor\Store\EnvironmentStore;
use PHPCensor\StoreRegistry;

class BuildInterpolator
{
    /**
     * An array of key => value pairs that will be used for
     * interpolation and environment variables
     *
     * @var mixed[]
     *
     * @see setupInterpolationVars()
     */
    private array $interpolationVars = [];

    private StoreRegistry $storeRegistry;

    public function __construct(StoreRegistry $storeRegistry)
    {
        $this->storeRegistry = $storeRegistry;
    }

    /**
     * Sets the variables that will be used for interpolation.
     *
     * @param BaseBuild $build
     * @param string    $url
     * @param string    $applicationVersion
     *
     * @throws Exception
     */
    public function setupInterpolationVars(BaseBuild $build, $url, $applicationVersion)
    {
        $this->interpolationVars = [];

        $this->interpolationVars['%COMMIT_ID%']       = $build->getCommitId();
        $this->interpolationVars['%SHORT_COMMIT_ID%'] = substr($build->getCommitId(), 0, 7);
        $this->interpolationVars['%PROJECT_ID%']      = $build->getProjectId();
        $this->interpolationVars['%BUILD_ID%']        = $build->getId();
        $this->interpolationVars['%COMMITTER_EMAIL%'] = $build->getCommitterEmail();
        $this->interpolationVars['%COMMIT_MESSAGE%']  = $build->getCommitMessage();
        $this->interpolationVars['%COMMIT_LINK%']     = $build->getCommitLink();
        $this->interpolationVars['%PROJECT_TITLE%']   = $build->getProjectTitle();
        $this->interpolationVars['%PROJECT_LINK%']    = $url . 'project/view/' . $build->getProjectId();
        $this->interpolationVars['%BUILD_PATH%']      = $build->getBuildPath();
        $this->interpolationVars['%BUILD_LINK%']      = $url . 'build/view/' . $build->getId();
        $this->interpolationVars['%BRANCH%']          = $build->getBranch();
        $this->interpolationVars['%BRANCH_LINK%']     = $build->getBranchLink();

        $environmentId = $build->getEnvironmentId();
        $environment   = null;
        if ($environmentId) {
            /** @var EnvironmentStore $environmentStore */
            $environmentStore  = $this->storeRegistry->get('Environment');
            $environmentObject = $environmentStore->getById($environmentId);
            if ($environmentObject) {
                $environment = $environmentObject->getName();
            }
        }

        $this->interpolationVars['%ENVIRONMENT%']    = $environment;
        $this->interpolationVars['%SYSTEM_VERSION%'] = $applicationVersion;

        putenv('PHP_CENSOR=1');
        putenv('PHP_CENSOR_COMMIT_ID=' . $this->interpolationVars['%COMMIT_ID%']);
        putenv('PHP_CENSOR_SHORT_COMMIT_ID=' . $this->interpolationVars['%SHORT_COMMIT_ID%']);
        putenv('PHP_CENSOR_COMMITTER_EMAIL=' . $this->interpolationVars['%COMMITTER_EMAIL%']);
        putenv('PHP_CENSOR_COMMIT_MESSAGE=' . $this->interpolationVars['%COMMIT_MESSAGE%']);
        putenv('PHP_CENSOR_COMMIT_LINK=' . $this->interpolationVars['%COMMIT_LINK%']);
        putenv('PHP_CENSOR_PROJECT_ID=' . $this->interpolationVars['%PROJECT_ID%']);
        putenv('PHP_CENSOR_PROJECT_TITLE=' . $this->interpolationVars['%PROJECT_TITLE%']);
        putenv('PHP_CENSOR_PROJECT_LINK=' . $this->interpolationVars['%PROJECT_LINK%']);
        putenv('PHP_CENSOR_BUILD_ID=' . $this->interpolationVars['%BUILD_ID%']);
        putenv('PHP_CENSOR_BUILD_PATH=' . $this->interpolationVars['%BUILD_PATH%']);
        putenv('PHP_CENSOR_BUILD_LINK=' . $this->interpolationVars['%BUILD_LINK%']);
        putenv('PHP_CENSOR_BRANCH=' . $this->interpolationVars['%BRANCH%']);
        putenv('PHP_CENSOR_BRANCH_LINK=' . $this->interpolationVars['%BRANCH_LINK%']);
        putenv('PHP_CENSOR_ENVIRONMENT=' . $this->interpolationVars['%ENVIRONMENT%']);
        putenv('PHP_CENSOR_SYSTEM_VERSION=' . $this->interpolationVars['%SYSTEM_VERSION%']);
    }

    /**
     * @param string $input
     *
     * @return string
     */
    private function realtimeInterpolate($input)
    {
        $input = str_replace('%CURRENT_DATE%', \date('Y-m-d'), $input);
        $input = str_replace('%CURRENT_TIME%', \date('H-i-s'), $input);

        return str_replace('%CURRENT_DATETIME%', \date('Y-m-d_H-i-s'), $input);
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
        $input = $this->realtimeInterpolate($input);

        $keys   = array_keys($this->interpolationVars);
        $values = array_values($this->interpolationVars);

        return str_replace($keys, $values, $input);
    }
}
