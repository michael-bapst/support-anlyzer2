<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportCase;

class CaseController extends Controller
{
    public function index()
    {
        return response()->json(SupportCase::orderBy('created_at', 'desc')->get());
    }
}
