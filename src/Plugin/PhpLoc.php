<?php

namespace PHPCensor\Plugin;

use PHPCensor;
use PHPCensor\Builder;
use PHPCensor\Model\Build;
use PHPCensor\Model\BuildMeta;
use PHPCensor\Plugin;
use PHPCensor\ZeroConfigPluginInterface;

/**
 * PHP Loc - Allows PHP Copy / Lines of Code testing.
 *
 * @author Johan van der Heide <info@japaveh.nl>
 */
class PhpLoc extends Plugin implements ZeroConfigPluginInterface
{
    /**
     * @return string
     */
    public static function pluginName()
    {
        return 'php_loc';
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
     * {@inheritdoc}
     */
    public function __construct(Builder $builder, Build $build, array $options = [])
    {
        parent::__construct($builder, $build, $options);

        $this->executable = $this->findBinary(['phploc', 'phploc.phar']);
    }

    /**
     * Runs PHP LOC in a specified directory.
     */
    public function execute()
    {
        $ignore = '';
        if (is_array($this->ignore)) {
            $map = function ($item) {
                return sprintf(' --exclude="%s"', $item);
            };

            $ignore = array_map($map, $this->ignore);
            $ignore = implode('', $ignore);
        }

        $phploc = $this->executable;

        $success = $this->builder->executeCommand('cd "%s" && ' . $phploc . ' %s "%s"', $this->builder->buildPath, $ignore, $this->directory);
        $output  = $this->builder->getLastOutput();

        if (preg_match_all('/\((LOC|CLOC|NCLOC|LLOC)\)\s+([0-9]+)/', $output, $matches)) {
            $data = [];
            foreach ($matches[1] as $k => $v) {
                $data[$v] = (int)$matches[2][$k];
            }

            $this->build->storeMeta(self::pluginName(), BuildMeta::KEY_DATA, $data);
        }

        return $success;
    }
}
