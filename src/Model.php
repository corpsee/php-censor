<?php

namespace PHPCensor;

use PHPCensor\Exception\InvalidArgumentException;

class Model
{
    /**
     * @var array
     */
    protected $data = [];

    /**
     * @var array
     */
    protected $modified = [];

    /**
     * @param array $initialData
     */
    public function __construct($initialData = [])
    {
        if (\is_array($initialData)) {
            foreach ($initialData as $index => $item) {
                if (\array_key_exists($index, $this->data)) {
                    $this->data[$index] = $item;
                }
            }
        }
    }

    /**
     * @return array
     */
    public function getDataArray()
    {
        return $this->data;
    }

    /**
     * @return array
     */
    public function getModified()
    {
        return $this->modified;
    }

    /**
     * @param string $column
     *
     * @return bool
     */
    protected function setModified($column)
    {
        $this->modified[$column] = $column;

        return true;
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @throws InvalidArgumentException
     */
    protected function validateString($name, $value)
    {
        if (!\is_string($value) && !\is_null($value)) {
            throw new InvalidArgumentException('Column "' . $name . '" must be a string.');
        }
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @throws InvalidArgumentException
     */
    protected function validateInt($name, $value)
    {
        if (!\is_int($value) && !\is_null($value)) {
            throw new InvalidArgumentException('Column "' . $name . '" must be an int.');
        }
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @throws InvalidArgumentException
     */
    protected function validateBoolean($name, $value)
    {
        if (!\is_bool($value) && !\is_null($value)) {
            throw new InvalidArgumentException('Column "' . $name . '" must be a bool.');
        }
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @throws InvalidArgumentException
     */
    protected function validateNotNull($name, $value)
    {
        if (\is_null($value)) {
            throw new InvalidArgumentException('Column "' . $name . '" must not be null.');
        }
    }
}
