<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::with('warehouse')->get();

        return OrderResource::collection($orders);
    }

    public function store(StoreOrderRequest $request)
    {
    }

    public function show(Order $order)
    {
    }

    public function update(UpdateOrderRequest $request, Order $order)
    {
    }

    public function destroy(Order $order)
    {
    }
}
