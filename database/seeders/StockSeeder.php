<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Stock;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class StockSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Получаем все продукты и склады из базы данных
        $products = Product::all();
        $warehouses = Warehouse::all();

        // Создаем 50 записей о запасах
        for ($i = 0; $i < 50; $i++) {
            do {
                // Выбираем случайный продукт и случайный склад
                $product = fake()->randomElement($products);
                $warehouse = fake()->randomElement($warehouses);
            } while(Stock::where('product_id', $product->id)
                ->where('warehouse_id', $warehouse->id)
                ->exists()
            );

             // Создаем запись о запасах с уникальной комбинацией продукта и склада
            Stock::factory()
                ->recycle($product) // Устанавливаем продукт для создания
                ->recycle($warehouse) // Устанавливаем склад для создания
                ->create()
            ;
        }
    }
}
