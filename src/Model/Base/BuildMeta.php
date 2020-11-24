<?php

namespace PHPCensor\Model\Base;

use PHPCensor\Exception\InvalidArgumentException;
use PHPCensor\Model;

class BuildMeta extends Model
{
    const KEY_DATA     = 'data';
    const KEY_META     = 'meta';
    const KEY_ERRORS   = 'errors';
    const KEY_WARNINGS = 'warnings';
    const KEY_COVERAGE = 'coverage';
    const KEY_SUMMARY  = 'summary';

    /**
     * @var array
     */
    protected $data = [
        'id'       => null,
        'build_id' => null,
        'key'      => null,
        'value'    => null,
        'plugin'   => null,
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

        $this->data['id'] = $value;

        return $this->setModified('id');
    }

    /**
     * @return int
     */
    public function getBuildId()
    {
        return (int)$this->data['build_id'];
    }

    /**
     * @param int $value
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     */
    public function setBuildId($value)
    {
        $this->validateNotNull('build_id', $value);
        $this->validateInt('build_id', $value);

        if ($this->data['build_id'] === $value) {
            return false ;
        }

        $this->data['build_id'] = $value;

        return $this->setModified('build_id');
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->data['key'];
    }

    /**
     * @param string $value
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     */
    public function setKey($value)
    {
        $this->validateNotNull('key', $value);
        $this->validateString('key', $value);

        if ($this->data['key'] === $value) {
            return false;
        }

        $this->data['key'] = $value;

        return $this->setModified('key');
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->data['value'];
    }

    /**
     * @param string $value
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     */
    public function setValue($value)
    {
        $this->validateNotNull('value', $value);
        $this->validateString('value', $value);

        if ($this->data['value'] === $value) {
            return false;
        }

        $this->data['value'] = $value;

        return $this->setModified('value');
    }

    /**
     * @return string|null
     */
    public function getPlugin()
    {
        return $this->data['plugin'];
    }

    /**
     * @param string|null $plugin
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     */
    public function setPlugin($plugin)
    {
        $this->validateString('plugin', $plugin);

        if ($this->data['plugin'] === $plugin) {
            return false;
        }

        $this->data['plugin'] = $plugin;

        return $this->setModified('plugin');
    }
}
