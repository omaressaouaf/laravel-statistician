<?php

namespace Omaressaouaf\LaravelStatistician\Statisticians;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Omaressaouaf\LaravelStatistician\Contracts\OneQueryStatistician;
use Omaressaouaf\LaravelStatistician\Contracts\Source;
use Omaressaouaf\LaravelStatistician\Sources\PercentageChangeSource;

class PercentageChangeStatistician extends OneQueryStatistician
{
    protected function sourceClass(): string
    {
        return PercentageChangeSource::class;
    }

    public function buildQuery(Source $source, Builder $query): Builder
    {
        [$baselineStart, $baselineEnd, $comparisonStart, $comparisonEnd] = $this->resolvePeriods();

        /** @var PercentageChangeSource $source */
        $newRecordsSubQuery = (clone $source->builder)
            ->whereDate($source->dateColumn, '>=', $comparisonStart)
            ->whereDate($source->dateColumn, '<=', $comparisonEnd)
            ->selectRaw('COUNT(*) as aggregate');

        $oldRecordsSubQuery = (clone $source->builder)
            ->whereDate($source->dateColumn, '>=', $baselineStart)
            ->whereDate($source->dateColumn, '<=', $baselineEnd)
            ->selectRaw('COUNT(*) as aggregate');

        return $query
            ->selectSub($newRecordsSubQuery, $this->newRecordsKey($source))
            ->selectSub($oldRecordsSubQuery, $this->oldRecordsKey($source));
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: Carbon, 3: Carbon}
     */
    protected function resolvePeriods(): array
    {
        if ($this->startDate && $this->endDate) {
            $baselineStart = $this->startDate->copy()->startOfDay();
            $baselineEnd = $this->endDate->copy()->endOfDay();
        } else {
            $baselineStart = today()->subMonth()->startOfMonth()->startOfDay();
            $baselineEnd = today()->subMonth()->endOfMonth()->endOfDay();
        }

        $comparisonStart = $baselineEnd->copy()->addDay()->startOfDay();
        $comparisonEnd = $comparisonStart
            ->copy()
            ->addDays($baselineStart->diffInDays($baselineEnd))
            ->endOfDay();

        if ($comparisonEnd->gt(today()->endOfDay())) {
            $comparisonEnd = today()->endOfDay();
        }

        return [$baselineStart, $baselineEnd, $comparisonStart, $comparisonEnd];
    }

    public function handle(Source $source, Collection $result): mixed
    {
        /** @var PercentageChangeSource $source */
        $row = $result->first();

        return $this->percentageChange(
            $row->{$this->newRecordsKey($source)},
            $row->{$this->oldRecordsKey($source)},
        );
    }

    protected function newRecordsKey(Source $source): string
    {
        return $source->getKey().'_new';
    }

    protected function oldRecordsKey(Source $source): string
    {
        return $source->getKey().'_old';
    }

    protected function percentageChange(int|float $new, int|float $old): float
    {
        if ($old == 0) {
            return $new > 0 ? 100.0 : 0.0;
        }

        return round((($new - $old) / $old) * 100);
    }
}
