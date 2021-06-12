<?php

declare(strict_types = 1);

namespace PHPCensor\Model\Base;

use DateTime;
use Exception;
use PHPCensor\Common\Exception\InvalidArgumentException;
use PHPCensor\Model;
use Symfony\Component\Yaml\Parser as YamlParser;

class Project extends Model
{
    const TYPE_LOCAL            = 'local';
    const TYPE_GIT              = 'git';
    const TYPE_GITHUB           = 'github';
    const TYPE_BITBUCKET        = 'bitbucket';
    const TYPE_GITLAB           = 'gitlab';
    const TYPE_GOGS             = 'gogs';
    const TYPE_HG               = 'hg';
    const TYPE_BITBUCKET_HG     = 'bitbucket-hg';
    const TYPE_BITBUCKET_SERVER = 'bitbucket-server';
    const TYPE_SVN              = 'svn';

    const MIN_BUILD_PRIORITY             = 1;
    const MAX_BUILD_PRIORITY             = 2000;
    const DEFAULT_BUILD_PRIORITY         = 1000;
    const OFFSET_BETWEEN_BUILD_AND_QUEUE = 24;

    /**
     * @var array
     */
    protected array $data = [
        'id'                     => null,
        'title'                  => null,
        'reference'              => null,
        'default_branch'         => null,
        'default_branch_only'    => 0,
        'ssh_private_key'        => null,
        'ssh_public_key'         => null,
        'type'                   => null,
        'access_information'     => null,
        'build_config'           => null,
        'overwrite_build_config' => 1,
        'allow_public_status'    => 0,
        'archived'               => 0,
        'group_id'               => 1,
        'create_date'            => null,
        'user_id'                => null,
    ];

    /**
     * @var array
     */
    public static $allowedTypes = [
        self::TYPE_LOCAL,
        self::TYPE_GIT,
        self::TYPE_GITHUB,
        self::TYPE_BITBUCKET,
        self::TYPE_GITLAB,
        self::TYPE_GOGS,
        self::TYPE_HG,
        self::TYPE_BITBUCKET_HG,
        self::TYPE_SVN,
        self::TYPE_BITBUCKET_SERVER
    ];

    public function getId(): int
    {
        return (int)$this->data['id'];
    }

    /**
     * @param int $value
     *
     * @return bool
     */
    public function setId(int $value)
    {
        if ($this->data['id'] === $value) {
            return false;
        }

        $this->data['id'] = $value;

        return $this->setModified('id');
    }

