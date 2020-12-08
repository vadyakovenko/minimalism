<?php
namespace CarloNicora\Minimalism\Tests\Unit\Services\Paths\Configurations;

use CarloNicora\Minimalism\Services\Paths\Configurations\PathsConfigurations;
use CarloNicora\Minimalism\Tests\Unit\AbstractTestCase;

class PathsConfigurationsTest extends AbstractTestCase
{
    public function testDefaultConfigurations() : void
    {
        $config = new PathsConfigurations();
        self::assertInstanceOf(PathsConfigurations::class, $config);
    }

    public function testGetServiceFactories()
    {
        $instance = new PathsConfigurations();
        self::assertNotEmpty($instance->getServiceFactories());
    }
}
