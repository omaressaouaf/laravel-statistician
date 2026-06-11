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
        [$previousStart, $previousEnd, $currentStart, $currentEnd] = $this->resolvePeriods();

        /** @var PercentageChangeSource $source */
        $newRecordsSubQuery = (clone $source->builder)
            ->whereDate($source->dateColumn, '>=', $currentStart)
            ->whereDate($source->dateColumn, '<=', $currentEnd)
            ->selectRaw('COUNT(*) as aggregate');

        $oldRecordsSubQuery = (clone $source->builder)
            ->whereDate($source->dateColumn, '>=', $previousStart)
            ->whereDate($source->dateColumn, '<=', $previousEnd)
            ->selectRaw('COUNT(*) as aggregate');

        return $query
            ->selectSub($newRecordsSubQuery, $this->newRecordsKey($source))
            ->selectSub($oldRecordsSubQuery, $this->oldRecordsKey($source));
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

    protected function resolveCachePeriodContext(): string
    {
        [$previousStart, $previousEnd, $currentStart, $currentEnd] = $this->resolvePeriods();

        return implode(':', [
            $previousStart->toDateString(),
            $previousEnd->toDateString(),
            $currentStart->toDateString(),
            $currentEnd->toDateString(),
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: Carbon, 3: Carbon}
     */
    protected function resolvePeriods(): array
    {
        if ($this->startDate && $this->endDate) {
            $currentStart = $this->startDate->copy()->startOfDay();
            $currentEnd = $this->endDate->copy()->endOfDay();

            $previousEnd = $currentStart->copy()->subDay()->endOfDay();
            $previousStart = $previousEnd
                ->copy()
                ->subDays($currentStart->diffInDays($currentEnd))
                ->startOfDay();

            return [$previousStart, $previousEnd, $currentStart, $currentEnd];
        }

        $previousStart = today()->subMonth()->startOfMonth()->startOfDay();
        $previousEnd = today()->subMonth()->endOfMonth()->endOfDay();

        $currentStart = $previousEnd->copy()->addDay()->startOfDay();
        $currentEnd = $currentStart
            ->copy()
            ->addDays($previousStart->diffInDays($previousEnd))
            ->endOfDay();

        if ($currentEnd->gt(today()->endOfDay())) {
            $currentEnd = today()->endOfDay();
        }

        return [$previousStart, $previousEnd, $currentStart, $currentEnd];
    }

    protected function newRecordsKey(Source $source): string
    {
        return $source->getKey() . '_new';
    }

    protected function oldRecordsKey(Source $source): string
    {
        return $source->getKey() . '_old';
    }

    protected function percentageChange(int|float $new, int|float $old): float
    {
        if ($old == 0) {
            return $new > 0 ? 100.0 : 0.0;
        }

        return round((($new - $old) / $old) * 100);
    }
}
