<?php

namespace PHPCensor\Plugin;

use DirectoryIterator;
use PHPCensor;
use PHPCensor\Builder;
use PHPCensor\Model\Build;
use PHPCensor\Model\BuildMeta;
use PHPCensor\Plugin;

/**
 * PHPTAL Lint Plugin - Provides access to PHPTAL lint functionality.
 *
 * @author Stephen Ball <phpci@stephen.rebelinblue.com>
 */
class PhpTalLint extends Plugin
{
    protected $recursive = true;

    protected $suffixes = ['zpt'];

    /**
     * @return string
     */
    public static function pluginName()
    {
        return 'php_tal_lint';
    }

    /**
     * @var int
     */
    protected $allowedWarnings = 0;

    /**
     * @var int
     */
    protected $allowedErrors = 0;

    /**
     * @var array The results of the lint scan
     */
    protected $failedPaths = [];

    /**
     * {@inheritdoc}
     */
    public function __construct(Builder $builder, Build $build, array $options = [])
    {
        parent::__construct($builder, $build, $options);

        if (!empty($options['allowed_errors']) && is_int($options['allowed_errors'])) {
            $this->allowedErrors = $options['allowed_errors'];
        }

        if (!empty($options['allowed_warnings']) && is_int($options['allowed_warnings'])) {
            $this->allowedWarnings = $options['allowed_warnings'];
        }

        if (isset($options['suffixes'])) {
            $this->suffixes = (array)$options['suffixes'];
        }
    }

    /**
     * Executes phptal lint
     */
    public function execute()
    {
        $this->lintDirectory($this->directory);

        $errors   = 0;
        $warnings = 0;

        foreach ($this->failedPaths as $path) {
            if ($path['type'] == 'error') {
                $errors++;
            } else {
                $warnings++;
            }
        }

        $this->build->storeMeta(self::pluginName(), BuildMeta::KEY_WARNINGS, $warnings);
        $this->build->storeMeta(self::pluginName(), BuildMeta::KEY_ERRORS, $errors);
        $this->build->storeMeta(self::pluginName(), BuildMeta::KEY_DATA, $this->failedPaths);

        $success = true;

        if ($this->allowedWarnings !== -1 && $warnings > $this->allowedWarnings) {
            $success = false;
        }

        if ($this->allowedErrors !== -1 && $errors > $this->allowedErrors) {
            $success = false;
        }

        return $success;
    }

    /**
     * Lint an item (file or directory) by calling the appropriate method.
     * @param $item
     * @param $itemPath
     * @return bool
     */
    protected function lintItem($item, $itemPath)
    {
        $success = true;

        if ($item->isFile() && in_array(strtolower($item->getExtension()), $this->suffixes)) {
            if (!$this->lintFile($itemPath)) {
                $success = false;
            }
        } elseif ($item->isDir() && $this->recursive && !$this->lintDirectory($itemPath . '/')) {
            $success = false;
        }

        return $success;
    }

    /**
     * Run phptal lint against a directory of files.
     * @param $path
     * @return bool
     */
    protected function lintDirectory($path)
    {
        $success = true;
        $directory = new DirectoryIterator($this->builder->buildPath . $path);

        foreach ($directory as $item) {
            if ($item->isDot()) {
                continue;
            }

            $itemPath = $path . $item->getFilename();

            if (in_array($itemPath, $this->ignore)) {
                continue;
            }

            if (!$this->lintItem($item, $itemPath)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Run phptal lint against a specific file.
     * @param $path
     * @return bool
     */
    protected function lintFile($path)
    {
        $success  = true;
        $suffixes = $this->getFlags();

        $lint = __DIR__ . '/';
        $lint .= 'vendor/phptal/phptal/';
        $lint .= 'tools/phptal_lint.php';
        $cmd  = 'php ' . $lint . ' %s "%s"';

        $this->builder->executeCommand($cmd, $suffixes, $this->builder->buildPath . $path);

        $output = $this->builder->getLastOutput();

        if (preg_match('/Found (.+?) (error|warning)/i', $output, $matches)) {
            $rows = explode(PHP_EOL, $output);

            unset($rows[0]);
            unset($rows[1]);
            unset($rows[2]);
            unset($rows[3]);

            foreach ($rows as $row) {
                $name = basename($path);

                $row = str_replace('(use -i to include your custom modifier functions)', '', $row);
                $message = str_replace($name . ': ', '', $row);

                $parts = explode(' (line ', $message);

                $message = trim($parts[0]);
                $line = str_replace(')', '', $parts[1]);

                $this->failedPaths[] = [
                    'file'    => $path,
                    'line'    => $line,
                    'type'    => $matches[2],
                    'message' => $message
                ];
            }

            $success = false;
        }

        return $success;
    }

    /**
     * Process options and produce an arguments string for PHPTAL Lint.
     *
     * @return string
     */
    protected function getFlags()
    {
        $suffixes = '';
        if (\is_array($this->suffixes) && \count($this->suffixes) > 0) {
            $suffixes = ' -e ' . \implode(',', $this->suffixes);
        }

        return $suffixes;
    }
}
