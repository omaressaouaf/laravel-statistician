<?php

namespace Omaressaouaf\LaravelStatistician\Contracts;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

abstract class Source
{
    public ?string $key = null;

    public QueryBuilder|EloquentBuilder $builder;

    public function keyBy(string $key): static
    {
        $this->key = $key;

        return $this;
    }

    public function getKey(): string
    {
        return $this->key ? $this->key : $this->defaultKey();
    }

    abstract protected function defaultKey(): string;

    protected function getTableName(): string
    {
        if ($this->builder instanceof EloquentBuilder) {
            return $this->builder->getModel()->getTable();
        }

        return $this->builder->from;
    }

    /**
     * @param  QueryBuilder|EloquentBuilder|class-string<Model>|string  $query
     */
    protected static function resolveBuilder(QueryBuilder|EloquentBuilder|string $query): QueryBuilder|EloquentBuilder
    {
        if ($query instanceof QueryBuilder || $query instanceof EloquentBuilder) {
            return $query;
        }

        if (is_string($query) && class_exists($query) && is_subclass_of($query, Model::class)) {
            return $query::query();
        }

        if (is_string($query)) {
            return DB::table($query);
        }

        throw new InvalidArgumentException(sprintf(
            'Query must be a query builder, eloquent builder, model class, or table name. [%s] given.',
            is_object($query) ? $query::class : gettype($query),
        ));
    }
}
