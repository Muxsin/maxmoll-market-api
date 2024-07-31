<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'v1'], function() {
    Route::get('/products', [ProductController::class, 'index']);

    Route::get('/warehouses', [WarehouseController::class, 'index']);

    Route::get('/orders', [OrderController::class, 'index']);
});
