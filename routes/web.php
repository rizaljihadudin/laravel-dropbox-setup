<?php

use App\Http\Controllers\DropBoxController;
use App\Http\Controllers\DropfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DropBoxController::class, 'index']);
Route::post('upload', [DropBoxController::class, 'upload'])->name('upload');


Route::get('drop', [DropfileController::class, 'index']);
Route::post('drop', [DropfileController::class, 'store']);
Route::get('drop/{filetitle}', [DropfileController::class, 'show']);
Route::get('drop/{filetitle}/download', [DropfileController::class, 'download']);
Route::get('drop/{id}/destroy', [DropfileController::class, 'destroy']);
