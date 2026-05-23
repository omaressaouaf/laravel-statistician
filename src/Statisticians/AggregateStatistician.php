<?php

namespace Omaressaouaf\LaravelStatistician\Statisticians;

use Illuminate\Database\Query\Builder;
use Omaressaouaf\LaravelStatistician\Contracts\OneQueryStatistician;
use Omaressaouaf\LaravelStatistician\Contracts\Source;
use Omaressaouaf\LaravelStatistician\Sources\AggregateSource;
use Illuminate\Support\Collection;

class AggregateStatistician extends OneQueryStatistician
{
    public function sourceClass(): string
    {
        return AggregateSource::class;
    }

    public function buildQuery(Source $source, Builder $query): Builder
    {
        /** @var AggregateSource $source */
        $aggregateQuery = $source->builder
            ->when(
                $this->startDate && $this->endDate,
                fn(Builder $query) => $query
                    ->whereDate($source->dateColumn, '>=', $this->startDate)
                    ->whereDate($source->dateColumn, '<=', $this->endDate)
            )
            ->{$source->aggregate}($source->aggregateColumn);

        return $query->addSelect(
            [
                $source->getKey() => $aggregateQuery
            ]
        );
    }

    public function handle(Source $source, Collection $result): mixed
    {
        return $result->first()->{$source->getKey()};
    }
}
