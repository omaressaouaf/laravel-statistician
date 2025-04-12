<?php

namespace Omaressaouaf\LaravelStatistician\Contracts;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

abstract class Source
{
    public ?string $key = null;

    public function keyBy(string $key): static
    {
        $this->key = $key;

        return $this;
    }

    public function getKey(): string
    {
        return $this->key ? $this->key : $this->defaultKey();
    }

    public function getCacheKey(): string
    {
        $sourceClassFormatted = Str::of($this::class)->classBasename()->snake();

        return "stats:{$sourceClassFormatted}:{$this->getKey()}";
    }

    public function isCached(): bool
    {
        return Cache::has($this->getCacheKey());
    }

    public function getFromCache(): mixed
    {
        return Cache::get($this->getCacheKey());
    }

    public function putToCache(mixed $sourceStats, \DateTimeInterface|\DateInterval|int $ttl): void
    {
        Cache::put($this->getCacheKey(), $sourceStats, $ttl);
    }

    abstract protected function defaultKey(): string;
}
