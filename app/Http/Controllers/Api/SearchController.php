<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ExtractedEntry;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('q');

        if (!$query) {
            return response()->json(['error' => 'Parameter "q" fehlt.'], 422);
        }

        $results = ExtractedEntry::where('content', 'like', "%$query%")
            ->limit(50)
            ->get();

        return response()->json($results);
    }
}
