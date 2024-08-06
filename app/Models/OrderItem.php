<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'count',
    ];

    public function product()
    {
        // Определяем связь "принадлежит одному" с моделью Product
        return $this->belongsTo(Product::class);
    }

    public function order()
    {
        // Определяем связь "принадлежит одному" с моделью Order
        return $this->belongsTo(Order::class);
    }
}
