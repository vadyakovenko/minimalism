<?php
namespace CarloNicora\Minimalism\Core\Modules\Abstracts\Controllers;

use CarloNicora\Minimalism\Core\Modules\Interfaces\ControllerInterface;
use CarloNicora\Minimalism\Core\Modules\Interfaces\ModelInterface;
use CarloNicora\Minimalism\Core\Response;
use CarloNicora\Minimalism\Core\Services\Factories\ServicesFactory;
use CarloNicora\Minimalism\Services\Logger\Events\MinimalismErrorEvents;
use CarloNicora\Minimalism\Services\Logger\Events\MinimalismInfoEvents;
use Exception;
use JsonException;
use RuntimeException;

abstract class AbstractController implements ControllerInterface
{
    /** @var string */
    protected string $modelName = 'index';

    /** @var ServicesFactory */
    protected ServicesFactory $services;

    /** @var ModelInterface */
    protected ModelInterface $model;

    /** @var array */
    protected array $passedParameters = [];

    /** @var array */
    protected ?array $file=null;

    /** @var string */
    public string $version;

    /** @var array  */
    protected array $bodyParameters = [];

    /** @var string|null  */
    protected ?string $phpInput=null;

    /** @var string  */
    protected string $verb='GET';

    /**
     * abstractController constructor.
     * @param ServicesFactory $services
     */
    public function __construct(ServicesFactory $services)
    {
        $this->services = $services;

        $this->getPhpInputParameters();
    }

    /**
     * @param array $parameterValueList
     * @param array $parameterValues
     * @return ControllerInterface
     * @throws Exception
     */
    public function initialiseParameters(array $parameterValueList=[], array $parameterValues=[]): ControllerInterface
    {
        if (!empty($parameterValueList) || !empty($parameterValues)) {
            $this->passedParameters = $parameterValueList;
            $this->bodyParameters = $parameterValues;
        } else {
            $this->parseUriParameters();

            switch ($this->getHttpType()) {
                case 'GET':
                    foreach ($_GET as $parameter => $value) {
                        if ($parameter !== 'path' && $parameter !== 'XDEBUG_SESSION_START') {
                            $this->passedParameters[$parameter] = $value;
                        }
                    }

                    break;
                case 'POST':
                case 'PUT':
                case 'DELETE':
                    if (!empty($this->phpInput)) {
                        try {
                            $this->bodyParameters = json_decode($this->phpInput, true, 512, JSON_THROW_ON_ERROR);
                        } catch (Exception $e) {
                            $this->bodyParameters = [];
                        }
                    }

                    if (isset($_FILES) && count($_FILES) === 1) {
                        $this->file = array_values($_FILES)[0];
                    }

                    foreach ($_POST as $parameter => $value) {
                        $this->bodyParameters[$parameter] = $value;
                    }

                    break;
            }
        }

        $this->services->logger()->info()->log(MinimalismInfoEvents::PARAMETERS_INITIALISED());

        return $this;
    }

    /**
     * @param string|null $modelName
     * @return ControllerInterface
     * @throws Exception
     */
    public function initialiseModel(string $modelName = null): ControllerInterface
    {
        if (isset($modelName)) {
            $this->modelName = str_replace('-', '\\', $modelName);
        }

        $modelClass = $this->services->paths()->getNamespace() . 'Models\\' . str_replace('/', '\\', $this->modelName);

        if (!class_exists($modelClass)){
            throw new RuntimeException('model ' . $this->modelName . ' not found', 404);
        }

        $this->model = new $modelClass($this->services);

        $this->model->initialise(array_merge($this->passedParameters, $this->bodyParameters), $this->file);

        if ($this->model->redirect() !== ''){
            $this->initialiseModel($this->model->redirect());
        }

        $this->services->logger()->info()->log(MinimalismInfoEvents::MODEL_INITIALISED($this->modelName));

        return $this;
    }

    /**
     *
     */
    protected function getPhpInputParameters(): void
    {
        $this->phpInput = file_get_contents('php://input');
    }

    /**
     *
     */
    protected function parseUriParameters(): void
    {
        if (array_key_exists('REQUEST_URI', $_SERVER)) {
            $uri = strtok($_SERVER['REQUEST_URI'], '?');

            if (!(isset($uri) && $uri === '/')) {
                $variables = array_filter(explode('/', substr($uri, 1)), 'strlen');

                $this->passedParameters = $this->parseModelNameFromUri($variables);
            }
        }
    }

    /**
     * @param array $uriVariables
     * @return array
     */
    protected function parseModelNameFromUri(array $uriVariables): array
    {
        $firstArgument = current($uriVariables);
        if (false === $firstArgument || is_numeric($firstArgument)) {
            return $uriVariables;
        }

        $response = [];
        $this->modelName = array_shift($uriVariables);
        foreach ($uriVariables as $uriParam) {
            $classPath = $this->services->paths()->getModelsFolder() . $this->modelName . DIRECTORY_SEPARATOR . $uriParam;
            if (is_dir($classPath) || is_file($classPath . '.php')) {
                $this->modelName .= DIRECTORY_SEPARATOR . $uriParam;
            } else {
                $response[] = $uriVariables[0];
            }
            array_shift($uriVariables);
        }

        return $response;
    }


    /**
     * @return Response
     */
    abstract public function render(): Response;

    /**
     * @param int $code
     * @param string $response
     */
    public function completeRender(int $code=null, string $response=null): void
    {
        try {
            $cookieValue = $this->services->serialiseCookies();
            setcookie('minimalismServices', $cookieValue, time() + (30 * 24 * 60 * 60));
        } catch (JsonException $e) {
            $this->services->logger()->error()
                ->log(MinimalismErrorEvents::COOKIE_SETTING_ERROR($e));
        }

        $this->services->cleanNonPersistentVariables();
        $_SESSION['minimalismServices'] = $this->services;
        $this->services->logger()->info()->log(MinimalismInfoEvents::SESSION_PERSISTED());

        $this->model->postRender($code, $response);
    }

    /**
     * @return string
     */
    protected function getHttpType(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    /**
     * @return ControllerInterface
     */
    abstract public function postInitialise(): ControllerInterface;
}