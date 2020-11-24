<?php

namespace PHPCensor\Plugin;

use PHPCensor\Builder;
use PHPCensor\Model\Build;
use PHPCensor\Model\BuildMeta;
use PHPCensor\Plugin;
use PHPCensor\ZeroConfigPluginInterface;

/**
 * Php Parallel Lint Plugin - Provides access to PHP lint functionality.
 *
 * @author Vaclav Makes <vaclav@makes.cz>
 */
class PhpParallelLint extends Plugin implements ZeroConfigPluginInterface
{
    /**
     * @var string - comma separated list of file extensions
     */
    protected $extensions;

    /**
     * @var bool - enable short tags
     */
    protected $shortTag;

    /**
     * @return string
     */
    public static function pluginName()
    {
        return 'php_parallel_lint';
    }

    /**
     * $options['directory']  Output Directory. Default: %BUILDPATH%
     * $options['filename']   Phar Filename. Default: build.phar
     * $options['extensions'] Filename extensions. Default: php
     * $options['shorttags']  Enable short tags. Default: false
     * $options['stub']       Stub Content. No Default Value
     */
    public function __construct(Builder $builder, Build $build, array $options = [])
    {
        parent::__construct($builder, $build, $options);

        $this->extensions = 'php';
        $this->shortTag   = false;

        $this->executable = $this->findBinary(['parallel-lint', 'parallel-lint.phar']);

        if (isset($options['shorttags'])) {
            $this->shortTag = $options['shorttags'];
        }

        if (isset($options['extensions'])) {
            // Only use if this is a comma delimited list
            $pattern = '/^([a-z]+)(,\ *[a-z]*)*$/';

            if (preg_match($pattern, $options['extensions'])) {
                $this->extensions = str_replace(' ', '', $options['extensions']);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function canExecuteOnStage($stage, Build $build)
    {
        if (Build::STAGE_TEST === $stage) {
            return true;
        }

        return false;
    }

    /**
     * Executes parallel lint
     */
    public function execute()
    {
        list($ignore) = $this->getFlags();

        $phplint = $this->executable;

        $cmd     = $phplint . ' -e %s' . '%s %s "%s"';
        $success = $this->builder->executeCommand(
            $cmd,
            $this->extensions,
            ($this->shortTag ? ' -s' : ''),
            $ignore,
            $this->directory
        );

        $output = $this->builder->getLastOutput();

        $matches = [];
        if (preg_match_all('/Parse error\:/', $output, $matches)) {
            $this->build->storeMeta(self::pluginName(), BuildMeta::KEY_ERRORS, count($matches[0]));
        }

        return $success;
    }

    /**
     * Produce an argument string for PHP Parallel Lint.
     * @return array
     */
    protected function getFlags()
    {
        $ignoreFlags = [];
        foreach ($this->ignore as $ignoreDir) {
            $ignoreFlags[] = sprintf(' --exclude "%s"', $this->builder->buildPath . $ignoreDir);
        }

        $ignore = implode(' ', $ignoreFlags);

        return [$ignore];
    }
}
