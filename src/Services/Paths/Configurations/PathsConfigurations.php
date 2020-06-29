<?php
namespace CarloNicora\Minimalism\Services\Paths\Configurations;

use CarloNicora\Minimalism\Core\Services\Abstracts\AbstractServiceConfigurations;
use CarloNicora\Minimalism\Core\Services\Interfaces\ServiceFactoryInterface;
use ReflectionClass;
use ReflectionException;

class PathsConfigurations extends AbstractServiceConfigurations
{
    /**
     * @var array
     */
    private array $servicesNamespaces = [];

    /**
     * @var array
     */
    private array $serviceFactories = [];

    /** @var array  */
    private array $servicesViewsDirectories = [];

    /** @var array  */
    private array $servicesModelsDirectories = [];

    /**
     * mailingConfigurations constructor.
     */
    public function __construct()
    {
        $this->initialiseServiceFactories();
    }

    /**
     *
     */
    private function initialiseServiceFactories(): void
    {
        $plugins = glob(realpath('./vendor') . '/*/*/src/Factories/ServiceFactory.php');
        $builtIn = glob(realpath('./vendor') . '/*/*/src/Services/*/Factories/ServiceFactory.php');
        $internal = glob(realpath('./src') . '/Services/*/Factories/ServiceFactory.php');
        $microservice = glob(realpath('./src') . '/Factories/ServiceFactory.php');

        $files = array_unique(array_merge($plugins, $builtIn, $internal, $microservice));

        $modelsDir = current(glob(realpath('./src') . '/Models'));
        if (false === empty($modelsDir) && file_exists($modelsDir)) {
            $this->servicesModelsDirectories[] = $modelsDir;
        }

        foreach ($files as $fileName) {
            /** @noinspection PhpIncludeInspection */
            require_once $fileName;

            $possibleModelDirectory = dirname($fileName, 2) . DIRECTORY_SEPARATOR . 'Models';
            if (file_exists($possibleModelDirectory)){
                $this->servicesModelsDirectories[] = $possibleModelDirectory;
            }

            $possibleViewDirectory = dirname($fileName, 2) . DIRECTORY_SEPARATOR . 'Views';
            if (file_exists($possibleViewDirectory)){
                $this->servicesViewsDirectories[] = $possibleViewDirectory;
            }
        }

        $classes = get_declared_classes();
        foreach ($classes as $singleClass) {
            try {
                $reflect = new ReflectionClass($singleClass);
                if ($reflect->implementsInterface(ServiceFactoryInterface::class) && !$reflect->isAbstract()) {

                    $serviceNamespaceParts = explode('\\', $singleClass);
                    $serviceNamespace = '';
                    for ($partsCount=0; $partsCount<count($serviceNamespaceParts)-2;$partsCount++){
                        $serviceNamespace .= ($partsCount === 0 ? '' : '\\') . $serviceNamespaceParts[$partsCount];
                    }

                    if (!in_array($serviceNamespace, $this->servicesNamespaces, true)){
                        $this->servicesNamespaces[] = $serviceNamespace;
                    }

                    $this->serviceFactories[] = $singleClass;
                }
            } catch (ReflectionException $e) {
            }
        }
    }

    /**
     * @return array
     */
    public function getServiceFactories(): array
    {
        return $this->serviceFactories;
    }

    /**
     * @return array
     */
    public function getServicesNamespaces(): array
    {
        return $this->servicesNamespaces;
    }

    /**
     * @return array
     */
    public function getServicesViewsDirectories(): array
    {
        return $this->servicesViewsDirectories;
    }

    /**
     * @return array
     */
    public function getServicesModelsDirectories(): array
    {
        return $this->servicesModelsDirectories;
    }
}