<?php

namespace App\Http\Controllers;

use App\Models\StockMovement;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    public function index(Request $request)
    {
        $query = StockMovement::query();

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->get('product_id'));
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->get('warehouse_id'));
        }

        if ($request->filled('start_date')) {
            $query->where('created_at', '>=', $request->get('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->where('created_at', '<=', $request->get('end_date'));
        }

        $movements = $query->paginate($request->get('per_page', 10));

        return response()->json($movements);
    }
}
