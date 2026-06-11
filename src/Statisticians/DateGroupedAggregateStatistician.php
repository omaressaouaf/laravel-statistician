<?php

namespace Omaressaouaf\LaravelStatistician\Statisticians;

use Illuminate\Support\Facades\DB;
use Omaressaouaf\LaravelStatistician\Contracts\MultiQueryStatistician;
use Omaressaouaf\LaravelStatistician\Contracts\Source;
use Omaressaouaf\LaravelStatistician\Sources\DateGroupedAggregateSource;

class DateGroupedAggregateStatistician extends MultiQueryStatistician
{
    protected function sourceClass(): string
    {
        return DateGroupedAggregateSource::class;
    }

    protected function handle(Source $source): mixed
    {
        [$periodStart, $periodEnd] = $this->resolvePeriod();
        $dateFormat = $this->resolveDateFormat();

        /** @var DateGroupedAggregateSource $source */
        $dateColumn = $source->builder->getGrammar()->wrap($source->dateColumn);

        $result = (clone $source->builder)
            ->whereDate($source->dateColumn, '>=', $periodStart)
            ->whereDate($source->dateColumn, '<=', $periodEnd)
            ->selectRaw($this->buildGroupedSelect($source, $dateFormat['sql_format']))
            ->groupBy('date_label')
            ->orderByRaw(sprintf('MIN(%s)', $dateColumn))
            ->get();

        return [
            'data' => $result->map(fn ($row) => (array) $row)->all(),
            'date_format' => $dateFormat['label_format'],
        ];
    }

    protected function resolveCachePeriodContext(): string
    {
        [$periodStart, $periodEnd] = $this->resolvePeriod();

        return $periodStart->toDateString().':'.$periodEnd->toDateString();
    }

    protected function resolvePeriod(): array
    {
        if ($this->startDate && $this->endDate) {
            return [
                $this->startDate->copy()->startOfDay(),
                $this->endDate->copy()->endOfDay(),
            ];
        }

        if ($this->startDate) {
            return [
                $this->startDate->copy()->startOfDay(),
                today()->endOfDay(),
            ];
        }

        if ($this->endDate) {
            return [
                $this->endDate->copy()->startOfYear()->startOfDay(),
                $this->endDate->copy()->endOfDay(),
            ];
        }

        return [
            today()->startOfYear()->startOfDay(),
            today()->endOfDay(),
        ];
    }

    /**
     * @return array{label_format: string, sql_format: string}
     */
    protected function resolveDateFormat(): array
    {
        [$periodStart, $periodEnd] = $this->resolvePeriod();

        $diffInDays = $periodStart->copy()->startOfDay()->diffInDays($periodEnd->copy()->startOfDay());

        return match (true) {
            $diffInDays <= 30 => [
                'label_format' => 'DD-MM-YYYY',
                'sql_format' => '%d-%m-%Y',
            ],
            $diffInDays > 360 => [
                'label_format' => 'YYYY',
                'sql_format' => '%Y',
            ],
            default => [
                'label_format' => 'MM-YYYY',
                'sql_format' => '%m-%Y',
            ],
        };
    }

    protected function buildGroupedSelect(DateGroupedAggregateSource $source, string $sqlFormat): string
    {
        $grammar = $source->builder->getGrammar();
        $aggregateFunction = strtoupper($source->aggregate->value);
        $aggregateAlias = $source->aggregate->value;
        $aggregateColumn = $grammar->wrap($source->aggregateColumn);
        $dateColumn = $grammar->wrap($source->dateColumn);

        return DB::connection()->getDriverName() === 'sqlite'
            ? sprintf(
                "%s(%s) AS %s, strftime('%s', %s) AS date_label",
                $aggregateFunction,
                $aggregateColumn,
                $aggregateAlias,
                $sqlFormat,
                $dateColumn,
            )
            : sprintf(
                "%s(%s) AS %s, DATE_FORMAT(%s, '%s') AS date_label",
                $aggregateFunction,
                $aggregateColumn,
                $aggregateAlias,
                $dateColumn,
                $sqlFormat,
            );
    }
}
