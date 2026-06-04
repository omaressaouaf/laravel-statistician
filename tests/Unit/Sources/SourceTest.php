<?php

namespace Omaressaouaf\LaravelStatistician\Tests\Unit\Sources;

use Illuminate\Support\Facades\DB;
use Omaressaouaf\LaravelStatistician\Enums\Aggregate;
use Omaressaouaf\LaravelStatistician\Sources\AggregateSource;
use Omaressaouaf\LaravelStatistician\Sources\PercentageChangeSource;
use Omaressaouaf\LaravelStatistician\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class SourceTest extends TestCase
{
    #[Test]
    #[DataProvider('aggregateDefaultKeyProvider')]
    public function it_generates_default_key_for_aggregate_source(
        Aggregate $aggregate,
        string $expectedKey,
    ): void {
        $source = new AggregateSource(
            DB::table('users'),
            aggregate: $aggregate,
        );

        $this->assertSame($expectedKey, $source->getKey());
    }

    public static function aggregateDefaultKeyProvider(): array
    {
        return [
            'count' => [Aggregate::COUNT, 'users_count'],
            'sum' => [Aggregate::SUM, 'users_sum'],
            'avg' => [Aggregate::AVG, 'users_avg'],
            'min' => [Aggregate::MIN, 'users_min'],
            'max' => [Aggregate::MAX, 'users_max'],
        ];
    }

    #[Test]
    public function it_generates_default_key_for_percentage_change_source(): void
    {
        $source = new PercentageChangeSource(DB::table('orders'));

        $this->assertSame('orders_percentage_change', $source->getKey());
    }

    #[Test]
    public function it_allows_overriding_default_key_with_key_by(): void
    {
        $source = (new PercentageChangeSource(DB::table('users')))->keyBy('active_users');

        $this->assertSame('active_users', $source->getKey());
    }

    #[Test]
    public function it_generates_cache_key(): void
    {
        $source = new PercentageChangeSource(DB::table('users'));

        $this->assertSame('stats:percentage_change_source:users_percentage_change', $source->getCacheKey());
    }

    #[Test]
    public function it_uses_custom_key_in_cache_key_when_key_by_is_used(): void
    {
        $source = (new PercentageChangeSource(DB::table('users')))->keyBy('active_users');

        $this->assertSame('stats:percentage_change_source:active_users', $source->getCacheKey());
    }
}
