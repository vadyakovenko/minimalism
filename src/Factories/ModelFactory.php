<?php
namespace CarloNicora\Minimalism\Factories;

use CarloNicora\Minimalism\Interfaces\ModelInterface;
use Exception;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

class ModelFactory
{
    /** @var array  */
    private array $models;

    /**
     * ModelFactory constructor.
     * @param ServiceFactory $services
     */
    public function __construct(private ServiceFactory $services)
    {
        $this->loadModels();
    }

    /**
     * @param string|null $modelName
     * @return ModelInterface
     * @throws Exception
     */
    public function create(?string $modelName=null): ModelInterface
    {
        $parametersFactory = new ParametersFactory($this->services, $this->models);

        /** @var ModelInterface $response */
        $response = null;

        try {
            $modelLoader = new ReflectionClass($modelName ?? $parametersFactory->getModelName());
            $response = $modelLoader->newInstance([$this->services]);
        } catch (ReflectionException) {
            throw new RuntimeException('Cannot create model', 500);
        }

        $response->setParameters(
            $parametersFactory->createParameters()
        );

        return $response;
    }

    /**
     *
     */
    private function loadModels(): void
    {
        $modelCache = $this->services->getRoot()
            . DIRECTORY_SEPARATOR . 'data'
            . DIRECTORY_SEPARATOR . 'cache'
            . DIRECTORY_SEPARATOR . 'models.cache';

        if (file_exists($modelCache) && ($modelsFile = file_get_contents($modelCache)) !== false){
            $this->models = unserialize($modelsFile, [true]);
        } else {
            $this->models = $this->loadFolderModels($this->services->getRoot()
                . DIRECTORY_SEPARATOR . 'src'
                . DIRECTORY_SEPARATOR . 'Models'
            );

            file_put_contents($modelCache, serialize($this->models));
        }
    }

    /**
     * @param string $folder
     * @return array
     */
    private function loadFolderModels(string $folder): array
    {
        $response = [];
        $models = glob($folder . DIRECTORY_SEPARATOR . '*');
        foreach ($models ?? [] as $model) {
            $modelInfo = pathinfo($model);
            if (!array_key_exists('extension', $modelInfo)){
                $response[strtolower(basename($model)) . '-folder'] = $this->loadFolderModels($model);
            } elseif ($modelInfo['extension'] === 'php'){
                $modelName = basename(substr($model, 0, -4));

                if (preg_match('#^namespace\s+(.+?);$#sm', file_get_contents($model), $m)) {
                    $modelClass = $m[1] . '\\' . $modelName;
                    $response[strtolower($modelName)] = $modelClass;
                }
            }
        }

        return $response;
    }
}