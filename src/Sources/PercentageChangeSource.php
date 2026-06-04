<?php

namespace Omaressaouaf\LaravelStatistician\Sources;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Str;
use Omaressaouaf\LaravelStatistician\Contracts\Source;

class PercentageChangeSource extends Source
{
    /**
     * @param  QueryBuilder|EloquentBuilder|class-string<Model>|string  $builder
     */
    public function __construct(
        QueryBuilder|EloquentBuilder|string $builder,
        public string $dateColumn = 'created_at',
    ) {
        $this->builder = static::resolveBuilder($builder);
    }

    protected function defaultKey(): string
    {
        return Str::snake($this->getTableName()).'_percentage_change';
    }
}
