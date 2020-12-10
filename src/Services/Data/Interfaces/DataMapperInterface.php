<?php
namespace CarloNicora\Minimalism\Services\Data\Interfaces;

interface DataMapperInterface
{
    /**
     * DataMapperInterface constructor.
     * @param int $operationType
     * @param DataSpecificationsInterface $executorFunction
     * @param DataCacheInterface $cache
     */
    public function __construct(
        int $operationType,
        DataSpecificationsInterface $executorFunction,
        DataCacheInterface $cache
    );

    /**
     * @return int
     */
    public function getOperationType(): int;

    /**
     * @param int $operationType
     */
    public function setOperationType(int $operationType): void;

    /**
     * @return DataSpecificationsInterface
     */
    public function getExecutorFunction(): DataSpecificationsInterface;

    /**
     * @param DataSpecificationsInterface $executorFunction
     */
    public function setExecutorFunction(DataSpecificationsInterface $executorFunction): void;

    /**
     * @return DataCacheInterface
     */
    public function getCache(): DataCacheInterface;

    /**
     * @param DataCacheInterface $cache
     */
    public function setCache(DataCacheInterface $cache): void;

    /**
     * @return DataInterface
     */
    public function execute(): DataInterface;
}