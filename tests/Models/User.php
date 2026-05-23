<?php

namespace Omaressaouaf\LaravelStatistician\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Omaressaouaf\LaravelStatistician\Tests\Factories\UserFactory;

class User extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function newFactory()
    {
        return UserFactory::new();
    }
}
