<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final class Time
{
    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC')->startOfSecond();
    }

    public static function iso(?DateTimeInterface $d): ?string
    {
        return $d === null ? null : CarbonImmutable::instance($d)->utc()->format('Y-m-d\TH:i:s\Z');
    }

    /** Valor para comparar contra columnas timestamptz, con desfase explícito. */
    public static function sql(DateTimeInterface $d): string
    {
        return CarbonImmutable::instance($d)->utc()->format('Y-m-d H:i:sP');
    }

    public static function parse(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value)->utc();
    }
}
