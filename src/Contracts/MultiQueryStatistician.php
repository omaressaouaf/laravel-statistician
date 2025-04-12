<?php

namespace Omaressaouaf\LaravelStatistician\Contracts;

abstract class MultiQueryStatistician extends BaseStatistician
{
    public function get(): array
    {
        $stats = [];

        /**
         * @var Source
         */
        foreach ($this->sources as $source) {
            $sourceStats = $this->isSourceStatsCached($source)
                ? $this->getSourceStatsFromCache($source)
                : $this->handle($source);

            $this->putSourceStatsToCache($source, $sourceStats);

            $stats[$source->getKey()] = $sourceStats;
        }

        return $stats;
    }

    abstract protected function handle(Source $source): mixed;
}
