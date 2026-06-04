<?php

namespace Omaressaouaf\LaravelStatistician\Tests\Unit\Statisticians;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Omaressaouaf\LaravelStatistician\Contracts\Source;
use Omaressaouaf\LaravelStatistician\Exceptions\InvalidSourceForStatisticianException;
use Omaressaouaf\LaravelStatistician\Sources\PercentageChangeSource;
use Omaressaouaf\LaravelStatistician\Statisticians\PercentageChangeStatistician;
use Omaressaouaf\LaravelStatistician\Tests\Models\Order;
use Omaressaouaf\LaravelStatistician\Tests\Models\User;
use Omaressaouaf\LaravelStatistician\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class PercentageChangeStatisticianTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

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

        new PercentageChangeStatistician($invalidSource);
    }

    #[Test]
    public function it_returns_percentage_change_for_a_single_source(): void
    {
        Carbon::setTestNow('2025-06-01');

        $this->seedUsersOn('2025-04-15', 10);
        $this->seedUsersOn('2025-05-15', 15);

        $stats = PercentageChangeStatistician::fromSources(
            new PercentageChangeSource(DB::table('users')),
        )
            ->start('2025-04-01')
            ->end('2025-04-30')
            ->get();

        $this->assertEquals(['users_percentage_change' => 50], $stats);
    }

    #[Test]
    public function it_returns_percentage_change_for_multiple_sources_in_one_query(): void
    {
        Carbon::setTestNow('2025-06-01');

        $this->seedUsersOn('2025-04-15', 10);
        $this->seedUsersOn('2025-05-15', 20);

        $this->seedOrdersOn('2025-04-15', 4);
        $this->seedOrdersOn('2025-05-15', 2);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $stats = PercentageChangeStatistician::fromSources(
            new PercentageChangeSource(DB::table('users')),
            new PercentageChangeSource(DB::table('orders'))->keyBy('orders_growth'),
        )
            ->start('2025-04-01')
            ->end('2025-04-30')
            ->get();

        $this->assertCount(1, DB::getQueryLog());
        $this->assertEquals(100, $stats['users_percentage_change']);
        $this->assertEquals(-50, $stats['orders_growth']);
    }

    #[Test]
    public function it_uses_default_periods_when_no_dates_are_provided(): void
    {
        Carbon::setTestNow('2025-06-15');

        $this->seedUsersOn('2025-05-15', 4);
        $this->seedUsersOn('2025-06-10', 6);

        $stats = PercentageChangeStatistician::fromSources(
            new PercentageChangeSource(DB::table('users')),
        )->get();

        $this->assertEquals(['users_percentage_change' => 50], $stats);
    }

    #[Test]
    public function it_uses_default_periods_when_only_start_date_is_provided(): void
    {
        Carbon::setTestNow('2025-06-15');

        $this->seedUsersOn('2025-01-15', 100);
        $this->seedUsersOn('2025-05-15', 4);
        $this->seedUsersOn('2025-06-10', 6);

        $stats = PercentageChangeStatistician::fromSources(
            new PercentageChangeSource(DB::table('users')),
        )
            ->start('2025-01-01')
            ->get();

        $this->assertEquals(['users_percentage_change' => 50], $stats);
    }

    #[Test]
    public function it_uses_default_periods_when_only_end_date_is_provided(): void
    {
        Carbon::setTestNow('2025-06-15');

        $this->seedUsersOn('2025-01-15', 100);
        $this->seedUsersOn('2025-05-15', 4);
        $this->seedUsersOn('2025-06-10', 6);

        $stats = PercentageChangeStatistician::fromSources(
            new PercentageChangeSource(DB::table('users')),
        )
            ->end('2025-01-31')
            ->get();

        $this->assertEquals(['users_percentage_change' => 50], $stats);
    }

    #[Test]
    public function it_caps_comparison_period_at_today(): void
    {
        Carbon::setTestNow('2025-02-15');

        $this->seedUsersOn('2025-01-15', 10);
        $this->seedUsersAt('2025-02-01 00:00:00', '2025-02-05 00:00:00', '2025-02-10 00:00:00', '2025-02-12 00:00:00', '2025-02-14 00:00:00');
        $this->seedUsersAt('2025-02-20 00:00:00', '2025-02-25 00:00:00');

        $stats = PercentageChangeStatistician::fromSources(
            new PercentageChangeSource(DB::table('users')),
        )
            ->start('2025-01-01')
            ->end('2025-01-31')
            ->get();

        $this->assertEquals(['users_percentage_change' => -50], $stats);
    }

    #[Test]
    public function it_returns_100_when_baseline_is_zero_and_comparison_is_not(): void
    {
        Carbon::setTestNow('2025-06-01');

        $this->seedUsersOn('2025-05-15', 5);

        $stats = PercentageChangeStatistician::fromSources(
            new PercentageChangeSource(DB::table('users')),
        )
            ->start('2025-04-01')
            ->end('2025-04-30')
            ->get();

        $this->assertEquals(['users_percentage_change' => 100], $stats);
    }

    #[Test]
    public function it_returns_0_when_both_periods_are_empty(): void
    {
        Carbon::setTestNow('2025-06-01');

        $stats = PercentageChangeStatistician::fromSources(
            new PercentageChangeSource(DB::table('users')),
        )
            ->start('2025-04-01')
            ->end('2025-04-30')
            ->get();

        $this->assertEquals(['users_percentage_change' => 0], $stats);
    }

    #[Test]
    public function it_caches_results_when_cache_for_is_used(): void
    {
        Carbon::setTestNow('2025-06-01');

        $this->seedUsersOn('2025-05-15', 10);
        $this->seedUsersOn('2025-06-01', 15);

        Cache::flush();

        $source = new PercentageChangeSource(DB::table('users'));

        PercentageChangeStatistician::fromSources($source)
            ->cacheFor(60)
            ->get();

        $this->assertTrue(Cache::has($source->getCacheKey()));
        $this->assertEquals(50, Cache::get($source->getCacheKey()));
    }

    #[Test]
    public function it_reads_from_cache_on_subsequent_calls(): void
    {
        Carbon::setTestNow('2025-06-01');

        $this->seedUsersOn('2025-05-15', 10);
        $this->seedUsersOn('2025-06-01', 15);

        Cache::flush();

        $source = new PercentageChangeSource(DB::table('users'));

        $statistician = PercentageChangeStatistician::fromSources($source)->cacheFor(60);

        $statistician->get();

        $this->seedUsersOn('2025-06-01', 100);

        $stats = $statistician->get();

        $this->assertEquals(['users_percentage_change' => 50], $stats);
    }

    #[Test]
    public function it_does_not_cache_when_a_date_range_is_set(): void
    {
        Carbon::setTestNow('2025-06-01');

        $this->seedUsersOn('2025-04-15', 10);

        Cache::flush();

        $source = new PercentageChangeSource(DB::table('users'));

        PercentageChangeStatistician::fromSources($source)
            ->start('2025-04-01')
            ->end('2025-04-30')
            ->cacheFor(60)
            ->get();

        $this->assertFalse(Cache::has($source->getCacheKey()));
    }

    #[Test]
    public function it_clears_cache_when_clear_cache_when_is_true(): void
    {
        Carbon::setTestNow('2025-06-01');

        $this->seedUsersOn('2025-05-15', 10);
        $this->seedUsersOn('2025-06-01', 15);

        Cache::flush();

        $source = new PercentageChangeSource(DB::table('users'));

        $statistician = PercentageChangeStatistician::fromSources($source)->cacheFor(60);

        $statistician->get();

        $this->assertTrue(Cache::has($source->getCacheKey()));

        $statistician->clearCacheWhen(true);

        $this->assertFalse(Cache::has($source->getCacheKey()));
    }

    #[Test]
    public function it_uses_cache_for_some_sources_and_queries_for_others(): void
    {
        Carbon::setTestNow('2025-06-01');

        $this->seedOrdersOn('2025-05-15', 4);
        $this->seedOrdersOn('2025-06-01', 2);

        Cache::flush();

        $usersSource = new PercentageChangeSource(DB::table('users'));
        $ordersSource = new PercentageChangeSource(DB::table('orders'))->keyBy('orders_growth');

        Cache::put($usersSource->getCacheKey(), 99, Carbon::now()->addHour());

        $stats = PercentageChangeStatistician::fromSources($usersSource, $ordersSource)->get();

        $this->assertEquals(99, $stats['users_percentage_change']);
        $this->assertEquals(-50, $stats['orders_growth']);
    }

    private function seedUsersOn(string $date, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            User::factory()->create(['created_at' => $date]);
        }
    }

    private function seedOrdersOn(string $date, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            Order::factory()->create(['created_at' => $date]);
        }
    }

    private function seedUsersAt(string ...$dates): void
    {
        foreach ($dates as $date) {
            User::factory()->create(['created_at' => $date]);
        }
    }
}
