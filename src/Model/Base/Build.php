<?php

namespace PHPCensor\Model\Base;

use DateTime;
use Exception;
use PHPCensor\Exception\InvalidArgumentException;
use PHPCensor\Model;
use PHPCensor\Store\BuildStore;
use PHPCensor\Store\Factory;

class Build extends Model
{
    const STATUS_PENDING = 0;
    const STATUS_RUNNING = 1;
    const STATUS_SUCCESS = 2;
    const STATUS_FAILED  = 3;

    const SOURCE_UNKNOWN                       = 0;
    const SOURCE_MANUAL_WEB                    = 1;
    const SOURCE_MANUAL_CONSOLE                = 2;
    const SOURCE_PERIODICAL                    = 3;
    const SOURCE_WEBHOOK_PUSH                  = 4;
    const SOURCE_WEBHOOK_PULL_REQUEST_CREATED  = 5;
    const SOURCE_WEBHOOK_PULL_REQUEST_UPDATED  = 6;
    const SOURCE_WEBHOOK_PULL_REQUEST_APPROVED = 7;
    const SOURCE_WEBHOOK_PULL_REQUEST_MERGED   = 8;
    const SOURCE_MANUAL_REBUILD_WEB            = 9;
    const SOURCE_MANUAL_REBUILD_CONSOLE        = 10;

    /**
     * @var array
     */
    protected $data = [
        'id'                    => null,
        'parent_id'             => null,
        'project_id'            => null,
        'commit_id'             => null,
        'status'                => null,
        'log'                   => null,
        'branch'                => null,
        'tag'                   => null,
        'create_date'           => null,
        'start_date'            => null,
        'finish_date'           => null,
        'committer_email'       => null,
        'commit_message'        => null,
        'extra'                 => null,
        'environment_id'        => null,
        'source'                => Build::SOURCE_UNKNOWN,
        'user_id'               => null,
        'errors_total'          => null,
        'errors_total_previous' => null,
        'errors_new'            => null,
    ];

    /**
     * @var array
     */
    protected $allowedStatuses = [
        self::STATUS_PENDING,
        self::STATUS_RUNNING,
        self::STATUS_SUCCESS,
        self::STATUS_FAILED,
    ];

    /**
     * @var array
     */
    protected $allowedSources = [
        self::SOURCE_UNKNOWN,
        self::SOURCE_MANUAL_WEB,
        self::SOURCE_MANUAL_CONSOLE,
        self::SOURCE_MANUAL_REBUILD_WEB,
        self::SOURCE_MANUAL_REBUILD_CONSOLE,
        self::SOURCE_PERIODICAL,
        self::SOURCE_WEBHOOK_PUSH,
        self::SOURCE_WEBHOOK_PULL_REQUEST_CREATED,
        self::SOURCE_WEBHOOK_PULL_REQUEST_UPDATED,
        self::SOURCE_WEBHOOK_PULL_REQUEST_APPROVED,
        self::SOURCE_WEBHOOK_PULL_REQUEST_MERGED,
    ];

    /**
     * @return int
     */
    public function getId()
    {
        return (int)$this->data['id'];
    }

    /**
     * @param int $value
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     */
    public function setId($value)
    {
        $this->validateNotNull('id', $value);
        $this->validateInt('id', $value);

        if ($this->data['id'] === $value) {
            return false;
        }

        $this->data['id'] = (int)$value;

        return $this->setModified('id');
    }

    /**
     * @return int|null
     */
    public function getParentId()
    {
        return (null !== $this->data['parent_id']) ? (int)$this->data['parent_id'] : null;
    }

    /**
     * @param int|null $value
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     */
    public function setParentId($value)
    {
        $this->validateInt('parent_id', $value);

        if ($this->data['parent_id'] === $value) {
            return false;
        }

        $this->data['parent_id'] = $value;

        return $this->setModified('parent_id');
    }

    /**
     * @return int
     */
    public function getProjectId()
    {
        return (int)$this->data['project_id'];
    }

    /**
     * @param int $value
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     */
    public function setProjectId($value)
    {
        $this->validateNotNull('project_id', $value);
        $this->validateInt('project_id', $value);

        if ($this->data['project_id'] === $value) {
            return false;
        }

        $this->data['project_id'] = $value;

        return $this->setModified('project_id');
    }

    /**
     * @return string
     */
    public function getCommitId()
    {
        return $this->data['commit_id'];
    }

    /**
     * @param string $value
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     */
    public function setCommitId($value)
    {
        $this->validateNotNull('commit_id', $value);
        $this->validateString('commit_id', $value);

        if ($this->data['commit_id'] === $value) {
            return false;
        }

        $this->data['commit_id'] = $value;

        return $this->setModified('commit_id');
    }

    /**
     * @return int
     */
    public function getStatus()
    {
        return (int)$this->data['status'];
    }

