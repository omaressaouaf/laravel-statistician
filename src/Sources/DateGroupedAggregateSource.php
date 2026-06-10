<?php

namespace Omaressaouaf\LaravelStatistician\Sources;

use Illuminate\Support\Str;

class DateGroupedAggregateSource extends AggregateSource
{
    protected function defaultKey(): string
    {
        return Str::snake($this->getTableName()).'_'.$this->aggregate->value.'_by_date';
    }
}
