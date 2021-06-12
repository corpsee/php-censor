<?php

namespace Tests\PHPCensor\Service;

use PHPCensor\ConfigurationInterface;
use PHPCensor\DatabaseManager;
use PHPCensor\Model\Project;
use PHPCensor\Service\ProjectService;
use PHPCensor\StoreRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the ProjectService class.
 *
 * @author Dan Cryer <dan@block8.co.uk>
 */
class ProjectServiceTest extends TestCase
{
    /**
     * @var ProjectService $testedService
     */
    protected $testedService;

    /**
     * @var $mockProjectStore
     */
    protected $mockProjectStore;

    protected ConfigurationInterface $configuration;

    protected DatabaseManager $databaseManager;

    protected StoreRegistry $storeRegistry;

    protected function setUp(): void
    {
        $this->configuration   = $this->getMockBuilder('PHPCensor\ConfigurationInterface')->getMock();
        $this->databaseManager = $this
            ->getMockBuilder('PHPCensor\DatabaseManager')
            ->setConstructorArgs([$this->configuration])
            ->getMock();
        $this->storeRegistry = $this
            ->getMockBuilder('PHPCensor\StoreRegistry')
            ->setConstructorArgs([$this->databaseManager])
            ->getMock();

        $this->mockProjectStore = $this
            ->getMockBuilder('PHPCensor\Store\ProjectStore')
            ->setConstructorArgs([$this->databaseManager, $this->storeRegistry])
            ->getMock();

        $this->mockProjectStore
            ->expects($this->any())
            ->method('save')
            ->will(
                $this->returnArgument(0)
            );

        $this->testedService = new ProjectService($this->storeRegistry, $this->mockProjectStore);
    }

    public function testExecuteCreateGithubProject()
    {
        $project = $this->testedService->createProject(
            'Test Project',
            'github',
            'php-censor/php-censor1',
            0
        );

        self::assertEquals('Test Project', $project->getTitle());
        self::assertEquals('github', $project->getType());
        self::assertEquals('php-censor/php-censor1', $project->getReference());
        self::assertEquals([], $project->getAccessInformation());
    }

    /**
     * @return array
     */
    public function getExecuteCreateGithubProjectAccessInformationData()
    {
        return [
            [
                'git@github.com:php-censor/php-censor.git', [
                    'user'      => 'git',
                    'domain'    => 'github.com',
                    'reference' => 'php-censor/php-censor',
                    'origin'    => 'git@github.com:php-censor/php-censor.git',
                ],
            ], [
                'git@sss.github.com:php-censor/php-censor.git', [
                    'user'      => 'git',
                    'domain'    => 'sss.github.com',
                    'reference' => 'php-censor/php-censor',
                    'origin'    => 'git@sss.github.com:php-censor/php-censor.git',
                ],
            ], [
                'git@172.168.23.4:php-censor/php-censor.git', [
                    'user'      => 'git',
                    'domain'    => '172.168.23.4',
                    'reference' => 'php-censor/php-censor',
                    'origin'    => 'git@172.168.23.4:php-censor/php-censor.git',
                ],
            ], [
                'ssh://git@github.com/php-censor/php-censor.git', [
                    'user'      => 'git',
                    'domain'    => 'github.com',
                    'reference' => 'php-censor/php-censor',
                    'origin'    => 'ssh://git@github.com/php-censor/php-censor.git',
                ],
            ], [
                'ssh://git@172.168.23.4/php-censor/php-censor.git', [
                    'user'      => 'git',
                    'domain'    => '172.168.23.4',
                    'reference' => 'php-censor/php-censor',
                    'origin'    => 'ssh://git@172.168.23.4/php-censor/php-censor.git',
                ],
            ], [
                'https://github.com/php-censor/php-censor.git', [
                    'domain'    => 'github.com',
                    'reference' => 'php-censor/php-censor',
                    'origin'    => 'https://github.com/php-censor/php-censor.git',
                ],
            ], [
                'https://172.168.23.4/php-censor/php-censor.git', [
                    'domain'    => '172.168.23.4',
                    'reference' => 'php-censor/php-censor',
                    'origin'    => 'https://172.168.23.4/php-censor/php-censor.git',
                ],
            ], [
                'http://github.com/php-censor/php-censor.git', [
                    'domain'    => 'github.com',
                    'reference' => 'php-censor/php-censor',
                    'origin'    => 'http://github.com/php-censor/php-censor.git',
                ],
            ], [
                'git@github.com:443/php-censor/php-censor.git', [
                    'user'      => 'git',
                    'domain'    => 'github.com',
                    'port'      => '443',
                    'reference' => 'php-censor/php-censor',
                    'origin'    => 'git@github.com:443/php-censor/php-censor.git',
                ],
            ], [
                'git@172.168.23.4:443/php-censor/php-censor.git', [
                    'user'      => 'git',
                    'domain'    => '172.168.23.4',
                    'port'      => '443',
                    'reference' => 'php-censor/php-censor',
                    'origin'    => 'git@172.168.23.4:443/php-censor/php-censor.git',
                ],
            ], [
                'ssh://git@github.com:443/php-censor/php-censor.git', [
                    'user'      => 'git',
                    'domain'    => 'github.com',
                    'port'      => '443',
                    'reference' => 'php-censor/php-censor',
                    'origin'    => 'ssh://git@github.com:443/php-censor/php-censor.git',
                ],
            ], [
                'ssh://git@172.168.23.4:443/php-censor/php-censor.git', [
                    'user'      => 'git',
                    'domain'    => '172.168.23.4',
                    'port'      => '443',
                    'reference' => 'php-censor/php-censor',
                    'origin'    => 'ssh://git@172.168.23.4:443/php-censor/php-censor.git',
                ],
            ], [
                'https://github.com:443/php-censor/php-censor.git', [
                    'domain'    => 'github.com',
                    'port'      => '443',
                    'reference' => 'php-censor/php-censor',
                    'origin'    => 'https://github.com:443/php-censor/php-censor.git',
                ],
            ], [
                'https://172.168.23.4:443/php-censor/php-censor.git', [
                    'domain'    => '172.168.23.4',
                    'port'      => '443',
                    'reference' => 'php-censor/php-censor',
                    'origin'    => 'https://172.168.23.4:443/php-censor/php-censor.git',
                ],
            ], [
                'http://github.com:443/php-censor/php-censor.git', [
                    'domain'    => 'github.com',
                    'port'      => '443',
                    'reference' => 'php-censor/php-censor',
                    'origin'    => 'http://github.com:443/php-censor/php-censor.git',
                ],
            ], [
                'git@github:php-censor.git', [
                    'user'      => 'git',
                    'domain'    => 'github',
                    'reference' => 'php-censor',
                    'origin'    => 'git@github:php-censor.git',
                ],
            ], [
                'ssh://git@github/php-censor.git', [
                    'user'      => 'git',
                    'domain'    => 'github',
                    'reference' => 'php-censor',
                    'origin'    => 'ssh://git@github/php-censor.git',
                ],
            ], [
                'https://github/php-censor.git', [
                    'domain'    => 'github',
                    'reference' => 'php-censor',
                    'origin'    => 'https://github/php-censor.git',
                ],
            ], [
                'http://github/php-censor.git', [
                    'domain'    => 'github',
                    'reference' => 'php-censor',
                    'origin'    => 'http://github/php-censor.git',
                ],
            ], [
                'git@github:443/php-censor.git', [
                    'user'      => 'git',
                    'domain'    => 'github',
                    'port'      => '443',
                    'reference' => 'php-censor',
                    'origin'    => 'git@github:443/php-censor.git',
                ],
            ], [
                'ssh://git@github:443/php-censor.git', [
                    'user'      => 'git',
                    'domain'    => 'github',
                    'port'      => '443',
                    'reference' => 'php-censor',
                    'origin'    => 'ssh://git@github:443/php-censor.git',
                ],
            ], [
                'https://github:443/php-censor.git', [
                    'domain'    => 'github',
                    'port'      => '443',
                    'reference' => 'php-censor',
                    'origin'    => 'https://github:443/php-censor.git',
                ],
            ], [
                'http://github:443/php-censor.git', [
                    'domain'    => 'github',
                    'port'      => '443',
                    'reference' => 'php-censor',
                    'origin'    => 'http://github:443/php-censor.git',
                ],
            ]
        ];
    }

