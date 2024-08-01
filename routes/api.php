<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;


Route::group(['prefix' => 'v1'], function() {
    Route::get('/products', [ProductController::class, 'index']);

    Route::get('/warehouses', [WarehouseController::class, 'index']);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::put('/orders/{order}', [OrderController::class, 'update']);
    Route::post('/orders/{order}/complete', [OrderController::class, 'complete']);
});
