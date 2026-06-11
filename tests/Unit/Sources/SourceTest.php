<?php

namespace Omaressaouaf\LaravelStatistician\Tests\Unit\Sources;

use Illuminate\Support\Facades\DB;
use Omaressaouaf\LaravelStatistician\Enums\Aggregate;
use Omaressaouaf\LaravelStatistician\Sources\AggregateSource;
use Omaressaouaf\LaravelStatistician\Sources\DateGroupedAggregateSource;
use Omaressaouaf\LaravelStatistician\Sources\PercentageChangeSource;
use Omaressaouaf\LaravelStatistician\Tests\Models\User;
use Omaressaouaf\LaravelStatistician\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class SourceTest extends TestCase
{
    #[Test]
    #[DataProvider('queryInputProvider')]
    public function it_accepts_different_query_inputs(string $type, string $expectedKey): void
    {
        $query = match ($type) {
            'query_builder' => DB::table('users'),
            'table_name' => 'users',
            'model_class' => User::class,
            'eloquent_builder' => User::query(),
        };

        $source = new AggregateSource($query);

        $this->assertSame($expectedKey, $source->getKey());
    }

    public static function queryInputProvider(): array
    {
        return [
            'query builder' => ['query_builder', 'users_count'],
            'table name' => ['table_name', 'users_count'],
            'model class' => ['model_class', 'users_count'],
            'eloquent builder' => ['eloquent_builder', 'users_count'],
        ];
    }

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
    public function it_generates_default_key_for_date_grouped_aggregate_source(): void
    {
        $source = new DateGroupedAggregateSource(DB::table('orders'));

        $this->assertSame('orders_count_by_date', $source->getKey());
    }

    #[Test]
    public function it_allows_overriding_default_key_with_key_by(): void
    {
        $source = (new PercentageChangeSource(DB::table('users')))->keyBy('active_users');

        $this->assertSame('active_users', $source->getKey());
    }

}
