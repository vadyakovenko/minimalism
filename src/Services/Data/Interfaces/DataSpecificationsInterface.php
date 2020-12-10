<?php
namespace CarloNicora\Minimalism\Services\Data\Interfaces;

/**
 * Interface DataSpecificationsInterface
 * @package CarloNicora\Minimalism\Services\Data\Interfaces
 *
 * Contains the specifications required to a DataExecutor function
 * to carry on the required CRUD operation
 *
 */
interface DataSpecificationsInterface
{
    /**
     * @param string $proxyName
     * @param string $functionName
     * @param array $parameters
     * @return DataSpecificationsInterface
     */
    public function withProxyName(
        string $proxyName,
        string $functionName,
        array $parameters
    ): DataSpecificationsInterface;

    /**
     * @param DataFunctionsProxyinterface $proxy
     * @param string $functionName
     * @param array $parameters
     * @return DataSpecificationsInterface
     */
    public function withProxy(
        DataFunctionsProxyinterface $proxy,
        string $functionName,
        array $parameters
    ): DataSpecificationsInterface;

    /**
     * @param DataCacheInterface $cache
     * @return DataSpecificationsInterface
     */
    public function withCache(
        DataCacheInterface $cache
    ): DataSpecificationsInterface;
}