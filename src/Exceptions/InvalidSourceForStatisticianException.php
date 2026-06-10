<?php

namespace Omaressaouaf\LaravelStatistician\Exceptions;

use Exception;

class InvalidSourceForStatisticianException extends Exception
{
    public function __construct(string $statisticianClass, string $expectedSourceClass)
    {
        parent::__construct(sprintf(
            'Source for %s should be of type %s',
            $statisticianClass,
            $expectedSourceClass
        ));
    }
}
