<?php

namespace Omaressaouaf\LaravelStatistician\Enums;

enum Aggregate: string
{
    case COUNT = 'count';
    case SUM = 'sum';
    case AVG = 'avg';
    case MIN = 'min';
    case MAX = 'max';
}
