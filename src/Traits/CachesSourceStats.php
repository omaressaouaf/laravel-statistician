<?php

namespace Omaressaouaf\LaravelStatistician\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Omaressaouaf\LaravelStatistician\Contracts\Source;

trait CachesSourceStats
{
    public function cacheFor(int $seconds): static
    {
        $this->cacheExpirationDate = now()->addSeconds($seconds);

        return $this;
    }

    public function cacheUntil(Carbon|string $date): static
    {
        $this->cacheExpirationDate = $date instanceof Carbon ? $date : Carbon::parse($date);

        return $this;
    }

    public function clearCacheWhen(?bool $condition): static
    {
        if ($condition) {
            foreach ($this->sources as $source) {
                Cache::forget($source->getCacheKey());
            }
        }

        return $this;
    }

    protected function eligibleToGetSourceStatsFromCache(): bool
    {
        return ! $this->startDate && ! $this->endDate;
    }

    protected function isSourceStatsCached(Source $source): bool
    {
        return $this->eligibleToGetSourceStatsFromCache() && Cache::has($source->getCacheKey());
    }

    protected function getSourceStatsFromCache(Source $source): mixed
    {
        return Cache::get($source->getCacheKey());
    }

    protected function eligibleToPutSourceStatsToCache(): bool
    {
        return ! $this->startDate && ! $this->endDate && $this->cacheExpirationDate;
    }

    protected function putSourceStatsToCache(Source $source, mixed $sourceStats): void
    {
        if (!$this->eligibleToPutSourceStatsToCache()) {
            return;
        }

        Cache::put($source->getCacheKey(), $sourceStats, $this->cacheExpirationDate);
    }
}
