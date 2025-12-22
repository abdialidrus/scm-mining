<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $limit = min(max((int) $request->query('limit', 20), 1), 50);

        $query = DB::table('items')
            ->select(['id', 'item_code', 'item_name', 'uom'])
            ->orderBy('item_name');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('item_code', 'ilike', '%' . $search . '%')
                    ->orWhere('item_name', 'ilike', '%' . $search . '%');
            });
        }

        return response()->json([
            'data' => $query->limit($limit)->get(),
        ]);
    }
}
