<?php

namespace PHPCensor\Controller;

use JasonGrimes\Paginator;
use PHPCensor\BuildFactory;
use PHPCensor\Exception\HttpException\NotFoundException;
use PHPCensor\Helper\AnsiConverter;
use PHPCensor\Helper\Lang;
use PHPCensor\Http\Response\JsonResponse;
use PHPCensor\Http\Response\RedirectResponse;
use PHPCensor\Model\Build;
use PHPCensor\Model\User;
use PHPCensor\Service\BuildService;
use PHPCensor\Store\BuildErrorStore;
use PHPCensor\Store\BuildStore;
use PHPCensor\Store\Factory;
use PHPCensor\Store\ProjectStore;
use PHPCensor\View;
use PHPCensor\WebController;

/**
 * Build Controller - Allows users to run and view builds.
 *
 * @author Dan Cryer <dan@block8.co.uk>
 */
class BuildController extends WebController
{
    /**
     * @var string
     */
    public $layoutName = 'layout';

    /**
     * @var BuildStore
     */
    protected $buildStore;

    /**
     * @var ProjectStore
     */
    protected $projectStore;

    /**
     * @var BuildService
     */
    protected $buildService;

    public function init()
    {
        parent::init();

        $this->buildStore   = Factory::getStore('Build');
        $this->projectStore = Factory::getStore('Project');

        $this->buildService = new BuildService($this->buildStore, $this->projectStore);
    }

    /**
     * View a specific build.
     *
     * @param int $buildId
     *
     * @throws NotFoundException
     */
    public function view($buildId)
    {
        $page   = (int)$this->getParam('page', 1);
        $plugin = $this->getParam('plugin', '');
        $isNew  = $this->getParam('is_new', '');

        $severity = $this->getParam('severity', null);
        if (null !== $severity && '' !== $severity) {
            $severity = (int)$severity;
        } else {
            $severity = null;
        }

        $build = BuildFactory::getBuildById($buildId);

        if (!$build) {
            throw new NotFoundException(Lang::get('build_x_not_found', $buildId));
        }

        /** @var User $user */
        $user    = $this->getUser();
        $perPage = $user->getFinalPerPage();
        $data    = $this->getBuildData($build, $plugin, $severity, $isNew, (($page - 1) * $perPage), $perPage);
        $pages   = ($data['errors'] === 0)
            ? 1
            : (int)ceil($data['errors'] / $perPage);

        if ($page > $pages) {
            $page = $pages;
        }

        /** @var BuildErrorStore $errorStore */
        $errorStore = Factory::getStore('BuildError');

        $this->view->uiPlugins = $this->getUiPlugins();
        $this->view->build     = $build;
        $this->view->data      = $data;

        $this->view->plugin     = urldecode($plugin);
        $this->view->plugins    = $errorStore->getKnownPlugins($buildId, $severity, $isNew);
        $this->view->severity   = urldecode(null !== $severity ? $severity : '');
        $this->view->severities = $errorStore->getKnownSeverities($buildId, $plugin, $isNew);
        $this->view->isNew      = urldecode($isNew);
        $this->view->isNews     = ['only_new', 'only_old'];

        $this->view->page      = $page;
        $this->view->perPage   = $perPage;
        $this->view->paginator = $this->getPaginatorHtml(
            $buildId,
            $plugin,
            $severity,
            $isNew,
            $data['errors'],
            $perPage,
            $page
        );

        $this->layout->title = Lang::get('build_n', $buildId);
        $this->layout->subtitle = $build->getProjectTitle();

        switch ($build->getStatus()) {
            case 0:
                $this->layout->skin = 'blue';
                break;

            case 1:
                $this->layout->skin = 'yellow';
                break;

            case 2:
                $this->layout->skin = 'green';
                break;

            case 3:
                $this->layout->skin = 'red';
                break;
        }

        $rebuild     = Lang::get('rebuild_now');
        $rebuildLink = APP_URL . 'build/rebuild/' . $build->getId();

        $rebuildDebug = Lang::get('rebuild_now_debug');

        $delete     = Lang::get('delete_build');
        $deleteLink = APP_URL . 'build/delete/' . $build->getId();

        $project = Factory::getStore('Project')->getByPrimaryKey($build->getProjectId());

        $actions = '';
        if (!$project->getArchived()) {
            if ($this->currentUserIsAdmin()) {
                $actions .= "<a class=\"btn btn-warning\" href=\"{$rebuildLink}?debug=1\">{$rebuildDebug}</a> ";
            }
            $actions .= "<a class=\"btn btn-success\" href=\"{$rebuildLink}\">{$rebuild}</a> ";
        }

        if ($this->currentUserIsAdmin()) {
            $actions .= " <a class=\"btn btn-danger\" id=\"delete-build\" href=\"{$deleteLink}\">{$delete}</a>";
        }

        $this->layout->actions = $actions;
    }

