<?php

namespace Omaressaouaf\LaravelStatistician\Contracts;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Traits\Conditionable;
use Omaressaouaf\LaravelStatistician\Exceptions\InvalidSourceForStatisticianException;

abstract class Statistician
{
    use Conditionable;

    protected array $sources;

    protected ?Carbon $startDate = null;

    protected ?Carbon $endDate = null;

    protected ?Carbon $cacheExpirationDate = null;

    public function __construct(Source ...$sources)
    {
        $this->validateSources(...$sources);

        $this->sources = $sources;

        return $this;
    }

    public static function fromSources(Source ...$sources): static
    {
        return new static(...$sources);
    }

    private function validateSources(Source ...$sources): void
    {
        foreach ($sources as $source) {
            throw_unless(
                $source instanceof ($this->sourceClass()),
                new InvalidSourceForStatisticianException($this)
            );
        }
    }

    public function start(Carbon|string $startDate): static
    {
        $this->startDate = $startDate instanceof Carbon ? $startDate : Carbon::parse($startDate);

        return $this;
    }

    public function end(Carbon|string $endDate): static
    {
        $this->endDate = $endDate instanceof Carbon ? $endDate : Carbon::parse($endDate);

        return $this;
    }

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

    private function shouldUseCaching(Source $source): bool
    {
        return ($this->cacheExpirationDate || Cache::has($source->getCacheKey()))
            && ! $this->startDate
            && ! $this->endDate;
    }

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

    abstract public function sourceClass(): string;

    abstract protected function handle(Source $source): mixed;
}
