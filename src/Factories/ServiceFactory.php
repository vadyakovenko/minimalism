<?php
namespace CarloNicora\Minimalism\Factories;

use CarloNicora\Minimalism\Interfaces\DefaultServiceInterface;
use CarloNicora\Minimalism\Interfaces\LoggerInterface;
use CarloNicora\Minimalism\Interfaces\ServiceInterface;
use CarloNicora\Minimalism\Interfaces\TransformerInterface;
use CarloNicora\Minimalism\Services\Path;
use Dotenv\Dotenv;
use Exception;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionUnionType;
use RuntimeException;

class ServiceFactory
{
    /** @var ServiceInterface[]  */
    private array $services = [];

    /** @var bool  */
    private bool $loaded=false;

    /** @var array  */
    private array $env;

    /**
     *
     */
    public function __construct(
        private MinimalismFactories $minimalismFactories,
    )
    {
    }

    /**
     *
     */
    public function initialiseFactory(
    ): void
    {
        $this->services[Path::class] = new Path();

        try {
            $this->env = Dotenv::createImmutable($this->getPath()->getRoot(), (empty($_SERVER['HTTP_TEST_ENVIRONMENT']) ? ['.env'] : ['.env.testing']))->load();
        } catch (Exception) {
            $this->env = [];
        }

        if (is_file($this->getPath()->getCacheFile('services.cache')) && ($serviceFile = file_get_contents($this->getPath()->getCacheFile('services.cache'))) !== false ) {
            $this->services = unserialize($serviceFile, [true]);

            foreach ($this->services as $service) {
                if ($service !== null && !is_string($service)) {
                    $service->initialise();
                    $service->setObjectFactory($this->minimalismFactories->getObjectFactory());
                }
            }
        } else {
            foreach ($this->getServiceFiles() as $serviceFile) {
                /** @noinspection UnusedFunctionResultInspection */
                $this->create(
                    className: MinimalismFactories::getNamespace($serviceFile)
                );
            }

            $this->loaded = true;
        }
    }

    /**
     * @return array
     */
    private function getServiceFiles(
    ): array
    {
        $vendorServicesFiles = glob(pattern: $this->getPath()->getRoot() . '/vendor/*/minimalism-service-*/src/*.php', flags: GLOB_NOSORT);
        $defaultServicesFiles = glob(pattern: $this->getPath()->getRoot() . '/src/*.php', flags: GLOB_NOSORT);
        $internalServicesFiles = glob(pattern: $this->getPath()->getRoot() . '/src/Services/*/*.php', flags: GLOB_NOSORT);

        return array_merge($vendorServicesFiles, $internalServicesFiles, $defaultServicesFiles);
    }

    /**
     *
     */
    public function __destruct()
    {
        if ($this->getDefaultService() !== null) {
            foreach ($this->getDefaultService()->getDelayedServices() as $delayedServices) {
                $this->services[$delayedServices]->destroy();
            }
        }

        $loggerClass = null;
        if ($this->getLogger() !== null) {
            $this->getLogger()->destroy();
            $loggerClass = $this->services[LoggerInterface::class];
        }

        /** @var ServiceInterface|string $service */
        foreach ($this->services ?? [] as $serviceName=>$service){
            if ($service !== null && !is_string($service) && $serviceName !== $loggerClass) {
                $service->destroy();
            }
        }

        if ($this->loaded) {
            file_put_contents($this->getPath()->getCacheFile('services.cache'), serialize($this->services));
        }
    }

