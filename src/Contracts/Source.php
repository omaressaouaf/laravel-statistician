<?php

namespace Omaressaouaf\LaravelStatistician\Contracts;

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

    abstract protected function defaultKey(): string;
}
