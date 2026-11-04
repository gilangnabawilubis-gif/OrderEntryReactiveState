<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\MenuController;
use App\Models\Order;
use App\Models\Product;
use App\Models\Menu;
use App\Http\Controllers\OrderController;

Route::get('/', function () {
    $ordersToday = Order::with('details.menuDetail.product')
        ->where('status', 'completed')
        ->whereDate('checked_out_at', today())
        ->get();

    $totalProfitHariIni = 0;
    foreach ($ordersToday as $order) {
        foreach ($order->details as $detail) {
            $totalProfitHariIni += ($detail->harga - $detail->menuDetail->product->modal)
                * $detail->qty;
        }
    }

    return view('dashboard', [
        'jumlahProduk' => Product::count(),
        'jumlahMenu' => Menu::count(),
        'totalPenjualanHariIni' => $ordersToday->sum('total'),
        'totalProfitHariIni' => $totalProfitHariIni,
    ]);
})->name('dashboard');

Route::get('/product', [ProductController::class, 'index'])
    ->name('product.index');
Route::get('/product/add', [ProductController::class, 'create'])
    ->name('product.create');
Route::get('/product/{id}/edit', [ProductController::class, 'edit'])
    ->name('product.edit');

Route::get('/menu', [MenuController::class, 'index'])
    ->name('menu.index');
Route::get('/menu/add', [MenuController::class, 'create'])
    ->name('menu.create');
Route::get('/menu/{id}/edit', [MenuController::class, 'edit'])
    ->name('menu.edit');

Route::get('/order', [OrderController::class, 'index'])->name('order.index');
Route::get('/order/add', [OrderController::class, 'create'])->name('order.create');
Route::get('/order/{id}/edit', [OrderController::class, 'edit'])->name('order.edit');
Route::get('/order/{id}/checkout', [OrderController::class, 'checkoutPage'])
    ->name('order.checkout');