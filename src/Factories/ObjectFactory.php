<?php
namespace CarloNicora\Minimalism\Factories;

use CarloNicora\Minimalism\Abstracts\AbstractFactory;
use CarloNicora\Minimalism\Interfaces\ObjectFactoryInterface;
use CarloNicora\Minimalism\Interfaces\ObjectInterface;
use CarloNicora\Minimalism\Interfaces\SimpleObjectInterface;
use CarloNicora\Minimalism\Objects\ModelParameters;
use Exception;
use ReflectionClass;
use ReflectionException;
use ReflectionUnionType;
use RuntimeException;

class ObjectFactory extends AbstractFactory
{
    /** @var array  */
    private array $objectsFactoriesDefinitions=[];

    /** @var bool  */
    private bool $objectsFactoriesDefinitionsUpdated=false;

    /**
     * @param MinimalismFactories $minimalismFactories
     * @throws Exception
     */
    public function __construct(
        MinimalismFactories $minimalismFactories,
    )
    {
        parent::__construct(minimalismFactories: $minimalismFactories);

        if (is_file($this->minimalismFactories->getServiceFactory()->getPath()->getCacheFile('minimalismObjectsDefinitions.cache'))  && ($cache = file_get_contents($this->minimalismFactories->getServiceFactory()->getPath()->getCacheFile('minimalismObjectsDefinitions.cache'))) !== false) {
            $this->objectsFactoriesDefinitions = unserialize($cache, [true]);
        }
    }

    /**
     *
     */
    public function __destruct(
    )
    {
        if ($this->objectsFactoriesDefinitionsUpdated) {
            file_put_contents($this->minimalismFactories->getServiceFactory()->getPath()->getCacheFile('minimalismObjectsDefinitions.cache'), serialize($this->objectsFactoriesDefinitions));
        }
    }

    /**
     * @param string $className
     * @param string $name
     * @param ModelParameters $parameters
     * @return ObjectInterface|SimpleObjectInterface
     * @throws Exception
     */
    public function create(
        string $className,
        string $name,
        ModelParameters $parameters,
    ): ObjectInterface|SimpleObjectInterface
    {
        if (array_key_exists($className, $this->objectsFactoriesDefinitions)){
            $isSimpleObject = !is_string($this->objectsFactoriesDefinitions[$className]);
        } else {
            $isSimpleObject = (new ReflectionClass($className))->implementsInterface(SimpleObjectInterface::class);
        }

        if ($isSimpleObject){
            $response = $this->createSimpleObject(className: $className,parameters: $parameters);
        } else {
            $response = $this->createComplexObject(className: $className,name:$name,parameters: $parameters);
        }

        return $response;
    }

    /**
     * @param string $className
     * @param string $name
     * @param ModelParameters $parameters
     * @return ObjectInterface
     * @throws Exception
     */
    private function createComplexObject(
        string $className,
        string $name,
        ModelParameters $parameters,
    ): ObjectInterface
    {
        if (array_key_exists($className, $this->objectsFactoriesDefinitions) && array_key_exists($this->objectsFactoriesDefinitions[$className], $this->objectsFactoriesDefinitions)) {
            $factoryName = $this->objectsFactoriesDefinitions[$className];
            $factoryConstructMethodParametersDefinitions = $this->objectsFactoriesDefinitions[$factoryName];
        } else {
            $factoryName = null;

            try {
                /** @var ReflectionUnionType $types */
                $types = (new ReflectionClass($className))->getMethod('getObjectFactoryClass')->getReturnType();

                foreach ($types->getTypes() as $type){
                    if ($type->getName() !== 'string'){
                        $factoryName = $type->getName();
                        break;
                    }
                }
            } catch (ReflectionException) {
                $factoryName = null;
            }

            if ($factoryName === null){
                throw new RuntimeException('nope', 500);
            }

            $reflectionMethod = (new ReflectionClass($factoryName))->getMethod('__construct');
            $factoryConstructMethodParametersDefinitions = $this->getMethodParametersDefinition($reflectionMethod);

            $this->objectsFactoriesDefinitions[$className] = $factoryName;
            $this->objectsFactoriesDefinitions[$factoryName] = $factoryConstructMethodParametersDefinitions;

            $this->objectsFactoriesDefinitionsUpdated = true;
        }

        /** @var ObjectFactoryInterface $factory */
        $factoryConstructorParameters = $this->generateMethodParametersValues(
            methodParametersDefinition: $factoryConstructMethodParametersDefinitions,
            parameters: $parameters,
        );

        return (new $factoryName(...$factoryConstructorParameters))->create(
            className: $className,
            parameterName: $name,
            parameters: $parameters,
        );
    }

    /**
     * @param string $className
     * @param ModelParameters $parameters
     * @return SimpleObjectInterface
     * @throws Exception
     */
    private function createSimpleObject(
        string $className,
        ModelParameters $parameters,
    ): SimpleObjectInterface
    {
        if (array_key_exists($className, $this->objectsFactoriesDefinitions)) {
            $objectConstructorParametersDefinitions = $this->objectsFactoriesDefinitions[$className];
        } else {
            $reflectionMethod = (new ReflectionClass($className))->getMethod('__construct');
            $objectConstructorParametersDefinitions = $this->getMethodParametersDefinition($reflectionMethod);

            $this->objectsFactoriesDefinitions[$className] = $objectConstructorParametersDefinitions;

            $this->objectsFactoriesDefinitionsUpdated = true;
        }

        $classConstructorParameters = $this->generateMethodParametersValues(
            methodParametersDefinition: $objectConstructorParametersDefinitions,
            parameters: $parameters,
        );

        return new $className(...$classConstructorParameters);
    }
}