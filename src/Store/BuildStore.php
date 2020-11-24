<?php

namespace PHPCensor\Store;

use Exception;
use PDO;
use PHPCensor\Database;
use PHPCensor\Exception\HttpException;
use PHPCensor\Model\Build;
use PHPCensor\Model\BuildMeta;
use PHPCensor\Store;

/**
 * @author Dan Cryer <dan@block8.co.uk>
 */
class BuildStore extends Store
{
    /**
     * @var string
     */
    protected $tableName  = 'builds';

    /**
     * @var string
     */
    protected $modelName  = '\PHPCensor\Model\Build';

    /**
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Get a Build by primary key (Id)
     *
     * @param int $key
     * @param string  $useConnection
     *
     * @return null|Build
     */
    public function getByPrimaryKey($key, $useConnection = 'read')
    {
        return $this->getById($key, $useConnection);
    }

    /**
     * Get a single Build by Id.
     *
     * @param int $id
     * @param string  $useConnection
     *
     * @return Build|null
     *
     * @throws HttpException
     */
    public function getById($id, $useConnection = 'read')
    {
        if (is_null($id)) {
            throw new HttpException('Value passed to ' . __FUNCTION__ . ' cannot be null.');
        }

        $query = 'SELECT * FROM {{' . $this->tableName . '}} WHERE {{id}} = :id LIMIT 1';
        $stmt = Database::getConnection($useConnection)->prepareCommon($query);
        $stmt->bindValue(':id', $id);

        if ($stmt->execute()) {
            if ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
                return new Build($data);
            }
        }

