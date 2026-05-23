<?php

namespace Omaressaouaf\LaravelStatistician\Contracts;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
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

            if (! $this->isSourceStatsCached($source)) {
                $this->putSourceStatsToCache($source, $sourceStats);
            }

            $stats[$source->getKey()] = $sourceStats;
        }

        return $stats;
    }

    private function runQuery(): Collection
    {
        $builder = DB::query();
        $hasQueries = false;

        /**
         * @var Source
         */
        foreach ($this->sources as $source) {
            if ($this->isSourceStatsCached($source)) {
                continue;
            }

            $builder = $this->buildQuery($source, $builder);
            $hasQueries = true;
        }

        if (! $hasQueries) {
            return collect([(object) []]);
        }

        return $builder->get();
    }

    abstract protected function buildQuery(Source $source, Builder $query): Builder;

    abstract protected function handle(Source $source, Collection $result): mixed;
}