    /**
     * @template InstanceOfType
     * @param class-string<InstanceOfType> $className
     * @return InstanceOfType|ServiceInterface|null
     * @noinspection PhpMixedReturnTypeCanBeReducedInspection
     */
    public function create(
        string $className,
    ): mixed
    {
        if (!class_exists($className) && !interface_exists($className)) {
            return null;
        }

        if (!array_key_exists($className, $this->services)){
            $response = $this->initialise(
                serviceFactory: $this,
                className: $className,
                parameters: $this->env,
            );

            if ($response === null){
                return null;
            }

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
                } elseif ($reflectionClass->implementsInterface(DefaultServiceInterface::class)) {
                    $this->services[DefaultServiceInterface::class] = $className;
                } elseif ($reflectionClass->implementsInterface(LoggerInterface::class)) {
                    $this->services[LoggerInterface::class] = $className;
                }
            } catch (ReflectionException) {
            }
        } else {
            /** @var ServiceInterface|string $response */
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
     * @return ServiceInterface|null
     */
    protected function initialise(
        ServiceFactory $serviceFactory,
        string &$className,
        ?array $parameters=null,
    ): ?ServiceInterface
    {
        $objectParameters = [];

        try {
            $reflectionClass = new ReflectionClass($className);
            if ($reflectionClass->isInterface()) {
                $interfaceClassFound = false;
                foreach ($this->getServiceFiles() as $serviceFile){
                    $serviceClassName = MinimalismFactories::getNamespace($serviceFile);
                    /** @noinspection PhpUndefinedMethodInspection */
                    if ($serviceClassName::getBaseInterface() === $className){
                        $className=$serviceClassName;
                        $interfaceClassFound = true;
                        break;
                    }
                }

                if (!$interfaceClassFound){
                    return null;
                }
            }

            if (!$reflectionClass->implementsInterface(ServiceInterface::class)){
                return null;
            }

            $objectParametersDefinition = (new ReflectionClass($className))->getMethod('__construct')->getParameters();

            foreach ($objectParametersDefinition as $objectParameterDefinition) {
                /** @var ReflectionNamedType|ReflectionUnionType $objectParameter */
                $objectParameter = $objectParameterDefinition->getType();
                try {
                    if (get_class($objectParameter) === ReflectionUnionType::class){
                        /** @var ReflectionNamedType $subParameter */
                        foreach ($objectParameter->getTypes() as $subParameter) {
                            $reflect = new ReflectionClass($subParameter->getName());
                            if ($reflect->implementsInterface(DefaultServiceInterface::class)) {
                                $objectParameters[] = $serviceFactory->create($reflect->getName());
                                break;
                            }
                        }
                    } else {
                        $reflect = new ReflectionClass($objectParameter->getName());
                        if ($reflect->getName() === MinimalismFactories::class) {
                            $objectParameters[] = $this->minimalismFactories;
                        } elseif ($reflect->getName() === __CLASS__){
                            $objectParameters[] = $this;
                        } elseif ($reflect->getName() === ObjectFactory::class){
                            $objectParameters[] = $this->minimalismFactories->getObjectFactory();
                        } elseif ($reflect->getName() === ModelFactory::class){
                            $objectParameters[] = $this->minimalismFactories->getModelFactory();
                        } elseif ($reflect->implementsInterface(ServiceInterface::class)) {
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
                    }

                    $parameter = $parameter??($objectParameterDefinition->isDefaultValueAvailable() ? $objectParameterDefinition->getDefaultValue() : null);

                    if ($objectParameterDefinition->hasType()) {
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

        /** @var ServiceInterface $response */
        $response = new $className(...$objectParameters);
        $response->setObjectFactory($this->minimalismFactories->getObjectFactory());

        return $response;
    }

    /**
     * @return Path
     */
    public function getPath(
    ): Path
    {
        /** @var Path $response */
        /** @noinspection PhpUnnecessaryLocalVariableInspection */
        /** @noinspection OneTimeUseVariablesInspection */
        $response = $this->services[Path::class];
        return $response;
    }

    /**
     * @return DefaultServiceInterface|null
     */
    public function getDefaultService(
    ): DefaultServiceInterface|null
    {
        if (!array_key_exists(DefaultServiceInterface::class, $this->services)) {
            return null;
        }

        /** @var DefaultServiceInterface $response */
        /** @noinspection PhpUnnecessaryLocalVariableInspection */
        /** @noinspection OneTimeUseVariablesInspection */
        $response = $this->create(DefaultServiceInterface::class);

        return $response;
    }

    /**
     * @return TransformerInterface|null
     */
    public function getTranformerService(
    ): TransformerInterface|null
    {
        if (!array_key_exists(TransformerInterface::class, $this->services)) {
            return null;
        }

        /** @var TransformerInterface $response */
        /** @noinspection PhpUnnecessaryLocalVariableInspection */
        /** @noinspection OneTimeUseVariablesInspection */
        $response = $this->create(TransformerInterface::class);

        return $response;
    }

    /**
     * @return LoggerInterface|null
     */
    public function getLogger(
    ): LoggerInterface|null
    {
        if (!array_key_exists(LoggerInterface::class, $this->services)){
            return null;
        }

        /** @var LoggerInterface $response */
        /** @noinspection PhpUnnecessaryLocalVariableInspection */
        /** @noinspection OneTimeUseVariablesInspection */
        $response = $this->create(LoggerInterface::class);

        return $response;
    }
}