    /**
     * @param string $reference
     * @param array  $accessInformation
     *
     * @dataProvider getExecuteCreateGithubProjectAccessInformationData
     */
    public function testExecuteCreateGithubProjectAccessInformation($reference, array $accessInformation)
    {
        $project = $this->testedService->createProject(
            'Test Project',
            'github',
            $reference,
            0
        );

        self::assertEquals($accessInformation, $project->getAccessInformation());
    }

    public function testExecuteCreateProjectWithOptions()
    {
        $options = [
            'ssh_private_key'     => 'private',
            'ssh_public_key'      => 'public',
            'allow_public_status' => true,
            'build_config'        => 'config',
            'default_branch'      => 'testbranch',
        ];

        $returnValue = $this->testedService->createProject(
            'Test Project',
            'github',
            'vendor/project',
            0,
            $options
        );

        self::assertEquals('private', $returnValue->getSshPrivateKey());
        self::assertEquals('public', $returnValue->getSshPublicKey());
        self::assertEquals(['config'], $returnValue->getBuildConfig());
        self::assertEquals('testbranch', $returnValue->getDefaultBranch());
        self::assertEquals(true, $returnValue->getAllowPublicStatus());
    }

    public function testExecuteUpdateExistingProject()
    {
        $project = new Project($this->storeRegistry);
        $project->setTitle('Before Title');
        $project->setReference('Before Reference');
        $project->setType('github');

        $returnValue = $this->testedService->updateProject($project, 'After Title', 'bitbucket', 'After Reference');

        self::assertEquals('After Title', $returnValue->getTitle());
        self::assertEquals('After Reference', $returnValue->getReference());
        self::assertEquals('bitbucket', $returnValue->getType());
    }

    public function testExecuteEmptyPublicStatus()
    {
        $project = new Project($this->storeRegistry);
        $project->setAllowPublicStatus(true);

        $options = [
            'ssh_private_key' => 'private',
            'ssh_public_key'  => 'public',
            'build_config'    => 'config',
        ];

        $returnValue = $this->testedService->updateProject($project, 'Test Project', 'github', 'vendor/project', $options);

        self::assertEquals(false, $returnValue->getAllowPublicStatus());
    }

    public function testExecuteDeleteProject()
    {
        $store = $this
            ->getMockBuilder('PHPCensor\Store\ProjectStore')
            ->setConstructorArgs([$this->databaseManager, $this->storeRegistry])
            ->getMock();
        $store->expects($this->once())
            ->method('delete')
            ->will($this->returnValue(true));

        $service = new ProjectService($this->storeRegistry, $store);
        $project = new Project($this->storeRegistry);

        self::assertEquals(true, $service->deleteProject($project));
    }
}
