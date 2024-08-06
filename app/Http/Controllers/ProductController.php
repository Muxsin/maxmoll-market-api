<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Product;

class ProductController extends Controller
{
    public function index()
    {
        // Получаем все продукты вместе с их запасами и связями со складами
        $products = Product::with('stocks.warehouse')->get();

        // Возвращаем коллекцию продуктов, преобразованную с помощью ресурса ProductResource
        return ProductResource::collection($products);
    }
}
