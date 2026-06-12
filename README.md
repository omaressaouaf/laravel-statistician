# Laravel Statistician

[![Latest Stable Version](https://img.shields.io/packagist/v/omaressaouaf/laravel-statistician.svg)(https://packagist.org/packages/omaressaouaf/laravel-statistician) 
[![License](https://img.shields.io/github/license/omaressaouaf/laravel-statistician)](LICENSE)
[![Tests](https://github.com/omaressaouaf/laravel-statistician/actions/workflows/tests.yml/badge.svg)](https://github.com/omaressaouaf/laravelstatistician/actions/workflows/tests.yml)

A Laravel package for generating **clean application statistics** from Eloquent models, query builders, or plain tables.

## Features

- Aggregate stats: **count**, **sum**, **avg**, **min**, **max**
- Date-range filtering with `start()` and `end()`
- Date-grouped statistics for charts
- Percentage change between two periods, Trend statistics
- Supports Eloquent builders, query builders, model classes, and table names
- Multiple statistics in one call
- Cache support

---

## Installation

Install via Composer:

```sh
composer require omaressaouaf/laravel-statistician
````

---

## Usage

### Basic Aggregate Statistic

```php
use App\Models\User;
use Omaressaouaf\LaravelStatistician\Enums\Aggregate;
use Omaressaouaf\LaravelStatistician\Sources\AggregateSource;
use Omaressaouaf\LaravelStatistician\Statisticians\AggregateStatistician;

$stats = AggregateStatistician::fromSources(
    new AggregateSource(User::class, Aggregate::COUNT)
)->get();

echo $stats['users_count'];
```

### Custom Key

```php
$stats = AggregateStatistician::fromSources(
    (new AggregateSource(User::class, Aggregate::COUNT))->keyBy('total_users')
)->get();

echo $stats['total_users'];
```

### Date Range

```php
$stats = AggregateStatistician::fromSources(
    new AggregateSource(User::class, Aggregate::COUNT)
)
    ->start('2025-01-01')
    ->end('2025-12-31')
    ->get();
```

### Sum / Avg / Min / Max

```php
use App\Models\Order;
use Omaressaouaf\LaravelStatistician\Enums\Aggregate;

$stats = AggregateStatistician::fromSources(
    new AggregateSource(Order::class, Aggregate::SUM, 'total'),
    new AggregateSource(Order::class, Aggregate::AVG, 'total'),
    new AggregateSource(Order::class, Aggregate::MAX, 'total'),
)->get();
```

### Date Grouped Statistics

Useful for charts and dashboards.

```php
use Omaressaouaf\LaravelStatistician\Sources\DateGroupedAggregateSource;
use Omaressaouaf\LaravelStatistician\Statisticians\DateGroupedAggregateStatistician;

$stats = DateGroupedAggregateStatistician::fromSources(
    new DateGroupedAggregateSource(User::class, Aggregate::COUNT)
)
    ->start('2025-01-01')
    ->end('2025-01-31')
    ->get();
```

Example result:

```php
[
    'users_count_by_date' => [
        'data' => [
            ['date_label' => '2025-01-01', 'aggregate' => 10],
            ['date_label' => '2025-01-02', 'aggregate' => 15],
        ],
        'date_format' => 'Y-m-d',
    ],
]
```

### Percentage Change

```php
use Omaressaouaf\LaravelStatistician\Sources\PercentageChangeSource;
use Omaressaouaf\LaravelStatistician\Statisticians\PercentageChangeStatistician;

$stats = PercentageChangeStatistician::fromSources(
    new PercentageChangeSource(User::class)
)
    ->start('2025-02-01')
    ->end('2025-02-28')
    ->get();

echo $stats['users_percentage_change'];
```

### Using Query Builders

```php
use Illuminate\Support\Facades\DB;

$stats = AggregateStatistician::fromSources(
    new AggregateSource(DB::table('users')->where('is_active', true), Aggregate::COUNT)
)->get();
```

### Multiple Sources

```php
$stats = AggregateStatistician::fromSources(
    (new AggregateSource(User::class, Aggregate::COUNT))->keyBy('users'),
    (new AggregateSource(Order::class, Aggregate::COUNT))->keyBy('orders'),
    (new AggregateSource(Order::class, Aggregate::SUM, 'total'))->keyBy('revenue'),
)->get();
```

---

## Caching

Cache statistics for a specific number of seconds:

```php
$stats = AggregateStatistician::fromSources(
    new AggregateSource(User::class)
)
    ->cacheFor(3600)
    ->get();
```

Cache until a specific date:

```php
$stats = AggregateStatistician::fromSources(
    new AggregateSource(User::class)
)
    ->cacheUntil(now()->addDay())
    ->get();
```

Clear cache conditionally:

```php
$stats = AggregateStatistician::fromSources(
    new AggregateSource(User::class)
)
    ->clearCacheWhen(request()->boolean('refresh'))
    ->cacheFor(3600)
    ->get();
```

---

### Custom Statisticians

The package is fully extensible. You can create your own statisticians by implementing one of the provided contracts:

- `OneQueryStatistician` – for statistics that can be computed with a single query for all provided sources (e.g: `AggregateStatistician`).
- `MultiQueryStatistician` – for statistics that require multiple queries or more advanced calculations per each source (e.g: `DateGroupedAggregateStatistician`).

A custom statistician works with its own source object, allowing you to encapsulate configuration and keep your API consistent with the built-in statisticians.

#### Example

```php
namespace App\Statistics\Sources;

use Omaressaouaf\LaravelStatistician\Contracts\Source;

class ActiveUsersSource extends Source
{
    public function __construct(
        public Aggregate $aggregate = Aggregate::COUNT
    ) {}
}
```

```php
namespace App\Statistics;

use Illuminate\Database\Eloquent\Builder;
use Omaressaouaf\LaravelStatistician\Contracts\OneQueryStatistician;
use App\Models\User;
use App\Statistics\Sources\ActiveUsersSource;

class ActiveUsersStatistician implements MultiQueryStatistician
{
    protected function sourceClass(): string
    {
        return ActiveUsersSource::class;
    }

    protected function handle(Source $source): mixed
    {
        return User::query()
            ->where('is_active', true)
            ->whereDate($source->dateColumn, '>=', $this->startDate->toDateString())
            ->whereDate($source->dateColumn, '<=', $this->endDate->toDateString())
            ->{strtoupper($source->aggregate->value)}();
    }
}
```

Then you can use your statistician in your codebase.

```php
$stats = ActiveUsersStatistician(
    new ActiveUsersSource(Aggregate::COUNT),
)
    ->start('2025-01-01')
    ->end('2025-01-31')
    ->get();

return $stats['active_users_count'];

```

This approach allows you to implement completely custom statistics while preserving the same architecture and developer experience as the package's built-in statisticians.

---

## Testing

Run unit tests:

```sh
composer test
```

---

## License

This package is licensed under the [MIT License](https://github.com/omaressaouaf/laravel-statistician/blob/master/LICENSE).
