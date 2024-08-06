<?php

namespace App\Http\Controllers;

use App\Models\StockMovement;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    public function index(Request $request)
    {
        // Создаем базовый запрос для получения записей о движении товаров
        $query = StockMovement::query();

        // Фильтруем по идентификатору продукта, если он передан в запросе
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->get('product_id'));
        }

        // Фильтруем по идентификатору склада, если он передан в запросе
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->get('warehouse_id'));
        }

        // Фильтруем по дате начала, если она передана в запросе
        if ($request->filled('start_date')) {
            $query->where('created_at', '>=', $request->get('start_date'));
        }

        // Фильтруем по дате окончания, если она передана в запросе
        if ($request->filled('end_date')) {
            $query->where('created_at', '<=', $request->get('end_date'));
        }

        // Пагинируем результаты, используя значение per_page из запроса или 10 по умолчанию
        $movements = $query->paginate($request->get('per_page', 10));

        // Возвращаем результат в формате JSON
        return response()->json($movements);
    }
}
