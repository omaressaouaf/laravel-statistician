<?php

namespace Omaressaouaf\LaravelStatistician\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Omaressaouaf\LaravelStatistician\Tests\Factories\OrderFactory;

class Order extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function newFactory()
    {
        return OrderFactory::new();
    }
}