    /**
     * @param int $value
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     */
    public function setStatus($value)
    {
        $this->validateNotNull('status', $value);
        $this->validateInt('status', $value);

        if (!in_array($value, $this->allowedStatuses, true)) {
            throw new InvalidArgumentException(
                'Column "status" must be one of: ' . join(', ', $this->allowedStatuses) . '.'
            );
        }

        if ($this->data['status'] === $value) {
            return false;
        }

        $this->data['status'] = $value;

        return $this->setModified('status');
    }

    public function setStatusPending()
    {
        if (self::STATUS_PENDING !== $this->data['status']) {
            $this->setModified('status');
        }

        $this->data['status'] = self::STATUS_PENDING;
    }

    public function setStatusRunning()
    {
        if (self::STATUS_RUNNING !== $this->data['status']) {
            $this->setModified('status');
        }

        $this->data['status'] = self::STATUS_RUNNING;
    }

    public function setStatusSuccess()
    {
        if (self::STATUS_SUCCESS !== $this->data['status']) {
            $this->setModified('status');
        }

        $this->data['status'] = self::STATUS_SUCCESS;
    }

    public function setStatusFailed()
    {
        if (self::STATUS_FAILED !== $this->data['status']) {
            $this->setModified('status');
        }

        $this->data['status'] = self::STATUS_FAILED;
    }

    /**
     * @return string
     */
    public function getLog()
    {
        return $this->data['log'];
    }

    /**
     * @param string|null $value
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     */
    public function setLog($value)
    {
        $this->validateString('log', $value);

        if ($this->data['log'] === $value) {
            return false;
        }

        $this->data['log'] = $value;

        return $this->setModified('log');
    }

    /**
     * @return string
     */
    public function getBranch()
    {
        return $this->data['branch'];
    }

    /**
     * @param string $value
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     */
    public function setBranch($value)
    {
        $this->validateNotNull('branch', $value);
        $this->validateString('branch', $value);

        if ($this->data['branch'] === $value) {
            return false;
        }

        $this->data['branch'] = $value;

        return $this->setModified('branch');
    }

    /**
     * @return string
     */
    public function getTag()
    {
        return $this->data['tag'];
    }

    /**
     * @param string|null $value
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     */
    public function setTag($value)
    {
        $this->validateString('tag', $value);

        if ($this->data['tag'] === $value) {
            return false;
        }

        $this->data['tag'] = $value;

        return $this->setModified('tag');
    }

    /**
     * @return DateTime|null
     *
     * @throws Exception
     */
    public function getCreateDate()
    {
        if ($this->data['create_date']) {
            return new DateTime($this->data['create_date']);
        }

        return null;
    }

    /**
     * @param DateTime $value
     *
     * @return bool
     */
    public function setCreateDate(DateTime $value)
    {
        $stringValue = $value->format('Y-m-d H:i:s');

        if ($this->data['create_date'] === $stringValue) {
            return false;
        }

        $this->data['create_date'] = $stringValue;

        return $this->setModified('create_date');
    }

    /**
     * @return DateTime|null
     *
     * @throws Exception
     */
    public function getStartDate()
    {
        if ($this->data['start_date']) {
            return new DateTime($this->data['start_date']);
        }

        return null;
    }

    /**
     * @param DateTime $value
     *
     * @return bool
     */
    public function setStartDate(DateTime $value)
    {
        $stringValue = $value->format('Y-m-d H:i:s');

        if ($this->data['start_date'] === $stringValue) {
            return false;
        }

        $this->data['start_date'] = $stringValue;

        return $this->setModified('start_date');
    }

    /**
     * @return DateTime|null
     *
     * @throws Exception
     */
    public function getFinishDate()
    {
        if ($this->data['finish_date']) {
            return new DateTime($this->data['finish_date']);
        }

        return null;
    }

    /**
     * @param DateTime $value
     *
     * @return bool
     */
    public function setFinishDate(DateTime $value)
    {
        $stringValue = $value->format('Y-m-d H:i:s');

        if ($this->data['finish_date'] === $stringValue) {
            return false;
        }

        $this->data['finish_date'] = $stringValue;

        return $this->setModified('finish_date');
    }

    /**
     * @return string
     */
    public function getCommitterEmail()
    {
        return $this->data['committer_email'];
    }

    /**
     * @param string|null $value
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     */
    public function setCommitterEmail($value)
    {
        $this->validateString('committer_email', $value);

        if ($this->data['committer_email'] === $value) {
            return false;
        }

        $this->data['committer_email'] = $value;

        return $this->setModified('committer_email');
    }

    /**
     * @return string
     */
    public function getCommitMessage()
    {
        return $this->data['commit_message'];
    }

    /**
     * @param string|null $value
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     */
    public function setCommitMessage($value)
    {
        $this->validateString('commit_message', $value);

        if ($this->data['commit_message'] === $value) {
            return false;
        }

        $this->data['commit_message'] = $value;

        return $this->setModified('commit_message');
    }

    /**
     * @param string|null $key
     *
     * @return array|string|null
     */
    public function getExtra($key = null)
    {
        $data  = json_decode($this->data['extra'], true);
        $extra = null;
        if (is_null($key)) {
            $extra = $data;
        } elseif (isset($data[$key])) {
            $extra = $data[$key];
        }

        return $extra;
    }

