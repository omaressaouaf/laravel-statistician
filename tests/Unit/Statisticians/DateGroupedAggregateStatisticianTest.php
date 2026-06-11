<?php

namespace Omaressaouaf\LaravelStatistician\Tests\Unit\Statisticians;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Omaressaouaf\LaravelStatistician\Contracts\Source;
use Omaressaouaf\LaravelStatistician\Enums\Aggregate;
use Omaressaouaf\LaravelStatistician\Exceptions\InvalidSourceForStatisticianException;
use Omaressaouaf\LaravelStatistician\Sources\DateGroupedAggregateSource;
use Omaressaouaf\LaravelStatistician\Statisticians\DateGroupedAggregateStatistician;
use Omaressaouaf\LaravelStatistician\Tests\Models\Order;
use Omaressaouaf\LaravelStatistician\Tests\Models\User;
use Omaressaouaf\LaravelStatistician\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class DateGroupedAggregateStatisticianTest extends TestCase
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

        new DateGroupedAggregateStatistician($invalidSource);
    }

    #[Test]
    public function it_returns_grouped_data_for_a_single_source(): void
    {
        Carbon::setTestNow('2025-01-31');

        $this->seedUsersOn('2025-01-10', 2);

        $stats = DateGroupedAggregateStatistician::fromSources(
            new DateGroupedAggregateSource(DB::table('users')),
        )
            ->start('2025-01-01')
            ->end('2025-01-31')
            ->get();

        $result = $stats['users_count_by_date'];

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('date_format', $result);
        $this->assertCount(1, $result['data']);
        $this->assertIsArray($result['data'][0]);
        $this->assertSame('10-01-2025', $result['data'][0]['date_label']);
        $this->assertEquals(2, $result['data'][0]['count']);
    }

    #[Test]
    public function it_returns_grouped_data_for_multiple_sources(): void
    {
        Carbon::setTestNow('2025-01-31');

        $this->seedUsersOn('2025-01-10', 2);
        $this->seedOrdersOn('2025-01-10', 1);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $stats = DateGroupedAggregateStatistician::fromSources(
            new DateGroupedAggregateSource(DB::table('users')),
            (new DateGroupedAggregateSource(DB::table('orders')))->keyBy('orders_by_date'),
        )
            ->start('2025-01-01')
            ->end('2025-01-31')
            ->get();

        $this->assertCount(2, DB::getQueryLog());
        $this->assertEquals(2, $stats['users_count_by_date']['data'][0]['count']);
        $this->assertEquals(1, $stats['orders_by_date']['data'][0]['count']);
    }

    #[Test]
    public function it_orders_data_chronologically_when_daily_labels_are_used(): void
    {
        Carbon::setTestNow('2025-01-31');

        User::factory()->create(['created_at' => '2025-01-05 10:00:00']);
        User::factory()->create(['created_at' => '2025-01-05 18:00:00']);
        User::factory()->create(['created_at' => '2025-01-15 10:00:00']);
        User::factory()->create(['created_at' => '2025-01-25 10:00:00']);

        $stats = DateGroupedAggregateStatistician::fromSources(
            new DateGroupedAggregateSource(DB::table('users')),
        )
            ->start('2025-01-01')
            ->end('2025-01-31')
            ->get();

        $result = $stats['users_count_by_date'];

        $this->assertSame('DD-MM-YYYY', $result['date_format']);
        $this->assertSame(
            ['05-01-2025', '15-01-2025', '25-01-2025'],
            array_column($result['data'], 'date_label'),
        );
        $this->assertSame([2, 1, 1], array_column($result['data'], 'count'));
    }

    #[Test]
    public function it_orders_data_chronologically_when_monthly_labels_are_used(): void
    {
        Carbon::setTestNow('2025-06-01');

        User::factory()->create(['created_at' => '2025-03-10 10:00:00']);
        User::factory()->create(['created_at' => '2025-01-15 10:00:00']);
        User::factory()->create(['created_at' => '2025-05-20 10:00:00']);

        $stats = DateGroupedAggregateStatistician::fromSources(
            new DateGroupedAggregateSource(DB::table('users')),
        )
            ->start('2025-01-01')
            ->end('2025-05-31')
            ->get();

        $result = $stats['users_count_by_date'];

        $this->assertSame('MM-YYYY', $result['date_format']);
        $this->assertSame(
            ['01-2025', '03-2025', '05-2025'],
            array_column($result['data'], 'date_label'),
        );
    }

    #[Test]
    public function it_uses_yearly_labels_for_long_periods(): void
    {
        Carbon::setTestNow('2025-06-01');

        User::factory()->create(['created_at' => '2023-06-15 10:00:00']);
        User::factory()->create(['created_at' => '2024-03-10 10:00:00']);

        $stats = DateGroupedAggregateStatistician::fromSources(
            new DateGroupedAggregateSource(DB::table('users')),
        )
            ->start('2023-01-01')
            ->end('2024-06-01')
            ->get();

        $result = $stats['users_count_by_date'];

        $this->assertSame('YYYY', $result['date_format']);
        $this->assertSame(
            ['2023', '2024'],
            array_column($result['data'], 'date_label'),
        );
    }

    #[Test]
    public function it_uses_default_period_when_no_dates_are_provided(): void
    {
        Carbon::setTestNow('2025-06-15');

        $this->seedUsersOn('2024-12-15', 1);
        $this->seedUsersOn('2025-03-15', 2);
        $this->seedUsersOn('2025-06-10', 1);

        $stats = DateGroupedAggregateStatistician::fromSources(
            new DateGroupedAggregateSource(DB::table('users')),
        )->get();

        $result = $stats['users_count_by_date'];

        $this->assertSame('MM-YYYY', $result['date_format']);
        $this->assertSame(
            ['03-2025', '06-2025'],
            array_column($result['data'], 'date_label'),
        );
        $this->assertSame([2, 1], array_column($result['data'], 'count'));
    }

    #[Test]
    public function it_filters_by_start_date_only(): void
    {
        Carbon::setTestNow('2025-06-15');

        $this->seedUsersOn('2025-02-15', 1);
        $this->seedUsersOn('2025-04-15', 2);

        $stats = DateGroupedAggregateStatistician::fromSources(
            new DateGroupedAggregateSource(DB::table('users')),
        )
            ->start('2025-03-01')
            ->get();

        $result = $stats['users_count_by_date'];

        $this->assertCount(1, $result['data']);
        $this->assertSame('04-2025', $result['data'][0]['date_label']);
        $this->assertEquals(2, $result['data'][0]['count']);
    }

    #[Test]
    public function it_filters_by_end_date_only(): void
    {
        Carbon::setTestNow('2025-06-15');

        $this->seedUsersOn('2025-02-15', 1);
        $this->seedUsersOn('2025-06-15', 1);

        $stats = DateGroupedAggregateStatistician::fromSources(
            new DateGroupedAggregateSource(DB::table('users')),
        )
            ->end('2025-03-31')
            ->get();

        $result = $stats['users_count_by_date'];

        $this->assertCount(1, $result['data']);
        $this->assertSame('02-2025', $result['data'][0]['date_label']);
        $this->assertEquals(1, $result['data'][0]['count']);
    }

    #[Test]
    public function it_excludes_data_outside_the_date_range(): void
    {
        Carbon::setTestNow('2025-06-01');

        $this->seedUsersOn('2025-01-15', 1);
        $this->seedUsersOn('2025-03-15', 1);
        $this->seedUsersOn('2025-06-15', 1);

        $stats = DateGroupedAggregateStatistician::fromSources(
            new DateGroupedAggregateSource(DB::table('users')),
        )
            ->start('2025-02-01')
            ->end('2025-04-30')
            ->get();

        $result = $stats['users_count_by_date'];

        $this->assertCount(1, $result['data']);
        $this->assertSame('03-2025', $result['data'][0]['date_label']);
    }

    #[Test]
    public function it_uses_the_aggregate_name_as_the_value_key(): void
    {
        Carbon::setTestNow('2025-03-31');

        Order::factory()->create(['total' => 10, 'created_at' => '2025-01-15']);
        Order::factory()->create(['total' => 20, 'created_at' => '2025-02-15']);

        $stats = DateGroupedAggregateStatistician::fromSources(
            new DateGroupedAggregateSource(
                DB::table('orders'),
                aggregate: Aggregate::SUM,
                aggregateColumn: 'total',
            ),
        )
            ->start('2025-01-01')
            ->end('2025-03-31')
            ->get();

        $data = $stats['orders_sum_by_date']['data'];

        $this->assertArrayHasKey('sum', $data[0]);
        $this->assertArrayNotHasKey('total', $data[0]);
        $this->assertEquals(10, $data[0]['sum']);
        $this->assertEquals(20, $data[1]['sum']);
    }

    #[Test]
    public function it_caches_results_when_cache_for_is_used(): void
    {
        Carbon::setTestNow('2025-06-15');

        $this->seedUsersOn('2025-03-15', 2);

        Cache::flush();

        $source = new DateGroupedAggregateSource(DB::table('users'));

        $statistician = DateGroupedAggregateStatistician::fromSources($source)->cacheFor(60);

        $statistician->get();

        $this->assertTrue(Cache::has($this->sourceCacheKey($statistician, $source)));
    }

    #[Test]
    public function it_reads_from_cache_on_subsequent_calls(): void
    {
        Carbon::setTestNow('2025-06-15');

        $this->seedUsersOn('2025-03-15', 2);

        Cache::flush();

        $source = new DateGroupedAggregateSource(DB::table('users'));

        $statistician = DateGroupedAggregateStatistician::fromSources($source)->cacheFor(60);

        $statistician->get();

        $this->seedUsersOn('2025-06-10', 10);

        $stats = $statistician->get();

        $this->assertEquals(2, $stats['users_count_by_date']['data'][0]['count']);
    }

    #[Test]
    public function it_caches_when_a_date_range_is_set(): void
    {
        Carbon::setTestNow('2025-06-15');

        $this->seedUsersOn('2025-03-15', 2);

        Cache::flush();

        $source = new DateGroupedAggregateSource(DB::table('users'));

        $statistician = DateGroupedAggregateStatistician::fromSources($source)
            ->start('2025-01-01')
            ->end('2025-06-15')
            ->cacheFor(60);

        $statistician->get();

        $this->assertTrue(Cache::has($this->sourceCacheKey($statistician, $source)));
    }

    #[Test]
    public function it_clears_cache_when_clear_cache_when_is_true(): void
    {
        Carbon::setTestNow('2025-06-15');

        $this->seedUsersOn('2025-03-15', 2);

        Cache::flush();

        $source = new DateGroupedAggregateSource(DB::table('users'));

        $statistician = DateGroupedAggregateStatistician::fromSources($source)->cacheFor(60);

        $statistician->get();

        $this->assertTrue(Cache::has($this->sourceCacheKey($statistician, $source)));

        $statistician->clearCacheWhen(true);

        $this->assertFalse(Cache::has($this->sourceCacheKey($statistician, $source)));
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
}
