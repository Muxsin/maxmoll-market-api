<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with('warehouse');

        if ($request->filled('status')) {
            $status = OrderStatus::tryFrom($request->get('status'));

            if ($status) {
                $query->where('status', $status);
            }
        }

        if ($request->filled('customer')) {
            $customer = $request->get('customer');
            $query->where('customer', 'like', "%$customer%");
        }

        $orders = $query->get();

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
