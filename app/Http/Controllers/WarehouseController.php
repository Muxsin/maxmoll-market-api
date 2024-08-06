<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;

class WarehouseController extends Controller
{
    public function index()
    {
        // Возвращаем все записи из таблицы складов
        return Warehouse::all();
    }
}
