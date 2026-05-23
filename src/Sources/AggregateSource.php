<?php

namespace Omaressaouaf\LaravelStatistician\Sources;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use Omaressaouaf\LaravelStatistician\Contracts\Source;
use Omaressaouaf\LaravelStatistician\Enums\Aggregate;

class AggregateSource extends Source
{
    public function __construct(
        public Builder $builder,
        public Aggregate $aggregate = Aggregate::COUNT,
        public string $aggregateColumn = 'id',
        public string $dateColumn = 'created_at',
    ) {}

    protected function defaultKey(): string
    {
        return Str::snake($this->builder->from).'_'.$this->aggregate->value;
    }
}
