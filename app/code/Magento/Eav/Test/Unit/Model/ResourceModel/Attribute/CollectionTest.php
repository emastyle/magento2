<?php declare(strict_types=1);
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\Eav\Test\Unit\Model\ResourceModel\Attribute;

use Magento\Customer\Model\ResourceModel\Attribute\Collection as CollectionResourceModel;
use Magento\Eav\Model\Config;
use Magento\Eav\Model\Entity\Type;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\DB\Select;
use Magento\Framework\DB\Select\SelectRenderer;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CollectionTest extends TestCase
{
    /**
     * @var \Magento\Eav\Model\ResourceModel\Attribute\Collection|MockObject
     */
    protected $model;

    /**
     * @var EntityFactory|MockObject
     */
    protected $entityFactoryMock;

    /**
     * @var LoggerInterface|MockObject
     */
    protected $loggerMock;

    /**
     * @var FetchStrategyInterface|MockObject
     */
    protected $fetchStrategyMock;

    /**
     * @var ManagerInterface|MockObject
     */
    protected $eventManagerMock;

    /**
     * @var Config|MockObject
     */
    protected $eavConfigMock;

    /**
     * @var Type
     */
    protected $entityTypeMock;

    /**
     * @var StoreManagerInterface|MockObject
     */
    protected $storeManagerMock;

    /**
     * @var AdapterInterface|MockObject
     */
    protected $connectionMock;

    /**
     * @var AbstractDb|MockObject
     */
    protected $resourceMock;

    /**
     * @var Select|MockObject
     */
    protected $select;

    /**
     * @var MockObject
     */
    protected $selectRenderer;

    protected function setUp(): void
    {
        $this->entityFactoryMock = $this->createMock(EntityFactory::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->fetchStrategyMock = $this->createMock(
            FetchStrategyInterface::class
        );
        $this->eventManagerMock = $this->createMock(ManagerInterface::class);

        $this->eavConfigMock = $this->createMock(Config::class);
        $this->entityTypeMock = $this->createPartialMock(Type::class, ['__wakeup']);
        $this->entityTypeMock->setAdditionalAttributeTable('some_extra_table');
        $this->eavConfigMock->expects($this->any())
            ->method('getEntityType')
            ->will($this->returnValue($this->entityTypeMock));

        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->storeManagerMock->expects($this->any())->method('getStore')->will($this->returnSelf());

        $this->connectionMock = $this->createPartialMock(
            Mysql::class,
            ['select', 'describeTable', 'quoteIdentifier', '_connect', '_quote']
        );
        $this->selectRenderer = $this->getMockBuilder(SelectRenderer::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->select = new Select($this->connectionMock, $this->selectRenderer);

        $this->resourceMock = $this->getMockForAbstractClass(
            AbstractDb::class,
            [],
            '',
            false,
            true,
            true,
            ['__wakeup', 'getConnection', 'getMainTable', 'getTable']
        );

        $this->connectionMock->expects($this->any())->method('select')->will($this->returnValue($this->select));
        $this->connectionMock->expects($this->any())->method('quoteIdentifier')->will($this->returnArgument(0));
        $this->connectionMock->expects($this->any())
            ->method('describeTable')
            ->will($this->returnValueMap(
                [
                    [
                        'some_main_table',
                        null,
                        [
                            'col1' => [],
                            'col2' => [],
                        ],
                    ],
                    [
                        'some_extra_table',
                        null,
                        [
                            'col2' => [],
                            'col3' => [],
                        ]
                    ],
                    [
                        null,
                        null,
                        [
                            'col2' => [],
                            'col3' => [],
                            'col4' => [],
                        ]
                    ],
                ]
            ));
        $this->connectionMock->expects($this->any())->method('_quote')->will($this->returnArgument(0));
        $this->resourceMock->expects($this->any())->method('getConnection')->willReturn($this->connectionMock);
        $this->resourceMock->expects($this->any())->method('getMainTable')->willReturn('some_main_table');
        $this->resourceMock->expects($this->any())->method('getTable')->willReturn('some_extra_table');
    }

    /**
     * @dataProvider initSelectDataProvider
     */
    public function testInitSelect($column, $value)
    {
        $helper = new ObjectManager($this);
        $this->model = $helper->getObject(
            CollectionResourceModel::class,
            [
                'entityFactory' => $this->entityFactoryMock,
                'logger' => $this->loggerMock,
                'fetchStrategy' => $this->fetchStrategyMock,
                'eventManager' => $this->eventManagerMock,
                'eavConfig' => $this->eavConfigMock,
                'storeManager' => $this->storeManagerMock,
                'connection' => $this->connectionMock,
                'resource' => $this->resourceMock
            ]
        );

        $this->model->addFieldToFilter($column, $value);
        $this->selectRenderer->expects($this->once())
            ->method('render')
            ->withAnyParameters();
        $this->model->getSelectCountSql()->assemble();
    }

    /**
     * @return array
     */
    public function initSelectDataProvider()
    {
        return [
            'main_table_expression' => [
                'col2', '1',
            ],
            'additional_table_expression' => [
                'col3', '2',
            ]
        ];
    }
}
