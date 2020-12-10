<?php
namespace CarloNicora\Minimalism\Services\Data\Interfaces;

interface DataTransformerInterface
{
    /**
     * @param DataSpecificationsInterface $transformationFunction
     * @param DataMapperInterface $executorFunction
     * @return DataInterface
     */
    public function transform(
        DataSpecificationsInterface $transformationFunction,
        DataMapperInterface $executorFunction
    ): DataInterface;
}