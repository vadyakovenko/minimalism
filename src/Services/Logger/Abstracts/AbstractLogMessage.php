<?php
namespace CarloNicora\Minimalism\Services\Logger\Abstracts;

use CarloNicora\Minimalism\Services\Logger\Interfaces\LogMessageInterface;
use Exception;
use Throwable;

abstract class AbstractLogMessage implements LogMessageInterface
{
    /** @var string  */
    protected string $serviceName='';

    /** @var int  */
    protected int $id;

    /** @var string  */
    protected ?string $message=null;

    /** @var Throwable|null  */
    protected ?Throwable $e=null;

    /** @var float  */
    private float $time;

    /**
     * LogMessageInterface constructor.
     * @param int $id
     * @param string $message
     * @param array $context
     * @param Throwable|null $e
     */
    public function __construct(int $id, string $message, array $context=[], Throwable $e=null)
    {
        $this->message = $this->mergeContext($message, $context);
        $this->id = $id;
        $this->e = $e;
        $this->time = microtime(true);
    }

    /**
     * @return string
     */
    final public function getService(): string
    {
        return $this->serviceName;
    }

    /**
     * @return float
     */
    final public function getTime(): float
    {
        return $this->time;
    }

    /**
     * @return string
     */
    public function getMessageCode(): string
    {
        return (string)$this->id;
    }

    /**
     * @param string $message
     * @param array $context
     * @return string
     */
    private function mergeContext(?string $message, ?array $context) : ?string
    {
        if ($message !== '' && count($context) > 0) {
            $message = sprintf($message, ...$context);
        }

        return $message;
    }

    /**
     * @return string
     */
    abstract public function generateMessage(): string;

    /**
     * @param string $exceptionName
     * @param string|null $message
     * @throws Throwable
     */
    public function throw(string $exceptionName= Exception::class, ?string $message = null): void
    {
        throw $this->generateException($exceptionName, $message);
    }

    /**
     * @param string $exceptionName
     * @param string|null $message
     * @return Throwable
     */
    public function generateException(string $exceptionName = Exception::class, ?string $message = null): Throwable
    {
        return new $exceptionName(
            $message ?? $this->message,
            $this->getMessageCode(),
            $this->e);
    }
}