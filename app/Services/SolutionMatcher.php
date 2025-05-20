<?php

namespace App\Services;

use App\Models\SolutionMatch;

class SolutionMatcher
{
    public static function match(?string $code, ?string $text = null)
    {
        $query = SolutionMatch::query();

        if ($code) {
            $query->orWhere('code', $code);
        }

        if ($text) {
            $query->orWhere('keyword', 'like', "%$text%");
        }

        return $query->get();
    }
}
