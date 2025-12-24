<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Uom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $paginator = Uom::query()
            ->orderBy('code')
            ->paginate($perPage)
            ->appends($request->query());

        return response()->json([
            'data' => $paginator,
        ]);
    }
}
