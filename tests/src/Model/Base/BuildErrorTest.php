<?php

namespace Tests\PHPCensor\Model\Base;

use DateTime;
use PHPCensor\Model\Base\BuildError;
use PHPCensor\StoreRegistry;
use PHPUnit\Framework\TestCase;

class BuildErrorTest extends TestCase
{
    protected StoreRegistry $storeRegistry;

    protected function setUp(): void
    {
        $configuration   = $this->getMockBuilder('PHPCensor\ConfigurationInterface')->getMock();
        $databaseManager = $this
            ->getMockBuilder('PHPCensor\DatabaseManager')
            ->setConstructorArgs([$configuration])
            ->getMock();
        $this->storeRegistry = $this
            ->getMockBuilder('PHPCensor\StoreRegistry')
            ->setConstructorArgs([$databaseManager])
            ->getMock();
    }

    public function testConstruct()
    {
        $buildError = new BuildError($this->storeRegistry);

        self::assertInstanceOf('PHPCensor\Model', $buildError);
        self::assertInstanceOf('PHPCensor\Model\Base\BuildError', $buildError);

        self::assertEquals([
            'id'          => null,
            'build_id'    => null,
            'plugin'      => null,
            'file'        => null,
            'line_start'  => null,
            'line_end'    => null,
            'severity'    => null,
            'message'     => null,
            'create_date' => null,
            'hash'        => null,
            'is_new'      => null,
        ], $buildError->getDataArray());
    }

    public function testId()
    {
        $buildError = new BuildError($this->storeRegistry);

        $result = $buildError->setId(100);
        self::assertEquals(true, $result);
        self::assertEquals(100, $buildError->getId());

        $result = $buildError->setId(100);
        self::assertEquals(false, $result);
    }

    public function testBuildId()
    {
        $buildError = new BuildError($this->storeRegistry);

        $result = $buildError->setBuildId(200);
        self::assertEquals(true, $result);
        self::assertEquals(200, $buildError->getBuildId());

        $result = $buildError->setBuildId(200);
        self::assertEquals(false, $result);
    }

    public function testPlugin()
    {
        $buildError = new BuildError($this->storeRegistry);

        $result = $buildError->setPlugin('plugin');
        self::assertEquals(true, $result);
        self::assertEquals('plugin', $buildError->getPlugin());

        $result = $buildError->setPlugin('plugin');
        self::assertEquals(false, $result);
    }

    public function testFile()
    {
        $buildError = new BuildError($this->storeRegistry);

        $result = $buildError->setFile('file.php');
        self::assertEquals(true, $result);
        self::assertEquals('file.php', $buildError->getFile());

        $result = $buildError->setFile('file.php');
        self::assertEquals(false, $result);
    }

    public function testLineStart()
    {
        $buildError = new BuildError($this->storeRegistry);

        $result = $buildError->setLineStart(5);
        self::assertEquals(true, $result);
        self::assertEquals(5, $buildError->getLineStart());

        $result = $buildError->setLineStart(5);
        self::assertEquals(false, $result);
    }

    public function testLineEnd()
    {
        $buildError = new BuildError($this->storeRegistry);

        $result = $buildError->setLineEnd(10);
        self::assertEquals(true, $result);
        self::assertEquals(10, $buildError->getLineEnd());

        $result = $buildError->setLineEnd(10);
        self::assertEquals(false, $result);
    }

    public function testSeverity()
    {
        $buildError = new BuildError($this->storeRegistry);

        $result = $buildError->setSeverity(BuildError::SEVERITY_CRITICAL);
        self::assertEquals(true, $result);
        self::assertEquals(BuildError::SEVERITY_CRITICAL, $buildError->getSeverity());

        $result = $buildError->setSeverity(BuildError::SEVERITY_CRITICAL);
        self::assertEquals(false, $result);
    }

    public function testMessage()
    {
        $buildError = new BuildError($this->storeRegistry);

        $result = $buildError->setMessage('message');
        self::assertEquals(true, $result);
        self::assertEquals('message', $buildError->getMessage());

        $result = $buildError->setMessage('message');
        self::assertEquals(false, $result);
    }

    public function testCreateDate()
    {
        $buildError = new BuildError($this->storeRegistry);
        self::assertEquals(null, $buildError->getCreateDate());

        $buildError = new BuildError($this->storeRegistry);
        $createDate = new DateTime();

        $result = $buildError->setCreateDate($createDate);
        self::assertEquals(true, $result);
        self::assertEquals($createDate->getTimestamp(), $buildError->getCreateDate()->getTimestamp());

        $result = $buildError->setCreateDate($createDate);
        self::assertEquals(false, $result);
    }

    public function testHash()
    {
        $buildError = new BuildError($this->storeRegistry);

        $result = $buildError->setHash('hash');
        self::assertEquals(true, $result);
        self::assertEquals('hash', $buildError->getHash());

        $result = $buildError->setHash('hash');
        self::assertEquals(false, $result);
    }

    public function testIsNew()
    {
        $buildError = new BuildError($this->storeRegistry);

        $result = $buildError->setIsNew(true);
        self::assertEquals(true, $result);
        self::assertEquals(true, $buildError->getIsNew());

        $result = $buildError->setIsNew(true);
        self::assertEquals(false, $result);
    }
}
