<?php

namespace Omaressaouaf\LaravelStatistician\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Omaressaouaf\LaravelStatistician\StatisticianServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // $this->loadMigrationsFrom(__DIR__.'/Migrations');
    }

    protected function getPackageProviders($app)
    {
        return [
            StatisticianServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => env('DB_CONNECTION', 'mysql'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel_statistician_testing'),
            'username' => env('DB_USER', 'root'),
            'password' => env('DB_PASSWORD', ''),
        ]);
    }
}
