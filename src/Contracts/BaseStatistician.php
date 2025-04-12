<?php

namespace Omaressaouaf\LaravelStatistician\Contracts;

use Carbon\Carbon;
use Illuminate\Support\Traits\Conditionable;
use Omaressaouaf\LaravelStatistician\Exceptions\InvalidSourceForStatisticianException;
use Omaressaouaf\LaravelStatistician\Traits\CachesSourceStats;

abstract class BaseStatistician
{
    use CachesSourceStats, Conditionable;

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

    abstract public function sourceClass(): string;
}
