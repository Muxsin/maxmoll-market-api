<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidProductException;
use App\Exceptions\NotEnoughStockException;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Stock;
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
              ->where('warehouses.name', 'like', "%$warehouse%");
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
                $stock = Stock::where([
                    'product_id' => $item['product_id'], 
                    'warehouse_id' => $request->input('warehouse_id')
                ])->first();

                if (!$stock) {
                    throw new InvalidProductException();
                }

                if ($stock->stock < $item['count']) {
                    throw new NotEnoughStockException();
                }

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'count' => $item['count'],
                ]);
                
                DB::table('stocks')
                    ->where('product_id', $item['product_id'])
                    ->where('warehouse_id', $request->input('warehouse_id'))
                    ->decrement('stock', $item['count']);
            }
            
            DB::commit();
        } catch(InvalidProductException $e) {
            DB::rollBack();

            return response()->json("There are no products in stock", 400);
        } catch(NotEnoughStockException $e) {
            DB::rollBack();

            return response()->json("Not enough stock available", 400);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json("An error occurred while processing the order", 500);
        }

        return response('', 201);
    }

    public function show(Order $order)
    {
        return $order->load('items');
    }

    public function update(UpdateOrderRequest $request, Order $order)
    {
        if ($order->status !== OrderStatus::Active->value) {
            return response('Only an active order can be updated.', 400);
        }

        DB::beginTransaction();

        try {
            $order->update($request->only(['customer']));

            $items = $request->input('items', []);
            
            $orderItemsData = [];

            foreach($order->items as $order_item) {
                Stock::where([
                    'product_id' => $order_item['product_id'], 
                    'warehouse_id' => $order->warehouse_id,
                ])->increment('stock', $order_item->count);
            }

            foreach ($items as $item) {
                $stock = Stock::where([
                    'product_id' => $item['product_id'], 
                    'warehouse_id' => $order->warehouse_id,
                ])->first();

                if (!$stock) {
                    throw new InvalidProductException();
                }

                if ($stock->stock < $item['count']) {
                    throw new NotEnoughStockException();
                }
                
                $orderItemsData[] = new OrderItem([
                    'product_id' => $item['product_id'],
                    'count' => $item['count'],
                ]);

                DB::table('stocks')
                    ->where('product_id', $item['product_id'])
                    ->where('warehouse_id', $order->warehouse_id)
                    ->decrement('stock', $item['count']);
            }

            $order->items()->delete();
            $order->items()->saveMany($orderItemsData);

            DB::commit();

            return $order->load('items');
        } catch(InvalidProductException $e) {
            DB::rollBack();

            return response()->json("There are no products in stock", 400);
        } catch(NotEnoughStockException $e) {
            DB::rollBack();

            return response()->json("Not enough stock available", 400);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json("An error occurred while processing the order", 500);
        }
    }

    public function complete(Order $order)
    {
        if ($order->status !== OrderStatus::Active->value) {
            return response('Only an active order can be completed.', 400);
        }
        
        $order->update([
            'status' => OrderStatus::Completed,
            'completed_at' => now(),
        ]);
        
        return $order->load('items');
    }

    public function cancel(Order $order)
    {
        if ($order->status !== OrderStatus::Active->value) {
            return response('Only an active order can be cancelled.', 400);
        }
    
        $order->update([
            'status' => OrderStatus::Canceled,
            'completed_at' => now(),
        ]);
    
        return $order->load('items');
    }

    public function resume(Order $order)
    {
        if ($order->status !== OrderStatus::Canceled->value) {
            return response('Only a cancelled order can be resumed.', 400);
        }
    
        $order->update([
            'status' => OrderStatus::Active,
            'completed_at' => null,
        ]);
    
        return $order->load('items');
    }
}
