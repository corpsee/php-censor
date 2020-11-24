<?php

namespace PHPCensor\Controller;

use Exception;
use PHPCensor\BuildFactory;
use PHPCensor\Exception\HttpException;
use PHPCensor\Exception\HttpException\NotFoundException;
use PHPCensor\Exception\InvalidArgumentException;
use PHPCensor\Http\Response;
use PHPCensor\Http\Response\RedirectResponse;
use PHPCensor\Model\Build;
use PHPCensor\Model\Project;
use PHPCensor\Service\BuildStatusService;
use PHPCensor\Store\BuildStore;
use PHPCensor\Store\Factory;
use PHPCensor\Store\ProjectStore;
use PHPCensor\WebController;
use SimpleXMLElement;

/**
 * Build Status Controller - Allows external access to build status information / images.
 *
 * @author Dan Cryer <dan@block8.co.uk>
 */
class BuildStatusController extends WebController
{
    /**
     * @var string
     */
    public $layoutName = 'layoutPublic';

    /**
     * @var ProjectStore
     */
    protected $projectStore;

    /**
     * @var BuildStore
     */
    protected $buildStore;

    /**
     * Returns status of the last build
     *
     * @param Project $project
     * @param string  $branch
     *
     * @return string
     */
    protected function getStatus(Project $project, $branch)
    {
        $status = 'passing';
        try {
            $build = $project->getLatestBuild($branch, [
                Build::STATUS_SUCCESS,
                Build::STATUS_FAILED,
            ]);

            if (isset($build) && $build instanceof Build && $build->getStatus() !== Build::STATUS_SUCCESS) {
                $status = 'failed';
            }
        } catch (Exception $e) {
            $status = 'error';
        }

        return $status;
    }

    /**
     * Returns coverage of the last build
     *
     * @param Project $project
     * @param string  $branch
     * @param string  $type
     *
     * @return string
     */
    protected function getPhpunitCoverage(Project $project, $branch, $type = 'lines')
    {
        $coverage = 0;
        if (!in_array($type, ['classes', 'methods', 'lines'], true)) {
            $type = 'lines';
        }

        try {
            $build = $project->getLatestBuild($branch, [
                Build::STATUS_SUCCESS,
                Build::STATUS_FAILED,
            ]);

            if (isset($build) && $build instanceof Build) {
                $coverageMeta = $this->buildStore->getMeta(
                    'php_unit-coverage',
                    $build->getProjectId(),
                    $build->getId(),
                    $build->getBranch()
                );

                if ($coverageMeta && isset($coverageMeta[0]['value'][$type])) {
                    $coverage = $coverageMeta[0]['value'][$type];
                }
            }
        } catch (Exception $e) {
        }

        return $coverage;
    }

    /**
     * @param SimpleXMLElement $xml
     *
     * @return Response
     */
    protected function renderXml(SimpleXMLElement $xml = null)
    {
        $response = new Response();

        $response->setHeader('Content-Type', 'text/xml');
        $response->setContent($xml->asXML());

        return $response;
    }

    /**
     * @param int    $projectId
     * @param string $branch
     *
     * @throws HttpException
     * @throws InvalidArgumentException
     *
     * @return array
     */
    protected function getLatestBuilds($projectId, $branch)
    {
        $criteria = [
            'project_id' => $projectId,
            'branch'     => $branch,
        ];

        $order  = ['id' => 'DESC'];
        $builds = $this->buildStore->getWhere($criteria, 10, 0, $order);

        foreach ($builds['items'] as &$build) {
            $build = BuildFactory::getBuild($build);
        }

        return $builds['items'];
    }

    public function init()
    {
        parent::init();

        $this->buildStore   = Factory::getStore('Build');
        $this->projectStore = Factory::getStore('Project');
    }

