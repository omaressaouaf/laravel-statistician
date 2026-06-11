<?php

namespace Omaressaouaf\LaravelStatistician\Tests\Unit\Statisticians;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Omaressaouaf\LaravelStatistician\Contracts\Source;
use Omaressaouaf\LaravelStatistician\Enums\Aggregate;
use Omaressaouaf\LaravelStatistician\Exceptions\InvalidSourceForStatisticianException;
use Omaressaouaf\LaravelStatistician\Sources\AggregateSource;
use Omaressaouaf\LaravelStatistician\Statisticians\AggregateStatistician;
use Omaressaouaf\LaravelStatistician\Tests\Models\Order;
use Omaressaouaf\LaravelStatistician\Tests\Models\User;
use Omaressaouaf\LaravelStatistician\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class AggregateStatisticianTest extends TestCase
{
    #[Test]
    public function it_rejects_incompatible_sources(): void
    {
        $invalidSource = new class extends Source
        {
            protected function defaultKey(): string
            {
                return 'invalid';
            }
        };

        $this->expectException(InvalidSourceForStatisticianException::class);

        new AggregateStatistician($invalidSource);
    }

    #[Test]
    public function it_returns_aggregate_for_a_single_source(): void
    {
        User::factory()->count(3)->create();

        $stats = AggregateStatistician::fromSources(
            new AggregateSource(DB::table('users')),
        )->get();

        $this->assertEquals(['users_count' => 3], $stats);
    }

    #[Test]
    public function it_returns_aggregates_for_multiple_sources_in_one_query(): void
    {
        User::factory()->count(2)->create();
        $this->seedOrders(10, 20, 30);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $stats = AggregateStatistician::fromSources(
            new AggregateSource(DB::table('users')),
            (new AggregateSource(
                DB::table('orders'),
                aggregate: Aggregate::SUM,
                aggregateColumn: 'total',
            ))->keyBy('orders_total'),
        )->get();

        $this->assertCount(1, DB::getQueryLog());
        $this->assertEquals(2, $stats['users_count']);
        $this->assertEquals(60, $stats['orders_total']);
    }

    #[Test]
    #[DataProvider('aggregateProvider')]
    public function it_computes_all_types_of_aggregates(
        Aggregate $aggregate,
        string $key,
        int|float $expected,
    ): void {
        $this->seedOrders(10, 20, 30);

        $stats = AggregateStatistician::fromSources(
            (new AggregateSource(
                DB::table('orders'),
                aggregate: $aggregate,
                aggregateColumn: 'total',
            ))->keyBy($key),
        )->get();

        $this->assertEquals($expected, $stats[$key]);
    }

    public static function aggregateProvider(): array
    {
        return [
            'count' => [Aggregate::COUNT, 'orders_count', 3],
            'sum' => [Aggregate::SUM, 'orders_sum', 60],
            'avg' => [Aggregate::AVG, 'orders_avg', 20],
            'min' => [Aggregate::MIN, 'orders_min', 10],
            'max' => [Aggregate::MAX, 'orders_max', 30],
        ];
    }

    #[Test]
    public function it_filters_by_start_date(): void
    {
        User::factory()->create(['created_at' => '2025-01-01 00:00:00']);
        User::factory()->create(['created_at' => '2025-06-01 00:00:00']);

        $stats = AggregateStatistician::fromSources(
            new AggregateSource(DB::table('users')),
        )
            ->start('2025-03-01')
            ->get();

        $this->assertEquals(['users_count' => 1], $stats);
    }

    #[Test]
    public function it_filters_by_end_date(): void
    {
        User::factory()->create(['created_at' => '2025-01-01 00:00:00']);
        User::factory()->create(['created_at' => '2025-06-01 00:00:00']);

        $stats = AggregateStatistician::fromSources(
            new AggregateSource(DB::table('users')),
        )
            ->end('2025-03-31')
            ->get();

        $this->assertEquals(['users_count' => 1], $stats);
    }

    #[Test]
    public function it_filters_by_start_and_end_date(): void
    {
        User::factory()->create(['created_at' => '2025-01-01 00:00:00']);
        User::factory()->create(['created_at' => '2025-03-15 00:00:00']);
        User::factory()->create(['created_at' => '2025-06-01 00:00:00']);

        $stats = AggregateStatistician::fromSources(
            new AggregateSource(DB::table('users')),
        )
            ->start('2025-02-01')
            ->end('2025-04-30')
            ->get();

        $this->assertEquals(['users_count' => 1], $stats);
    }

    #[Test]
    public function it_caches_results_when_cache_for_is_used(): void
    {
        Cache::flush();

        User::factory()->count(2)->create();

        $source = new AggregateSource(DB::table('users'));

        $statistician = AggregateStatistician::fromSources($source)->cacheFor(60);

        $statistician->get();

        $this->assertTrue(Cache::has($this->sourceCacheKey($statistician, $source)));
        $this->assertEquals(2, Cache::get($this->sourceCacheKey($statistician, $source)));
    }

    #[Test]
    public function it_reads_from_cache_on_subsequent_calls(): void
    {
        Cache::flush();

        User::factory()->count(2)->create();

        $source = new AggregateSource(DB::table('users'));

        $statistician = AggregateStatistician::fromSources($source)->cacheFor(60);

        $statistician->get();

        User::factory()->count(5)->create();

        $stats = $statistician->get();

        $this->assertEquals(['users_count' => 2], $stats);
    }

    #[Test]
    public function it_caches_when_a_date_range_is_set(): void
    {
        Cache::flush();

        User::factory()->count(2)->create(['created_at' => '2025-06-15']);

        $source = new AggregateSource(DB::table('users'));

        $statistician = AggregateStatistician::fromSources($source)
            ->start('2025-01-01')
            ->end('2025-12-31')
            ->cacheFor(60);

        $statistician->get();

        $this->assertTrue(Cache::has($this->sourceCacheKey($statistician, $source)));
        $this->assertEquals(2, Cache::get($this->sourceCacheKey($statistician, $source)));
    }

    #[Test]
    public function it_caches_different_date_ranges_under_separate_keys(): void
    {
        Cache::flush();

        User::factory()->count(2)->create(['created_at' => '2025-01-15']);
        User::factory()->count(5)->create(['created_at' => '2025-06-15']);

        $source = new AggregateSource(DB::table('users'));

        $januaryStatistician = AggregateStatistician::fromSources($source)
            ->start('2025-01-01')
            ->end('2025-01-31')
            ->cacheFor(60);

        $juneStatistician = AggregateStatistician::fromSources($source)
            ->start('2025-06-01')
            ->end('2025-06-30')
            ->cacheFor(60);

        $januaryStatistician->get();
        $juneStatistician->get();

        $this->assertEquals(2, Cache::get($this->sourceCacheKey($januaryStatistician, $source)));
        $this->assertEquals(5, Cache::get($this->sourceCacheKey($juneStatistician, $source)));
    }

    #[Test]
    public function it_clears_all_registered_cache_keys_for_a_source(): void
    {
        Cache::flush();

        User::factory()->count(2)->create(['created_at' => '2025-01-15']);
        User::factory()->count(5)->create(['created_at' => '2025-06-15']);

        $source = new AggregateSource(DB::table('users'));

        $januaryStatistician = AggregateStatistician::fromSources($source)
            ->start('2025-01-01')
            ->end('2025-01-31')
            ->cacheFor(60);

        $juneStatistician = AggregateStatistician::fromSources($source)
            ->start('2025-06-01')
            ->end('2025-06-30')
            ->cacheFor(60);

        $januaryStatistician->get();
        $juneStatistician->get();

        $januaryStatistician->clearCacheWhen(true);

        $this->assertFalse(Cache::has($this->sourceCacheKey($januaryStatistician, $source)));
        $this->assertFalse(Cache::has($this->sourceCacheKey($juneStatistician, $source)));
    }

    #[Test]
    public function it_clears_cache_when_clear_cache_when_is_true(): void
    {
        Cache::flush();

        User::factory()->count(2)->create();

        $source = new AggregateSource(DB::table('users'));

        $statistician = AggregateStatistician::fromSources($source)->cacheFor(60);

        $statistician->get();

        $this->assertTrue(Cache::has($this->sourceCacheKey($statistician, $source)));

        $statistician->clearCacheWhen(true);

        $this->assertFalse(Cache::has($this->sourceCacheKey($statistician, $source)));
    }

    #[Test]
    public function it_uses_cache_for_some_sources_and_queries_for_others(): void
    {
        Cache::flush();

        User::factory()->count(2)->create();
        $this->seedOrders(10, 20, 30);

        $usersSource = new AggregateSource(DB::table('users'));
        $ordersSource = (new AggregateSource(
            DB::table('orders'),
            aggregate: Aggregate::SUM,
            aggregateColumn: 'total',
        ))->keyBy('orders_total');

        $statistician = AggregateStatistician::fromSources($usersSource, $ordersSource);

        Cache::put($this->sourceCacheKey($statistician, $usersSource), 99, Carbon::now()->addHour());

        $stats = $statistician->get();

        $this->assertEquals(99, $stats['users_count']);
        $this->assertEquals(60, $stats['orders_total']);
    }

    private function seedOrders(int|float ...$totals): void
    {
        foreach ($totals as $total) {
            Order::factory()->create(['total' => $total]);
        }
    }
}
