<?php

namespace Omaressaouaf\LaravelStatistician\Statisticians;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Omaressaouaf\LaravelStatistician\Contracts\OneQueryStatistician;
use Omaressaouaf\LaravelStatistician\Contracts\Source;
use Omaressaouaf\LaravelStatistician\Sources\AggregateSource;

class AggregateStatistician extends OneQueryStatistician
{
    protected function sourceClass(): string
    {
        return AggregateSource::class;
    }

    public function buildQuery(Source $source, Builder $query): Builder
    {
        /** @var AggregateSource $source */
        $subQuery = (clone $source->builder)
            ->when(
                $this->startDate,
                fn (Builder $query) => $query->whereDate($source->dateColumn, '>=', $this->startDate)
            )
            ->when(
                $this->endDate,
                fn (Builder $query) => $query->whereDate($source->dateColumn, '<=', $this->endDate)
            )
            ->selectRaw(sprintf(
                '%s(%s) as aggregate',
                strtoupper($source->aggregate->value),
                $query->getGrammar()->wrap($source->aggregateColumn),
            ));

        return $query->selectSub($subQuery, $source->getKey());
    }

    public function handle(Source $source, Collection $result): mixed
    {
        return $result->first()->{$source->getKey()};
    }
}
