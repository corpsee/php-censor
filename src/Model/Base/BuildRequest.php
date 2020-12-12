<?php

namespace PHPCensor\Model\Base;

use DateTime;
use Exception;
use PHPCensor\Exception\InvalidArgumentException;
use PHPCensor\Model;
use PHPCensor\Model\Build;

class BuildRequest extends Model
{
    /**
     * @var array
     */
    protected $data = [
        'id'          => null,
        'project_id'  => null,
        'user_id'     => null,
        'source'      => Build::SOURCE_UNKNOWN,
        'payload'     => null,
        'create_date' => null,
    ];

    /**
     * @var array
     */
    protected $allowedSources = [
        Build::SOURCE_UNKNOWN,
        Build::SOURCE_MANUAL_WEB,
        Build::SOURCE_MANUAL_CONSOLE,
        Build::SOURCE_MANUAL_REBUILD_WEB,
        Build::SOURCE_MANUAL_REBUILD_CONSOLE,
        Build::SOURCE_PERIODICAL,
        Build::SOURCE_WEBHOOK_PUSH,
        Build::SOURCE_WEBHOOK_PULL_REQUEST_CREATED,
        Build::SOURCE_WEBHOOK_PULL_REQUEST_UPDATED,
        Build::SOURCE_WEBHOOK_PULL_REQUEST_APPROVED,
        Build::SOURCE_WEBHOOK_PULL_REQUEST_MERGED,
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
    public function getPayload()
    {
        return $this->data['payload'];
    }

    /**
     * @param string|null $value
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     */
    public function setPayload($value)
    {
        $this->validateString('payload', $value);

        if ($this->data['payload'] === $value) {
            return false;
        }

        $this->data['payload'] = $value;

        return $this->setModified('payload');
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
}
