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
use App\Models\StockMovement;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        // Инициализируем запрос к модели Order с загрузкой связанных моделей warehouse
        $query = Order::with('warehouse');

        // Проверяем, передан ли параметр 'status' в запросе
        if ($request->filled('status')) {
            // Пробуем преобразовать переданный статус в enum OrderStatus
            $status = OrderStatus::tryFrom($request->get('status'));

             // Если статус успешно преобразован, добавляем условие в запрос
            if ($status) {
                $query->where('status', $status);
            }
        }

        // Проверяем, передан ли параметр 'customer' в запросе
        if ($request->filled('customer')) {
            // Получаем значение параметра 'customer'
            $customer = $request->get('customer');
            // Добавляем условие в запрос для поиска по имени клиента с использованием LIKE
            $query->where('customer', 'like', "%$customer%");
        }

        // Проверяем, передан ли параметр 'warehouse' в запросе
        if ($request->filled('warehouse')) {
            // Получаем значение параметра 'warehouse'
            $warehouse = $request->get('warehouse');
            // Присоединяем таблицу warehouses и добавляем условие в запрос для поиска по имени склада с использованием LIKE
            $query->join('warehouses', 'orders.warehouse_id', '=', 'warehouses.id')
                ->where('warehouses.name', 'like', "%$warehouse%");
        }

        // Выполняем запрос с пагинацией
        $orders = $query->paginate($request->input('per_page', 10));

        // Возвращаем коллекцию заказов в формате ресурса
        return OrderResource::collection($orders);
    }

    public function store(StoreOrderRequest $request)
    {
        // Начинаем транзакцию базы данных
        DB::beginTransaction();

        try {
            // Создаем новый заказ с данными из запроса
            $order = Order::create([
                'customer' => $request->input('customer'),
                'warehouse_id' => $request->input('warehouse_id'),
                'status' => OrderStatus::Active,
            ]);

            // Проходимся по каждому элементу из запроса
            foreach ($request->input('items') as $item) {
                // Проверяем наличие товара на складе
                $stock = Stock::where([
                    'product_id' => $item['product_id'], 
                    'warehouse_id' => $request->input('warehouse_id')
                ])->first();

                // Если товар не найден, выбрасываем InvalidProductException исключение
                if (!$stock) {
                    throw new InvalidProductException();
                }

                // Если товара на складе недостаточно, выбрасываем NotEnoughStockException исключение
                if ($stock->stock < $item['count']) {
                    throw new NotEnoughStockException();
                }

                // Создаем запись о товаре в заказе
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'count' => $item['count'],
                ]);
                
                // Уменьшаем количество товара на складе
                DB::table('stocks')
                    ->where('product_id', $item['product_id'])
                    ->where('warehouse_id', $request->input('warehouse_id'))
                    ->decrement('stock', $item['count']);

                // Записываем движение товара в историю
                StockMovement::create([
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $order->warehouse_id,
                    'order_id' => $order->id,
                    'quantity_change' => -$item['count']
                ]);
            }
            
            // Подтверждаем транзакцию
            DB::commit();
        } catch(InvalidProductException $e) {
            // Откатываем транзакцию в случае ошибки с товаром
            DB::rollBack();

            return response()->json("There are no products in stock", 400);
        } catch(NotEnoughStockException $e) {
            // Откатываем транзакцию в случае нехватки товара
            DB::rollBack();

            return response()->json("Not enough stock available", 400);
        } catch (Exception $e) {
            // Откатываем транзакцию в случае любой другой ошибки
            DB::rollBack();

            return response()->json("An error occurred while processing the order", 500);
        }

        // Возвращаем успешный ответ с кодом 201
        return response('', 201);
    }

    public function show(Order $order)
    {
        // Загружаем связанные записи items для переданного заказа и возвращаем заказ с этими записями
        return $order->load('items');
    }

    public function update(UpdateOrderRequest $request, Order $order)
    {
        // Проверяем, является ли заказ активным. Если нет, возвращаем ошибку.
        if ($order->status !== OrderStatus::Active->value) {
            return response('Only an active order can be updated.', 400);
        }

        // Начинаем транзакцию базы данных
        DB::beginTransaction();

        try {
            // Обновляем информацию о заказе, используя только переданные данные о клиенте
            $order->update($request->only(['customer']));

            // Получаем элементы заказа из запроса, или пустой массив, если они не переданы
            $items = $request->input('items', []);
            
            // Массив для хранения новых данных о товарах в заказе
            $orderItemsData = [];

            // Возвращаем товары на склад для текущих элементов заказа
            foreach($order->items as $order_item) {
                Stock::where([
                    'product_id' => $order_item['product_id'], 
                    'warehouse_id' => $order->warehouse_id,
                ])->increment('stock', $order_item->count);

                // Удаляем записи о движении товара, связанные с этим заказом
                StockMovement::where('product_id', $order_item['product_id'])
                    ->where('warehouse_id', $order->warehouse_id)
                    ->where('order_id', $order->id)
                    ->delete();
            }

            // Обрабатываем новые элементы заказа
            foreach ($items as $item) {
                // Проверяем наличие товара на складе
                $stock = Stock::where([
                    'product_id' => $item['product_id'], 
                    'warehouse_id' => $order->warehouse_id,
                ])->first();

                // Если товар не найден, выбрасываем InvalidProductException исключение
                if (!$stock) {
                    throw new InvalidProductException();
                }

                // Если товара на складе недостаточно, выбрасываем NotEnoughStockException исключение
                if ($stock->stock < $item['count']) {
                    throw new NotEnoughStockException();
                }
                
                // Добавляем новый элемент в массив данных о заказе
                $orderItemsData[] = new OrderItem([
                    'product_id' => $item['product_id'],
                    'count' => $item['count'],
                ]);

                // Уменьшаем количество товара на складе
                DB::table('stocks')
                    ->where('product_id', $item['product_id'])
                    ->where('warehouse_id', $order->warehouse_id)
                    ->decrement('stock', $item['count']);

                // Записываем движение товара в историю
                StockMovement::create([
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $order->warehouse_id,
                    'order_id' => $order->id,
                    'quantity_change' => -$item['count']
                ]);
            }

            // Удаляем старые элементы заказа и сохраняем новые
            $order->items()->delete();
            $order->items()->saveMany($orderItemsData);

            // Подтверждаем транзакцию
            DB::commit();

             // Возвращаем заказ с загруженными элементами
            return $order->load('items');
        } catch(InvalidProductException $e) {
            // Откатываем транзакцию в случае ошибки с товаром
            DB::rollBack();

            return response()->json("There are no products in stock", 400);
        } catch(NotEnoughStockException $e) {
            // Откатываем транзакцию в случае нехватки товара
            DB::rollBack();

            return response()->json("Not enough stock available", 400);
        } catch (Exception $e) {
            // Откатываем транзакцию в случае любой другой ошибки
            DB::rollBack();

            return response()->json("An error occurred while processing the order", 500);
        }
    }

    public function complete(Order $order)
    {
        // Проверяем, является ли заказ активным. Если нет, возвращаем ошибку.
        if ($order->status !== OrderStatus::Active->value) {
            return response('Only an active order can be completed.', 400);
        }
        
        // Обновляем статус заказа на "Завершен" и устанавливаем текущее время в поле completed_at
        $order->update([
            'status' => OrderStatus::Completed,
            'completed_at' => now(),
        ]);
        
        // Возвращаем заказ с загруженными элементами
        return $order->load('items');
    }

    public function cancel(Order $order)
    {
        // Проверяем, является ли заказ активным. Если нет, возвращаем ошибку
        if ($order->status !== OrderStatus::Active->value) {
            return response('Only an active order can be cancelled.', 400);
        }

        // Возвращаем товары на склад для текущих элементов заказа
        foreach($order->items as $order_item) {
            // Увеличиваем количество товара на складе на значение количества товара в заказе
            Stock::where([
                'product_id' => $order_item['product_id'], 
                'warehouse_id' => $order->warehouse_id,
            ])->increment('stock', $order_item->count);

            // Удаляем записи о движении товара, связанные с этим заказом
            StockMovement::where('product_id', $order_item['product_id'])
                ->where('warehouse_id', $order->warehouse_id)
                ->where('order_id', $order->id)
                ->delete();
        }
    
        // Обновляем статус заказа на "Отменен" и устанавливаем текущее время в поле completed_at
        $order->update([
            'status' => OrderStatus::Canceled,
            'completed_at' => now(),
        ]);
    
        // Возвращаем заказ с загруженными элементами
        return $order->load('items');
    }

    public function resume(Order $order)
    {
        // Проверяем, является ли заказ отмененным. Если нет, возвращаем ошибку
        if ($order->status !== OrderStatus::Canceled->value) {
            return response('Only a cancelled order can be resumed.', 400);
        }

        // Начинаем транзакцию базы данных
        DB::beginTransaction();
        
        try {
            // Обрабатываем каждый элемент заказа
            foreach($order->items as $order_item) {
                // Проверяем наличие товара на складе
                $stock = Stock::where([
                    'product_id' => $order_item['product_id'], 
                    'warehouse_id' => $order->warehouse_id,
                ])->first();
    
                // Если товар не найден, выбрасываем InvalidProductException исключение
                if (!$stock) {
                    throw new InvalidProductException();
                }
    
                // Если товара на складе недостаточно, выбрасываем NotEnoughStockException исключение
                if ($stock->stock < $order_item['count']) {
                    throw new NotEnoughStockException();
                }

                // Уменьшаем количество товара на складе
                DB::table('stocks')
                    ->where('product_id', $order_item['product_id'])
                    ->where('warehouse_id', $order->warehouse_id)
                    ->decrement('stock', $order_item['count']);

                // Записываем движение товара в историю
                StockMovement::create([
                    'product_id' => $order_item['product_id'],
                    'warehouse_id' => $order->warehouse_id,
                    'order_id' => $order->id,
                    'quantity_change' => -$order_item['count']
                ]);
            }

            // Обновляем статус заказа на "Активный" и устанавливаем поле completed_at в null
            $order->update([
                'status' => OrderStatus::Active,
                'completed_at' => null,
            ]);
            
            // Подтверждаем транзакцию
            DB::commit();

            // Возвращаем заказ с загруженными элементами
            return $order->load('items');
        } catch(InvalidProductException $e) {
            // Откатываем транзакцию в случае ошибки с товаром
            DB::rollBack();

            return response()->json("There are no products in stock", 400);
        } catch(NotEnoughStockException $e) {
            // Откатываем транзакцию в случае нехватки товара
            DB::rollBack();

            return response()->json("Not enough stock available", 400);
        } catch (Exception $e) {
            // Откатываем транзакцию в случае любой другой ошибки
            DB::rollBack();

            return response()->json("An error occurred while processing the order", 500);
        }
    }
}
