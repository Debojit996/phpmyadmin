<?php

declare(strict_types=1);

namespace PhpMyAdmin\Tests\Database;

use PhpMyAdmin\ColumnFull;
use PhpMyAdmin\Config;
use PhpMyAdmin\ConfigStorage\Relation;
use PhpMyAdmin\ConfigStorage\RelationParameters;
use PhpMyAdmin\Current;
use PhpMyAdmin\Database\CentralColumns;
use PhpMyAdmin\DatabaseInterface;
use PhpMyAdmin\Dbal\ConnectionType;
use PhpMyAdmin\Tests\AbstractTestCase;
use PhpMyAdmin\Tests\Stubs\DummyResult;
use PhpMyAdmin\Types;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionProperty;

use function array_slice;

#[CoversClass(CentralColumns::class)]
#[PreserveGlobalState(false)]
#[RunTestsInSeparateProcesses]
class CentralColumnsTest extends AbstractTestCase
{
    private CentralColumns $centralColumns;

    private DatabaseInterface&MockObject $dbi;

    /** @var array<int, array<string, string|int>> */
    private array $columnData = [
        [
            'col_name' => 'id',
            'col_type' => 'integer',
            'col_length' => 0,
            'col_isNull' => 0,
            'col_extra' => 'UNSIGNED,auto_increment',
            'col_default' => 1,
            'col_collation' => '',
        ],
        [
            'col_name' => 'col1',
            'col_type' => 'varchar',
            'col_length' => 100,
            'col_isNull' => 1,
            'col_extra' => 'BINARY',
            'col_default' => 1,
            'col_collation' => '',
        ],
        [
            'col_name' => 'col2',
            'col_type' => 'DATETIME',
            'col_length' => 0,
            'col_isNull' => 1,
            'col_extra' => 'on update CURRENT_TIMESTAMP',
            'col_default' => 'CURRENT_TIMESTAMP',
            'col_collation' => '',
        ],
    ];

    /** @var array<int, array<string, string|int>> */
    private array $modifiedColumnData = [
        [
            'col_name' => 'id',
            'col_type' => 'integer',
            'col_length' => 0,
            'col_isNull' => 0,
            'col_extra' => 'auto_increment',
            'col_default' => 1,
            'col_collation' => '',
            'col_attribute' => 'UNSIGNED',
        ],
        [
            'col_name' => 'col1',
            'col_type' => 'varchar',
            'col_length' => 100,
            'col_isNull' => 1,
            'col_extra' => '',
            'col_default' => 1,
            'col_collation' => '',
            'col_attribute' => 'BINARY',
        ],
        [
            'col_name' => 'col2',
            'col_type' => 'DATETIME',
            'col_length' => 0,
            'col_isNull' => 1,
            'col_extra' => '',
            'col_default' => 'CURRENT_TIMESTAMP',
            'col_collation' => '',
            'col_attribute' => 'on update CURRENT_TIMESTAMP',
        ],
    ];

