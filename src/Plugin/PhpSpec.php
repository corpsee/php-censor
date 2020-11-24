<?php

namespace PHPCensor\Plugin;

use PHPCensor\Builder;
use PHPCensor\Model\Build;
use PHPCensor\Model\BuildMeta;
use PHPCensor\Plugin;
use SimpleXMLElement;

/**
 * PHP Spec Plugin - Allows PHP Spec testing.
 *
 * @author Dan Cryer <dan@block8.co.uk>
 */
class PhpSpec extends Plugin
{
    /**
     * @return string
     */
    public static function pluginName()
    {
        return 'php_spec';
    }

    public function __construct(Builder $builder, Build $build, array $options = [])
    {
        parent::__construct($builder, $build, $options);

        $this->executable = $this->findBinary(['phpspec', 'phpspec.php', 'phpspec.phar']);
    }

    /**
     * Runs PHP Spec tests.
     */
    public function execute()
    {
        $phpspec = $this->executable;

        $success = $this->builder->executeCommand($phpspec . ' --format=junit --no-code-generation run');
        $output  = $this->builder->getLastOutput();

        /*
         * process xml output
         *
         * <testsuites time=FLOAT tests=INT failures=INT errors=INT>
         *   <testsuite name=STRING time=FLOAT tests=INT failures=INT errors=INT skipped=INT>
         *     <testcase name=STRING time=FLOAT classname=STRING status=STRING/>
         *   </testsuite>
         * </testsuites
         */

        $xml  = new SimpleXMLElement($output);
        $attr = $xml->attributes();
        $data = [
            'time'     => (float)$attr['time'],
            'tests'    => (int)$attr['tests'],
            'failures' => (int)$attr['failures'],
            'errors'   => (int)$attr['errors'],
            // now all the tests
            'suites'   => [],
        ];

        /**
         * @var SimpleXMLElement $group
         */
        foreach ($xml->xpath('testsuite') as $group) {
            $attr  = $group->attributes();
            $suite = [
                'name'     => (string)$attr['name'],
                'time'     => (float)$attr['time'],
                'tests'    => (int)$attr['tests'],
                'failures' => (int)$attr['failures'],
                'errors'   => (int)$attr['errors'],
                'skipped'  => (int)$attr['skipped'],
                // now the cases
                'cases'    => [],
            ];

            /**
             * @var SimpleXMLElement $child
             */
            foreach ($group->xpath('testcase') as $child) {
                $attr = $child->attributes();
                $case = [
                    'name'      => (string)$attr['name'],
                    'classname' => (string)$attr['classname'],
                    'time'      => (float)$attr['time'],
                    'status'    => (string)$attr['status'],
                ];

                if ('failed' == $case['status']) {
                    $error = [];
                    /*
                     * ok, sad, we had an error
                     *
                     * there should be one - foreach makes this easier
                     */
                    foreach ($child->xpath('failure') as $failure) {
                        $attr             = $failure->attributes();
                        $error['type']    = (string)$attr['type'];
                        $error['message'] = (string)$attr['message'];
                    }

                    foreach ($child->xpath('system-err') as $systemError) {
                        $error['raw'] = (string)$systemError;
                    }

                    $case['error'] = $error;
                }

                $suite['cases'][] = $case;
            }

            $data['suites'][] = $suite;
        }

        $this->build->storeMeta(self::pluginName(), BuildMeta::KEY_DATA, $data);

        return $success;
    }
}
