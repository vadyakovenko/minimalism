<?php
namespace CarloNicora\Minimalism\Services\Data\Interfaces;

/**
 * Interface DataExecutorInterface
 * @package CarloNicora\Minimalism\Services\Data\Interfaces
 *
 * Executes the required operations defined in the DataSpecificationsInterface
 *
 */
interface DataExecutorInterface
{
    public const CREATE=1;
    public const READ=1;
    public const UPDATE=1;
    public const DELETE=1;

    /**
     * @param DataSpecificationsInterface $function
     * @return DataInterface
     */
    public function create(
        DataSpecificationsInterface $function
    ): DataInterface;

    /**
     * @param DataSpecificationsInterface $function
     * @return DataInterface
     */
    public function read(
        DataSpecificationsInterface $function
    ): DataInterface;

    /**
     * @param DataSpecificationsInterface $function
     * @return DataInterface
     */
    public function update(
        DataSpecificationsInterface $function
    ): DataInterface;

    /**
     * @param DataSpecificationsInterface $function
     * @return DataInterface
     */
    public function delete(
        DataSpecificationsInterface $function
    ): DataInterface;
}