    /**
     * prepares environment for tests
     */
    protected function setUp(): void
    {
        parent::setUp();

        parent::setGlobalConfig();

        $config = Config::getInstance();
        $config->selectedServer['user'] = 'pma_user';
        $config->selectedServer['DisableIS'] = true;
        $config->settings['MaxRows'] = 10;
        $config->settings['ServerDefault'] = 'PMA_server';
        $config->settings['ActionLinksMode'] = 'icons';
        $config->settings['CharEditing'] = '';
        $config->settings['LimitChars'] = 50;
        Current::$database = 'PMA_db';
        Current::$table = 'PMA_table';

        $relationParameters = RelationParameters::fromArray([
            'centralcolumnswork' => true,
            'relwork' => true,
            'db' => 'phpmyadmin',
            'relation' => 'relation',
            'central_columns' => 'pma_central_columns',
        ]);
        (new ReflectionProperty(Relation::class, 'cache'))->setValue(null, $relationParameters);

        // mock DBI
        $this->dbi = $this->getMockBuilder(DatabaseInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->dbi->types = new Types($this->dbi);
        DatabaseInterface::$instance = $this->dbi;

        // set some common expectations
        $this->dbi->expects(self::any())
            ->method('selectDb')
            ->willReturn(true);
        $this->dbi->expects(self::any())
            ->method('getColumns')
            ->willReturn([
                'id' => new ColumnFull('id', 'integer', null, false, '', null, '', '', ''),
                'col1' => new ColumnFull('col1', 'varchar(100)', null, true, '', null, '', '', ''),
                'col2' => new ColumnFull('col2', 'DATETIME', null, false, '', null, '', '', ''),
            ]);
        $this->dbi->expects(self::any())
            ->method('getColumnNames')
            ->willReturn(['id', 'col1', 'col2']);
        $this->dbi->expects(self::any())
            ->method('tryQuery')
            ->willReturn(self::createStub(DummyResult::class));
        $this->dbi->expects(self::any())
            ->method('getTables')
            ->willReturn(['PMA_table', 'PMA_table1', 'PMA_table2']);
        $this->dbi->expects(self::any())->method('quoteString')
        ->willReturnCallback(static fn (string $string): string => "'" . $string . "'");

        $this->centralColumns = new CentralColumns($this->dbi);
    }

    /**
     * Test for getParams
     */
    public function testGetParams(): void
    {
        self::assertSame(
            ['user' => 'pma_user', 'db' => 'phpmyadmin', 'table' => 'pma_central_columns'],
            $this->centralColumns->getParams(),
        );
    }

    /**
     * Test for getColumnsList
     */
    public function testGetColumnsList(): void
    {
        $this->dbi->expects(self::exactly(2))
            ->method('fetchResult')
            ->willReturnOnConsecutiveCalls(
                $this->columnData,
                array_slice($this->columnData, 1, 2),
            );

        self::assertEquals(
            $this->modifiedColumnData,
            $this->centralColumns->getColumnsList('phpmyadmin'),
        );
        self::assertEquals(
            array_slice($this->modifiedColumnData, 1, 2),
            $this->centralColumns->getColumnsList('phpmyadmin', 1, 2),
        );
    }

    /**
     * Test for getCount
     */
    public function testGetCount(): void
    {
        $this->dbi->expects(self::once())
            ->method('fetchResult')
            ->with(
                'SELECT count(db_name) FROM `pma_central_columns` WHERE db_name = \'phpmyadmin\';',
                null,
                null,
                ConnectionType::ControlUser,
            )
            ->willReturn([3]);

        self::assertEquals(
            3,
            $this->centralColumns->getCount('phpmyadmin'),
        );
    }

    /**
     * Test for syncUniqueColumns
     */
    public function testSyncUniqueColumns(): void
    {
        $_POST['db'] = 'PMA_db';
        $_POST['table'] = 'PMA_table';

        self::assertTrue(
            $this->centralColumns->syncUniqueColumns(
                ['PMA_table'],
            ),
        );
    }

    /**
     * Test for makeConsistentWithList
     */
    public function testMakeConsistentWithList(): void
    {
        $this->dbi->expects(self::any())
            ->method('fetchResult')
            ->willReturn($this->columnData);
        $this->dbi->expects(self::any())
            ->method('fetchValue')
            ->willReturn('PMA_table=CREATE table `PMA_table` (id integer)');
        self::assertTrue(
            $this->centralColumns->makeConsistentWithList(
                'phpmyadmin',
                ['PMA_table'],
            ),
        );
    }

    /**
     * Test for getFromTable
     */
    public function testGetFromTable(): void
    {
        $db = 'PMA_db';
        $table = 'PMA_table';

        $this->dbi->expects(self::once())
            ->method('fetchResult')
            ->with(
                'SELECT col_name FROM `pma_central_columns` '
                . "WHERE db_name = 'PMA_db' AND col_name IN ('id','col1','col2');",
                null,
                null,
                ConnectionType::ControlUser,
            )
            ->willReturn(['id', 'col1']);
        self::assertEquals(
            ['id', 'col1'],
            $this->centralColumns->getFromTable(
                $db,
                $table,
            ),
        );
    }

    /**
     * Test for getFromTable with $allFields = true
     */
    public function testGetFromTableWithAllFields(): void
    {
        $db = 'PMA_db';
        $table = 'PMA_table';

        $this->dbi->expects(self::once())
            ->method('fetchResult')
            ->with(
                'SELECT * FROM `pma_central_columns` '
                . "WHERE db_name = 'PMA_db' AND col_name IN ('id','col1','col2');",
                null,
                null,
                ConnectionType::ControlUser,
            )
            ->willReturn(array_slice($this->columnData, 0, 2));
        self::assertEquals(
            array_slice($this->modifiedColumnData, 0, 2),
            $this->centralColumns->getFromTable(
                $db,
                $table,
                true,
            ),
        );
    }

    /**
     * Test for updateOneColumn
     */
    public function testUpdateOneColumn(): void
    {
        self::assertTrue(
            $this->centralColumns->updateOneColumn(
                'phpmyadmin',
                '',
                '',
                '',
                '',
                '',
                false,
                '',
                '',
                '',
            ),
        );
        self::assertTrue(
            $this->centralColumns->updateOneColumn(
                'phpmyadmin',
                'col1',
                '',
                '',
                '',
                '',
                false,
                '',
                '',
                '',
            ),
        );
    }

    /**
     * Test for updateMultipleColumn
     */
    public function testUpdateMultipleColumn(): void
    {
        $params = [];
        $params['db'] = 'phpmyadmin';
        $params['orig_col_name'] = ['col1', 'col2'];
        $params['field_name'] = ['col1', 'col2'];
        $params['field_default_type'] = ['', ''];
        $params['col_extra'] = ['', ''];
        $params['field_length'] = ['', ''];
        $params['field_attribute'] = ['', ''];
        $params['field_type'] = ['', ''];
        $params['field_collation'] = ['', ''];
        self::assertTrue(
            $this->centralColumns->updateMultipleColumn($params),
        );
    }

    /**
     * Test for getHtmlForEditingPage
     */
    public function testGetHtmlForEditingPage(): void
    {
        $this->dbi->expects(self::any())
            ->method('fetchResult')
            ->with(
                'SELECT * FROM `pma_central_columns` '
                . "WHERE db_name = 'phpmyadmin' AND col_name IN ('col1','col2');",
                null,
                null,
                ConnectionType::ControlUser,
            )
            ->willReturn($this->columnData);
        $result = $this->centralColumns->getHtmlForEditingPage(
            ['col1', 'col2'],
            'phpmyadmin',
        );
        $listDetailCols = $this->callFunction(
            $this->centralColumns,
            CentralColumns::class,
            'findExistingColNames',
            ['phpmyadmin', ['col1', 'col2'], true],
        );
        self::assertStringContainsString(
            $this->callFunction(
                $this->centralColumns,
                CentralColumns::class,
                'getHtmlForEditTableRow',
                [$listDetailCols[0], 0],
            ),
            $result,
        );
    }

    /**
     * Test for getListRaw
     */
    public function testGetListRaw(): void
    {
        $this->dbi->expects(self::once())
            ->method('fetchResult')
            ->with(
                'SELECT * FROM `pma_central_columns` WHERE db_name = \'phpmyadmin\';',
                null,
                null,
                ConnectionType::ControlUser,
            )
            ->willReturn($this->columnData);
        self::assertEquals(
            $this->modifiedColumnData,
            $this->centralColumns->getListRaw(
                'phpmyadmin',
                '',
            ),
        );
    }

    /**
     * Test for getListRaw with a table name
     */
    public function testGetListRawWithTable(): void
    {
        $this->dbi->expects(self::once())
            ->method('fetchResult')
            ->with(
                'SELECT * FROM `pma_central_columns` '
                . "WHERE db_name = 'phpmyadmin' AND col_name "
                . "NOT IN ('id','col1','col2');",
                null,
                null,
                ConnectionType::ControlUser,
            )
            ->willReturn($this->columnData);
        self::assertEquals(
            $this->modifiedColumnData,
            $this->centralColumns->getListRaw(
                'phpmyadmin',
                'table1',
            ),
        );
    }

    /**
     * Test for findExistingColNames
     */
    public function testFindExistingColNames(): void
    {
        $this->dbi->expects(self::once())
            ->method('fetchResult')
            ->with(
                'SELECT * FROM `pma_central_columns` WHERE db_name = \'phpmyadmin\' AND col_name IN (\'col1\');',
                null,
                null,
                ConnectionType::ControlUser,
            )
            ->willReturn(array_slice($this->columnData, 1, 1));
        self::assertEquals(
            array_slice($this->modifiedColumnData, 1, 1),
            $this->callFunction(
                $this->centralColumns,
                CentralColumns::class,
                'findExistingColNames',
                ['phpmyadmin', ['col1'], true],
            ),
        );
    }

    public function testGetColumnsNotInCentralList(): void
    {
        $columns = $this->centralColumns->getColumnsNotInCentralList('PMA_db', 'PMA_table');
        self::assertIsArray($columns);
        self::assertEquals(['id', 'col1', 'col2'], $columns);
    }
}
