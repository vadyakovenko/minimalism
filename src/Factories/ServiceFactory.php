<?php
namespace CarloNicora\Minimalism\Factories;

use CarloNicora\Minimalism\Interfaces\DefaultServiceInterface;
use CarloNicora\Minimalism\Interfaces\LoggerInterface;
use CarloNicora\Minimalism\Interfaces\ServiceInterface;
use CarloNicora\Minimalism\Interfaces\TransformerInterface;
use CarloNicora\Minimalism\Services\Path;
use Dotenv\Dotenv;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionUnionType;
use RuntimeException;

class ServiceFactory
{
    /** @var ServiceInterface[]  */
    private array $services = [];

    /** @var string|null  */
    private ?string $defaultService=null;

    /** @var string|null  */
    private ?string $transformerService=null;

    /** @var array  */
    private array $env;

    /**
     *
     */
    public function __construct()
    {
        $this->services[Path::class] = new Path();

        $this->env = Dotenv::createImmutable($this->getPath()->getRoot(), (empty($_SERVER['HTTP_TEST_ENVIRONMENT']) ? ['.env'] : ['.env.testing']))->load();

        if (is_file($this->getPath()->getCacheFile('services.cache')) && ($serviceFile = file_get_contents($this->getPath()->getCacheFile('services.cache'))) !== false ) {
            $this->services = unserialize($serviceFile, [true]);

            foreach ($this->services ?? [] as $service) {
                if ($service !== null && !is_string($service)) {
                    $service->initialise();
                }
            }
        } else {
            $vendorServicesFiles = glob(pattern: $this->getPath()->getRoot() . '/vendor/*/minimalism-service-*/src/*.php', flags: GLOB_NOSORT);
            $minimalismServicesFiles = glob(pattern: $this->getPath()->getRoot() . '/vendor/carlonicora/minimalism/src/Services/*.php', flags: GLOB_NOSORT);
            $internalServicesFiles = glob(pattern: $this->getPath()->getRoot() . '/src/Services/*/*.php', flags: GLOB_NOSORT);

            $allServicesFiles = array_merge($minimalismServicesFiles, $vendorServicesFiles, $internalServicesFiles);

            foreach ($allServicesFiles ?? [] as $serviceFile) {
                /** @noinspection UnusedFunctionResultInspection */
                $this->create(
                    className: MinimalismFactories::getNamespace($serviceFile)
                );
            }

            file_put_contents($this->getPath()->getCacheFile('services.cache'), serialize($this->services));
        }
    }

    /**
     * @param string $className
     * @return ServiceInterface
     */
    public function create(
        string $className,
    ): ServiceInterface
    {
        if (!array_key_exists($className, $this->services)){
            $response = $this->initialise(
                serviceFactory: $this,
                className: $className,
                parameters: $this->env,
            );
            $this->services[$className] = $response;

            /** @noinspection PhpUndefinedMethodInspection */
            if (($baseInterface = $className::getBaseInterface()) !== null){
                if (array_key_exists($baseInterface, $this->services)){
                    throw new RuntimeException('A base interface can only be extend by one service', 500);
                }

                $this->services[$baseInterface] = $className;
            }

            try {
                $reflectionClass = new ReflectionClass($className);
                if ($reflectionClass->implementsInterface(TransformerInterface::class)) {
                    $this->services[TransformerInterface::class] = $className;
                    $this->transformerService = $className;
                } elseif ($reflectionClass->implementsInterface(DefaultServiceInterface::class)) {
                    $this->services[TransformerInterface::class] = $className;
                    $this->defaultService = $className;
                }
            } catch (ReflectionException) {
            }
        } else {
            $response = $this->services[$className];

            if (is_string($response)){
                $response = $this->services[$response];
            }
        }

        return $response;
    }

    /**
     * @param ServiceFactory $serviceFactory
     * @param string $className
     * @param array|null $parameters
     * @return mixed
     */
    protected function initialise(
        ServiceFactory $serviceFactory,
        string $className,
        ?array $parameters=null,
    ): mixed
    {
        $objectParameters = [];

        try {
            $objectParametersDefinition = (new ReflectionClass($className))->getMethod('__construct')->getParameters();

            foreach ($objectParametersDefinition ?? [] as $objectParameterDefinition) {
                /** @var ReflectionNamedType|ReflectionUnionType $objectParameter */
                $objectParameter = $objectParameterDefinition->getType();
                try {
                    if (get_class($objectParameter) === ReflectionUnionType::class){
                        /** @var ReflectionNamedType $subParameter */
                        foreach ($objectParameter->getTypes() ?? [] as $subParameter) {
                            $reflect = new ReflectionClass($subParameter->getName());
                            if ($reflect->implementsInterface(DefaultServiceInterface::class)) {
                                $objectParameters[] = $serviceFactory->create($reflect->getName());
                                break;
                            }
                        }
                    } else {
                        $reflect = new ReflectionClass($objectParameter->getName());
                        if ($reflect->implementsInterface(ServiceInterface::class)) {
                            $objectParameters[] = $this->create($reflect->getName());
                        }
                    }
                } catch (ReflectionException) {
                    $parameter = $parameters[$objectParameterDefinition->getName()]??null;

                    if ($parameter === null && !$objectParameterDefinition->isOptional()) {
                        throw new RuntimeException(
                            message: 'An parameter is missing: ' . $objectParameterDefinition->getName(),
                            code: 500,
                        );
                    } else {
                        $parameter = $parameter??($objectParameterDefinition->isDefaultValueAvailable() ? $objectParameterDefinition->getDefaultValue() : null);
                    }

                    if ($objectParameterDefinition->hasType() && get_class($parameter) !== ReflectionUnionType::class) {
                        /** @var ReflectionNamedType $namedType */
                        $namedType = $objectParameterDefinition->getType();

                        $parameter = match ($namedType->getName()) {
                            'int' => (int)$parameter,
                            'bool' => filter_var($parameter, FILTER_VALIDATE_BOOLEAN),
                            default => $parameter,
                        };
                    }

                    $objectParameters[] = $parameter;
                }
            }
        } catch (ReflectionException) {
            throw new RuntimeException('Object dependecies loading failed for ' . $className, 500);
        }

        return new $className(...$objectParameters);
    }

    /**
     * @return ServiceInterface|Path
     */
    public function getPath(
    ): ServiceInterface|Path
    {
        return $this->services[Path::class];
    }

    /**
     * @return ServiceInterface|null
     */
    public function getDefaultService(
    ): ?ServiceInterface
    {
        if ($this->defaultService === null) {
            return null;
        }

        return $this->services[$this->defaultService];
    }

    /**
     * @return ServiceInterface|null
     */
    public function getTranformerService(
    ): ?ServiceInterface
    {
        if ($this->transformerService === null){
            return null;
        }

        return $this->services[$this->transformerService];
    }

    /**
     * @return LoggerInterface|null
     */
    public function getLogger(
    ): ?LoggerInterface
    {
        $response = null;

        if (array_key_exists(LoggerInterface::class, $this->services)){
            /** @var LoggerInterface $response */
            $response = $this->create(LoggerInterface::class);
        }

        return $response;
    }
}