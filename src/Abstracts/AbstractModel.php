<?php
namespace CarloNicora\Minimalism\Abstracts;

use CarloNicora\JsonApi\Document;
use CarloNicora\Minimalism\Factories\ParametersFactory;
use CarloNicora\Minimalism\Factories\ServiceFactory;
use CarloNicora\Minimalism\Interfaces\ModelInterface;
use Exception;

class AbstractModel implements ModelInterface
{
    /** @var string  */
    private string $function;

    /** @var string|null  */
    protected ?string $view=null;

    /** @var array|null  */
    protected ?array $redirectionParameters=null;

    /** @var string|null  */
    protected ?string $redirection=null;

    /** @var array  */
    private array $parameters=[];

    /** @var Document  */
    protected Document $document;

    /**
     * AbstractModel constructor.
     * @param ServiceFactory $services
     */
    public function __construct(private ServiceFactory $services)
    {
        if ($this->services->getPath()->getUrl() === null) {
            $this->function = 'cli';
        } else {
            $this->function = strtolower($_SERVER['REQUEST_METHOD'] ?? 'GET');
            if ($this->function === 'post' && array_key_exists('HTTP_X_HTTP_METHOD', $_SERVER)) {
                if ($_SERVER['HTTP_X_HTTP_METHOD'] === 'DELETE') {
                    $this->function = 'delete';
                } elseif ($_SERVER['HTTP_X_HTTP_METHOD'] === 'PUT') {
                    $this->function = 'put';
                }
            }
        }

        $this->document = new Document();
    }

    /**
     * @param array $parameters
     */
    final public function setParameters(array $parameters): void
    {
        $this->parameters = $parameters;
    }

    /**
     * @return Document
     */
    final public function getDocument(): Document
    {
        return $this->document;
    }

    /**
     * @return string|null
     */
    final public function getView(): ?string
    {
        return $this->view;
    }

    /**
     * @return string|null
     */
    final public function getRedirection(): ?string
    {
        return $this->redirection;
    }

    /**
     * @return array|null
     */
    final public function getRedirectionParameters(): ?array
    {
        return $this->redirectionParameters;
    }

    /**
     * @return int
     * @throws Exception
     */
    final public function run(): int
    {
        $parametersFactory = new ParametersFactory(
            services: $this->services,
        );

        $parameters = $parametersFactory->getModelFunctionParameters(
            $this,
            $this->function,
            $this->parameters
        );

        return $this->{$this->function}(...$parameters);
    }
}