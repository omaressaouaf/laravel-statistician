<?php

namespace Omaressaouaf\LaravelStatistician\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

abstract class OneQueryStatistician extends BaseStatistician
{
    public function get(): array
    {
        $result = $this->runQuery();
        $stats = [];

        /**
         * @var Source
         */
        foreach ($this->sources as $source) {
            $sourceStats = $this->isSourceStatsCached($source)
                ? $this->getSourceStatsFromCache($source)
                : $this->handle($source, $result);

            $this->putSourceStatsToCache($source, $sourceStats);

            $stats[$source->getKey()] = $sourceStats;
        }

        return $stats;
    }

    private function runQuery(): Collection
    {
        $builder = DB::query();

        /**
         * @var Source
         */
        foreach ($this->sources as $source) {
            if ($this->isSourceStatsCached($source)) {
                continue;
            };

            $builder = $this->buildQuery($builder);
        }

        return $builder->get();
    }

    abstract protected function buildQuery(Builder $query): Builder;

    abstract protected function handle(Source $source, Collection $result): mixed;
}