    /**
     * Returns an array of the JS plugins to include.
     * @return array
     */
    protected function getUiPlugins()
    {
        $rtn  = [];
        $path = PUBLIC_DIR . 'assets/js/build-plugins/';
        $dir  = opendir($path);

        while ($item = readdir($dir)) {
            if (substr($item, 0, 1) == '.' || substr($item, -3) != '.js') {
                continue;
            }

            $rtn[] = $item;
        }

        return $rtn;
    }

    /**
     * Get build data from database and json encode it.
     *
     * @param Build   $build
     * @param string  $plugin
     * @param int $severity
     * @param string  $isNew
     * @param int $start
     * @param int $perPage
     *
     * @return array
     */
    protected function getBuildData(Build $build, $plugin, $severity, $isNew, $start = 0, $perPage = 10)
    {
        $data                = [];
        $data['status']      = (int)$build->getStatus();
        $data['log']         = $this->cleanLog($build->getLog());

        $data['create_date'] = !is_null($build->getCreateDate())
            ? $build->getCreateDate()->format('Y-m-d H:i:s')
            : null;

        $data['start_date'] = !is_null($build->getStartDate())
            ? $build->getStartDate()->format('Y-m-d H:i:s')
            : null;

        $data['finish_date'] = !is_null($build->getFinishDate())
            ? $build->getFinishDate()->format('Y-m-d H:i:s')
            : null;

        $data['duration'] = $build->getDuration();

        /** @var BuildErrorStore $errorStore */
        $errorStore = Factory::getStore('BuildError');
        $errors     = $errorStore->getByBuildId($build->getId(), $perPage, $start, $plugin, $severity, $isNew);

        $errorView         = new View('Build/errors');
        $errorView->build  = $build;
        $errorView->errors = $errors['items'];

        $data['errors']       = $build->getTotalErrorsCount($plugin, $severity, $isNew);
        $data['errors_total'] = (int)$build->getErrorsTotal();
        $data['error_html']   = $errorView->render();

        return $data;
    }

    /**
     * @param int $buildId
     * @param string  $plugin
     * @param int $severity
     * @param string  $isNew
     * @param int $total
     * @param int $perPage
     * @param int $page
     *
     * @return string
     */
    protected function getPaginatorHtml($buildId, $plugin, $severity, $isNew, $total, $perPage, $page)
    {
        $view = new View('pagination');

        $urlPattern = APP_URL . 'build/view/' . $buildId;
        $params     = [];
        if (!empty($plugin)) {
            $params['plugin'] = $plugin;
        }

        if (null !== $severity) {
            $params['severity'] = $severity;
        }

        if (!empty($isNew)) {
            $params['is_new'] = $isNew;
        }

        $urlPattern = $urlPattern . '?' . str_replace(
            '%28%3Anum%29',
            '(:num)',
            http_build_query(array_merge($params, ['page' => '(:num)']))
        ) . '#errors';
        $paginator = new Paginator($total, $perPage, $page, $urlPattern);

        $view->paginator = $paginator;

        return $view->render();
    }

