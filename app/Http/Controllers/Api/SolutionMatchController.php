<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SolutionMatcher;
use Illuminate\Http\Request;

class SolutionMatchController extends Controller
{
    public function match(Request $request)
    {
        $code = $request->input('code');
        $text = $request->input('text');

        if (!$code && !$text) {
            return response()->json(['error' => 'Bitte code oder text übergeben.'], 422);
        }

        $matches = SolutionMatcher::match($code, $text);
        return response()->json($matches);
    }
}
