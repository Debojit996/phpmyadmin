<?php

declare(strict_types=1);

namespace PhpMyAdmin\Tests\Properties\Plugins;

use PhpMyAdmin\Properties\Plugins\ExportPluginProperties;
use PhpMyAdmin\Tests\AbstractTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ExportPluginProperties::class)]
class ExportPluginPropertiesTest extends AbstractTestCase
{
    protected ExportPluginProperties $object;

    /**
     * Configures global environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->object = new ExportPluginProperties();
    }

    /**
     * tearDown for test cases
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->object);
    }

    public function testGetItemType(): void
    {
        self::assertEquals(
            'export',
            $this->object->getItemType(),
        );
    }

    /**
     * Test for
     *     - PhpMyAdmin\Properties\Plugins\ExportPluginProperties::getForceFile
     *     - PhpMyAdmin\Properties\Plugins\ExportPluginProperties::setForceFile
     */
    public function testSetGetForceFile(): void
    {
        $this->object->setForceFile(true);

        self::assertTrue(
            $this->object->getForceFile(),
        );
    }
}
