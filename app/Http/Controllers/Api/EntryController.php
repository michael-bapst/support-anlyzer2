<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExtractedEntry;
use Illuminate\Http\Request;

class EntryController extends Controller
{
    public function index(Request $request)
    {
        $query = ExtractedEntry::query();

        if ($request->has('case_id')) {
            $query->whereHas('file', function ($q) use ($request) {
                $q->where('case_id', $request->input('case_id'));
            });
        }

        if ($request->has('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->has('q')) {
            $query->where('content', 'like', '%' . $request->input('q') . '%');
        }

        return response()->json($query->limit(100)->latest()->get());
    }

    public function timeline()
    {
        return response()->json(
            ExtractedEntry::whereNotNull('timestamp')
                ->orderBy('timestamp', 'asc')
                ->limit(100)
                ->get()
        );
    }
}
