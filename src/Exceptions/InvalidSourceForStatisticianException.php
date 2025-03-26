<?php

namespace Omaressaouaf\LaravelStatistician\Exceptions;

use Exception;
use Omaressaouaf\LaravelStatistician\Contracts\Statistician;

class InvalidSourceForStatisticianException extends Exception
{
    public function __construct(Statistician $statistician)
    {
        parent::__construct(sprintf(
            'Source for %s should be of type %s',
            get_class($statistician),
            $statistician->sourceClass()
        ));
    }
}