    public function getTitle(): string
    {
        return $this->data['title'];
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    public function setTitle(string $value)
    {
        if ($this->data['title'] === $value) {
            return false;
        }

        $this->data['title'] = $value;

        return $this->setModified('title');
    }

    /**
     * @return string
     */
    public function getReference()
    {
        return $this->data['reference'];
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    public function setReference(string $value)
    {
        if ($this->data['reference'] === $value) {
            return false;
        }

        $this->data['reference'] = $value;

        return $this->setModified('reference');
    }

    /**
     * @return string
     */
    public function getDefaultBranch()
    {
        return $this->data['default_branch'];
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    public function setDefaultBranch(string $value)
    {
        if ($this->data['default_branch'] === $value) {
            return false;
        }

        $this->data['default_branch'] = $value;

        return $this->setModified('default_branch');
    }

    /**
     * @return bool
     */
    public function getDefaultBranchOnly()
    {
        return (bool)$this->data['default_branch_only'];
    }

    /**
     * @param bool $value
     *
     * @return bool
     */
    public function setDefaultBranchOnly(bool $value)
    {
        if ($this->data['default_branch_only'] === (int)$value) {
            return false;
        }

        $this->data['default_branch_only'] = (int)$value;

        return $this->setModified('default_branch_only');
    }

    /**
     * @return string
     */
    public function getSshPrivateKey()
    {
        return $this->data['ssh_private_key'];
    }

    /**
     * @param string|null $value
     *
     * @return bool
     */
    public function setSshPrivateKey(?string $value)
    {
        if ($this->data['ssh_private_key'] === $value) {
            return false;
        }

        $this->data['ssh_private_key'] = $value;

        return $this->setModified('ssh_private_key');
    }

    /**
     * @return string
     */
    public function getSshPublicKey()
    {
        return $this->data['ssh_public_key'];
    }

    /**
     * @param string|null $value
     *
     * @return bool
     */
    public function setSshPublicKey(?string $value)
    {
        if ($this->data['ssh_public_key'] === $value) {
            return false;
        }

        $this->data['ssh_public_key'] = $value;

        return $this->setModified('ssh_public_key');
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->data['type'];
    }

    /**
     * @param string $value
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     */
    public function setType(string $value)
    {
        if (!in_array($value, static::$allowedTypes, true)) {
            throw new InvalidArgumentException(
                'Column "type" must be one of: ' . join(', ', static::$allowedTypes) . '.'
            );
        }

        if ($this->data['type'] === $value) {
            return false;
        }

        $this->data['type'] = $value;

        return $this->setModified('type');
    }

    /**
     * @param string|null $key
     *
     * @return array|string|null
     */
    public function getAccessInformation($key = null)
    {
        $data              = json_decode($this->data['access_information'], true);
        $accessInformation = null;
        if (is_null($key)) {
            $accessInformation = $data;
        } elseif (isset($data[$key])) {
            $accessInformation = $data[$key];
        }

        return $accessInformation;
    }

    /**
     * @param array $value
     *
     * @return bool
     */
    public function setAccessInformation(array $value)
    {
        $accessInformation = json_encode($value);
        if ($this->data['access_information'] === $accessInformation) {
            return false;
        }

        $this->data['access_information'] = $accessInformation;

        return $this->setModified('access_information');
    }

    public function getBuildConfig(): array
    {
        $rawConfig = $this->data['build_config'];

        if (!$rawConfig) {
            return [];
        }

        $yamlParser = new YamlParser();

        return (array)$yamlParser->parse($rawConfig);
    }

    /**
     * @param string|null $value
     *
     * @return bool
     */
    public function setBuildConfig(?string $value)
    {
        if ($this->data['build_config'] === $value) {
            return false;
        }

        $this->data['build_config'] = $value;

        return $this->setModified('build_config');
    }

    /**
     * @return bool
     */
    public function getOverwriteBuildConfig()
    {
        return (bool)$this->data['overwrite_build_config'];
    }

    /**
     * @param bool $value
     *
     * @return bool
     */
    public function setOverwriteBuildConfig(bool $value)
    {
        if ($this->data['overwrite_build_config'] === (int)$value) {
            return false;
        }

        $this->data['overwrite_build_config'] = (int)$value;

        return $this->setModified('overwrite_build_config');
    }

    /**
     * @return bool
     */
    public function getAllowPublicStatus()
    {
        return (bool)$this->data['allow_public_status'];
    }

    /**
     * @param bool $value
     *
     * @return bool
     */
    public function setAllowPublicStatus(bool $value)
    {
        if ($this->data['allow_public_status'] === (int)$value) {
            return false;
        }

        $this->data['allow_public_status'] = (int)$value;

        return $this->setModified('allow_public_status');
    }

    /**
     * @return bool
     */
    public function getArchived()
    {
        return (bool)$this->data['archived'];
    }

    /**
     * @param bool $value
     *
     * @return bool
     */
    public function setArchived(bool $value)
    {
        if ($this->data['archived'] === (int)$value) {
            return false;
        }

        $this->data['archived'] = (int)$value;

        return $this->setModified('archived');
    }

    /**
     * @return int
     */
    public function getGroupId()
    {
        return (int)$this->data['group_id'];
    }

    /**
     * @param int $value
     *
     * @return bool
     */
    public function setGroupId(int $value)
    {
        if ($this->data['group_id'] === $value) {
            return false;
        }

        $this->data['group_id'] = $value;

        return $this->setModified('group_id');
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
     */
    public function setUserId(?int $value)
    {
        if ($this->data['user_id'] === $value) {
            return false;
        }

        $this->data['user_id'] = $value;

        return $this->setModified('user_id');
    }
}
