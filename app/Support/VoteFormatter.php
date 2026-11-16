<?php

namespace App\Support;

class VoteFormatter
{
    public static function percent(float|int|null $value): string
    {
        return number_format((float) ($value ?? 0), 1, ',', ' ').' %';
    }
}
