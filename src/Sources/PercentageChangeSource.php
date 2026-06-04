<?php

namespace Omaressaouaf\LaravelStatistician\Sources;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use Omaressaouaf\LaravelStatistician\Contracts\Source;

class PercentageChangeSource extends Source
{
    public function __construct(
        public Builder $builder,
        public string $dateColumn = 'created_at',
    ) {}

    protected function defaultKey(): string
    {
        return Str::snake($this->builder->from).'_percentage_change';
    }
}