        return null;
    }

    /**
     * Get multiple Build by ProjectId.
     *
     * @param int $projectId
     * @param int $limit
     * @param string  $useConnection
     *
     * @return array
     *
     * @throws HttpException
     */
    public function getByProjectId($projectId, $limit = 1000, $useConnection = 'read')
    {
        if (is_null($projectId)) {
            throw new HttpException('Value passed to ' . __FUNCTION__ . ' cannot be null.');
        }

        $query = 'SELECT * FROM {{' . $this->tableName . '}} WHERE {{project_id}} = :project_id LIMIT :limit';
        $stmt = Database::getConnection($useConnection)->prepareCommon($query);
        $stmt->bindValue(':project_id', $projectId);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $res = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $map = function ($item) {
                return new Build($item);
            };
            $rtn = array_map($map, $res);

            $count = count($rtn);

            return ['items' => $rtn, 'count' => $count];
        } else {
            return ['items' => [], 'count' => 0];
        }
    }

    /**
     * Get multiple Build by Status.
     *
     * @param int $status
     * @param int $limit
     * @param string  $useConnection
     *
     * @return array
     *
     * @throws HttpException
     */
    public function getByStatus($status, $limit = 1000, $useConnection = 'read')
    {
        if (is_null($status)) {
            throw new HttpException('Value passed to ' . __FUNCTION__ . ' cannot be null.');
        }

        $query = 'SELECT * FROM {{' . $this->tableName . '}} WHERE {{status}} = :status ORDER BY {{create_date}} ASC LIMIT :limit';
        $stmt = Database::getConnection($useConnection)->prepareCommon($query);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $res = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $map = function ($item) {
                return new Build($item);
            };
            $rtn = array_map($map, $res);

            $count = count($rtn);

            return ['items' => $rtn, 'count' => $count];
        } else {
            return ['items' => [], 'count' => 0];
        }
    }

    /**
     * @param int $limit
     * @param int $offset
     *
     * @return array
     */
    public function getBuilds($limit = 5, $offset = 0)
    {
        $query = 'SELECT * FROM {{' . $this->tableName . '}} ORDER BY {{id}} DESC LIMIT :limit OFFSET :offset';
        $stmt  = Database::getConnection('read')->prepareCommon($query);

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $res = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $map = function ($item) {
                return new Build($item);
            };
            $rtn = array_map($map, $res);

            return $rtn;
        } else {
            return [];
        }
    }

    /**
     * @param int $projectId
     * @param string $branch
     *
     * @return null|Build
     *
     * @throws Exception
     */
    public function getLatestBuildByProjectAndBranch($projectId, $branch)
    {
        $query = 'SELECT * FROM {{' . $this->tableName . '}} WHERE {{project_id}} = :project_id AND {{branch}} = :branch ORDER BY {{id}} DESC';
        $stmt  = Database::getConnection('read')->prepareCommon($query);

        $stmt->bindValue(':project_id', $projectId);
        $stmt->bindValue(':branch', $branch);

        if ($stmt->execute() && $data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return new Build($data);
        }

        return null;
    }

    /**
     * Return an array of the latest builds for a given project.
     *
     * @param int $projectId
     * @param int $limit
     *
     * @return array
     *
     * @throws Exception
     */
    public function getLatestBuilds($projectId = null, $limit = 5)
    {
        if (!is_null($projectId)) {
            $query = 'SELECT * FROM {{' . $this->tableName . '}} WHERE {{project_id}} = :pid ORDER BY {{id}} DESC LIMIT :limit';
        } else {
            $query = 'SELECT * FROM {{' . $this->tableName . '}} ORDER BY {{id}} DESC LIMIT :limit';
        }

        $stmt = Database::getConnection('read')->prepareCommon($query);

        if (!is_null($projectId)) {
            $stmt->bindValue(':pid', $projectId);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $res = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $map = function ($item) {
                return new Build($item);
            };
            $rtn = array_map($map, $res);

            return $rtn;
        } else {
            return [];
        }
    }

    /**
     * Return the latest build for a specific project, of a specific build status.
     *
     * @param int|null $projectId
     * @param int      $status
     *
     * @return array|Build
     */
    public function getLastBuildByStatus($projectId = null, $status = Build::STATUS_SUCCESS)
    {
        $query = 'SELECT * FROM {{' . $this->tableName . '}} WHERE {{project_id}} = :pid AND {{status}} = :status ORDER BY {{id}} DESC LIMIT 1';
        $stmt = Database::getConnection('read')->prepareCommon($query);
        $stmt->bindValue(':pid', $projectId);
        $stmt->bindValue(':status', $status);

        if ($stmt->execute()) {
            if ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
                return new Build($data);
            }
        } else {
            return [];
        }
    }

    /**
     * Return an array of the latest builds for all projects.
     *
     * @param int $limitByProject
     * @param int $limitAll
     *
     * @return array
     */
    public function getAllProjectsLatestBuilds($limitByProject = 5, $limitAll = 10)
    {
        // don't fetch log field - contain many data
        $query = '
            SELECT
                {{id}},
                {{project_id}},
                {{commit_id}},
                {{status}},
                {{branch}},
                {{create_date}},
                {{start_date}},
                {{finish_date}},
                {{committer_email}},
                {{commit_message}},
                {{extra}},
                {{environment_id}},
                {{tag}}
            FROM {{' . $this->tableName . '}}
            ORDER BY {{id}} DESC
            LIMIT 10000
        ';

        $stmt = Database::getConnection('read')->prepareCommon($query);

        if ($stmt->execute()) {
            $res = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $projects = [];
            $latest   = [];
            foreach ($res as $item) {
                $projectId = $item['project_id'];
                $environment = $item['environment_id'];
                if (empty($projects[$projectId])) {
                    $projects[$projectId] = [];
                }
                if (empty($projects[$projectId][$environment])) {
                    $projects[$projectId][$environment] = [
                        'latest' => [],
                        'success' => null,
                        'failed' => null,
                    ];
                }
                $build = null;
                if (count($projects[$projectId][$environment]['latest']) < $limitByProject) {
                    $build = new Build($item);
                    $projects[$projectId][$environment]['latest'][] = $build;
                }
                if (count($latest) < $limitAll) {
                    if (is_null($build)) {
                        $build = new Build($item);
                    }
                    $latest[] = $build;
                }
                if (empty($projects[$projectId][$environment]['success']) && Build::STATUS_SUCCESS === $item['status']) {
                    if (is_null($build)) {
                        $build = new Build($item);
                    }
                    $projects[$projectId][$environment]['success'] = $build;
                }
                if (empty($projects[$projectId][$environment]['failed']) && Build::STATUS_FAILED === $item['status']) {
                    if (is_null($build)) {
                        $build = new Build($item);
                    }
                    $projects[$projectId][$environment]['failed'] = $build;
                }
            }

            foreach ($projects as $idx => $project) {
                $projects[$idx] = array_filter($project, function ($val) {
                    return ($val['latest'][0]->getStatus() != Build::STATUS_SUCCESS);
                });
            }

            $projects = array_filter($projects);

            return ['projects' => $projects, 'latest' => $latest];
        } else {
            return [];
        }
    }

    /**
     * Return an array of builds for a given project and commit ID.
     *
     * @param int $projectId
     * @param string  $commitId
     *
     * @return array
     */
    public function getByProjectAndCommit($projectId, $commitId)
    {
        $query = 'SELECT * FROM {{' . $this->tableName . '}} WHERE {{project_id}} = :project_id AND {{commit_id}} = :commit_id';
        $stmt  = Database::getConnection('read')->prepareCommon($query);

        $stmt->bindValue(':project_id', $projectId);
        $stmt->bindValue(':commit_id', $commitId);

        if ($stmt->execute()) {
            $res = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $map = function ($item) {
                return new Build($item);
            };

            $rtn = array_map($map, $res);

            return ['items' => $rtn, 'count' => count($rtn)];
        } else {
            return ['items' => [], 'count' => 0];
        }
    }

    /**
     * Returns all registered branches for project
     *
     * @param int $projectId
     *
     * @return array
     *
     * @throws Exception
     */
    public function getBuildBranches($projectId)
    {
        $query = 'SELECT DISTINCT {{branch}} FROM {{' . $this->tableName . '}} WHERE {{project_id}} = :project_id';
        $stmt = Database::getConnection('read')->prepareCommon($query);
        $stmt->bindValue(':project_id', $projectId);

        if ($stmt->execute()) {
            $res = $stmt->fetchAll(PDO::FETCH_COLUMN);
            return $res;
        } else {
            return [];
        }
    }

    /**
     * Return build metadata by key, project and optionally build id.
     *
     * @param string       $key
     * @param string|null  $plugin
     * @param int          $projectId
     * @param int|null     $buildId
     * @param string|null  $branch
     * @param int          $numResults
     *
     * @return array|null
     */
    public function getMeta($key, $plugin, $projectId, $buildId = null, $branch = null, $numResults = 1)
    {
        $query = 'SELECT bm.build_id, bm.key, bm.value
                    FROM {{build_metas}} AS {{bm}}
                    LEFT JOIN {{' . $this->tableName . '}} AS {{b}} ON b.id = bm.build_id
                    WHERE bm.key = :key AND bm.plugin = :plugin AND b.project_id = :projectId';

        // If we're getting comparative meta data, include previous builds
        // otherwise just include the specified build ID:
        if ($numResults > 1) {
            $query .= ' AND bm.build_id <= :buildId ';
        } else {
            $query .= ' AND bm.build_id = :buildId ';
        }

        // Include specific branch information if required:
        if (!is_null($branch)) {
            $query .= ' AND b.branch = :branch ';
        }

        $query .= ' ORDER BY bm.id DESC LIMIT :numResults';

        $stmt = Database::getConnection('read')->prepareCommon($query);
        $stmt->bindValue(':key', $key, PDO::PARAM_STR);
        $stmt->bindValue(':plugin', $plugin);
        $stmt->bindValue(':projectId', (int)$projectId, PDO::PARAM_INT);
        $stmt->bindValue(':buildId', (int)$buildId, PDO::PARAM_INT);
        $stmt->bindValue(':numResults', (int)$numResults, PDO::PARAM_INT);

        if (!is_null($branch)) {
            $stmt->bindValue(':branch', $branch, PDO::PARAM_STR);
        }

        if ($stmt->execute()) {
            $rtn = $stmt->fetchAll(PDO::FETCH_ASSOC);

            /** @var BuildErrorStore $errorStore */
            $errorStore = Factory::getStore('BuildError');

            $rtn = array_reverse($rtn);
            $rtn = array_map(function ($item) use ($key, $plugin, $errorStore, $buildId) {
                $item['value'] = json_decode($item['value'], true);
                if (BuildMeta::KEY_SUMMARY === $key && null === $plugin) {
                    foreach ($item['value'] as $stage => $stageData) {
                        foreach ($stageData as $pluginDatum => $pluginData) {
                            $item['value'][$stage][$pluginDatum]['errors'] = $errorStore->getErrorTotalForBuild(
                                $buildId,
                                $pluginDatum
                            );
                        }
                    }
                }

                return $item;
            }, $rtn);

            if (!count($rtn)) {
                return null;
            } else {
                return $rtn;
            }
        } else {
            return null;
        }
    }

    /**
     * Set a metadata value for a given project and build ID.
     *
     * @param int         $buildId
     * @param string      $key
     * @param string|null $plugin
     * @param string      $value
     */
    public function setMeta($buildId, $plugin, $key, $value)
    {
        /** @var BuildMetaStore $store */
        $store = Factory::getStore('BuildMeta');
        $meta  = $store->getByPluginAndKey($buildId, $plugin, $key);
        if (is_null($meta)) {
            $meta = new BuildMeta();
            $meta->setBuildId($buildId);
            $meta->setKey($key);
        }
        $meta->setValue($value);

        $store->save($meta);
    }

    public function deleteAllByProject($projectId)
    {
        $q = Database::getConnection('write')
            ->prepareCommon(
                'DELETE FROM {{' . $this->tableName . '}} WHERE {{project_id}} = :project_id'
            );
        $q->bindValue(':project_id', $projectId);
        $q->execute();

        return $q->rowCount();
    }

    /**
     * @param int $projectId
     * @param int $keep
     *
     * @return array
     *
     * @throws HttpException
     */
    public function getOldByProject($projectId, $keep = 100)
    {
        if (is_null($projectId)) {
            throw new HttpException('Value passed to ' . __FUNCTION__ . ' cannot be null.');
        }

        $query = 'SELECT * FROM {{' . $this->tableName . '}} WHERE {{project_id}} = :project_id ORDER BY {{create_date}} DESC LIMIT 1000000 OFFSET :keep';
        $stmt = Database::getConnection('read')->prepareCommon($query);
        $stmt->bindValue(':project_id', $projectId);
        $stmt->bindValue(':keep', (int)$keep, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $res = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $map = function ($item) {
                return new Build($item);
            };
            $rtn = array_map($map, $res);

            $count = count($rtn);

            return ['items' => $rtn, 'count' => $count];
        }

        return ['items' => [], 'count' => 0];
    }

    /**
     * @param int $buildId
     *
     * @return int
     *
     * @throws Exception
     */
    public function getNewErrorsCount($buildId)
    {
        $query = 'SELECT COUNT(*) AS {{total}} FROM {{build_errors}} WHERE {{build_id}} = :build_id AND {{is_new}} = true';

        $stmt = Database::getConnection('read')->prepareCommon($query);

        $stmt->bindValue(':build_id', $buildId);

        if ($stmt->execute()) {
            $res = $stmt->fetch(PDO::FETCH_ASSOC);

            return (int)$res['total'];
        }

        return 0;
    }

    /**
     * @param int $buildId
     *
     * @return int
     *
     * @throws Exception
     */
    public function getErrorsCount($buildId)
    {
        $query = 'SELECT COUNT(*) AS {{total}} FROM {{build_errors}} WHERE {{build_id}} = :build_id';

        $stmt = Database::getConnection('read')->prepareCommon($query);

        $stmt->bindValue(':build_id', $buildId);

        if ($stmt->execute()) {
            $res = $stmt->fetch(PDO::FETCH_ASSOC);

            return (int)$res['total'];
        }

        return 0;
    }

    /**
     * @param int    $buildId
     * @param int    $projectId
     * @param string $branch
     *
     * @return array
     *
     * @throws Exception
     */
    public function getBuildErrorsTrend($buildId, $projectId, $branch)
    {
        $query = '
SELECT b.id AS {{build_id}}, count(be.id) AS {{count}} FROM {{' . $this->tableName . '}} AS b
LEFT JOIN {{build_errors}} AS be
ON b.id = be.build_id
WHERE b.project_id = :project_id AND b.branch = :branch AND b.id <= :build_id
GROUP BY b.id
order BY b.id DESC
LIMIT 2';

        $stmt = Database::getConnection('read')->prepareCommon($query);

        $stmt->bindValue(':build_id', $buildId, PDO::PARAM_INT);
        $stmt->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $stmt->bindValue(':branch', $branch, PDO::PARAM_STR);

        if ($stmt->execute()) {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return [];
    }
}
