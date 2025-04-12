<?php

namespace Omaressaouaf\LaravelStatistician\Contracts;

use Illuminate\Support\Facades\Cache;

abstract class MultiQueryStatistician extends BaseStatistician
{
    public function get(): array
    {
        $stats = [];

        /**
         * @var Source
         */
        foreach ($this->sources as $source) {
            $stats[$source->getKey()] = $this->shouldUseCaching($source)
                ? Cache::remember(
                    $source->getCacheKey(),
                    $this->cacheExpirationDate ?? today()->endOfMonth(),
                    fn() => $this->handle($source)
                )
                : $this->handle($source);
        }

        return $stats;
    }

    abstract protected function handle(Source $source): mixed;
}