    /**
     * @param array $value
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     */
    public function setExtra(array $value)
    {
        $this->validateNotNull('extra', $value);

        $extra = json_encode($value);
        if ($this->data['extra'] === $extra) {
            return false;
        }

        $this->data['extra'] = $extra;

        return $this->setModified('extra');
    }

    /**
     * @return int|null
     */
    public function getEnvironmentId()
    {
        return (null !== $this->data['environment_id']) ? (int)$this->data['environment_id'] : null;
    }

    /**
     * @param int|null $value
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     */
    public function setEnvironmentId($value)
    {
        $this->validateInt('environment_id', $value);

        if ($this->data['environment_id'] === $value) {
            return false;
        }

        $this->data['environment_id'] = $value;

        return $this->setModified('environment_id');
    }

    /**
     * @return int
     */
    public function getSource()
    {
        return (int)$this->data['source'];
    }

    /**
     * @param int $value
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     */
    public function setSource($value)
    {
        $this->validateInt('source', $value);

        if (!in_array($value, $this->allowedSources, true)) {
            throw new InvalidArgumentException(
                'Column "source" must be one of: ' . \join(', ', $this->allowedSources) . '.'
            );
        }

        if ($this->data['source'] === $value) {
            return false;
        }

        $this->data['source'] = $value;

        return $this->setModified('source');
    }

    /**
     * @return int|null
     */
    public function getUserId()
    {
        return (null !== $this->data['user_id']) ? (int)$this->data['user_id'] : null;
    }

    /**
     * @param int|null $value
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     */
    public function setUserId($value)
    {
        $this->validateInt('user_id', $value);

        if ($this->data['user_id'] === $value) {
            return false;
        }

        $this->data['user_id'] = $value;

        return $this->setModified('user_id');
    }

    /**
     * @return int
     *
     * @throws InvalidArgumentException
     */
    public function getErrorsTotal()
    {
        if (null === $this->data['errors_total'] &&
            !\in_array($this->getStatus(), [self::STATUS_PENDING, self::STATUS_RUNNING], true)) {
            /** @var BuildStore $store */
            $store = Factory::getStore('Build');

            $this->setErrorsTotal($store->getErrorsCount($this->getId()));
            $store->save($this);
        }

        return $this->data['errors_total'];
    }

    /**
     * @param int $value
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     */
    public function setErrorsTotal($value)
    {
        $this->validateNotNull('errors_total', $value);
        $this->validateInt('errors_total', $value);

        if ($this->data['errors_total'] === $value) {
            return false;
        }

        $this->data['errors_total'] = $value;

        return $this->setModified('errors_total');
    }

    /**
     * @return int|null
     *
     * @throws Exception
     */
    public function getErrorsTotalPrevious()
    {
        if (null === $this->data['errors_total_previous']) {
            /** @var BuildStore $store */
            $store = Factory::getStore('Build');

            $trend = $store->getBuildErrorsTrend($this->getId(), $this->getProjectId(), $this->getBranch());

            if (isset($trend[1])) {
                $previousBuild = $store->getById($trend[1]['build_id']);
                if ($previousBuild &&
                    !\in_array(
                        $previousBuild->getStatus(),
                        [self::STATUS_PENDING, self::STATUS_RUNNING],
                        true
                    )) {
                    $this->setErrorsTotalPrevious((int)$trend[1]['count']);
                    $store->save($this);
                }
            }
        }

        return $this->data['errors_total_previous'];
    }

    /**
     * @param int $value
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     */
    public function setErrorsTotalPrevious($value)
    {
        $this->validateNotNull('errors_total_previous', $value);
        $this->validateInt('errors_total_previous', $value);

        if ($this->data['errors_total_previous'] === $value) {
            return false;
        }

        $this->data['errors_total_previous'] = $value;

        return $this->setModified('errors_total_previous');
    }

    /**
     * @return int
     *
     * @throws InvalidArgumentException
     */
    public function getErrorsNew()
    {
        if (null === $this->data['errors_new']) {
            /** @var BuildStore $errorStore */
            $store = Factory::getStore('Build');

            $this->setErrorsNew(
                (int)$store->getNewErrorsCount($this->getId())
            );

            $store->save($this);
        }

        return $this->data['errors_new'];
    }

    /**
     * @param int $value
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     */
    public function setErrorsNew($value)
    {
        $this->validateNotNull('errors_new', $value);
        $this->validateInt('errors_new', $value);

        if ($this->data['errors_new'] === $value) {
            return false;
        }

        $this->data['errors_new'] = $value;

        return $this->setModified('errors_new');
    }

    /**
     * @return bool
     */
    public function isDebug()
    {
        if (\defined('DEBUG_MODE') && DEBUG_MODE) {
            return true;
        }

        if ($this->getExtra('debug')) {
            return true;
        }

        return false;
    }
}
