<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer',
        'warehouse_id',
        'status',
        'completed_at',
    ];

    public function warehouse()
    {
        // Определяем связь "принадлежит одному" с моделью Warehouse
        return $this->belongsTo(Warehouse::class);
    }

    public function items()
    {
        // Определяем связь "имеет много" с моделью OrderItem
        return $this->hasMany(OrderItem::class);
    }
}
