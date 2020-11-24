<?php

namespace Tests\PHPCensor\Model\Base;

use PHPCensor\Model\Base\BuildMeta;
use PHPUnit\Framework\TestCase;

class BuildMetaTest extends TestCase
{
    public function testConstruct()
    {
        $buildMeta = new BuildMeta();

        self::assertInstanceOf('PHPCensor\Model', $buildMeta);
        self::assertInstanceOf('PHPCensor\Model\Base\BuildMeta', $buildMeta);

        self::assertEquals([
            'id'       => null,
            'build_id' => null,
            'key'      => null,
            'value'    => null,
            'plugin'   => null,
        ], $buildMeta->getDataArray());
    }

    public function testId()
    {
        $buildMeta = new BuildMeta();

        $result = $buildMeta->setId(100);
        self::assertEquals(true, $result);
        self::assertEquals(100, $buildMeta->getId());

        $result = $buildMeta->setId(100);
        self::assertEquals(false, $result);
    }

    public function testBuildId()
    {
        $buildMeta = new BuildMeta();

        $result = $buildMeta->setBuildId(200);
        self::assertEquals(true, $result);
        self::assertEquals(200, $buildMeta->getBuildId());

        $result = $buildMeta->setBuildId(200);
        self::assertEquals(false, $result);
    }

    public function testKey()
    {
        $buildMeta = new BuildMeta();

        $result = $buildMeta->setKey('key');
        self::assertEquals(true, $result);
        self::assertEquals('key', $buildMeta->getKey());

        $result = $buildMeta->setKey('key');
        self::assertEquals(false, $result);
    }

    public function testValue()
    {
        $buildMeta = new BuildMeta();

        $result = $buildMeta->setValue('value');
        self::assertEquals(true, $result);
        self::assertEquals('value', $buildMeta->getValue());

        $result = $buildMeta->setValue('value');
        self::assertEquals(false, $result);
    }
}
