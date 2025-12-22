<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Uom;
use Illuminate\Http\JsonResponse;

class UomController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Uom::query()->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }
}
