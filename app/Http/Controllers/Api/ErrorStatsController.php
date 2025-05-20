<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\ExtractedEntry;

class ErrorStatsController extends Controller
{
    public function index()
    {
        $topCodes = ExtractedEntry::select('code', DB::raw('COUNT(*) as anzahl'))
            ->whereNotNull('code')
            ->groupBy('code')
            ->orderByDesc('anzahl')
            ->limit(10)
            ->get();

        $byCategory = ExtractedEntry::select('category', DB::raw('COUNT(*) as anzahl'))
            ->groupBy('category')
            ->orderByDesc('anzahl')
            ->get();

        return response()->json([
            'top_codes' => $topCodes,
            'by_category' => $byCategory,
        ]);
    }
}

