<?php

declare(strict_types=1);

namespace PhpMyAdmin\Tests\Plugins\Export\Helpers;

use PhpMyAdmin\Plugins\Export\Helpers\TableProperty;
use PhpMyAdmin\Tests\AbstractTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(TableProperty::class)]
class TablePropertyTest extends AbstractTestCase
{
    protected TableProperty $object;

    /**
     * Configures global environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $row = [' name ', 'int ', true, ' PRI', '0', 'mysql'];
        $this->object = new TableProperty($row);
    }

    /**
     * tearDown for test cases
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->object);
    }

    public function testConstructor(): void
    {
        self::assertEquals('name', $this->object->name);

        self::assertEquals('int', $this->object->type);

        self::assertEquals(1, $this->object->nullable);

        self::assertEquals('PRI', $this->object->key);

        self::assertEquals('0', $this->object->defaultValue);

        self::assertEquals('mysql', $this->object->ext);
    }

    public function testGetPureType(): void
    {
        $this->object->type = 'int(10)';

        self::assertEquals(
            'int',
            $this->object->getPureType(),
        );

        $this->object->type = 'char';

        self::assertEquals(
            'char',
            $this->object->getPureType(),
        );
    }

    /**
     * @param string $nullable nullable value
     * @param string $expected expected output
     */
    #[DataProvider('isNotNullProvider')]
    public function testIsNotNull(string $nullable, string $expected): void
    {
        $this->object->nullable = $nullable;

        self::assertEquals(
            $expected,
            $this->object->isNotNull(),
        );
    }

    /**
     * Data provider for testIsNotNull
     *
     * @return mixed[] Test Data
     */
    public static function isNotNullProvider(): array
    {
        return [['NO', 'true'], ['', 'false'], ['no', 'false']];
    }

    /**
     * @param string $key      key value
     * @param string $expected expected output
     */
    #[DataProvider('isUniqueProvider')]
    public function testIsUnique(string $key, string $expected): void
    {
        $this->object->key = $key;

        self::assertEquals(
            $expected,
            $this->object->isUnique(),
        );
    }

    /**
     * Data provider for testIsUnique
     *
     * @return mixed[] Test Data
     */
    public static function isUniqueProvider(): array
    {
        return [['PRI', 'true'], ['UNI', 'true'], ['', 'false'], ['pri', 'false'], ['uni', 'false']];
    }

    /**
     * @param string $type     type value
     * @param string $expected expected output
     */
    #[DataProvider('getDotNetPrimitiveTypeProvider')]
    public function testGetDotNetPrimitiveType(string $type, string $expected): void
    {
        $this->object->type = $type;

        self::assertEquals(
            $expected,
            $this->object->getDotNetPrimitiveType(),
        );
    }

    /**
     * Data provider for testGetDotNetPrimitiveType
     *
     * @return mixed[] Test Data
     */
    public static function getDotNetPrimitiveTypeProvider(): array
    {
        return [
            ['int', 'int'],
            ['long', 'long'],
            ['char', 'string'],
            ['varchar', 'string'],
            ['text', 'string'],
            ['longtext', 'string'],
            ['tinyint', 'bool'],
            ['datetime', 'DateTime'],
            ['', 'unknown'],
            ['dummy', 'unknown'],
            ['INT', 'unknown'],
        ];
    }

    /**
     * @param string $type     type value
     * @param string $expected expected output
     */
    #[DataProvider('getDotNetObjectTypeProvider')]
    public function testGetDotNetObjectType(string $type, string $expected): void
    {
        $this->object->type = $type;

        self::assertEquals(
            $expected,
            $this->object->getDotNetObjectType(),
        );
    }

    /**
     * Data provider for testGetDotNetObjectType
     *
     * @return mixed[] Test Data
     */
    public static function getDotNetObjectTypeProvider(): array
    {
        return [
            ['int', 'Int32'],
            ['long', 'Long'],
            ['char', 'String'],
            ['varchar', 'String'],
            ['text', 'String'],
            ['longtext', 'String'],
            ['tinyint', 'Boolean'],
            ['datetime', 'DateTime'],
            ['', 'Unknown'],
            ['dummy', 'Unknown'],
            ['INT', 'Unknown'],
        ];
    }

    public function testGetIndexName(): void
    {
        $this->object->name = "ä'7<ab>";
        $this->object->key = 'PRI';

        self::assertEquals(
            "index=\"ä'7&lt;ab&gt;\"",
            $this->object->getIndexName(),
        );

        $this->object->key = '';

        self::assertEquals(
            '',
            $this->object->getIndexName(),
        );
    }

    public function testIsPK(): void
    {
        $this->object->key = 'PRI';

        self::assertTrue(
            $this->object->isPK(),
        );

        $this->object->key = '';

        self::assertFalse(
            $this->object->isPK(),
        );
    }

    public function testFormatCs(): void
    {
        $this->object->name = 'Name#name#123';

        self::assertEquals(
            'text123Namename',
            $this->object->formatCs('text123#name#'),
        );
    }

    public function testFormatXml(): void
    {
        $this->object->name = '"a\'';

        self::assertEquals(
            '&quot;a\'index="&quot;a\'"',
            $this->object->formatXml('#name##indexName#'),
        );
    }

    public function testFormat(): void
    {
        self::assertEquals(
            'NameintInt32intfalsetrue',
            $this->object->format(
                '#ucfirstName##dotNetPrimitiveType##dotNetObjectType##type##notNull##unique#',
            ),
        );
    }
}
