<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    public function stocks()
    {
        // Определяем связь "имеет много" с моделью Stock
        return $this->hasMany(Stock::class);
    }
}
