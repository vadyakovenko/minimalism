<?php
namespace carlonicora\minimalism\helpers;


class apiResponse {
    /** @var bool */
    public $isSuccess;

    /** @var array */
    public $returnedValue;

    /** @var int */
    public $errorId;

    /** @var string */
    public $errorMessage;
}