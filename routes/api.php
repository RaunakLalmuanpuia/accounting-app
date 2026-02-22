<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RegisterController;

Route::get('/user', function (Request $request) {
    return $request->user()->load(['roles']);
})->middleware('auth:sanctum');


Route::group(['prefix' => 'auth'], function () {
    Route::post('login', [RegisterController::class, 'login']);
    Route::delete('logout', [RegisterController::class, 'logout'])->middleware('auth:sanctum');
});
