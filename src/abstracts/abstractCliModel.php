<?php declare(strict_types=1);

namespace carlonicora\minimalism\abstracts;

abstract class abstractCliModel extends abstractModel {
    /**
     * @return bool
     */
    abstract public function run(): bool;
}