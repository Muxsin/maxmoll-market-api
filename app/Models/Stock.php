<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    use HasFactory;

    protected $primaryKey = ['product_id', 'warehouse_id'];
    public $incrementing = false;

    public function product()
    {
        // Определяем связь "принадлежит одному" с моделью Product
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        // Определяем связь "принадлежит одному" с моделью Warehouse
        return $this->belongsTo(Warehouse::class);
    }
}
