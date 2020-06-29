<?php

namespace CarloNicora\Minimalism\Tests\Unit\Core\Modules\Abstracts\Models;

use CarloNicora\Minimalism\Core\Modules\Abstracts\Models\AbstractApiModel;
use CarloNicora\Minimalism\Tests\Unit\AbstractTestCase;
use InvalidArgumentException;

class AbstractApiModelTest extends AbstractTestCase
{

    /** @noinspection PhpUndefinedMethodInspection */
    public function testRequiresAuthVerbs()
    {
        $instance = $this->getMockBuilder(AbstractApiModel::class)
            ->setConstructorArgs([$this->getServices()])
            ->getMockForAbstractClass();

        $this->assertFalse($instance->requiresAuth('DELETE'));
        $this->assertFalse($instance->requiresAuth('GET'));
        $this->assertFalse($instance->requiresAuth('POST'));
        $this->assertFalse($instance->requiresAuth('PUT'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('HTTP verb not supported');
        $this->assertFalse($instance->requiresAuth('PATCH'));
    }


    public function testGetParameters()
    {
        $instance = $this->getMockBuilder(AbstractApiModel::class)
            ->setConstructorArgs([$this->getServices()])
            ->getMockForAbstractClass();

        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertEmpty($instance->getParameters());
    }


    /** @noinspection PhpUndefinedFieldInspection */
    public function testSetVerb()
    {
        $instance = $this->getMockBuilder(AbstractApiModel::class)
            ->setConstructorArgs([$this->getServices()])
            ->getMockForAbstractClass();

        $this->assertEquals('GET', $instance->verb);

        /** @noinspection PhpUndefinedMethodInspection */
        $instance->setVerb('POST');
        $this->assertEquals('POST', $instance->verb);
    }
}