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

        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $query = DB::table('items')
            ->leftJoin('uoms', 'uoms.id', '=', 'items.base_uom_id')
            ->select([
                'items.id',
                'items.sku',
                'items.name',
                'items.base_uom_id',
                DB::raw('uoms.code as base_uom_code'),
                DB::raw('uoms.name as base_uom_name'),
            ])
            ->orderBy('items.name');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('items.sku', 'ilike', '%' . $search . '%')
                    ->orWhere('items.name', 'ilike', '%' . $search . '%');
            });
        }

        $paginator = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'data' => $paginator,
        ]);
    }
}
