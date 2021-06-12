<?php

declare(strict_types = 1);

namespace PHPCensor;

use PHPCensor\Common\Build\BuildInterface;
use PHPCensor\Common\Project\ProjectInterface;
use PHPCensor\Common\VariableInterpolatorInterface;

/**
 * @package    PHP Censor
 * @subpackage Application
 *
 * @author Dmitry Khomutov <poisoncorpsee@gmail.com>
 */
class VariableInterpolator implements VariableInterpolatorInterface
{
    private array $variables = [];

    public function __construct(
        BuildInterface $build,
        ProjectInterface $project,
        string $applicationVersion
    ) {
        $this->initVariables($build, $project, $applicationVersion);
        $this->initEnvironmentVariables();
    }

    /**
     * {@inheritdoc}
     */
    public function interpolate(string $string): string
    {
        $string = $this->realtimeInterpolate($string);

        $keys   = \array_keys($this->variables);
        $values = \array_values($this->variables);

        return \str_replace($keys, $values, $string);
    }

    private function realtimeInterpolate(string $string): string
    {
        $string = \str_replace('%CURRENT_DATE%', \date('Y-m-d'), $string);
        $string = \str_replace('%CURRENT_TIME%', \date('H-i-s'), $string);
        $string = \str_replace('%CURRENT_DATETIME%', \date('Y-m-d_H-i-s'), $string);

        return $string;
    }

    private function initVariables(
        BuildInterface $build,
        ProjectInterface $project,
        string $applicationVersion
    ): void {
        $this->variables = [
            '%COMMIT_ID%'       => $build->getCommitId(),
            '%SHORT_COMMIT_ID%' => \substr($build->getCommitId(), 0, 7),
            '%COMMITTER_EMAIL%' => $build->getCommitterEmail(),
            '%COMMIT_MESSAGE%'  => $build->getCommitMessage(),
            '%COMMIT_LINK%'     => $build->getCommitLink(),
            '%PROJECT_ID%'      => $project->getId(),
            '%PROJECT_TITLE%'   => $project->getTitle(),
            '%PROJECT_LINK%'    => $project->getLink(),
            '%BUILD_ID%'        => $build->getId(),
            '%BUILD_PATH%'      => $build->getBuildPath(),
            '%BUILD_LINK%'      => $build->getLink(),
            '%BRANCH%'          => $build->getBranch(),
            '%BRANCH_LINK%'     => $build->getBranchLink(),
            '%ENVIRONMENT%'     => $build->getEnvironment(),
            '%SYSTEM_VERSION%'  => $applicationVersion,
        ];
    }

    private function initEnvironmentVariables(): void
    {
        \putenv('PHP_CENSOR=1');
        \putenv('PHP_CENSOR_COMMIT_ID=' . $this->variables['%COMMIT_ID%']);
        \putenv('PHP_CENSOR_SHORT_COMMIT_ID=' . $this->variables['%SHORT_COMMIT_ID%']);
        \putenv('PHP_CENSOR_COMMITTER_EMAIL=' . $this->variables['%COMMITTER_EMAIL%']);
        \putenv('PHP_CENSOR_COMMIT_MESSAGE=' . $this->variables['%COMMIT_MESSAGE%']);
        \putenv('PHP_CENSOR_COMMIT_LINK=' . $this->variables['%COMMIT_LINK%']);
        \putenv('PHP_CENSOR_PROJECT_ID=' . $this->variables['%PROJECT_ID%']);
        \putenv('PHP_CENSOR_PROJECT_TITLE=' . $this->variables['%PROJECT_TITLE%']);
        \putenv('PHP_CENSOR_PROJECT_LINK=' . $this->variables['%PROJECT_LINK%']);
        \putenv('PHP_CENSOR_BUILD_ID=' . $this->variables['%BUILD_ID%']);
        \putenv('PHP_CENSOR_BUILD_PATH=' . $this->variables['%BUILD_PATH%']);
        \putenv('PHP_CENSOR_BUILD_LINK=' . $this->variables['%BUILD_LINK%']);
        \putenv('PHP_CENSOR_BRANCH=' . $this->variables['%BRANCH%']);
        \putenv('PHP_CENSOR_BRANCH_LINK=' . $this->variables['%BRANCH_LINK%']);
        \putenv('PHP_CENSOR_ENVIRONMENT=' . $this->variables['%ENVIRONMENT%']);
        \putenv('PHP_CENSOR_SYSTEM_VERSION=' . $this->variables['%SYSTEM_VERSION%']);
    }
}