    /**
     * Returns the appropriate build PHPUnit coverage image in SVG format for a given project.
     *
     * @param $projectId
     *
     * @return Response
     */
    public function phpunitCoverageImage($projectId)
    {
        $project = $this->projectStore->getById($projectId);

        // plastic|flat|flat-squared|social
        $style  = $this->getParam('style', 'flat');
        $label  = $this->getParam('label', 'build');
        $type   = $this->getParam('type', 'lines');
        $branch = $this->getParam('branch', $project->getDefaultBranch());

        $optionalParams = [
            'logo'      => $this->getParam('logo'),
            'logoWidth' => $this->getParam('logoWidth'),
            'link'      => $this->getParam('link'),
            'maxAge'    => $this->getParam('maxAge'),
        ];

        $coverage = $this->getPhpunitCoverage($project, $branch, $type);
        $imageUrl = sprintf(
            'http://img.shields.io/badge/%s-%s-%s.svg?style=%s',
            $label,
            $coverage. '%25',
            'green',
            $style
        );

        foreach ($optionalParams as $paramName => $param) {
            if ($param) {
                $imageUrl .= '&' . $paramName . '=' . $param;
            }
        }

        $cacheDir  = RUNTIME_DIR . 'status_cache/';
        $cacheFile = $cacheDir . md5($imageUrl) . '.svg';
        if (!is_file($cacheFile)) {
            $image = file_get_contents($imageUrl);
            file_put_contents($cacheFile, $image);
        }

        $image = file_get_contents($cacheFile);

        $response = new Response();

        $response->setHeader('Content-Type', 'image/svg+xml');
        $response->setContent($image);

        return $response;
    }

    /**
     * Returns the appropriate build status image in SVG format for a given project.
     *
     * @param $projectId
     *
     * @return Response
     */
    public function image($projectId)
    {
        $project = $this->projectStore->getById($projectId);

        // plastic|flat|flat-squared|social
        $style  = $this->getParam('style', 'flat');
        $label  = $this->getParam('label', 'build');
        $branch = $this->getParam('branch', $project->getDefaultBranch());

        $optionalParams = [
            'logo'      => $this->getParam('logo'),
            'logoWidth' => $this->getParam('logoWidth'),
            'link'      => $this->getParam('link'),
            'maxAge'    => $this->getParam('maxAge'),
        ];

        $status = $this->getStatus($project, $branch);

        if (is_null($status)) {
            $response = new RedirectResponse();
            $response->setHeader('Location', '/');

            return $response;
        }

        $color    = ($status == 'passing') ? 'green' : 'red';
        $imageUrl = sprintf(
            'http://img.shields.io/badge/%s-%s-%s.svg?style=%s',
            $label,
            $status,
            $color,
            $style
        );

        foreach ($optionalParams as $paramName => $param) {
            if ($param) {
                $imageUrl .= '&' . $paramName . '=' . $param;
            }
        }

        $cacheDir  = RUNTIME_DIR . 'status_cache/';
        $cacheFile = $cacheDir . md5($imageUrl) . '.svg';
        if (!is_file($cacheFile)) {
            $image = file_get_contents($imageUrl);
            file_put_contents($cacheFile, $image);
        }

        $image = file_get_contents($cacheFile);

        $response = new Response();

        $response->setHeader('Content-Type', 'image/svg+xml');
        $response->setContent($image);

        return $response;
    }

    /**
     * View the public status page of a given project, if enabled.
     *
     * @param int $projectId
     *
     * @return string
     *
     * @throws HttpException
     * @throws InvalidArgumentException
     * @throws NotFoundException
     */
    public function view($projectId)
    {
        $project = $this->projectStore->getById($projectId);
        $branch  = $this->getParam('branch', $project->getDefaultBranch());

        if (empty($project) || !$project->getAllowPublicStatus()) {
            throw new NotFoundException('Project with id: ' . $projectId . ' not found');
        }

        $builds = $this->getLatestBuilds($projectId, $branch);

        if (count($builds)) {
            $this->view->latest = $builds[0];
        }

        $this->view->builds  = $builds;
        $this->view->project = $project;

        return $this->view->render();
    }

    /**
     * Displays projects information in ccmenu format
     *
     * @param $projectId
     *
     * @return Response
     *
     * @throws Exception
     */
    public function ccxml($projectId)
    {
        /* @var Project $project */
        $project = $this->projectStore->getById($projectId);
        $xml     = new SimpleXMLElement('<Projects/>');

        if (!$project instanceof Project || !$project->getAllowPublicStatus()) {
            return $this->renderXml($xml);
        }

        try {
            $branchList = $this->buildStore->getBuildBranches($projectId);

            if (!$branchList) {
                $branchList = [$project->getDefaultBranch()];
            }

            foreach ($branchList as $branch) {
                $buildStatusService = new BuildStatusService($branch, $project, $project->getLatestBuild($branch));
                if ($attributes = $buildStatusService->toArray()) {
                    $projectXml = $xml->addChild('Project');
                    foreach ($attributes as $attributeKey => $attributeValue) {
                        $projectXml->addAttribute($attributeKey, $attributeValue);
                    }
                }
            }
        } catch (Exception $e) {
            $xml = new SimpleXMLElement('<projects/>');
        }

        return $this->renderXml($xml);
    }
}
