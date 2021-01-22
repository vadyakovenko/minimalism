<?php
namespace CarloNicora\Minimalism;

use CarloNicora\JsonApi\Document;
use CarloNicora\JsonApi\Objects\Error;
use CarloNicora\Minimalism\Factories\ModelFactory;
use CarloNicora\Minimalism\Factories\ServiceFactory;
use Composer\InstalledVersions;
use Exception;
use JsonException;
use PackageVersions\Versions;
use Throwable;

class Minimalism
{
    /** @var ServiceFactory  */
    private ServiceFactory $services;

    /** @var string[]  */
    private array $httpCodes = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-status',
        208 => 'Already Reported',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Switch Proxy',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Time-out',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Large',
        415 => 'Unsupported Media Type',
        416 => 'Requested range not satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Time-out',
        505 => 'HTTP Version not supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        511 => 'Network Authentication Required',
    ];

    /**
     * Minimalism constructor.
     */
    public function __construct()
    {
        $this->services = new ServiceFactory();
    }

    /**
     * @return string
     */
    private function getProtocol() : string
    {
        return ($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1');
    }

    /**
     * @param string|null $modelName
     * @return string
     */
    public function render(?string $modelName=null): string
    {
        try {
            $this->services->initialise();
        } catch (Exception $e) {
            echo $e->getMessage();
            $this->services->getLogger()->emergency('Failed to initialise services');
            exit;
        }

        header('Content-Type: application/vnd.api+json');

        if ($this->services->getPath()->getUrl() !== null) {
            header(
                'X-Minimalism-App: '
                . explode('/', Versions::rootPackageName())[1] . '/'
                . InstalledVersions::getPrettyVersion(Versions::rootPackageName())
            );
            header(
                'X-Minimalism: '
                . InstalledVersions::getPrettyVersion('carlonicora/minimalism')
            );
        }

        $modelFactory = new ModelFactory($this->services);

        $model = null;
        $data = null;
        $function = null;

        try {
            $parameters = null;
            do {
                $model = $modelFactory->create($modelName, $parameters, $function);
                $httpResponse = $model->run();
                if ($httpResponse === 302){
                    $parameters = $model->getRedirectionParameters();
                    $modelName = $model->getRedirection();
                    $function = $model->getRedirectionFunction();
                }
            } while ($httpResponse === 302);
            $data = $model->getDocument();
            $response = $data->export();
        } catch (Exception $e) {
            $httpResponse = $e->getCode() ?? 500;

            if ($httpResponse === 500){
                $this->services->getLogger()->emergency($e->getMessage());
            }

            $document = new Document();
            $document->addError(
                new Error(
                    e: $e,
                    httpStatusCode: $httpResponse,
                    detail: $e->getMessage(),
                )
            );
            try {
                $response = $document->export();
            } catch (JsonException) {
                $response = 'Error';
            }
        } catch (Throwable $e){
            $this->services->getLogger()->emergency($e->getMessage());
            echo $e;
            exit;
        }

        if ($response === '{"meta":[]}'){
            $response = '';
        }

        if ($httpResponse < 400 && $model !== null && ($transformer = $this->services->getTransformer()) !== null && ($view = $model->getView()) !== null){
            header('Content-Type: ' . $transformer->getContentType());
            try {
                $response = $transformer->transform(
                    $data,
                    $view
                );
            } catch (Exception| Throwable $e) {
                if ($httpResponse === 500){
                    $this->services->getLogger()->error($e->getMessage());
                }
                $httpResponse = 500;
                $response = 'Error transforming the view';
            }
        }

        header($this->getProtocol() . ' ' . $httpResponse . ' ' . $this->httpCodes[$httpResponse]);

        return $response ?? '';
    }
}