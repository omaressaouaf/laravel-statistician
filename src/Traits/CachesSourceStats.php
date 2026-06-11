<?php

namespace Omaressaouaf\LaravelStatistician\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
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
                $this->clearSourceCacheRegistry($source);
            }
        }

        return $this;
    }

    protected function isSourceStatsCached(Source $source): bool
    {
        return Cache::has($this->getSourceCacheKey($source));
    }

    protected function getSourceStatsFromCache(Source $source): mixed
    {
        return Cache::get($this->getSourceCacheKey($source));
    }

    protected function eligibleToPutSourceStatsToCache(): bool
    {
        return $this->cacheExpirationDate !== null;
    }

    protected function putSourceStatsToCache(Source $source, mixed $sourceStats): void
    {
        if (! $this->eligibleToPutSourceStatsToCache()) {
            return;
        }

        $itemKey = $this->getSourceCacheKey($source);

        Cache::put($itemKey, $sourceStats, $this->cacheExpirationDate);

        $this->registerSourceCacheKey($source, $itemKey);
    }

    protected function registerSourceCacheKey(Source $source, string $itemKey): void
    {
        $registryKey = $this->getSourceCacheRegistryKey($source);
        $keys = Cache::get($registryKey, []);
        $keys[] = $itemKey;

        Cache::put(
            $registryKey,
            array_values(array_unique($keys)),
            $this->cacheExpirationDate,
        );
    }

    protected function clearSourceCacheRegistry(Source $source): void
    {
        $registryKey = $this->getSourceCacheRegistryKey($source);

        foreach (Cache::get($registryKey, []) as $itemKey) {
            Cache::forget($itemKey);
        }

        Cache::forget($registryKey);
    }

    protected function getSourceCacheKey(Source $source): string
    {
        return $this->getSourceCacheBaseKey($source).':'.$this->resolveCachePeriodContext();
    }

    protected function getSourceCacheRegistryKey(Source $source): string
    {
        return $this->getSourceCacheBaseKey($source).':keys';
    }

    protected function getSourceCacheBaseKey(Source $source): string
    {
        $sourceClassFormatted = Str::of($source::class)->classBasename()->snake();

        return "stats:{$sourceClassFormatted}:{$source->getKey()}";
    }

    protected function resolveCachePeriodContext(): string
    {
        if ($this->startDate && $this->endDate) {
            return $this->startDate->toDateString().':'.$this->endDate->toDateString();
        }

        if ($this->startDate) {
            return $this->startDate->toDateString().':none';
        }

        if ($this->endDate) {
            return 'none:'.$this->endDate->toDateString();
        }

        return 'none:none';
    }
}
