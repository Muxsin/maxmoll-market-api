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
        $products = Product::all();
        $warehouses = Warehouse::all();

        for ($i = 0; $i < 50; $i++) {
            do {
                $product = fake()->randomElement($products);
                $warehouse = fake()->randomElement($warehouses);
            } while(Stock::where('product_id', $product->id)
                ->where('warehouse_id', $warehouse->id)
                ->exists()
            );

            Stock::factory()
                ->recycle($product)
                ->recycle($warehouse)
                ->create()
            ;
        }
    }
}
