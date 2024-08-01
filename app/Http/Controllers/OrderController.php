<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        if ($request->filled('warehouse')) {
            $warehouse = $request->get('warehouse');
            $query->join('warehouses', 'orders.warehouse_id', '=', 'warehouses.id')
              ->where('warehouses.name', 'like', "%{$warehouse}%");
        }

        $orders = $query->paginate();

        return OrderResource::collection($orders);
    }

    public function store(StoreOrderRequest $request)
    {
        DB::beginTransaction();

        try {
            $order = Order::create([
                'customer' => $request->input('customer'),
                'warehouse_id' => $request->input('warehouse_id'),
                'status' => OrderStatus::Active,
            ]);

            foreach ($request->input('items') as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'count' => $item['count'],
                ]);
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            return response('', 500);
        }

        return response('', 201);
    }

    public function show(Order $order)
    {
        return $order->load('items');
    }

    public function update(UpdateOrderRequest $request, Order $order)
    {
        if ($order->status === OrderStatus::Active->value) {
            DB::beginTransaction();

            try {
                $order->update($request->only(['customer', 'warehouse_id']));
    
                $items = $request->input('items', []);
                
                $orderItemsData = [];
    
                foreach ($items as $item) {
                    $orderItemsData[] = new OrderItem([
                        'product_id' => $item['product_id'],
                        'count' => $item['count'],
                    ]);
                }
    
                $order->items()->delete();
                $order->items()->saveMany($orderItemsData);
    
                DB::commit();
    
                return $order->load('items');
            } catch (\Exception $e) {
                DB::rollBack();
    
                return response('', 500);
            }
        } else {
            return response('', 400);
        }
    }

    public function destroy(Order $order)
    {
    }
}
