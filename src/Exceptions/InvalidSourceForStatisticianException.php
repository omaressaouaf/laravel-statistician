<?php

namespace Omaressaouaf\LaravelStatistician\Exceptions;

use Exception;
use Omaressaouaf\LaravelStatistician\Contracts\BaseStatistician;

class InvalidSourceForStatisticianException extends Exception
{
    public function __construct(BaseStatistician $statistician)
    {
        parent::__construct(sprintf(
            'Source for %s should be of type %s',
            get_class($statistician),
            $statistician->sourceClass()
        ));
    }
}
