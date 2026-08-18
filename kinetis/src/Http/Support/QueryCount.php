<?php

declare(strict_types=1);

namespace App\Http\Support;

final class QueryCount
{
    public static function clamp(?string $raw): int
    {
        if ($raw === null) {
            return 1;
        }

        $n = filter_var($raw, FILTER_VALIDATE_INT);

        if ($n === false) {
            return 1;
        }

        return max(1, min(500, $n));
    }
}
