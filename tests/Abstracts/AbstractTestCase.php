<?php /** @noinspection PhpExpressionResultUnusedInspection */

namespace CarloNicora\Minimalism\Tests\Abstracts;

use CarloNicora\Minimalism\Tests\Factories\MocksFactory;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;

abstract class AbstractTestCase extends TestCase
{
    /** @var MocksFactory */
    protected MocksFactory $mocker;

    /**
     * @return void
     */
    protected function setUp(
    ): void
    {
        parent::setUp();

        $this->mocker = new MocksFactory($this);
    }

    /**
     * @param $object
     * @param $parameterName
     * @return mixed
     */
    protected function getProperty($object, $parameterName): mixed
    {
        try {
            $property = (new ReflectionClass(get_class($object)))->getProperty($parameterName);
            $property->setAccessible(true);
            return $property->getValue($object);
        } catch (ReflectionException) {
            return null;
        }
    }

    /**
     * @param $object
     * @param $parameterName
     * @param $parameterValue
     */
    protected function setProperty($object, $parameterName, $parameterValue): void
    {
        try {
            $property = (new ReflectionClass(get_class($object)))->getProperty($parameterName);
            $property->setAccessible(true);
            $property->setValue($object, $parameterValue);
        } catch (ReflectionException) {
        }
    }
}