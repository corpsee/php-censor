<?php

namespace PHPCensor\Plugin;

use Exception;
use PHPCensor\Builder;
use PHPCensor\Model\Build;
use PHPCensor\Model\BuildMeta;
use PHPCensor\Plugin;
use PHPCensor\Plugin\Util\TestResultParsers\Codeception as Parser;
use PHPCensor\ZeroConfigPluginInterface;
use Symfony\Component\Yaml\Parser as YamlParser;

/**
 * Codeception Plugin - Enables full acceptance, unit, and functional testing.
 *
 * @author Don Gilbert <don@dongilbert.net>
 * @author Igor Timoshenko <contact@igortimoshenko.com>
 * @author Adam Cooper <adam@networkpie.co.uk>
 */
class Codeception extends Plugin implements ZeroConfigPluginInterface
{
    /**
     * @var string
     */
    protected $args = '';

    /**
     * @var string $ymlConfigFile The path of a yml config for Codeception
     */
    protected $ymlConfigFile;

    /**
     * default sub-path for report.xml file
     *
     * @var array $path The path to the report.xml file
     */
    protected $outputPath = [
        'tests/_output',
        'tests/_log',
    ];

    /**
     * @return string
     */
    public static function pluginName()
    {
        return 'codeception';
    }

    /**
     * {@inheritdoc}
     */
    public function __construct(Builder $builder, Build $build, array $options = [])
    {
        parent::__construct($builder, $build, $options);

        if (empty($options['config'])) {
            $this->ymlConfigFile = self::findConfigFile($this->directory);
        } else {
            $this->ymlConfigFile = $this->directory . $options['config'];
        }

        if (isset($options['args'])) {
            $this->args = (string)$options['args'];
        }

        /** @deprecated Option "path" is deprecated and will be deleted in version 2.0. Use the option "output_path" instead. */
        if (isset($options['path']) && !isset($options['output_path'])) {
            $this->builder->logWarning(
                '[DEPRECATED] Option "path" is deprecated and will be deleted in version 2.0. Use the option "output_path" instead.'
            );

            $options['output_path'] = $options['path'];
        }

        if (isset($options['output_path'])) {
            array_unshift($this->outputPath, $options['output_path']);
        }

        /** @deprecated Option "executable" is deprecated and will be deleted in version 2.0. Use the option "binary_path" and "binary_name" instead. */
        if (isset($options['executable'])) {
            $this->builder->logWarning(
                '[DEPRECATED] Option "executable" is deprecated and will be deleted in version 2.0. Use the option "binary_path" and "binary_name" instead.'
            );

            $this->executable = $options['executable'];
        } else {
            $this->executable = $this->findBinary(['codecept', 'codecept.phar']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function canExecuteOnStage($stage, Build $build)
    {
        return (Build::STAGE_TEST === $stage && !is_null(self::findConfigFile($build->getBuildPath())));
    }

    /**
     * Try and find the codeception YML config file.
     * @param $buildPath
     * @return null|string
     */
    public static function findConfigFile($buildPath)
    {
        if (file_exists($buildPath . 'codeception.yml')) {
            return $buildPath . 'codeception.yml';
        }

        if (file_exists($buildPath . 'codeception.dist.yml')) {
            return $buildPath . 'codeception.dist.yml';
        }

        return null;
    }

    /**
     * Runs Codeception tests
     */
    public function execute()
    {
        if (empty($this->ymlConfigFile)) {
            throw new Exception("No configuration file found");
        }

        // Run any config files first. This can be either a single value or an array.
        return $this->runConfigFile();
    }

    /**
     * Run tests from a Codeception config file.
     *
     * @return bool|mixed
     * @throws Exception
     */
    protected function runConfigFile()
    {
        $codeception = $this->executable;

        if (!$codeception) {
            $this->builder->logFailure(sprintf('Could not find "%s" binary', 'codecept'));

            return false;
        }

        $cmd = 'cd "%s" && ' . $codeception . ' run -c "%s" ' . $this->args . ' --xml';

        $success = $this->builder->executeCommand($cmd, $this->directory, $this->ymlConfigFile);
        if (!$success) {
            return false;
        }

        $parser = new YamlParser();
        $yaml   = file_get_contents($this->ymlConfigFile);
        $config = (array)$parser->parse($yaml);

        $trueReportXmlPath = null;
        if ($config && isset($config['paths']['log'])) {
            $trueReportXmlPath = rtrim($config['paths']['log'], '/\\') . '/';
        }

        if (!file_exists($trueReportXmlPath . 'report.xml')) {
            foreach ($this->outputPath as $outputPath) {
                $trueReportXmlPath = rtrim($outputPath, '/\\') . '/';
                if (file_exists($trueReportXmlPath . 'report.xml')) {
                    break;
                }
            }
        }

        if (!file_exists($trueReportXmlPath . 'report.xml')) {
            $this->builder->logFailure('"report.xml" file can not be found in configured "output_path!"');

            return false;
        }

        $parser = new Parser($this->builder, ($trueReportXmlPath . 'report.xml'));
        $output = $parser->parse();

        $meta = [
            'tests'     => $parser->getTotalTests(),
            'timetaken' => $parser->getTotalTimeTaken(),
            'failures'  => $parser->getTotalFailures(),
        ];

        // NOTE: Codeception does not use stderr, so failure can only be detected
        // through tests
        $success = $success && (intval($meta['failures']) < 1);

        $this->build->storeMeta(self::pluginName(), BuildMeta::KEY_META, $meta);
        $this->build->storeMeta(self::pluginName(), BuildMeta::KEY_DATA, $output);
        $this->build->storeMeta(self::pluginName(), BuildMeta::KEY_ERRORS, $parser->getTotalFailures());

        return $success;
    }
}