    /**
     * Create a build using an existing build as a template:
     *
     * @param $buildId
     *
     * @return RedirectResponse
     * @throws NotFoundException
     */
    public function rebuild($buildId)
    {
        $copy    = BuildFactory::getBuildById($buildId);
        $project = Factory::getStore('Project')->getByPrimaryKey($copy->getProjectId());

        if (!$copy || $project->getArchived()) {
            throw new NotFoundException(Lang::get('build_x_not_found', $buildId));
        }

        $debug = (bool)$this->getParam('debug', false);

        if ($debug && $this->currentUserIsAdmin()) {
            $copy->addExtraValue('debug', true);
        } elseif (!$debug) {
            $copy->removeExtraValue('debug');
        }

        $build = $this->buildService->createDuplicateBuild($copy, Build::SOURCE_MANUAL_REBUILD_WEB);

        if ($this->buildService->queueError) {
            $_SESSION['global_error'] = Lang::get('add_to_queue_failed');
        }

        $response = new RedirectResponse();
        $response->setHeader('Location', APP_URL.'build/view/' . $build->getId());

        return $response;
    }

    /**
    * Delete a build.
    */
    public function delete($buildId)
    {
        $this->requireAdmin();

        $build = BuildFactory::getBuildById($buildId);

        if (!$build) {
            throw new NotFoundException(Lang::get('build_x_not_found', $buildId));
        }

        $this->buildService->deleteBuild($build);

        $response = new RedirectResponse();
        $response->setHeader('Location', APP_URL.'project/view/' . $build->getProjectId());

        return $response;
    }

    /**
    * Parse log for unix colours and replace with HTML.
    */
    protected function cleanLog($log)
    {
        return AnsiConverter::convert($log);
    }

    /**
     * Formats a list of builds into rows suitable for the dropdowns in the header bar.
     *
     * @param $builds
     *
     * @return array
     */
    protected function formatBuilds($builds)
    {
        $rtn = ['count' => $builds['count'], 'items' => []];

        /** @var Build $build */
        foreach ($builds['items'] as $build) {
            $header        = new View('Build/header-row');
            $header->build = $build;

            $rtn['items'][$build->getId()]['header_row'] = $header->render();
        }

        ksort($rtn['items']);
        return $rtn;
    }

    public function ajaxData($buildId)
    {
        $page    = (int)$this->getParam('page', 1);
        $perPage = (int)$this->getParam('per_page', 10);
        $plugin  = $this->getParam('plugin', null);
        $isNew   = $this->getParam('is_new', null);

        $severity = $this->getParam('severity', null);
        if (null !== $severity && '' !== $severity) {
            $severity = (int)$severity;
        } else {
            $severity = null;
        }

        $response = new JsonResponse();
        $build = BuildFactory::getBuildById($buildId);

        if (!$build) {
            $response->setResponseCode(404);
            $response->setContent([]);

            return $response;
        }

        $data = $this->getBuildData(
            $build,
            $plugin,
            $severity,
            $isNew,
            (($page - 1) * $perPage),
            $perPage
        );

        $data['paginator'] = $this->getPaginatorHtml(
            $buildId,
            $plugin,
            $severity,
            $isNew,
            $data['errors'],
            $perPage,
            $page
        );

        $response->setContent($data);

        return $response;
    }

    public function ajaxMeta($buildId)
    {
        $build     = BuildFactory::getBuildById($buildId);

        $key       = $this->getParam('key', null);
        $plugin    = $this->getParam('plugin', null);
        $numBuilds = $this->getParam('num_builds', 1);
        $data      = null;

        if ($key && $build) {
            $data = $this->buildStore->getMeta($key, $plugin, $build->getProjectId(), $buildId, $build->getBranch(), $numBuilds);
        }

        $response = new JsonResponse();
        $response->setContent($data);

        return $response;
    }

    public function ajaxQueue()
    {
        $rtn = [
            'pending' => $this->formatBuilds($this->buildStore->getByStatus(Build::STATUS_PENDING)),
            'running' => $this->formatBuilds($this->buildStore->getByStatus(Build::STATUS_RUNNING)),
        ];

        $response = new JsonResponse();
        $response->setContent($rtn);

        return $response;
    }
}
