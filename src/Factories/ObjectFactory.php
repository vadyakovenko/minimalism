<?php
namespace CarloNicora\Minimalism\Factories;

use CarloNicora\Minimalism\Abstracts\AbstractFactory;
use CarloNicora\Minimalism\Enums\HttpCode;
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
    private array $objectsDefinitions=[];

    /** @var array  */
    private array $objectsFactoriesDefinitions=[];

    /** @var bool  */
    private bool $objectUpdated=false;

    /** @var array  */
    private array $pool=[];

    /** @var string|null  */
    protected ?string $objectsFactoriesDefinitionsCache=null;

    /** @var ?string|null  */
    protected ?string $objectsDefinitionsCache=null;

    /**
     *
     */
    public function initialiseFactory(
    ): void
    {
        $this->pool = [];
        $this->objectsFactoriesDefinitionsCache = $this->minimalismFactories->getServiceFactory()->getPath()->getCacheFile('objectsFactoriesDefinitions.cache');
        $this->objectsDefinitionsCache = $this->minimalismFactories->getServiceFactory()->getPath()->getCacheFile('objectsDefinitions.cache');

        if (
            is_file($this->objectsFactoriesDefinitionsCache)
            && ($cache = file_get_contents($this->objectsFactoriesDefinitionsCache)) !== false
        ) {
            $this->objectsFactoriesDefinitions = unserialize($cache, [true]);
        }

        if (
            is_file($this->objectsDefinitionsCache)
            && ($cache = file_get_contents($this->objectsDefinitionsCache)) !== false
        ) {
            $this->objectsDefinitions = unserialize($cache, [true]);
        }
    }

    /**
     * @return void
     */
    public function __wakeup(
    ): void
    {
        throw new RuntimeException('One or more services has not released ObjectFactory correctly.', HttpCode::InternalServerError->value);
    }

    /**
     * @return void
     */
    public function destroy(
    ): void
    {
        $this->pool = [];
        if ($this->objectUpdated) {
            if (!empty($this->objectsFactoriesDefinitionsCache)) {
                file_put_contents($this->objectsFactoriesDefinitionsCache, serialize($this->objectsFactoriesDefinitions));
            }
            if (!empty($this->objectsDefinitionsCache)) {
                file_put_contents($this->objectsDefinitionsCache, serialize($this->objectsDefinitions));
            }
        }
    }

    /**
     * @template InstanceOfType
     * @param class-string<InstanceOfType> $className
     * @param string|null $name
     * @param ModelParameters|null $parameters
     * @return InstanceOfType
     * @throws Exception
     * @noinspection PhpMixedReturnTypeCanBeReducedInspection
     */
    public function create(
        string $className,
        ?string $name=null,
        ?ModelParameters $parameters=null,
    ): mixed
    {
        $objectId = $className . $name . serialize($parameters);

        $response = $this->pool[$objectId] ??= null;

        if($response === null) {
            if (array_key_exists($className, $this->objectsDefinitions)) {
                $isSimpleObject = !array_key_exists($className, $this->objectsFactoriesDefinitions);
            } else {
                $isSimpleObject = (new ReflectionClass($className))->implementsInterface(SimpleObjectInterface::class);
            }

            if ($isSimpleObject) {
                $response = $this->createSimpleObject(
                    className: $className,
                    parameters: $parameters,
                );
            } else {
                $response = $this->createComplexObject(
                    className: $className,
                    name: $name,
                    parameters: $parameters,
                );
            }

            $this->pool[$objectId] = $response;
        }

        return $response;
    }

    /**
     * @param string $className
     * @param string|null $name
     * @param ModelParameters|null $parameters
     * @return ObjectInterface|null
     * @throws Exception
     */
    protected function createComplexObject(
        string $className,
        ?string $name,
        ?ModelParameters $parameters,
    ): ?ObjectInterface
    {
        if (array_key_exists($className, $this->objectsFactoriesDefinitions)){
            $factoryName = $this->objectsFactoriesDefinitions[$className]['factoryName'];
            $factoryConstructMethodParametersDefinitions = $this->objectsFactoriesDefinitions[$className]['coonstructMethodParameters'];
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
            }

            if ($factoryName === null){
                throw new RuntimeException('Missing factory name', HttpCode::InternalServerError);
            }

            $reflectionMethod = (new ReflectionClass($factoryName))->getMethod('__construct');
            $factoryConstructMethodParametersDefinitions = $this->getMethodParametersDefinition($reflectionMethod);

            $this->objectsFactoriesDefinitions[$className] = [
                'factoryName' => $factoryName,
                'coonstructMethodParameters' => $factoryConstructMethodParametersDefinitions,
            ];

            $this->objectUpdated = true;
        }

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
     * @param ModelParameters|null $parameters
     * @return SimpleObjectInterface|ObjectInterface
     * @throws Exception
     */
    public function createSimpleObject(
        string $className,
        ?ModelParameters $parameters=null,
    ): SimpleObjectInterface|ObjectInterface
    {
        if (array_key_exists($className, $this->objectsDefinitions)) {
            $objectConstructorParametersDefinitions = $this->objectsDefinitions[$className];
        } else {
            $reflectionClass = new ReflectionClass($className);

            if ($reflectionClass->hasMethod('__construct')) {
                $reflectionMethod = $reflectionClass->getMethod('__construct');
                $objectConstructorParametersDefinitions = $this->getMethodParametersDefinition($reflectionMethod);
            } else {
                $objectConstructorParametersDefinitions = [];
            }

            $this->objectsDefinitions[$className] = $objectConstructorParametersDefinitions;

            $this->objectUpdated = true;
        }

        $classConstructorParameters = $this->generateMethodParametersValues(
            methodParametersDefinition: $objectConstructorParametersDefinitions,
            parameters: $parameters,
        );

        return new $className(...$classConstructorParameters);
    }
}