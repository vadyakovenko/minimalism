<?php
namespace CarloNicora\Minimalism\Services\Data\Interfaces;

interface DataWranglerInterface extends DataExecutorInterface, DataTransformerInterface
{
    /**
     * @param int $operationType
     * @param DataSpecificationsInterface $function
     * @return mixed
     */
    public function generateMapper(
        int $operationType,
        DataSpecificationsInterface $function
    ): DataMapperInterface;

    /**
     * @param DataTransformerInterface $transformer
     */
    public function setTransformer(
        DataTransformerInterface $transformer
    ): void;

    /**
     * @param DataExecutorInterface $executor
     */
    public function setExecutor(
        DataExecutorInterface $executor
    ): void;

    /**
     * @param DataExecutorInterface $cacher
     */
    public function setCacher(
        DataExecutorInterface $cacher
    ): void;
}